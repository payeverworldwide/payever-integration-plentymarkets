<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

class RefundEventProcedure
{
    use Loggable;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @throws \Exception
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper
    ) {
        $orderId = $paymentHelper->getOrderIdByEvent($eventTriggered);

        if (empty($orderId)) {
            throw new \Exception('Refund payever payment failed! The given order is invalid!');
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
                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.refundData',
                    'TransactionId: ' . $transactionId . ', amount: ' . $amount
                );
                if ($transactionId > 0) {
                    // refund the payment
                    $refundResult = $paymentService->refundPayment($transactionId, $amount);
                    if ($refundResult) {
                        $this->getLogger(__METHOD__)->debug('Payever::debug.refundResponse', $refundResult);
                        $payment->status = $paymentHelper->mapStatus($refundResult['result']['status']);
                        // update the refunded payment
                        $paymentContract->updatePayment($payment);
                    } else {
                        $this->getLogger(__METHOD__)->debug(
                            'Payever::debug.refundResponse',
                            'Refund payever payment is not allowed!'
                        );
                        throw new \Exception('Refund payever payment is not allowed!');
                    }
                }
            }
        }
    }
}
