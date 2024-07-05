<?php

namespace Payever\Repositories;

use Payever\Contracts\PaymentActionRepositoryContract;
use Payever\Models\PaymentAction;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class PaymentActionRepository implements PaymentActionRepositoryContract
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
     */
    public function create(): PaymentAction
    {
        /** @var PaymentAction $paymentAction */
        $paymentAction = pluginApp(PaymentAction::class);

        return $paymentAction;
    }

    /**
     * @inheritDoc
     */
    public function persist(PaymentAction $paymentAction): PaymentAction
    {
        /** @var PaymentAction $result */
        $result = $this->dataBase->save($paymentAction);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function delete(PaymentAction $paymentAction): bool
    {
        return $this->dataBase->delete($paymentAction);
    }

    /**
     * @inheritDoc
     */
    public function getAction(int $orderId, string $identifier): ?PaymentAction
    {
        $query = $this->dataBase->query(PaymentAction::class);
        $query->where('orderId', '=', $orderId);
        $query->where('uniqueIdentifier', '=', $identifier);

        $rows = $query->get();

        return $rows[0] ?? null;
    }
}
