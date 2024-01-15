<?php

namespace Payever\Migrations;

use Payever\Models\OrderTotalItem;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateOrderTotalItemTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(OrderTotalItem::class);
    }
}
