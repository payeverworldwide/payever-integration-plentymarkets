<?php

namespace Payever\Migrations;

use Payever\Models\PaymentAction;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreatePaymentActionTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(PaymentAction::class);
    }
}
