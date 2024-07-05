<?php

namespace Payever\Repositories;

use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Models\PendingPayment;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class PendingPaymentRepository implements PendingPaymentRepositoryContract
{
    /**
     * @var DataBase
     */
    private $dataBase;

    /**
     * @param DataBase $dataBase
     */
    public function __construct(DataBase $dataBase)
    {
        $this->dataBase = $dataBase;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function create(): PendingPayment
    {
        /** @var PendingPayment $pendingPayment */
        $pendingPayment = pluginApp(PendingPayment::class);
        $pendingPayment->timestamp = time();

        return $pendingPayment;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function persist(PendingPayment $pendingPayment): PendingPayment
    {
        return $this->dataBase->save($pendingPayment);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function delete(PendingPayment $pendingPayment): bool
    {
        return $this->dataBase->delete($pendingPayment);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(string $orderId)
    {
        $result = null;
        $query = $this->dataBase->query(PendingPayment::class);
        $query->where('orderId', '=', $orderId);
        $rows = $query->get();
        if (!empty($rows[0]) && $rows[0] instanceof PendingPayment) {
            // @codeCoverageIgnoreStart
            $result = $rows[0];
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }
}
