<?php

namespace Payever\Services;

use Plenty\Modules\Cron\Contracts\CronHandler;
use Payever\Controllers\ConfigController;

class PayeverCronHandler extends CronHandler
{
    /**
     * @var ConfigController
     */
    private $configController;

    /**
     * PayeverCronHandler constructor.
     * @param ConfigController $configController
     */
    public function __construct(ConfigController $configController)
    {
        $this->configController = $configController;
    }

    public function handle()
    {
        $this->configController->executeCommand();
    }
}
