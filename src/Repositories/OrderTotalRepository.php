<?php

namespace Payever\Repositories;

use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Models\OrderTotal;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Plugin\Log\Loggable;

class OrderTotalRepository implements OrderTotalRepositoryContract
{
    use Loggable;

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
    public function create($orderId): OrderTotal
    {
        /** @var OrderTotal $orderTotal */
        $orderTotal = pluginApp(OrderTotal::class);

        $orderTotal->orderId = $orderId;
        $orderTotal->cancelledTotal = 0.0;
        $orderTotal->capturedTotal = 0.0;
        $orderTotal->refundedTotal = 0.0;
        $orderTotal->manual = false;
        $orderTotal->timestamp = (string) time();
        $this->dataBase->save($orderTotal);

        return $orderTotal;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function persist(OrderTotal $orderTotal): OrderTotal
    {
        return $this->dataBase->save($orderTotal);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function delete(OrderTotal $orderTotal): bool
    {
        return $this->dataBase->delete($orderTotal);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(string $orderId): ?OrderTotal
    {
        $result = null;
        $query = $this->dataBase->query(OrderTotal::class);
        $query->where('orderId', '=', $orderId);
        $rows = $query->get();
        if (!empty($rows[0]) && $rows[0] instanceof OrderTotal) {
            // @codeCoverageIgnoreStart
            $result = $rows[0];
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getAll(string $orderBy = 'desc'): array
    {
        return $this->dataBase->query(OrderTotal::class)
            ->orderBy('id', $orderBy)
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function getPaginate($page, $itemsPerPage, string $orderBy = 'desc'): array
    {
        $totalCount = $this->dataBase->query(OrderTotal::class)->count();
        $pagesCount = ceil($totalCount / $itemsPerPage);
        $entries = $this->dataBase->query(OrderTotal::class)
            ->forPage($page, $itemsPerPage)
            ->orderBy('id', $orderBy)
            ->get();

        return [
            'pages' => $pagesCount,
            'total' => $totalCount,
            'entries' => $entries
        ];
    }
}
