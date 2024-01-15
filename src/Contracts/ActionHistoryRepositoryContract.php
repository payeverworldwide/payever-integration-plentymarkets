<?php

namespace Payever\Contracts;

use Payever\Models\ActionHistory;

interface ActionHistoryRepositoryContract
{
    /**
     * @return ActionHistory
     */
    public function create(): ActionHistory;

    /**
     * @param ActionHistory $actionHistory
     * @return ActionHistory
     */
    public function persist(ActionHistory $actionHistory): ActionHistory;

    /**
     * @param ActionHistory $actionHistory
     * @return bool
     */
    public function delete(ActionHistory $actionHistory): bool;

    /**
     * @param string $orderId
     * @return ActionHistory|null
     */
    public function getByOrderId(string $orderId);

    /**
     * @param $action
     * @param $orderId
     * @param $amount
     * @return bool
     */
    public function checkActionInHistory($action, $orderId, $amount = null): bool;
}
