<?php

namespace Payever\Contracts;

use Payever\Models\PendingPayment;

interface PendingPaymentRepositoryContract
{
    /**
     * @return PendingPayment
     */
    public function create(): PendingPayment;

    /**
     * @param PendingPayment $pendingPayment
     * @return PendingPayment
     */
    public function persist(PendingPayment $pendingPayment): PendingPayment;

    /**
     * @param PendingPayment $pendingPayment
     * @return bool
     */
    public function delete(PendingPayment $pendingPayment): bool;

    /**
     * @param string $orderId
     * @return PendingPayment|null
     */
    public function getByOrderId(string $orderId);
}
