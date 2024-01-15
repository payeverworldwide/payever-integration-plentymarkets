<?php

namespace Payever\Contracts;

use Payever\Models\OrderTotal;

interface OrderTotalRepositoryContract
{
    /**
     * @param $orderId
     * @return OrderTotal
     */
    public function create($orderId): OrderTotal;

    /**
     * @param OrderTotal $orderTotal
     * @return OrderTotal
     */
    public function persist(OrderTotal $orderTotal): OrderTotal;

    /**
     * @param OrderTotal $orderTotal
     * @return bool
     */
    public function delete(OrderTotal $orderTotal): bool;

    /**
     * @param string $orderId
     * @return OrderTotal|null
     */
    public function getByOrderId(string $orderId): ?OrderTotal;

    /**
     * @param string $orderBy
     * @return OrderTotal[]
     */
    public function getAll(string $orderBy): array;

    /**
     * @param $page
     * @param $itemsPerPage
     * @param string $orderBy
     * @return OrderTotal[]
     */
    public function getPaginate($page, $itemsPerPage, string $orderBy = 'desc'): array;
}
