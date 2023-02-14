<?php

namespace Payever\tests\unit\Services;

use Payever\Controllers\ConfigController;
use Payever\Services\PayeverCronHandler;

class PayeverCronHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ConfigController
     */
    private $configController;

    /**
     * @var PayeverCronHandler
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->configController = $this->getMockBuilder(ConfigController::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new PayeverCronHandler($this->configController);
    }

    public function testHandle()
    {
        $this->configController->expects($this->once())
            ->method('executeCommand');
        $this->handler->handle();
    }
}
