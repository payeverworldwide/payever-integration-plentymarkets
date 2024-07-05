<?php

namespace Payever\Services\Payment\Notification;

use Payever\Contracts\ActionHistoryRepositoryContract;
use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Helper\OrderItemsManager;
use Payever\Models\ActionHistory;
use Payever\Services\PayeverService;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;
use Payever\Traits\Logger;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class NotificationActionHandler
{
    use Logger;

    const STATUS_REFUNDED = "STATUS_REFUNDED";
    const STATUS_CANCELLED = "STATUS_CANCELLED";

    /**
     * @var OrderItemsManager
     */
    private OrderItemsManager $orderItemsManager;

    /**
     * @var OrderRepositoryContract
     */
    private OrderRepositoryContract $orderRepository;

    /**
     * @var OrderTotalRepositoryContract
     */
    private OrderTotalRepositoryContract $orderTotalRepository;

    /**
     * @var OrderTotalItemRepositoryContract
     */
    private OrderTotalItemRepositoryContract $orderTotalItemRepository;

    /**
     * @var ActionHistoryRepositoryContract
     */
    private ActionHistoryRepositoryContract $actionHistoryRepository;

    /**
     * @param OrderItemsManager $orderItemsManager
     * @param OrderRepositoryContract $orderRepository
     * @param OrderTotalRepositoryContract $orderTotalRepository
     * @param OrderTotalItemRepositoryContract $orderTotalItemRepository
     * @param ActionHistoryRepositoryContract $actionHistoryRepository
     */
    public function __construct(
        OrderItemsManager $orderItemsManager,
        OrderRepositoryContract $orderRepository,
        OrderTotalRepositoryContract $orderTotalRepository,
        OrderTotalItemRepositoryContract $orderTotalItemRepository,
        ActionHistoryRepositoryContract $actionHistoryRepository
    ) {

        $this->orderItemsManager = $orderItemsManager;
        $this->orderRepository = $orderRepository;
        $this->orderTotalRepository = $orderTotalRepository;
        $this->orderTotalItemRepository = $orderTotalItemRepository;
        $this->actionHistoryRepository = $actionHistoryRepository;
    }

    /**
     * @param array $notificationPayment
     * @param int $orderId
     * @return void
     */
    public function handleNotificationAction(array $notificationPayment, int $orderId): void
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.startNotificationActionHandler',
            'Start handle notification action',
            ['notificationPayment' => $notificationPayment]
        );

        $status = $notificationPayment['status'];
        $paymentId = $notificationPayment['id'] ?? null;

        if (!$notificationPayment || !$paymentId) {
            throw new \UnexpectedValueException('Notification entity is invalid', 21);
        }

        $order = $this->orderRepository->findOrderById($orderId);

        if (in_array($status, [self::STATUS_REFUNDED, self::STATUS_CANCELLED])) {
            // Handle refund by items
            if ($this->isRefundedItemsSet($notificationPayment)) {
                $this->handleRefundItemsActionNotification($order, $notificationPayment);

                return;
            }

            // Handle refund by amount
            if ($this->isRefundAmountSet($notificationPayment)) {
                $this->handleRefundAmountActionNotification($order, $notificationPayment);

                return;
            }
        }

        // Handle cancel by amount
        if ($this->isCancelAmountSet($notificationPayment)) {
            $this->handleCancelAmountActionNotification($order, $notificationPayment);

            return;
        }

        // Capture notification can have any `status`
        // Handle capturing by items
        if ($this->isCapturedItemsSet($notificationPayment)) {
            $this->handleShippingGoodsItemsActionNotification($order, $notificationPayment);

            return;
        }

        // Handle capturing by amount
        if ($this->isCaptureAmountSet($notificationPayment)) {
            $this->handleShippingGoodsAmountActionNotification($order, $notificationPayment);
        }
    }

    /**
     * Handle Refund Action by Items.
     *
     * @param Order $order
     * @param array $notificationPayment
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function handleRefundItemsActionNotification(Order $order, array $notificationPayment): void
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleRefundItemsAction',
            'Handle refund items action',
            ['notificationPayment' => $notificationPayment, 'order' => $order]
        );

        $amount = 0;
        $items = $notificationPayment['refunded_items'];
        $deliveryFee = isset($notificationPayment['delivery_fee']) ?
            round($notificationPayment['delivery_fee'], 2) : 0;

        // Attention: `refunded_items` is historical, so update database. `refund_amount` is not historical.
        foreach ($items as $item) {
            $orderTotalItem = $this->orderItemsManager->getOrderTotalItemsByProductIdentifier(
                (int)$order->id,
                $item['identifier'],
                true
            );

            if (!$orderTotalItem) {
                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.notificationItemIsNotFound',
                    'Item is not found',
                    ['itemIdentifier' => $item['identifier']]
                );

                continue;
            }

            $orderTotalItem->setQtyRefunded($item['quantity']);

            $this->orderTotalItemRepository->persist($orderTotalItem);

            $amount += $item['price'] * $item['quantity'];
        }

        // Set historical amount
        $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);

        $orderTotal->setRefundedTotal($amount);
        $this->orderTotalRepository->persist($orderTotal);

        // Update totals
        if (isset($notificationPayment['refund_amount'])) {
            // Transaction amount could have delivery fee
            $transactionAmount = $notificationPayment['refund_amount'];
            if ($transactionAmount > $amount) {
                $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);
                $orderTotal->setRefundedTotal($transactionAmount);
                $this->orderTotalRepository->persist($orderTotal);
            }

            // Mark shipping refunded
            if (round($transactionAmount - $amount, 2) >= $deliveryFee) {
                $orderTotalItem = $this->orderItemsManager->getOrderShippingItem($order->id);
                if ($orderTotalItem) {
                    $orderTotalItem->setQtyRefunded(1);
                    $this->orderTotalItemRepository->persist($orderTotalItem);
                }
            }
        }

        $totalAmount = isset($notificationPayment['total_refunded_amount'])
            ? (float)$notificationPayment['total_refunded_amount'] : null;

        if ($totalAmount) {
            $orderTotal->setRefundedTotal(round($totalAmount, 2));
        }

        $this->orderTotalRepository->persist($orderTotal);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleRefundItemsAction',
            'Handle refund items action',
            [
                'message' => sprintf(
                    '[Notification] Refunded items. Amount of historical items: %s. Transaction amount: %s',
                    $amount,
                    $notificationPayment['refund_amount'] ?? null
                ),
                'items' => $items,
            ]
        );
    }

    /**
     * Handle Refund Action by Amount.
     *
     * @param Order $order
     * @param array $notificationPayment
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function handleRefundAmountActionNotification(Order $order, array $notificationPayment): void
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleRefundAmountAction',
            'Handle refund amount action',
            [
                'notificationPayment' => $notificationPayment,
                'order' => $order,
            ]
        );

        if (
            $this->actionHistoryRepository->checkActionInHistory(
                PayeverService::ACTION_REFUND,
                $order->id,
                $notificationPayment['refund_amount']
            )
        ) {
            $this->addActionInHistoryLog($order, $notificationPayment['refund_amount']);

            return;
        }

        // Update totals
        $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);

        $amount = $orderTotal->getRefundedTotal() + $notificationPayment['refund_amount'];

        $totalAmount = isset($notificationPayment['total_refunded_amount'])
            ? (float)$notificationPayment['total_refunded_amount'] : null;

        $orderTotal->setRefundedTotal($amount);

        if ($totalAmount) {
            $orderTotal->setRefundedTotal(round($totalAmount, 2));
        }

        $orderTotal->setManual(1);
        $this->orderTotalRepository->persist($orderTotal);

        // Save in history
        $actionHistory = $this->actionHistoryRepository->create();
        $actionHistory->setAction('refund');
        $actionHistory->setOrderId($order->id);
        $actionHistory->setSource(ActionHistory::SOURCE_NOTIFICATION);
        $actionHistory->setAmount($notificationPayment['refund_amount']);
        $actionHistory->setTimestamp(time());
        $this->actionHistoryRepository->persist($actionHistory);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleRefundAmountAction',
            'Handle refund amount action',
            [
                'order' => $order->id,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Handle Cancel Action by Amount.
     *
     * @param Order $order
     * @param array $notificationPayment
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function handleCancelAmountActionNotification(Order $order, array $notificationPayment): void
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleCancelAmountAction',
            'Handle cancel amount action',
            [
                'notificationPayment' => $notificationPayment,
                'order' => $order,
            ]
        );

        if (
            $this->actionHistoryRepository->checkActionInHistory(
                PayeverService::ACTION_CANCEL,
                $order->id,
                $notificationPayment['cancel_amount']
            )
        ) {
            $this->addActionInHistoryLog($order, $notificationPayment['cancel_amount']);

            return;
        }

        // Workaround for the problem: cancel_amount has missing delivery_fee
        if (
            $this->actionHistoryRepository->checkActionInHistory(
                PayeverService::ACTION_CANCEL,
                $order->id,
                $notificationPayment['cancel_amount'] + $notificationPayment['delivery_fee']
            )
        ) {
            $this->addActionInHistoryLog(
                $order,
                $notificationPayment['cancel_amount'] + $notificationPayment['delivery_fee']
            );

            return;
        }

        $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);

        $amount = $orderTotal->getCancelledTotal() + $notificationPayment['cancel_amount'];

        // Update totals
        $totalAmount = isset($notificationPayment['total_canceled_amount'])
            ? (float)$notificationPayment['total_canceled_amount'] : null;

        $orderTotal->setCancelledTotal($amount);

        if ($totalAmount) {
            $orderTotal->setCancelledTotal(round($totalAmount, 2));
        }
        $orderTotal->setManual(1);
        $this->orderTotalRepository->persist($orderTotal);

        // Save in history
        $actionHistory = $this->actionHistoryRepository->create();
        $actionHistory->setAction('cancel');
        $actionHistory->setOrderId($order->id);
        $actionHistory->setSource(ActionHistory::SOURCE_NOTIFICATION);
        $actionHistory->setAmount($notificationPayment['cancel_amount']);
        $actionHistory->setTimestamp(time());
        $this->actionHistoryRepository->persist($actionHistory);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleCancelAmountAction',
            'Handle cancel amount action',
            [
                'order' => $order->id,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Handle Shipping Goods Action by Items.
     *
     * @param Order $order
     * @param array $notificationPayment
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function handleShippingGoodsItemsActionNotification(Order $order, array $notificationPayment): void
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleShippingItemsAction',
            'Handle shipping items action',
            [
                'notificationPayment' => $notificationPayment,
                'order' => $order,
            ]
        );

        $capturedItems = $notificationPayment['captured_items'];
        $deliveryFee = isset($notificationPayment['delivery_fee']) ?
            round($notificationPayment['delivery_fee'], 2) : 0;

        $amount = $this->calculateCapturedItems($order, $capturedItems);

        // Set historical amount
        $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);

        $orderTotal->setCapturedTotal($amount);
        $this->orderTotalRepository->persist($orderTotal);

        // Update totals
        if (isset($notificationPayment['capture_amount'])) {
            // Transaction amount could have delivery fee
            $transactionAmount = $notificationPayment['capture_amount'];
            if ($transactionAmount > $amount) {
                $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);
                $orderTotal->setCapturedTotal($transactionAmount);
                $this->orderTotalRepository->persist($orderTotal);

                // Mark shipping captured
                if (round($transactionAmount - $amount, 2) >= $deliveryFee) {
                    $orderTotalItem = $this->orderItemsManager->getOrderShippingItem($order->id);
                    if ($orderTotalItem) {
                        $orderTotalItem->setQtyCaptured(1);
                        $this->orderTotalItemRepository->persist($orderTotalItem);
                    }
                }
            }
        }

        $totalAmount = isset($notificationPayment['total_captured_amount'])
            ? (float)$notificationPayment['total_captured_amount'] : null;

        if ($totalAmount) {
            $orderTotal->setCapturedTotal(round($totalAmount, 2));
        }

        $this->orderTotalRepository->persist($orderTotal);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleShippingItemsAction',
            sprintf(
                '[Notification] Captured items. Amount of historical items: %s. Transaction amount: %s',
                $amount,
                $notificationPayment['capture_amount'] ?? null
            ),
            [
                'items' => $capturedItems,
            ]
        );
    }

    /**
     * Handle Shipping Goods Action by Amount.
     *
     * @param Order $order
     * @param array $notificationPayment
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function handleShippingGoodsAmountActionNotification(Order $order, array $notificationPayment): void
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleShippingAmountAction',
            'Handle shipping amount action',
            [
                'notificationPayment' => $notificationPayment,
                'order' => $order,
            ]
        );

        if (
            $this->actionHistoryRepository->checkActionInHistory(
                PayeverService::ACTION_SHIPPING_GOODS,
                $order->id,
                $notificationPayment['capture_amount']
            )
        ) {
            $this->addActionInHistoryLog($order, $notificationPayment['capture_amount']);

            return;
        }

        $orderTotal = $this->orderItemsManager->getOrderTotal($order->id);

        $amount = $orderTotal->getCapturedTotal() + $notificationPayment['capture_amount'];

        // Update totals
        $totalAmount = isset($notificationPayment['total_captured_amount'])
            ? (float)$notificationPayment['total_captured_amount'] : null;

        $orderTotal->setCapturedTotal($amount);

        if ($totalAmount) {
            $orderTotal->setCapturedTotal(round($totalAmount, 2));
        }

        $orderTotal->setManual(1);
        $this->orderTotalRepository->persist($orderTotal);

        // Save in history
        $actionHistory = $this->actionHistoryRepository->create();
        $actionHistory->setAction('shipping_goods');
        $actionHistory->setOrderId($order->id);
        $actionHistory->setSource(ActionHistory::SOURCE_NOTIFICATION);
        $actionHistory->setAmount($notificationPayment['capture_amount']);
        $actionHistory->setTimestamp(time());
        $this->actionHistoryRepository->persist($actionHistory);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationHandleShippingAmountAction',
            '[Notification] Captured amount. Amount: ' . $amount,
            [
                'order' => $order->id,
                'amount' => $amount,
            ]
        );
    }

    /**
     * @param Order $order
     * @param array $capturedItems
     * @return float|int
     */
    private function calculateCapturedItems(Order $order, array $capturedItems)
    {
        $amount = 0;

        // Attention: `captured_items` is historical, so update database. `capture_amount` is not historical.
        foreach ($capturedItems as $item) {
            $orderTotalItem = $this->orderItemsManager->getOrderTotalItemsByProductIdentifier(
                $order->id,
                $item['identifier'],
                true
            );

            if (!$orderTotalItem) {
                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.notificationItemIsNotFound',
                    'Item is not found',
                    ['itemIdentifier' => $item['identifier']]
                );

                continue;
            }

            $orderTotalItem->setQtyCaptured($item['quantity']);
            $this->orderTotalItemRepository->persist($orderTotalItem);

            $amount += $item['price'] * $item['quantity'];
        }

        return $amount;
    }

    /**
     * @param Order $order
     * @param float $amount
     * @return void
     */
    private function addActionInHistoryLog(Order $order, $amount)
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationPaymentActionRejected',
            'Payment action was rejected because it was registered before',
            [
                'order' => $order->id,
                'amount' => $amount,
            ]
        );
    }

    /**
     * @param $notificationPayment
     * @return bool
     */
    private function isRefundedItemsSet($notificationPayment): bool
    {
        return isset($notificationPayment['refunded_items'])
            && count($notificationPayment['refunded_items']) > 0;
    }

    /**
     * @param $notificationPayment
     * @return bool
     */
    private function isRefundAmountSet($notificationPayment): bool
    {
        return
            (
                !isset($notificationPayment['refunded_items'])
                || count($notificationPayment['refunded_items']) === 0
            )
            && (isset($notificationPayment['refund_amount']) && $notificationPayment['refund_amount'] > 0);
    }

    /**
     * @param $notificationPayment
     * @return bool
     */
    private function isCancelAmountSet($notificationPayment): bool
    {
        return isset($notificationPayment['cancel_amount'])
            && $notificationPayment['cancel_amount'] > 0;
    }

    /**
     * @param $notificationPayment
     * @return bool
     */
    private function isCapturedItemsSet($notificationPayment): bool
    {
        return isset($notificationPayment['captured_items'])
            && count($notificationPayment['captured_items']) > 0;
    }

    /**
     * @param $notificationPayment
     * @return bool
     */
    private function isCaptureAmountSet($notificationPayment): bool
    {
        return
            (
                !isset($notificationPayment['captured_items'])
                || count($notificationPayment['captured_items']) === 0
            )
            && (isset($notificationPayment['capture_amount']) && $notificationPayment['capture_amount'] > 0);
    }
}
