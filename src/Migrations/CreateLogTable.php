<?php

namespace Payever\Migrations;

use Payever\Models\Log;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateLogTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(Log::class);
    }
}
