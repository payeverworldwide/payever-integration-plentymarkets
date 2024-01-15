<?php

namespace Payever\Contracts;

use Payever\Models\Log;

interface LogRepositoryContract
{
    /**
     * @return Log
     */
    public function create(): Log;

    /**
     * @param Log $log
     * @return Log
     */
    public function persist(Log $log): Log;

    /**
     * @param Log $log
     * @return bool
     */
    public function delete(Log $log): bool;

    /**
     * @param string $orderId
     * @return Log|null
     */
    public function getByOrderId(string $orderId);

    /**
     * Get by date.
     *
     * @param string $from
     * @param string  $to
     * @return array
     */
    public function getByDate(string $from, string $to): array;
}
