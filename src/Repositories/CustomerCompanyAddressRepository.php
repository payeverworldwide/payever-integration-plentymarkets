<?php

namespace Payever\Repositories;

use Payever\Contracts\CustomerCompanyAddressContract;
use Payever\Models\CustomerCompanyAddress;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class CustomerCompanyAddressRepository implements CustomerCompanyAddressContract
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
    public function create(): CustomerCompanyAddress
    {
        /** @var CustomerCompanyAddress $actionHistory */
        $model = pluginApp(CustomerCompanyAddress::class);

        return $model;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function persist(CustomerCompanyAddress $model): CustomerCompanyAddress
    {
        /** @var CustomerCompanyAddress $result */
        $result = $this->dataBase->save($model);

        return $result;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function delete(CustomerCompanyAddress $model): bool
    {
        return $this->dataBase->delete($model);
    }

    /**
     * @inheritDoc
     */
    public function getByAddressHash(string $hash)
    {
        $result = null;
        $query = $this->dataBase->query(CustomerCompanyAddress::class);
        $query->where('addressHash', '=', $hash);
        $rows = $query->get();
        if (!empty($rows[0]) && $rows[0] instanceof CustomerCompanyAddress) {
            // @codeCoverageIgnoreStart
            $result = $rows[0];
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }
}
