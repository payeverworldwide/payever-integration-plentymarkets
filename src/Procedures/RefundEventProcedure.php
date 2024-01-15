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

class RefundEventProcedure
{
    use Logger;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @throws Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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

            throw new Exception('Refund payever payment failed! The given order is invalid!');
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
                $amount = $payment->amount;

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.refundData',
                    'RefundEventProcedure: TransactionId: ' . $transactionId,
                    []
                )->setReferenceValue($orderId);

                if ($transactionId > 0) {
                    // refund the payment
                    $refundResult = $paymentService->refundPayment($transactionId, $amount);

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

                        $payment->status = $paymentHelper->mapStatus($refundResult['result']['status']);
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

                        throw new Exception('Refund payever payment is not allowed!');
                    }
                }
            }
        }
    }
}
