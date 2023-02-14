<?php

namespace Payever\tests\unit\Services;

use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;

class PayeverSdkServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|LibraryCallContract
     */
    private $libCall;

    /**
     * @var MockObject|ConfigRepository
     */
    private $config;

    /**
     * @var MockObject|PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var PayeverSdkService
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->libCall = $this->getMockBuilder(LibraryCallContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder(ConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverHelper = $this->getMockBuilder(PayeverHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new PayeverSdkService(
            $this->libCall,
            $this->config,
            $this->payeverHelper
        );
    }

    public function testCall()
    {
        $this->libCall->expects($this->once())
            ->method('call')
            ->willReturn(['some-data']);
        $this->assertNotEmpty($this->handler->call('some-method', []));
    }
}
