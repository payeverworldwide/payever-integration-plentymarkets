<?php

namespace Payever\tests\unit\Services;

use IO\Models\LocalizedOrder;
use IO\Services\OrderService;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\ShippingGoodsPaymentRequest;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Payever\Services\PayeverService;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Frontend\Session\Storage\Models\Plugin;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\mixed;

class PayeverServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|AuthHelper
     */
    private $authHelper;

    /**
     * @var MockObject|AccountService
     */
    private $accountService;

    /**
     * @var MockObject|OrderService
     */
    private $orderService;

    /**
     * @var MockObject|CountryRepositoryContract
     */
    private $countryRepository;

    /**
     * @var MockObject|ItemRepositoryContract
     */
    private $itemRepository;

    /**
     * @var MockObject|PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var MockObject|PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var MockObject|PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepo;

    /**
     * @var MockObject|OrderRepositoryContract
     */
    private $orderRepository;

    /**
     * @var MockObject|ConfigRepository
     */
    private $config;

    /**
     * @var MockObject|PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var MockObject|AddressRepositoryContract
     */
    private $addressRepo;

    /**
     * @var MockObject|ContactRepositoryContract
     */
    private $contactRepository;

    /**
     * @var MockObject|FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * @var MockObject|PayeverSdkService
     */
    private $sdkService;

    /**
     * @var MockObject|BasketRepositoryContract
     */
    private $basketRepository;

    /**
     * @var MockObject|BasketItemRepositoryContract
     */
    private $basketItemRepository;

    /**
     * @var MockObject|PendingPaymentRepositoryContract
     */
    private $pendingPaymentRepository;

    /**
     * @var MockObject|Checkout Checkout
     */
    private $checkout;

    /**
     * @var PayeverService
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->authHelper = $this->getMockBuilder(AuthHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderService = $this->getMockBuilder(OrderService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->countryRepository = $this->getMockBuilder(CountryRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->itemRepository = $this->getMockBuilder(ItemRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethodRepository = $this->getMockBuilder(PaymentMethodRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentRepository = $this->getMockBuilder(PaymentRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentOrderRelationRepo = $this->getMockBuilder(PaymentOrderRelationRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderRepository = $this->getMockBuilder(OrderRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder(ConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverHelper = $this->getMockBuilder(PayeverHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->addressRepo = $this->getMockBuilder(AddressRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->contactRepository = $this->getMockBuilder(ContactRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sessionStorage = $this->getMockBuilder(FrontendSessionStorageFactoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sdkService = $this->getMockBuilder(PayeverSdkService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->basketRepository = $this->getMockBuilder(BasketRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->basketItemRepository = $this->getMockBuilder(BasketItemRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pendingPaymentRepository = $this->getMockBuilder(PendingPaymentRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkout = $this->getMockBuilder(Checkout::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new PayeverService(
            $this->authHelper,
            $this->accountService,
            $this->countryRepository,
            $this->itemRepository,
            $this->paymentMethodRepository,
            $this->paymentRepository,
            $this->paymentOrderRelationRepo,
            $this->orderRepository,
            $this->config,
            $this->payeverHelper,
            $this->addressRepo,
            $this->contactRepository,
            $this->sessionStorage,
            $this->sdkService,
            $this->basketRepository,
            $this->basketItemRepository,
            $this->pendingPaymentRepository,
            $this->checkout
        );
        $this->handler->setOrderService($this->orderService);
    }

    public function testIsSubmitMethod()
    {
        $this->config->expects($this->once())
            ->method('has')
            ->willReturn(true);
        $this->config->expects($this->once())
            ->method('get')
            ->willReturn(new mixed());
        $this->assertTrue($this->handler->isSubmitMethod('paypal'));
    }

    public function testPreparePayeverPayment()
    {
        $this->config->expects($this->any())
            ->method('get')
            ->willReturn(new mixed());
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->basketRepository->expects($this->once())
            ->method('load')
            ->willReturn(
                $basket = $this->getMockBuilder(Basket::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $basket->id = 1;
        $basket->shippingCountryId = 1;
        $basket->customerInvoiceAddressId = 1;
        $basket->basketAmount = 1.1;
        $basket->basketItems = [];
        $this->orderService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $orderData = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderData->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->id = 1;
        $this->accountService->expects($this->once())
            ->method('getAccountContactId')
            ->willReturn(1);
        $this->addressRepo->expects($this->any())
            ->method('findAddressById')
            ->willReturn(
                $address = $this->getMockBuilder(Address::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $address->countryId = 1;
        $this->countryRepository->expects($this->any())
            ->method('findIsoCode')
            ->willReturn($country = 'DE');
        $this->contactRepository->expects($this->once())
            ->method('findContactById')
            ->willReturn(
                $this->getMockBuilder(Contact::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->pendingPaymentRepository->expects($this->once())
            ->method('getByOrderId')
            ->willReturn(
                $this->getMockBuilder(PendingPaymentRepositoryContract::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->handler->preparePayeverPayment($basket, 'paypal');
    }

    public function testPreparePayeverPaymentCaseRedirectMethod()
    {
        $this->config->expects($this->any())
            ->method('get')
            ->willReturn(new mixed());
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->basketRepository->expects($this->once())
            ->method('load')
            ->willReturn(
                $basket = $this->getMockBuilder(Basket::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $basket->id = 1;
        $basket->shippingCountryId = 1;
        $basket->customerInvoiceAddressId = 1;
        $basket->basketAmount = 1.1;
        $basket->basketItems = [];
        $this->orderService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $orderData = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderData->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->id = 1;
        $this->accountService->expects($this->once())
            ->method('getAccountContactId')
            ->willReturn(1);
        $this->addressRepo->expects($this->any())
            ->method('findAddressById')
            ->willReturn(
                $address = $this->getMockBuilder(Address::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $address->countryId = 1;
        $this->countryRepository->expects($this->any())
            ->method('findIsoCode')
            ->willReturn($country = 'DE');
        $this->contactRepository->expects($this->once())
            ->method('findContactById')
            ->willReturn(
                $this->getMockBuilder(Contact::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->config->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $this->sdkService->expects($this->at(0))
            ->method('call')
            ->willReturn([
                'error' => '',
                'result' => [
                    'id' => 'some-uuid',
                    'payment_details' => [
                        'redirect_url' => 'http://some.domain/path',
                    ],
                ],
            ]);
        $this->pendingPaymentRepository->expects($this->once())
            ->method('getByOrderId')
            ->willReturn(
                $this->getMockBuilder(PendingPaymentRepositoryContract::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->handler->preparePayeverPayment($basket, 'paypal');
    }

    public function testPlaceOrder()
    {
        $this->orderService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $orderData = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderData->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->id = 1;
        $order->methodOfPaymentId = 1;
        $this->orderService->expects($this->once())
            ->method('executePayment')
            ->willReturn(['type' => 'success']);
        $this->assertNotEmpty($this->handler->placeOrder(true));
    }

    public function testPlaceOrderCaseError()
    {
        $this->orderService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $orderData = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderData->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->id = 1;
        $order->methodOfPaymentId = 1;
        $this->orderService->expects($this->once())
            ->method('executePayment')
            ->willReturn([
                'type' => 'error',
                'value' => 'some-value',
            ]);
        $this->expectException(\Exception::class);
        $this->handler->placeOrder(true);
    }

    public function testProcessOrderPayment()
    {
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->basketRepository->expects($this->once())
            ->method('load')
            ->willReturn(
                $basket = $this->getMockBuilder(Basket::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $basket->id = 1;
        $basket->shippingCountryId = 1;
        $basket->customerInvoiceAddressId = 1;
        $basket->basketAmount = 1.1;
        $basket->basketItems = [];
        $this->orderService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $orderData = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderData->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->accountService->expects($this->once())
            ->method('getAccountContactId')
            ->willReturn(1);
        $this->addressRepo->expects($this->any())
            ->method('findAddressById')
            ->willReturn(
                $address = $this->getMockBuilder(Address::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $address->countryId = 1;
        $this->countryRepository->expects($this->any())
            ->method('findIsoCode')
            ->willReturn($country = 'DE');
        $this->contactRepository->expects($this->once())
            ->method('findContactById')
            ->willReturn(
                $this->getMockBuilder(Contact::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn(['error' => '', 'redirect_url' => 'http://some.domain/path']);
        $this->pendingPaymentRepository->expects($this->once())
            ->method('getByOrderId')
            ->willReturn(
                $this->getMockBuilder(PendingPaymentRepositoryContract::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->handler->processOrderPayment('paypal');
    }

    public function testProcessOrderPaymentCaseError()
    {
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->basketRepository->expects($this->once())
            ->method('load')
            ->willReturn(
                $basket = $this->getMockBuilder(Basket::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $basket->id = 1;
        $basket->shippingCountryId = 1;
        $basket->customerInvoiceAddressId = 1;
        $basket->basketAmount = 1.1;
        $basket->basketItems = [];
        $this->orderService->expects($this->once())
            ->method('placeOrder')
            ->willReturn(
                $orderData = $this->getMockBuilder(LocalizedOrder::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderData->order = $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->accountService->expects($this->once())
            ->method('getAccountContactId')
            ->willReturn(1);
        $this->addressRepo->expects($this->any())
            ->method('findAddressById')
            ->willReturn(
                $address = $this->getMockBuilder(Address::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $address->countryId = 1;
        $this->countryRepository->expects($this->any())
            ->method('findIsoCode')
            ->willReturn($country = 'DE');
        $this->contactRepository->expects($this->once())
            ->method('findContactById')
            ->willReturn(
                $this->getMockBuilder(Contact::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn(['error' => 'some-error']);
        $this->expectException(\Exception::class);
        $this->pendingPaymentRepository->expects($this->once())
            ->method('getByOrderId')
            ->willReturn(
                $this->getMockBuilder(PendingPaymentRepositoryContract::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->handler->processOrderPayment('paypal');
    }

    public function testGetReturnType()
    {
        $this->assertEmpty($this->handler->getReturnType());
    }

    public function testGetPayeverPaymentId()
    {
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $plugin = $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getValue')
            ->willReturn('some-value');
        $this->assertNotEmpty($this->handler->getPayeverPaymentId());
    }

    public function testPluginExecutePayment()
    {
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn([
                'error' => '',
                'result' => [
                    'total' => 1.1,
                    'payment_type' => 'paypal',
                    'status' => 'in_progress',
                    'currency' => 'EUR',
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'id' => 'some-uuid',
                    'customer_email' => 'example@com',
                    'customer_name' => 'Jhon',
                    'reference' => 1,
                    'payment_details' => [
                        'usage_text' => '',
                    ],
                ],
            ]);
        $this->config->expects($this->any())
            ->method('get')
            ->willReturn(new mixed());
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->handler->pluginExecutePayment('1'));
    }

    public function testPluginExecutePaymentCaseError()
    {
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn([
                'error' => true,
                'error_msg' => $message = 'some error description',
            ]);
        $this->payeverHelper->expects($this->once())
            ->method('retrieveErrorMessageFromSdkResponse')
            ->willReturn($message);
        $this->assertNotEmpty($this->handler->pluginExecutePayment('1'));
    }

    public function testPluginExecutePaymentCaseNoPaymentId()
    {
        $this->assertNotEmpty($this->handler->pluginExecutePayment(''));
    }

    public function testOriginExecutePayment()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getPaymentMopId')
            ->willReturn('1');
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByPropertyTypeAndValue')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 2;
        $this->paymentMethodRepository->expects($this->once())
            ->method('executePayment');
        $this->handler->originExecutePayment([
            'id' => '1',
            'payment_type' => '1',
            'reference' => '1',
        ]);
    }

    public function testGetCreatedPlentyPayment()
    {
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByPropertyTypeAndValue')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 1;
        $this->assertNotEmpty($this->handler->getCreatedPlentyPayment(['id' => '1'], 1));
    }

    public function testIsAssignedPlentyPayment()
    {
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByOrderId')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 1;
        $this->assertTrue($this->handler->isAssignedPlentyPayment(1, 1));
    }

    public function testIsAssignedPlentyPaymentCaseNoMatch()
    {
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByOrderId')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 2;
        $this->assertFalse($this->handler->isAssignedPlentyPayment(1, 1));
    }

    public function testHandlePayeverPayment()
    {
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn(['result' => ['some' => 'data']]);
        $this->assertNotEmpty($this->handler->handlePayeverPayment('some-uuid'));
    }

    public function testCreateAndUpdatePlentyPayment()
    {
        $this->payeverHelper->expects($this->once())
            ->method('getPaymentMopId')
            ->willReturn('1');
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByPropertyTypeAndValue')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 2;
        $this->sdkService->expects($this->once())
            ->method('call')
            ->willReturn([
                'error' => '',
                'result' => [
                    'total' => 1.1,
                    'payment_type' => 'paypal',
                    'status' => 'in_progress',
                    'currency' => 'EUR',
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'id' => 'some-uuid',
                    'customer_email' => 'example@com',
                    'customer_name' => 'Jhon',
                    'reference' => 1,
                    'payment_details' => [
                        'usage_text' => '',
                    ],
                ],
            ]);
        $this->config->expects($this->any())
            ->method('get')
            ->willReturn(new mixed());
        $this->sessionStorage->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $this->getMockBuilder(Plugin::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->paymentRepository->expects($this->once())
            ->method('createPayment')
            ->willReturn(
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByOrderId')
            ->willReturn([
                $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);

        $this->assertNotEmpty($this->handler->createAndUpdatePlentyPayment([
            'reference' => '1',
            'payment_type' => 'paypal',
            'id' => '1',
            'status' => 'in_progress',
        ]));
    }

    public function testUpdatePlentyPayment()
    {
        $datetime = new \DateTime();
        $this->paymentRepository->expects($this->once())
            ->method('getPaymentsByPropertyTypeAndValue')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->receivedAt = $datetime->format('Y-m-d H:i:s');
        $payment->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $payment->order->orderId = 1;
        $payment->status = 'new';
        $this->payeverHelper->expects($this->once())
            ->method('mapStatus')
            ->willReturn('in_progress');
        $this->handler->updatePlentyPayment(
            'some-uuid',
            'in_progress',
            $datetime->add(new \DateInterval('PT1H'))->format('Y-m-d H:i:s')
        );
    }

    public function testRefundPayment()
    {
        $this->sdkService->expects($this->once())
            ->method('call');
        $this->handler->refundPayment('some-uuid', 1.0);
    }

    public function testGetTransaction()
    {
        $this->sdkService->expects($this->once())
            ->method('call');
        $this->handler->getTransaction('some-uuid');
    }

    public function testCancelPayment()
    {
        $this->sdkService->expects($this->once())
            ->method('call');
        $this->handler->cancelPayment('some-uuid');
    }

    public function testShippingGoodsPayment()
    {
        $this->sdkService->expects($this->once())
            ->method('call');

        $this->handler->shippingGoodsPayment(
            'some-uuid',
            125,
            [],
            0,
            'Some reason',
            'Free Delivery',
            null,
            null
        );
    }
}
