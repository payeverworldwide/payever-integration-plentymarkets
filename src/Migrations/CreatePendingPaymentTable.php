<?php

namespace Payever\Migrations;

use Payever\Models\PendingPayment;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreatePendingPaymentTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(PendingPayment::class);
    }
}
