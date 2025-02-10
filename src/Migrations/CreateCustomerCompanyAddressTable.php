<?php

namespace Payever\Migrations;

use Payever\Models\CustomerCompanyAddress;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateCustomerCompanyAddressTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(CustomerCompanyAddress::class);
    }
}
