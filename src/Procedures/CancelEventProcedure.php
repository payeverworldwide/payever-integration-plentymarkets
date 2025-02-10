<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Helper\PaymentActionManager;
use Payever\Helper\StatusHelper;
use Payever\Models\PaymentAction;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;

class CancelEventProcedure
{
    use Logger;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @param PaymentActionManager $paymentActionManager
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper,
        PaymentActionManager $paymentActionManager
    ) {
        $orderId = $paymentHelper->getOrderIdByEvent($eventTriggered);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.cancelEventProcedure',
            'Run CancelEventProcedure',
            []
        )->setReferenceValue($orderId);

        if (empty($orderId)) {
            $this->log(
                'error',
                __METHOD__,
                'Payever::debug.cancelEventProcedure',
                'CancelEventProcedure: Cancel payever payment failed! The given order is invalid!',
                []
            )->setReferenceValue($orderId);

            throw new \UnexpectedValueException('Cancel payever payment failed! The given order is invalid!');
        }

        /** @var Payment[] $payment */
        $payments = $paymentContract->getPaymentsByOrderId($orderId);

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if (!$paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                continue;
            }

            $transactionId = $paymentHelper->getPaymentPropertyValue(
                $payment,
                PaymentProperty::TYPE_TRANSACTION_ID
            );
            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.cancelData',
                'CancelEventProcedure: TransactionId: ' . $transactionId,
                []
            )->setReferenceValue($orderId);

            if (!empty($transactionId)) {
                $transaction = $paymentService->getTransaction($transactionId);

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.transactionData',
                    'Transaction',
                    [
                        ['transaction' => $transaction]
                    ]
                )->setReferenceValue($transactionId);

                if ($paymentHelper->isAllowedTransaction($transaction, 'cancel')) {
                    $identifier = $paymentActionManager->generateIdentifier();
                    $paymentActionManager->addAction(
                        $orderId,
                        $identifier,
                        PayeverService::ACTION_CANCEL,
                        PaymentAction::SOURCE_EXTERNAL
                    );

                    // cancel the payment
                    $cancelResult = $paymentService->cancelPayment($transactionId, null, $identifier);

                    $this->log(
                        'debug',
                        __METHOD__,
                        'Payever::debug.cancelResponse',
                        'Cancel response',
                        [
                            ['cancelResult' => $cancelResult]
                        ]
                    )->setReferenceValue($transactionId);

                    if ($cancelResult['call']['status'] == 'success') {
                        $payment->status = StatusHelper::mapPaymentStatus($cancelResult['result']['status']);
                        // update the cancelled payment
                        $paymentContract->updatePayment($payment);
                    }
                } else {
                    $this->log(
                        'debug',
                        __METHOD__,
                        'Payever::debug.cancelResponse',
                        'Payever::debug.cancelResponse: Cancel payever payment is not allowed!',
                        []
                    )->setReferenceValue($transactionId);

                    throw new \BadMethodCallException('Cancel payever payment is not allowed!');
                }
            }
        }
    }
}
