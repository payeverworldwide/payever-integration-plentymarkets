<?php

namespace Payever\tests\unit\Controllers;

use IO\Models\LocalizedOrder;
use IO\Services\NotificationService;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Controllers\PaymentController;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Payever\Services\PayeverService;
use Payever\Services\Payment\Notification\NotificationRequestProcessor;
use Payever\tests\unit\mock\Component\HttpFoundation\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Frontend\Session\Storage\Models\Plugin;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Templates\Twig;

class PaymentControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|AuthHelper
     */
    private $authHelper;

    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|Response
     */
    private $response;

    /**
     * @var MockObject|ConfigRepository
     */
    private $config;

    /**
     * @var MockObject|PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var MockObject|PayeverService
     */
    private $payeverService;

    /**
     * @var MockObject|BasketRepositoryContract
     */
    private $basketContract;

    /**
     * @var MockObject|FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * @var MockObject|SessionStorageRepositoryContract
     */
    private $sessionStorageRepository;

    /**
     * @var MockObject|PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var MockObject|PayeverSdkService
     */
    private $sdkService;

    /**
     * @var MockObject|OrderRepositoryContract
     */
    private $orderContract;

    /**
     * @var MockObject|NotificationService
     */
    private $notificationService;

    /**
     * @var MockObject|NotificationRequestProcessor
     */
    private $notificationRequestProcessor;

    /**
     * @var MockObject|PendingPaymentRepositoryContract
     */
    private $pendingPaymentRepository;

    /**
     * @var PaymentController
     */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->authHelper = $this->getMockBuilder(AuthHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder(ConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverHelper = $this->getMockBuilder(PayeverHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverService = $this->getMockBuilder(PayeverService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->basketContract = $this->getMockBuilder(BasketRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sessionStorage = $this->getMockBuilder(FrontendSessionStorageFactoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sessionStorageRepository = $this->getMockBuilder(SessionStorageRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethodRepository = $this->getMockBuilder(PaymentMethodRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sdkService = $this->getMockBuilder(PayeverSdkService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderContract = $this->getMockBuilder(OrderRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->notificationService = $this->getMockBuilder(NotificationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->notificationRequestProcessor = $this->getMockBuilder(NotificationRequestProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pendingPaymentRepository = $this->getMockBuilder(PendingPaymentRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new PaymentController(
            $this->authHelper,
            $this->request,
            $this->config,
            $this->payeverHelper,
            $this->payeverService,
            $this->basketContract,
            $this->orderContract,
            $this->sessionStorage,
            $this->sessionStorageRepository,
            $this->paymentMethodRepository,
            $this->sdkService,
            $this->notificationService,
            $this->notificationRequestProcessor,
            $this->pendingPaymentRepository
        );
    }

    public function testCheckoutCancel()
    {
        $this->notificationService->expects($this->once())
            ->method('warn');
        $this->controller->checkoutCancel();
    }

    public function testCheckoutFailure()
    {
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'reference' => 1,
                'status' => 'failure',
            ]);
        $this->payeverService->expects($this->once())
            ->method('updateOrderStatus');
        $this->controller->checkoutFailure();
    }

    public function testCheckoutSuccess()
    {
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'reference' => 1,
                'status' => 'in_progress',
            ]);
        $this->controller->checkoutSuccess();
    }

    public function testCheckoutSuccessCaseNoReference()
    {
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'status' => 'in_progress',
            ]);
        $this->payeverHelper->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(true);
        $this->payeverService->expects($this->once())
            ->method('updatePlentyPayment')
            ->willReturn(false);
        $this->payeverService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $localizedOrder = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $localizedOrder->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->id = 1;
        $this->controller->checkoutSuccess();
    }

    public function testCheckoutSuccessCaseNoReferenceException()
    {
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'status' => 'in_progress',
            ]);
        $this->payeverHelper->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(true);
        $this->payeverService->expects($this->once())
            ->method('updatePlentyPayment')
            ->willReturn(false);
        $this->payeverService->expects($this->once())
            ->method('placeOrder')
            ->willThrowException(new \Exception());
        $this->notificationService->expects($this->once())
            ->method('error');
        $this->controller->checkoutSuccess();
    }

    public function testCheckoutSuccessCaseNoReferenceUnsuccessfulStatus()
    {
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([
                'status' => 'in_progress',
            ]);
        $this->payeverHelper->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(false);
        $this->controller->checkoutSuccess();
    }

    public function testCheckoutSuccessCaseNoPayment()
    {
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverService->expects($this->once())
            ->method('handlePayeverPayment')
            ->willReturn([]);
        $this->notificationService->expects($this->once())
            ->method('error');
        $this->controller->checkoutSuccess();
    }

    public function testCheckoutFinish()
    {
        $this->payeverHelper->expects($this->once())
            ->method('buildSuccessURL')
            ->willReturn('http://some.domain/path');
        $this->controller->checkoutFinish();
    }

    public function testCheckoutNotice()
    {
        $this->notificationRequestProcessor->expects($this->once())
            ->method('processNotification');
        $this->controller->checkoutNotice();
    }

    public function testCheckoutIframe()
    {
        $twig = $this->getMockBuilder(Twig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $plugin = $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getValue')
            ->willReturn('http://some.domain/path');
        $this->controller->checkoutIframe($twig);
    }

    public function testCheckoutIframeCaseOrderBeforePayment()
    {
        $twig = $this->getMockBuilder(Twig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willReturn(
                $plugin = $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getValue')
            ->willReturn('1');
        $this->payeverService->expects($this->once())
            ->method('processOrderPayment')
            ->willReturn('http://some.domain/path');
        $this->controller->checkoutIframe($twig);
    }

    public function testCheckoutIframeCaseException()
    {
        $twig = $this->getMockBuilder(Twig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sessionStorage->expects($this->once())
            ->method('getPlugin')
            ->willThrowException(new \Exception());
        $this->notificationService->expects($this->once())
            ->method('warn');
        $this->controller->checkoutIframe($twig);
    }
}
