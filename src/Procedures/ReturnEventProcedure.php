<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Helper\PaymentActionManager;
use Payever\Models\PaymentAction;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\PaymentProperty;

class ReturnEventProcedure
{
    use Logger;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @param PaymentActionManager $paymentActionManager
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper,
        PaymentActionManager $paymentActionManager
    ) {
        $orderId = false;
        $order = $eventTriggered->getOrder();

        $originOrders = $order->originOrders;
        if (!$originOrders->isEmpty() && $originOrders->count() > 0) {
            $originOrder = $originOrders->first();
            $orderId = $originOrder->id;
        }

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.returnEventProcedure',
            'Run ReturnEventProcedure',
            []
        )->setReferenceValue($orderId);

        if (empty($orderId)) {
            $this->log(
                'error',
                __METHOD__,
                'Payever::debug.returnEventProcedure',
                'ReturnEventProcedure: Return payever payment failed! The given order is invalid!',
                []
            )->setReferenceValue($orderId);

            throw new \UnexpectedValueException('Return payever payment failed! The given order is invalid!');
        }

        $amount = 0;
        foreach ($order->orderItems as $item) {
            $quantity = $item->quantity;
            $price = $item->amounts->first()->priceGross;
            $amount += ($quantity * $price);
        }

        $payments = $paymentContract->getPaymentsByOrderId($orderId);
        foreach ($payments as $payment) {
            if (!$paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                return;
            }

            $transactionId = $paymentHelper->getPaymentPropertyValue(
                $payment,
                PaymentProperty::TYPE_TRANSACTION_ID
            );

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.transactionData',
                'ReturnEventProcedure: TransactionId: ' . $transactionId,
                []
            )->setReferenceValue($orderId);

            if ($transactionId) {
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

                // partial cancel
                if ($paymentHelper->isAllowedTransaction($transaction, 'cancel')) {
                    $identifier = $paymentActionManager->generateIdentifier();
                    $paymentActionManager->addAction(
                        $orderId,
                        $identifier,
                        PayeverService::ACTION_CANCEL,
                        PaymentAction::SOURCE_EXTERNAL,
                        $amount
                    );

                    $cancelResult = $paymentService->cancelPayment($transactionId, $amount);

                    $this->log(
                        'debug',
                        __METHOD__,
                        'Payever::debug.cancelResponse',
                        'Cancel response',
                        [
                            ['cancelResult' => $cancelResult]
                        ]
                    )->setReferenceValue($transactionId);
                }

                // partial refund
                if ($paymentHelper->isAllowedTransaction($transaction, 'refund')) {
                    $identifier = $paymentActionManager->generateIdentifier();
                    $paymentActionManager->addAction(
                        $orderId,
                        $identifier,
                        PayeverService::ACTION_REFUND,
                        PaymentAction::SOURCE_EXTERNAL,
                        $amount
                    );

                    $refundResult = $paymentService->refundPayment($transactionId, $amount, $identifier);

                    $this->log(
                        'debug',
                        __METHOD__,
                        'Payever::debug.refundResponse',
                        'Refund response',
                        [
                            ['refundResult' => $refundResult]
                        ]
                    )->setReferenceValue($transactionId);
                }
            }
        }
    }
}
