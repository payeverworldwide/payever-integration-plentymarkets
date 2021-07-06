<?php

namespace Payever\Services;

use Payever\Controllers\ConfigController;
use Plenty\Modules\Cron\Contracts\CronHandler;

class PayeverCronHandler extends CronHandler
{
    /**
     * @var ConfigController
     */
    private $configController;

    /**
     * @param ConfigController $configController
     */
    public function __construct(ConfigController $configController)
    {
        $this->configController = $configController;
    }

    /**
     * @return void
     */
    public function handle()
    {
        $this->configController->executeCommand();
    }
}
