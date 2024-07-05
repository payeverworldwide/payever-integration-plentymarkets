<?php

namespace Payever\Helper;

use Exception;
use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Models\OrderTotalItem;
use Payever\Models\OrderTotal;
use Payever\Services\PayeverService;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class OrderItemsManager
{
    use Loggable;

    /**
     * @link https://developers.plentymarkets.com/en-gb/interface/stable7/Order.html#order_models_orderitemtype
     */
    const TYPE_PRODUCT = 1;
    const TYPE_SHIPPING = 6;

    const CANCEL_ACTION = 'cancel';
    const REFUND_ACTION = 'refund';
    const CAPTURE_ACTION = 'shipping';

    /**
     * @var OrderTotalRepositoryContract
     */
    private OrderTotalRepositoryContract $orderTotalRepository;

    /**
     * @var OrderTotalItemRepositoryContract
     */
    private OrderTotalItemRepositoryContract $orderTotalItemRepository;

    /**
     * @var PaymentMethodRepositoryContract
     */
    private PaymentMethodRepositoryContract $paymentMethodRepository;

    /**
     * @var PayeverService
     */
    private PayeverService $payeverService;

    /**
     * @var OrderRepositoryContract
     */
    private OrderRepositoryContract $orderRepository;

    public function __construct(
        OrderTotalRepositoryContract $orderTotalRepository,
        OrderTotalItemRepositoryContract $orderTotalItemRepository,
        PaymentMethodRepositoryContract $paymentMethodRepository,
        OrderRepositoryContract $orderRepository,
        PayeverService $payeverService
    ) {
        $this->orderTotalRepository = $orderTotalRepository;
        $this->orderTotalItemRepository = $orderTotalItemRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->payeverService = $payeverService;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param $page
     * @param $itemsPerPage
     * @return array
     */
    public function getOrderTotals($page, $itemsPerPage): array
    {
        $orderTotalsPaginate = $this->orderTotalRepository->getPaginate($page, $itemsPerPage);

        $this->getLogger(__METHOD__)
            ->setReferenceType('payeverLog')
            ->debug('Payever::debug.basic', $orderTotalsPaginate);

        $result = [];
        foreach ($orderTotalsPaginate['entries'] as $orderTotal) {
            $order = $this->orderRepository->findOrderById($orderTotal->orderId);
            $result[] = $this->compactOrderTotal($orderTotal, $order);
        }

        $orderTotalsPaginate['entries'] = $result;

        return $orderTotalsPaginate;
    }

    /**
     * @param OrderTotal $orderTotal
     * @param Order $order
     * @return array
     */
    private function compactOrderTotal(OrderTotal $orderTotal, Order $order): array
    {
        $payment = $this->paymentMethodRepository->findByPaymentMethodId($order->methodOfPaymentId);

        return [
            'id' => $orderTotal->id,
            'orderId' => $orderTotal->orderId,
            'refundedTotal' => $orderTotal->refundedTotal,
            'cancelledTotal' => $orderTotal->cancelledTotal,
            'capturedTotal' => $orderTotal->capturedTotal,
            'status' => $order->statusName,
            'amount' => $order->amount,
            'payment' => $payment,
            'actions' => $this->payeverService->getRequiredActions($orderTotal->orderId),
            'transactions' => $this->payeverService->getTransactionsId($orderTotal->orderId),
            'manual' => (bool) $orderTotal->manual,
        ];
    }

    /**
     * @param $orderId
     * @param $action
     * @return array
     */
    public function getOrderTotalItems($orderId, $action): array
    {
        $orderTotalItems = $this->orderTotalItemRepository->getByOrderId($orderId);

        $entries = [];

        foreach ($orderTotalItems as $item) {
            $entries[] = $this->compactOrderTotalItem($item, $action);
        }

        return [
            'action' => $action,
            'availableAmountByAction' => $this->getAvailableAmountByAction($orderId, $action),
            'entries' => $entries
        ];
    }

    /**
     * @param OrderTotalItem $item
     * @param string $action
     * @return array
     */
    private function compactOrderTotalItem(OrderTotalItem $item, string $action): array
    {
        $compactItem = [
            'id' => $item->id,
            'orderId' => $item->orderId,
            'itemType' => $item->itemType,
            'itemId' => $item->itemId,
            'name' => $item->name,
            'identifier' => $item->identifier,
            'quantity' => $item->quantity,
            'unitPrice' => $item->unitPrice,
            'totalPrice' => $item->totalPrice,
            'qtyByAction' => 0,
            'totalByAction' => 0,
            'availableQtyByAction' => 0
        ];

        switch ($action) {
            case self::CANCEL_ACTION:
                $compactItem['qtyByAction'] = $item->qtyCancelled;
                $compactItem['totalByAction'] = $item->unitPrice * $item->qtyCancelled;
                $compactItem['availableQtyByAction'] = $item->quantity - $item->qtyCaptured - $item->qtyCancelled;
                break;
            case self::REFUND_ACTION:
                $compactItem['qtyByAction'] = $item->qtyRefunded;
                $compactItem['totalByAction'] = $item->unitPrice * $item->qtyRefunded;
                $compactItem['availableQtyByAction'] = $item->qtyCaptured - $item->qtyRefunded;
                break;
            case self::CAPTURE_ACTION:
                $compactItem['qtyByAction'] = $item->qtyCaptured;
                $compactItem['totalByAction'] = $item->unitPrice * $item->qtyCaptured;
                $compactItem['availableQtyByAction'] = $item->quantity - $item->qtyCaptured - $item->qtyCancelled;
                break;
            default:
                break;
        }

        return $compactItem;
    }

    /**
     * @param $orderId
     * @param $action
     * @return float
     */
    private function getAvailableAmountByAction($orderId, $action): float
    {
        $orderTotal = $this->orderTotalRepository->getByOrderId($orderId);

        return match ($action) {
            self::CANCEL_ACTION => $orderTotal->getAvailableForCancel(),
            self::REFUND_ACTION => $orderTotal->getAvailableForRefund(),
            self::CAPTURE_ACTION => $orderTotal->getAvailableForCapture(),
            default => 0,
        };
    }

    /**
     * Prepare items for request to SDK
     *
     * @param array $items
     * @return array
     */
    public function getPaymentItemsForAction(array $items): array
    {
        $paymentItems = [];
        foreach ($items as $item) {
            /** @var array{id: int, reference: string, qty: int} $item */
            $orderTotalItem = $this->orderTotalItemRepository->findById($item['id']);

            if (self::TYPE_SHIPPING == $orderTotalItem->itemType) {
                continue;
            }

            $paymentItems[] = [
                'identifier' => $orderTotalItem->identifier,
                'name' => $orderTotalItem->name,
                'price' => (float) $orderTotalItem->unitPrice,
                'quantity' => (int) $item['qty']
            ];
        }

        return $paymentItems;
    }

    /**
     *
     * @param array $items
     * @param bool $withDeliveryFee
     * @return float
     */
    public function getPaymentAmountByItems(array $items, bool $withDeliveryFee = false): float
    {
        $amount = 0;
        foreach ($items as $item) {
            /** @var array{id: int, reference: string, qty: int} $item */
            $orderTotalItem = $this->orderTotalItemRepository->findById($item['id']);

            if (self::TYPE_SHIPPING == $orderTotalItem->itemType) {
                if ($withDeliveryFee) {
                    $amount += $orderTotalItem->unitPrice * $item['qty'];
                }
                continue;
            }

            $amount += $orderTotalItem->unitPrice * $item['qty'];
        }

        return $amount;
    }

    /**
     * @param $orderId
     * @return OrderTotalItem|null
     */
    public function getOrderShippingItem($orderId): ?OrderTotalItem
    {
        $orderTotalItems = $this->orderTotalItemRepository->getByItemType($orderId, self::TYPE_SHIPPING);

        foreach ($orderTotalItems as $orderTotalItem) {
            return $orderTotalItem;
        }

        return null;
    }

    /**
     * @param array $items
     * @return float|null
     */
    public function getDeliveryFee(array $items): float|null
    {
        $amount = null;
        foreach ($items as $item) {
            /** @var array{id: int, reference: string, qty: int} $item */
            $orderTotalItem = $this->orderTotalItemRepository->findById($item['id']);

            if (self::TYPE_SHIPPING == $orderTotalItem->itemType) {
                $amount += round($orderTotalItem->totalPrice, 2);
            }
        }

        return $amount;
    }

    /**
     * @param $orderId
     * @param $productIdentifier
     * @param bool $single
     * @return false|OrderTotalItem|OrderTotalItem[]
     */
    public function getOrderTotalItemsByProductIdentifier(
        $orderId,
        $productIdentifier,
        bool $single = true
    ): bool|OrderTotalItem|array {
        $orderTotalItems = $this->orderTotalItemRepository->loadByProductIdentifier($orderId, $productIdentifier);
        foreach ($orderTotalItems as $idx => $orderTotalItem) {
            if ($single) {
                return $orderTotalItem;
            }

            $orderTotalItems[$idx] = $orderTotalItem;
        }

        if (empty($orderTotalItems)) {
            return false;
        }

        return $orderTotalItems;
    }

    /**
     * @param $orderId
     * @return OrderTotal
     */
    public function getOrderTotal($orderId): OrderTotal
    {
        return $this->orderTotalRepository->getByOrderId($orderId);
    }

    /**
     * @param $orderItemId
     * @return OrderTotalItem
     */
    public function getOrderTotalItem($orderItemId): OrderTotalItem
    {
        return $this->orderTotalItemRepository->findById($orderItemId);
    }

    /**
     * @param $orderId
     * @param float $amount
     * @param bool $isManual
     * @return OrderTotal
     */
    public function addCancelledAmount($orderId, float $amount, bool $isManual = false): OrderTotal
    {
        $orderTotal = $this->orderTotalRepository->getByOrderId($orderId);

        $orderTotal->cancelledTotal += $amount;

        if ($orderTotal->cancelledTotal > $orderTotal->getTotal()) {
            $orderTotal->cancelledTotal = $orderTotal->getTotal();
        }

        $orderTotal->manual = $isManual;

        return $this->orderTotalRepository->persist($orderTotal);
    }

    /**
     * @param $orderId
     * @param float $amount
     * @param bool $isManual
     * @return OrderTotal
     */
    public function addRefundedAmount($orderId, float $amount, bool $isManual = false): OrderTotal
    {
        $orderTotal = $this->orderTotalRepository->getByOrderId($orderId);

        $orderTotal->refundedTotal += $amount;

        if ($orderTotal->refundedTotal > $orderTotal->getTotal()) {
            $orderTotal->refundedTotal = $orderTotal->getTotal();
        }

        $orderTotal->manual = $isManual;

        return $this->orderTotalRepository->persist($orderTotal);
    }

    /**
     * @param $orderId
     * @param float $amount
     * @param bool $isManual
     * @return OrderTotal
     */
    public function addCapturedAmount($orderId, float $amount, bool $isManual = false): OrderTotal
    {
        $orderTotal = $this->orderTotalRepository->getByOrderId($orderId);

        $orderTotal->capturedTotal += $amount;

        if ($orderTotal->capturedTotal > $orderTotal->getTotal()) {
            $orderTotal->capturedTotal = $orderTotal->getTotal();
        }

        $orderTotal->manual = $isManual;

        return $this->orderTotalRepository->persist($orderTotal);
    }

    /**
     * @param $orderId
     * @param array $items
     * @return OrderTotal
     */
    public function cancelOrderItems($orderId, array $items): OrderTotal
    {
        $amount = 0;
        foreach ($items as $item) {
            $orderTotalItem = $this->orderTotalItemRepository->findById($item['id']);

            $cancelQty = $item['qty'];
            $unitPrice = $orderTotalItem->unitPrice;

            $orderTotalItem->qtyCancelled += $cancelQty;

            $amount += ($unitPrice * $cancelQty);

            if ($orderTotalItem->qtyCancelled > $orderTotalItem->quantity) {
                $orderTotalItem->qtyCancelled = $orderTotalItem->quantity;
                $amount = $orderTotalItem->totalPrice;
            }

            $this->orderTotalItemRepository->persist($orderTotalItem);
        }

        return $this->addCancelledAmount($orderId, $amount);
    }

    /**
     * @param $orderId
     * @param array $items
     * @return OrderTotal
     */
    public function refundOrderItems($orderId, array $items): OrderTotal
    {
        $amount = 0;
        foreach ($items as $item) {
            $orderTotalItem = $this->orderTotalItemRepository->findById($item['id']);

            $refundQty = $item['qty'];
            $unitPrice = $orderTotalItem->unitPrice;

            $orderTotalItem->qtyRefunded += $refundQty;

            $amount += ($unitPrice * $refundQty);

            if ($orderTotalItem->qtyRefunded > $orderTotalItem->quantity) {
                $orderTotalItem->qtyRefunded = $orderTotalItem->quantity;
                $amount = $orderTotalItem->totalPrice;
            }

            $this->orderTotalItemRepository->persist($orderTotalItem);
        }

        return $this->addRefundedAmount($orderId, $amount);
    }

    /**
     * @param $orderId
     * @param array $items
     * @return OrderTotal
     */
    public function shipOrderItems($orderId, array $items): OrderTotal
    {
        $amount = 0;
        foreach ($items as $item) {
            $orderTotalItem = $this->orderTotalItemRepository->findById($item['id']);

            $captureQty = $item['qty'];
            $unitPrice = $orderTotalItem->unitPrice;

            $orderTotalItem->qtyCaptured += $captureQty;

            $amount += ($unitPrice * $captureQty);

            if ($orderTotalItem->qtyCaptured > $orderTotalItem->quantity) {
                $orderTotalItem->qtyCaptured = $orderTotalItem->quantity;
                $amount = $orderTotalItem->totalPrice;
            }

            $this->orderTotalItemRepository->persist($orderTotalItem);
        }

        return $this->addCapturedAmount($orderId, $amount);
    }
}
