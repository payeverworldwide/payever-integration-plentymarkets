<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

class ReturnEventProcedure
{
    use Loggable;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper
    ) {
        $orderId = false;
        $order = $eventTriggered->getOrder();

        $originOrders = $order->originOrders;
        if (!$originOrders->isEmpty() && $originOrders->count() > 0) {
            $originOrder = $originOrders->first();
            $orderId = $originOrder->id;
        }

        if (empty($orderId)) {
            throw new \Exception('Return payever payment failed! The given order is invalid!');
        }

        $amount = 0;
        foreach ($order->orderItems as $item) {
            $quantity = $item->quantity;
            $price = $item->amounts->first()->priceGross;
            $amount += ($quantity * $price);
        }

        $payments = $paymentContract->getPaymentsByOrderId($orderId);
        foreach ($payments as $payment) {
            if ($paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                $transactionId = $paymentHelper->getPaymentPropertyValue(
                    $payment,
                    PaymentProperty::TYPE_TRANSACTION_ID
                );

                if ($transactionId) {
                    $transaction = $paymentService->getTransaction($transactionId);
                    $this->getLogger(__METHOD__)->debug('Payever::debug.transactionData', $transaction);

                    // partial cancel
                    if ($paymentHelper->isAllowedTransaction($transaction, 'cancel')) {
                        $cancelResult = $paymentService->cancelPayment($transactionId, $amount);
                        $this->getLogger(__METHOD__)->debug('Payever::debug.cancelResponse', $cancelResult);
                    }

                    // partial refund
                    if ($paymentHelper->isAllowedTransaction($transaction, 'refund')) {
                        $refundResult = $paymentService->refundPayment($transactionId, $amount);
                        $this->getLogger(__METHOD__)->debug('Payever::debug.refundResponse', $refundResult);
                    }
                }
            }
        }
    }
}
