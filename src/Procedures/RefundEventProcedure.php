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

class RefundEventProcedure
{
    use Logger;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @param PaymentActionManager $paymentActionManager
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.StaticAccess)
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
            'Payever::debug.refundEventProcedure',
            'Run RefundEventProcedure',
            []
        )->setReferenceValue($orderId);

        if (empty($orderId)) {
            $this->log(
                'error',
                __METHOD__,
                'Payever::debug.refundEventProcedure',
                'RefundEventProcedure: Return payever payment failed! The given order is invalid!',
                []
            )->setReferenceValue($orderId);

            throw new \UnexpectedValueException('Refund payever payment failed! The given order is invalid!');
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
            $amount = $payment->amount;

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.refundData',
                'RefundEventProcedure: TransactionId: ' . $transactionId,
                []
            )->setReferenceValue($orderId);

            if ($transactionId > 0) {
                $identifier = $paymentActionManager->generateIdentifier();
                $paymentActionManager->addAction(
                    $orderId,
                    $identifier,
                    PayeverService::ACTION_REFUND,
                    PaymentAction::SOURCE_EXTERNAL,
                    $amount
                );

                // refund the payment
                $refundResult = $paymentService->refundPayment($transactionId, $amount, $identifier);

                if ($refundResult) {
                    $this->log(
                        'debug',
                        __METHOD__,
                        'Payever::debug.refundResponse',
                        'Refund response',
                        [
                            ['refundResult' => $refundResult]
                        ]
                    )->setReferenceValue($transactionId);

                    $payment->status = StatusHelper::mapPaymentStatus($refundResult['result']['status']);
                    // update the refunded payment
                    $paymentContract->updatePayment($payment);
                } else {
                    $this->log(
                        'debug',
                        __METHOD__,
                        'Payever::debug.refundResponse',
                        'Refund payever payment action is not allowed!',
                        []
                    )->setReferenceValue($transactionId);

                    throw new \BadMethodCallException('Refund payever payment is not allowed!');
                }
            }
        }
    }
}
