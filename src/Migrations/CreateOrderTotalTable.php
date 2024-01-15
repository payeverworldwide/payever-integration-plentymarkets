<?php

namespace Payever\Migrations;

use Payever\Models\OrderTotal;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateOrderTotalTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(OrderTotal::class);
    }
}
