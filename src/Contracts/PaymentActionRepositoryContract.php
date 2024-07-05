<?php

namespace Payever\Contracts;

use Payever\Models\PaymentAction;

interface PaymentActionRepositoryContract
{
    /**
     * @return PaymentAction
     */
    public function create(): PaymentAction;

    /**
     * @param PaymentAction $paymentAction
     * @return PaymentAction
     */
    public function persist(PaymentAction $paymentAction): PaymentAction;

    /**
     * @param PaymentAction $paymentAction
     * @return bool
     */
    public function delete(PaymentAction $paymentAction): bool;

    /**
     * Get payment action by $identifier
     *
     * @param int $orderId
     * @param string $identifier
     *
     * @return PaymentAction|null
     */
    public function getAction(int $orderId, string $identifier): ?PaymentAction;
}
