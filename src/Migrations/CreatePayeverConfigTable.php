<?php

namespace Payever\Migrations;

use Payever\Models\PayeverConfig;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

/**
 * Class CreatePayeverConfigTable
 */
class CreatePayeverConfigTable
{
    /**
     * @param Migrate $migrate
     */
    public function run(Migrate $migrate)
    {
        $migrate->createTable(PayeverConfig::class);
    }
}