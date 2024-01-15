<?php

namespace Payever\Migrations;

use Payever\Models\ActionHistory;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateActionHistoryTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(ActionHistory::class);
    }
}
