<?php

namespace Payever\tests\unit\Helper;

use Illuminate\Support\Collection;
use Payever\Helper\PayeverHelper;
use Payever\Repositories\PayeverConfigRepository;
use Payever\Services\Lock\StorageLock;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\System\Models\WebstoreConfiguration;
use Plenty\Plugin\Translation\Translator;

class PayeverHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var MockObject|Translator
     */
    private $translator;

    /**
     * @var MockObject|WebstoreHelper
     */
    private $webStoreHelper;

    /**
     * @var MockObject|PayeverConfigRepository
     */
    private $payeverConfigRepository;

    /**
     * @var MockObject|StorageLock
     */
    private $storageLock;

    /**
     * @var PayeverHelper
     */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->getMockBuilder(PaymentMethodRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->translator = $this->getMockBuilder(Translator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->webStoreHelper = $this->getMockBuilder(WebstoreHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverConfigRepository = $this->getMockBuilder(PayeverConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->storageLock = $this->getMockBuilder(StorageLock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new PayeverHelper(
            $this->paymentMethodRepository,
            $this->translator,
            $this->webStoreHelper,
            $this->payeverConfigRepository,
            $this->storageLock
        );
    }

    public function testGetMethodsMetaData()
    {
        $this->assertNotEmpty($this->helper->getMethodsMetaData());
    }

    public function testGetPaymentMopId()
    {
        $this->paymentMethodRepository->expects($this->once())
            ->method('allForPlugin')
            ->willReturn([
                (object) [
                    'paymentKey' => 1,
                    'id' => 1,
                ],
            ]);
        $this->assertNotEmpty($this->helper->getPaymentMopId(1));
    }

    public function testGetPaymentMopIdCaseMethodNotFound()
    {
        $this->paymentMethodRepository->expects($this->once())
            ->method('allForPlugin')
            ->willReturn([
                (object) [
                    'paymentKey' => 1,
                    'id' => 1,
                ],
            ]);
        $this->assertNotEmpty($this->helper->getPaymentMopId(2));
    }

    public function testGetMopKeyToIdMap()
    {
        $this->paymentMethodRepository->expects($this->once())
            ->method('allForPlugin')
            ->willReturn([
                (object) [
                    'paymentKey' => 1,
                    'id' => 1,
                ],
            ]);
        $this->assertNotEmpty($this->helper->getMopKeyToIdMap());
    }

    public function testGetBaseUrl()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getBaseUrl());
    }

    public function testGetCommandEndpoint()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getCommandEndpoint());
    }

    public function testGetSuccessURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getSuccessURL());
    }

    public function testBuildSuccessURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->buildSuccessURL('some-payment-uuid'));
    }

    public function testGetFinishURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getFinishURL());
    }

    public function testGetNoticeURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getNoticeURL());
    }

    public function testGetCancelURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getCancelURL());
    }

    public function testGetFailureURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getFailureURL());
    }

    public function testGetIframeURL()
    {
        $this->webStoreHelper->expects($this->once())
            ->method('getCurrentWebstoreConfiguration')
            ->willReturn(
                $config = $this->getMockBuilder(WebstoreConfiguration::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->domainSsl = 'https://some.domain';
        $this->assertNotEmpty($this->helper->getIframeURL('paypal'));
    }

    public function testIsPayeverPaymentMopId()
    {
        $this->paymentMethodRepository->expects($this->once())
            ->method('allForPlugin')
            ->willReturn([
                (object) [
                    'paymentKey' => 1,
                    'id' => 1,
                ],
            ]);
        $this->helper->isPayeverPaymentMopId(1);
    }

    public function testGetPaymentProperty()
    {
        $this->assertNotEmpty($this->helper->getPaymentProperty(1, 'some-value'));
    }

    public function testGetPaymentPropertyValue()
    {
        /** @var MockObject|Payment $payment */
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $payment->properties = [
            null,
            $property = $this->getMockBuilder(PaymentProperty::class)
                ->disableOriginalConstructor()
                ->getMock(),
        ];
        $property->typeId = 1;
        $property->value = 'some-value';
        $this->assertNotEmpty($this->helper->getPaymentPropertyValue($payment, 1));
    }

    public function testGetPaymentPropertyValueCaseEmpty()
    {
        /** @var MockObject|Payment $payment */
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $payment->properties = [
            $this->getMockBuilder(PaymentProperty::class)
                ->disableOriginalConstructor()
                ->getMock(),
        ];
        $this->assertEmpty($this->helper->getPaymentPropertyValue($payment, 1));
    }

    public function testMapStatusCasePaid()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_PAID));
    }

    public function testMapStatusCaseAccepted()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_ACCEPTED));
    }

    public function testMapStatusCaseInProgress()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_IN_PROCESS));
    }

    public function testMapStatusCaseCancelled()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_CANCELLED));
    }

    public function testMapStatusCaseRefunded()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_REFUNDED));
    }

    public function testMapStatusCaseDeclined()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_DECLINED));
    }

    public function testMapStatusCaseNew()
    {
        $this->assertNotEmpty($this->helper->mapStatus(PayeverHelper::STATUS_NEW));
    }

    public function testMapStatusCaseNone()
    {
        $this->assertEmpty($this->helper->mapStatus('unknown'));
    }

    public function testMapOrderStatusCasePaid()
    {
        $this->assertNotEmpty($this->helper->mapOrderStatus(PayeverHelper::STATUS_PAID));
    }

    public function testMapOrderStatusCaseInProgress()
    {
        $this->assertNotEmpty($this->helper->mapOrderStatus(PayeverHelper::STATUS_IN_PROCESS));
    }

    public function testMapOrderStatusCaseFailed()
    {
        $this->assertNotEmpty($this->helper->mapOrderStatus(PayeverHelper::STATUS_FAILED));
    }

    public function testMapOrderStatusCaseRefunded()
    {
        $this->assertNotEmpty($this->helper->mapOrderStatus(PayeverHelper::STATUS_REFUNDED));
    }

    public function testMapOrderStatusCaseNew()
    {
        $this->assertNotEmpty($this->helper->mapOrderStatus(PayeverHelper::STATUS_NEW));
    }

    public function testIsSuccessfulPaymentStatus()
    {
        $this->assertTrue($this->helper->isSuccessfulPaymentStatus(PayeverHelper::STATUS_PAID));
    }

    public function testLockAndBlock()
    {
        $this->storageLock->expects($this->once())
            ->method('getLockName')
            ->willReturn('some-name');
        $this->storageLock->expects($this->once())
            ->method('lock');
        $this->helper->lockAndBlock('some-uuid');
    }

    public function testIsLocked()
    {
        $this->storageLock->expects($this->once())
            ->method('getLockName')
            ->willReturn('some-name');
        $this->storageLock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $this->helper->isLocked('some-uuid');
    }

    public function testUnlock()
    {
        $this->storageLock->expects($this->once())
            ->method('getLockName')
            ->willReturn('some-name');
        $this->storageLock->expects($this->once())
            ->method('unlock');
        $this->helper->unlock('some-uuid');
    }

    public function testWaitForUnlock()
    {
        $this->storageLock->expects($this->once())
            ->method('getLockName')
            ->willReturn('some-name');
        $this->storageLock->expects($this->once())
            ->method('waitForUnlock');
        $this->helper->waitForUnlock('some-uuid');
    }

    public function testGetOrderIdByEventCaseSalesOrder()
    {
        /** @var MockObject|EventProceduresTriggered $eventTriggered */
        $eventTriggered = $this->getMockBuilder(EventProceduresTriggered::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventTriggered->expects($this->once())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(Order::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->typeId = OrderType::TYPE_SALES_ORDER;
        $order->id = 1;
        $this->assertNotEmpty($this->helper->getOrderIdByEvent($eventTriggered));
    }

    public function testGetOrderIdByEventCaseCreditNote()
    {
        /** @var MockObject|EventProceduresTriggered $eventTriggered */
        $eventTriggered = $this->getMockBuilder(EventProceduresTriggered::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventTriggered->expects($this->once())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(Order::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->typeId = OrderType::TYPE_CREDIT_NOTE;
        $order->originOrders = $originOrders = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $originOrders->expects($this->once())
            ->method('isEmpty')
            ->willReturn(false);
        $originOrders->expects($this->once())
            ->method('count')
            ->willReturn(1);
        $originOrders->expects($this->once())
            ->method('first')
            ->willReturn(
                $originOrder = $this->getMockBuilder(Order::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $originOrder->typeId = 1;
        $originOrder->id = 1;
        $this->assertNotEmpty($this->helper->getOrderIdByEvent($eventTriggered));
    }

    public function testIsAllowedTransaction()
    {
        $this->assertTrue(
            $this->helper->isAllowedTransaction([
                'result' => [
                    'actions' => [
                        [
                            'action' => 'cancel',
                            'enabled' => true,
                        ],
                    ],
                ],
            ])
        );
    }

    public function testGetCustomSandboxUrl()
    {
        $this->payeverConfigRepository->expects($this->once())
            ->method('get');
        $this->helper->getCustomSandboxUrl();
    }

    public function testGetCustomLiveUrl()
    {
        $this->payeverConfigRepository->expects($this->once())
            ->method('get');
        $this->helper->getCustomLiveUrl();
    }

    public function testGetCommandTimestamp()
    {
        $this->payeverConfigRepository->expects($this->once())
            ->method('get');
        $this->helper->getCommandTimestamp();
    }

    public function testSetCustomSandboxUrl()
    {
        $this->payeverConfigRepository->expects($this->once())
            ->method('set');
        $this->helper->setCustomSandboxUrl('http://some.domain');
    }

    public function testSetCustomLiveUrl()
    {
        $this->payeverConfigRepository->expects($this->once())
            ->method('set');
        $this->helper->setCustomLiveUrl('http://some.domain');
    }

    public function testSetCommandTimestamp()
    {
        $this->payeverConfigRepository->expects($this->once())
            ->method('set');
        $this->helper->setCommandTimestamp(1);
    }

    public function testRetrieveErrorMessageFromSdkResponse()
    {
        $this->assertNotEmpty($this->helper->retrieveErrorMessageFromSdkResponse([]));
    }

    public function testRetrieveErrorMessageFromSdkResponseCaseErrorDescription()
    {
        $errorDescription = 'error_description';
        $this->assertEquals(
            $errorDescription,
            $this->helper->retrieveErrorMessageFromSdkResponse(['error_description' => $errorDescription])
        );
    }
    public function testRetrieveErrorMessageFromSdkResponseCaseMsg()
    {
        $errorMsg = 'error_msg';
        $this->assertEquals(
            $errorMsg,
            $this->helper->retrieveErrorMessageFromSdkResponse(['error_msg' => $errorMsg])
        );
    }
}
