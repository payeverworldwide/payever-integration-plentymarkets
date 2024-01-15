<?php

namespace Payever\Procedures;

use Exception;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

class CancelEventProcedure
{
    use Logger;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @throws Exception
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper
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

            throw new Exception('Cancel payever payment failed! The given order is invalid!');
        }

        /** @var Payment[] $payment */
        $payments = $paymentContract->getPaymentsByOrderId($orderId);

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
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
                        // cancel the payment
                        $cancelResult = $paymentService->cancelPayment($transactionId);

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
                            $payment->status = $paymentHelper->mapStatus($cancelResult['result']['status']);
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

                        throw new Exception('Cancel payever payment is not allowed!');
                    }
                }
            }
        }
    }
}
