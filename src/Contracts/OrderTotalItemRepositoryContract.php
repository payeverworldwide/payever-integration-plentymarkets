<?php

namespace Payever\Contracts;

use Payever\Models\OrderTotalItem;
use Plenty\Modules\Order\Models\OrderItem;

interface OrderTotalItemRepositoryContract
{
    /**
     * @param OrderItem $orderItem
     * @return OrderTotalItem
     */
    public function create(OrderItem $orderItem): OrderTotalItem;

    /**
     * @param OrderTotalItem $orderTotalItem
     * @return OrderTotalItem
     */
    public function persist(OrderTotalItem $orderTotalItem): OrderTotalItem;

    /**
     * @param OrderTotalItem $orderTotalItem
     * @return bool
     */
    public function delete(OrderTotalItem $orderTotalItem): bool;

    /**
     * @param string $orderId
     * @return OrderTotalItem[]
     */
    public function getByOrderId(string $orderId): array;

    /**
     * @param string $id
     * @return OrderTotalItem|null
     */
    public function findById(string $id): OrderTotalItem|null;

    /**
     * @param $orderId
     * @param $productIdentifier
     * @return OrderTotalItem[]
     */
    public function loadByProductIdentifier($orderId, $productIdentifier): array;

    /**
     * @param $orderId
     * @param $type
     * @return OrderTotalItem[]
     */
    public function getByItemType($orderId, $type): array;
}
