<?php

namespace Payever\Services\Processor;

use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Helper\StatusHelper;
use Payever\Traits\Logger;
use Plenty\Modules\Order\Models\Order;

/**
 * Class OrderTotalProcessor
 */
class OrderTotalProcessor
{
    use Logger;

    /**
     * @var OrderTotalRepositoryContract
     */
    private OrderTotalRepositoryContract $orderTotalRepository;

    /**
     * @var OrderTotalItemRepositoryContract
     */
    private OrderTotalItemRepositoryContract $orderTotalItemRepository;

    /**
     * @param OrderTotalRepositoryContract $orderTotalRepository
     * @param OrderTotalItemRepositoryContract $orderTotalItemRepository
     */
    public function __construct(
        OrderTotalRepositoryContract $orderTotalRepository,
        OrderTotalItemRepositoryContract $orderTotalItemRepository
    ) {
        $this->orderTotalRepository = $orderTotalRepository;
        $this->orderTotalItemRepository = $orderTotalItemRepository;
    }

    /**
     * @param Order $order
     *
     * @return void
     */
    public function assignOrderTotalsIfNotExists(Order $order)
    {
        if ($this->orderTotalRepository->getByOrderId($order->id)) {
            return;
        }

        $orderTotal = $this->orderTotalRepository->create($order->id);
        if (is_array($order->orderItems) || $order->orderItems instanceof \Traversable) {
            foreach ($order->orderItems as $orderItem) {
                $this->orderTotalItemRepository->create($orderItem);
            }
        }

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.assignOrderTotalsIfNotExists',
            'Assign order totals if not exists',
            ['orderTotal' => $orderTotal]
        )->setReferenceValue($order->id);
    }

    /**
     * @param int $orderId
     * @param string $paymentStatus
     *
     * @return void
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function updateOrderSuccessTotals(int $orderId, string $paymentStatus)
    {
        $statusId = StatusHelper::mapOrderStatus($paymentStatus);
        $orderTotal = $this->orderTotalRepository->getByOrderId($orderId);

        if (
            StatusHelper::PLENTY_ORDER_SUCCESS == $statusId
            && $orderTotal->capturedTotal == 0
            && $paymentStatus !== StatusHelper::STATUS_ACCEPTED
        ) {
            $orderTotalItems = $this->orderTotalItemRepository->getByOrderId($orderId);

            $amount = 0;
            foreach ($orderTotalItems as $orderTotalItem) {
                $amount += $orderTotalItem->totalPrice;
                $orderTotalItem->qtyCaptured = $orderTotalItem->quantity;
                $this->orderTotalItemRepository->persist($orderTotalItem);
            }

            $orderTotal->capturedTotal = $amount;
            $this->orderTotalRepository->persist($orderTotal);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.updateOrderSuccessTotals',
                'Update order totals for shipped status',
                ['orderTotal' => $orderTotal]
            )->setReferenceValue($orderId);
        }
    }
}
