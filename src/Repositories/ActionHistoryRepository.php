<?php

namespace Payever\Repositories;

use Payever\Contracts\ActionHistoryRepositoryContract;
use Payever\Models\ActionHistory;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class ActionHistoryRepository implements ActionHistoryRepositoryContract
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
    public function create(): ActionHistory
    {
        return pluginApp(ActionHistory::class);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function persist(ActionHistory $actionHistory): ActionHistory
    {
        return $this->dataBase->save($actionHistory);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function delete(ActionHistory $actionHistory): bool
    {
        return $this->dataBase->delete($actionHistory);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(string $orderId)
    {
        $result = null;
        $query = $this->dataBase->query(ActionHistory::class);
        $query->where('orderId', '=', $orderId);
        $rows = $query->get();
        if (!empty($rows[0]) && $rows[0] instanceof ActionHistory) {
            // @codeCoverageIgnoreStart
            $result = $rows[0];
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function checkActionInHistory($action, $orderId, $amount = null): bool
    {
        $query = $this->dataBase->query(ActionHistory::class);

        $query
            ->where('orderId', '=', $orderId)
            ->where('action', '=', $action)
            ->whereBetween('timestamp', [time() - 300, time() + 300]);

        if ($amount) {
            $query->where('amount', '=', round($amount, 2));
        }

        return $query->count() > 0;
    }
}
