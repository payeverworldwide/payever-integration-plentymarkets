<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Helper\PaymentActionManager;
use Payever\Models\PaymentAction;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderAmount;
use Plenty\Modules\Order\Models\OrderItem;
use Plenty\Modules\Order\Models\OrderItemAmount;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Models\OrderShippingPackage;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;

class ShippingEventProcedure
{
    use Logger;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @param OrderRepositoryContract $orderRepository
     * @param OrderShippingPackageRepositoryContract $orderShippingPackageRepository
     * @param ParcelServicePresetRepositoryContract $parcelServicePresetRepository
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper,
        OrderRepositoryContract $orderRepository,
        OrderShippingPackageRepositoryContract $orderShippingPackageRepository,
        ParcelServicePresetRepositoryContract $parcelServicePresetRepository,
        PaymentActionManager $paymentActionManager
    ) {
        $orderId = $paymentHelper->getOrderIdByEvent($eventTriggered);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.shippingEventProcedure',
            'Run ShippingEventProcedure',
            []
        )->setReferenceValue($orderId);

        if (empty($orderId)) {
            $this->log(
                'error',
                __METHOD__,
                'Payever::debug.shippingEventProcedure',
                'ShippingEventProcedure: Shipping goods payever payment failed! The given order is invalid!',
                []
            );

            throw new \UnexpectedValueException(
                'Shipping goods payever payment action is failed! The given order is invalid!'
            );
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
                'Payever::debug.shippingData',
                'TransactionId: ' . $transactionId . '. OrderID: ' . $orderId,
                []
            )->setReferenceValue($transactionId);

            if (empty($transactionId)) {
                continue;
            }

            $transaction = $paymentService->getTransaction($transactionId);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.transactionData',
                'Transaction',
                [
                    'transaction' => $transaction
                ]
            )->setReferenceValue($transactionId);

            if (!$paymentHelper->isAllowedTransaction($transaction, 'shipping_goods')) {
                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.shippingResponse',
                    'Shipping goods payever payment action is not allowed!',
                    []
                )->setReferenceValue($transactionId);

                throw new \BadMethodCallException('Shipping goods payever payment action is not allowed!');
            }

            /** @var Order $order */
            $order = $orderRepository->findOrderById($orderId);

            /** @var OrderAmount $amount */
            $amount = $order->amount;
            $paymentItems = $this->getPaymentItems($order);
            $deliveryFee = $this->getDeliveryFee($order);
            $trackingNumber = null;
            $trackingUrl = null;
            $carrier = null;

            // Get tracking number
            $orderShippingPackages = $orderShippingPackageRepository->listOrderShippingPackages($order->id);
            foreach ($orderShippingPackages as $orderShippingPackage) {
                if ($orderShippingPackage instanceof OrderShippingPackage) {
                    $trackingNumber = $orderShippingPackage->packageNumber;

                    break;
                }
            }

            // Get tracking url
            $parcelServicePreset = $parcelServicePresetRepository->getPresetById($order->shippingProfileId);
            if ($parcelServicePreset->parcelService) {
                $trackingUrl = $parcelServicePreset->parcelService->trackingUrl;
                $carrier = $parcelServicePreset->parcelService->backendName;
            }

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.shippingRequest',
                'Shipping request',
                [
                    $transactionId,
                    $amount->grossTotal,
                    $paymentItems,
                    $deliveryFee,
                    $carrier,
                    $trackingNumber,
                    $trackingUrl
                ]
            )->setReferenceValue($transactionId);

            $identifier = $paymentActionManager->generateIdentifier();
            $paymentActionManager->addAction(
                $orderId,
                $identifier,
                PayeverService::ACTION_REFUND,
                PaymentAction::SOURCE_EXTERNAL,
                $amount->grossTotal
            );

            // shipping the payment
            $shippingResult = $paymentService->shippingGoodsPayment(
                $transactionId,
                $amount->grossTotal,
                $paymentItems,
                $deliveryFee,
                'Ship goods',
                $carrier,
                $trackingNumber,
                $trackingUrl
            );

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.shippingGoodsResponse',
                'Shipping response',
                [
                    'shippingResult' => $shippingResult
                ]
            )->setReferenceValue($transactionId);
        }
    }

    /**
     * Get Payment Items.
     *
     * @param Order $order
     * @return array{identifier: string, name: string, price: float, quantity: int}
     */
    private function getPaymentItems(Order $order)
    {
        $paymentItems = [];
        foreach ($order->orderItems as $orderItem) {
            /** @var OrderItem $orderItem */
            if ($orderItem->typeId !== 1) {
                // Sales order
                continue;
            }

            /** @var OrderItemAmount $amount */
            $amount = $orderItem->amount;

            $paymentItems[] = [
                'identifier' => (string) $orderItem->itemVariationId,
                'name' => $orderItem->orderItemName,
                'price' => $amount->priceGross,
                'quantity' => $orderItem->quantity
            ];
        }

        return $paymentItems;
    }

    /**
     * Get Delivery Fee.
     *
     * @param Order $order
     * @return float|int
     */
    private function getDeliveryFee(Order $order)
    {
        $deliveryFee = 0;
        foreach ($order->orderItems as $orderItem) {
            /** @var OrderItem $orderItem */
            if ($orderItem->typeId !== 6) {
                // Delivery
                continue;
            }

            /** @var OrderItemAmount $amount */
            $amount = $orderItem->amount;

            $deliveryFee += $amount->priceGross;
        }

        return $deliveryFee;
    }
}
