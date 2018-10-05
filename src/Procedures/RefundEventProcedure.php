<?php

namespace Payever\Procedures;

use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Payever\Services\PayeverService;
use Payever\Helper\PayeverHelper;
use Plenty\Plugin\Log\Loggable;

class RefundEventProcedure
{
    use Loggable;
    public function run(EventProceduresTriggered $eventTriggered, PayeverService $paymentService, PaymentRepositoryContract $paymentContract, PayeverHelper $paymentHelper)
    {
        /** @var Order $order */
        $order = $eventTriggered->getOrder();
        // only sales orders and credit notes are allowed order types to refund
        switch ($order->typeId) {
            case OrderType::TYPE_SALES_ORDER:
                $orderId = $order->id;
                break;
            case OrderType::TYPE_CREDIT_NOTE:
                $originOrders = $order->originOrders;
                if (! $originOrders->isEmpty() && $originOrders->count() > 0) {
                    $originOrder = $originOrders->first();
                    if ($originOrder instanceof Order) {
                        if ($originOrder->typeId == 1) {
                            $orderId = $originOrder->id;
                        } else {
                            $originOriginOrders = $originOrder->originOrders;
                            if (is_array($originOriginOrders) && count($originOriginOrders) > 0) {
                                $originOriginOrder = $originOriginOrders->first();
                                if ($originOriginOrder instanceof Order) {
                                    $orderId = $originOriginOrder->id;
                                }
                            }
                        }
                    }
                }
                break;
        }
        if (empty($orderId)) {
            throw new \Exception('Refund payever payment failed! The given order is invalid!');
        }
        /** @var Payment[] $payment */
        $payments = $paymentContract->getPaymentsByOrderId($orderId);
        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                $transactionId = $paymentHelper->getPaymentPropertyValue($payment, PaymentProperty::TYPE_TRANSACTION_ID);
                $amount = $payment->amount;
                $this->getLogger(__METHOD__)->debug('Payever::debug.refundData', 'TransactionId: '. $transactionId .', amount: '. $amount);
                if ($transactionId > 0) {
                    // refund the payment
                    $refundResult = $paymentService->refundPayment($transactionId, $amount);
                    if ($refundResult) {
                        $this->getLogger(__METHOD__)->debug('Payever::debug.refundResponse', $refundResult);
                        $payment->status = $paymentHelper->mapStatus($refundResult["result"]["status"]);
                        // update the refunded payment
                        $paymentContract->updatePayment($payment);
                    } else {
                        $this->getLogger(__METHOD__)->debug('Payever::debug.refundResponse', 'Refund payever payment is not allowed!');
                        throw new \Exception('Refund payever payment is not allowed!');
                    }
                }
            }
        }
    }
}
