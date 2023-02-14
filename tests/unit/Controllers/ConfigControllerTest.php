<?php

namespace Payever\tests\unit\Controllers;

use Payever\Controllers\ConfigController;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Payever\tests\unit\mock\Component\HttpFoundation\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Plenty\Plugin\Http\Request;

class ConfigControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|Response
     */
    private $response;

    /**
     * @var MockObject|ConfigurationRepositoryContract
     */
    private $config;

    /**
     * @var MockObject|PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var MockObject|PayeverSdkService
     */
    private $sdkService;

    /**
     * @var ConfigController
     */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder(ConfigurationRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverHelper = $this->getMockBuilder(PayeverHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sdkService = $this->getMockBuilder(PayeverSdkService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new ConfigController(
            $this->request,
            $this->config,
            $this->payeverHelper,
            $this->sdkService
        );
    }

    public function testSynchronize()
    {
        $this->request->expects($this->at(0))
            ->method('get')
            ->willReturn(1);
        $this->config->expects($this->once())
            ->method('export')
            ->willReturn([
                'Payever' => [
                    'clientId' => 'some-client-id',
                    'clientSecret' => 'some-client-secret',
                    'slug' => 'some-slug',
                    'environment' => 'dev',
                ],
            ]);
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn([
                'result' => [
                    [
                        'payment_method' => 'paypal',
                        'status' => 'active',
                        'name' => 'paypal',
                        'description_offer' => 'paypal',
                        'variable_fee' => 1,
                        'fixed_fee' => 2,
                        'min' => 3,
                        'max' => 3,
                        'options' => [
                            'countries' => ['DE'],
                            'currencies' => ['EUR'],
                        ],
                    ]
                ],
            ]);
        $this->payeverHelper->expects($this->once())
            ->method('getMethodsMetaData')
            ->willReturn([
                'paypal' => []
            ]);
        $this->controller->synchronize();
    }

    public function testExecuteCommandCaseSandbox()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->sdkService->expects($this->at(1))
            ->method('call')
            ->willReturn([
                [
                    'id' => 'some-command-id',
                    'name' => $commandName = ConfigController::SET_SANDBOX_HOST,
                    'value' => 'http://some.domain',
                ]
            ]);
        $this->sdkService->expects($this->at(2))
            ->method('call')
            ->willReturn([$commandName]);
        $this->payeverHelper->expects($this->once())
            ->method('setCustomSandboxUrl');
        $this->controller->executeCommand();
    }

    public function testExecuteCommandCaseLive()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->sdkService->expects($this->at(1))
            ->method('call')
            ->willReturn([
                [
                    'id' => 'some-command-id',
                    'name' => $commandName = ConfigController::SET_LIVE_HOST,
                    'value' => 'http://some.domain',
                ]
            ]);
        $this->sdkService->expects($this->at(2))
            ->method('call')
            ->willReturn([$commandName]);
        $this->payeverHelper->expects($this->once())
            ->method('setCustomLiveUrl');
        $this->controller->executeCommand();
    }

    public function testExecuteCommandCaseLiveUnsupportedCommand()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->sdkService->expects($this->at(1))
            ->method('call')
            ->willReturn([
                [
                    'id' => 'some-command-id',
                    'name' => $commandName = ConfigController::SET_LIVE_HOST,
                    'value' => 'http://some.domain',
                ]
            ]);
        $this->sdkService->expects($this->at(2))
            ->method('call')
            ->willReturn([]);
        $this->payeverHelper->expects($this->never())
            ->method('setCustomLiveUrl');
        $this->controller->executeCommand();
    }

    public function testExecuteCommandCaseLiveInvalidUrl()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->sdkService->expects($this->at(1))
            ->method('call')
            ->willReturn([
                [
                    'id' => 'some-command-id',
                    'name' => $commandName = ConfigController::SET_LIVE_HOST,
                    'value' => 'invalid-domain',
                ]
            ]);
        $this->sdkService->expects($this->at(2))
            ->method('call')
            ->willReturn([$commandName]);
        $this->payeverHelper->expects($this->never())
            ->method('setCustomLiveUrl');
        $this->controller->executeCommand();
    }

    public function testExecuteCommandCaseUnknownCommand()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->sdkService->expects($this->at(1))
            ->method('call')
            ->willReturn([
                [
                    'id' => 'some-command-id',
                    'name' => $commandName = 'unknown-command',
                    'value' => 'http://some.domain',
                ]
            ]);
        $this->sdkService->expects($this->at(2))
            ->method('call')
            ->willReturn([$commandName]);
        $this->controller->executeCommand();
    }

    public function testExecuteCommandCaseException()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getCommandTimestamp')
            ->willThrowException(new \Exception());
        $this->controller->executeCommand();
    }
}
