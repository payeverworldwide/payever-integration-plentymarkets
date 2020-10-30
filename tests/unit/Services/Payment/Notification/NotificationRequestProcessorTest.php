<?php

namespace Payever\tests\unit\Services\Payment\Notification;

use Payever\Services\Lock\StorageLock;
use Payever\Services\PayeverService;
use Payever\Services\Payment\Notification\NotificationRequestProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;

class NotificationRequestProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|ConfigRepository
     */
    private $config;

    /**
     * @var MockObject|StorageLock
     */
    private $lock;

    /**
     * @var MockObject|PayeverService
     */
    private $payeverService;

    /**
     * @var NotificationRequestProcessor
     */
    private $processor;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder(ConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->lock = $this->getMockBuilder(StorageLock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverService = $this->getMockBuilder(PayeverService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->processor = new NotificationRequestProcessor(
            $this->request,
            $this->config,
            $this->lock,
            $this->payeverService
        );
    }

    public function testProcessNotification()
    {
        $this->request->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn(\json_encode([
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]));
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'id' => $paymentId,
                'reference' => 1,
            ]);
        $this->payeverService->expects($this->once())
            ->method('createAndUpdatePlentyPayment')
            ->willReturn(['some' => 'data']);
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseUpdate()
    {
        $this->request->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn(\json_encode([
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]));
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'id' => $paymentId,
                'status' => 'some-status',
            ]);
        $this->payeverService->expects($this->once())
            ->method('updatePlentyPayment')
            ->willReturn(['some' => 'data']);
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseSignature()
    {
        $clientId = 'some-client-id';
        $clientSecret = 'some-client-secret';
        $this->request->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $this->request->expects($this->once())
            ->method('header')
            ->willReturn($signature = hash_hmac('sha256', $clientId . $paymentId, $clientSecret));
        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn(\json_encode([
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
                'data' => [
                    'payment' => [
                        'id' => $paymentId,
                        'reference' => 2,
                    ],
                ]
            ]));
        $this->config->expects($this->at(0))
            ->method('get')
            ->willReturn(new \Plenty\Plugin\mixed($clientId));
        $this->config->expects($this->at(1))
            ->method('get')
            ->willReturn(new \Plenty\Plugin\mixed($clientSecret));
        $this->payeverService->expects($this->once())
            ->method('createAndUpdatePlentyPayment')
            ->willReturn(['some' => 'data']);
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseInvalidSignature()
    {
        $this->request->expects($this->once())
            ->method('get')
            ->willReturn('some-payment-id');
        $this->request->expects($this->once())
            ->method('header')
            ->willReturn('some-invalid-signature');
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseEmptyPayload()
    {
        $clientId = 'some-client-id';
        $clientSecret = 'some-client-secret';
        $this->request->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $this->request->expects($this->once())
            ->method('header')
            ->willReturn($signature = hash_hmac('sha256', $clientId . $paymentId, $clientSecret));
        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn('');
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseInvalidData()
    {
        $clientId = 'some-client-id';
        $clientSecret = 'some-client-secret';
        $this->request->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $this->request->expects($this->once())
            ->method('header')
            ->willReturn($signature = hash_hmac('sha256', $clientId . $paymentId, $clientSecret));
        $this->request->expects($this->once())
            ->method('getContent')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->assertNotEmpty($this->processor->processNotification());
    }
}
