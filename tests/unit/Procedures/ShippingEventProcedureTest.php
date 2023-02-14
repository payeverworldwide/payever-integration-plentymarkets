<?php

namespace Payever\tests\unit\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Procedures\ShippingEventProcedure;
use Payever\Services\PayeverService;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderAmount;
use Plenty\Modules\Order\Models\OrderItem;
use Plenty\Modules\Order\Models\OrderItemAmount;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\ParcelService\Models\ParcelServicePreset;

class ShippingEventProcedureTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EventProceduresTriggered
     */
    private $eventTriggered;

    /**
     * @var MockObject|PayeverService
     */
    private $paymentService;

    /**
     * @var MockObject|PaymentRepositoryContract
     */
    private $paymentContract;

    /**
     * @var MockObject|PayeverHelper
     */
    private $paymentHelper;

    /**
     * @var MockObject|OrderRepositoryContract
     */
    private $orderRepository;

    /**
     * @var MockObject|OrderShippingPackageRepositoryContract
     */
    private $orderShippingPackageRepository;

    /**
     * @var MockObject|ParcelServicePresetRepositoryContract
     */
    private $parcelServicePresetRepository;

    /**
     * @var ShippingEventProcedure
     */
    private $procedure;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->eventTriggered = $this->getMockBuilder(EventProceduresTriggered::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentService = $this->getMockBuilder(PayeverService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentContract = $this->getMockBuilder(PaymentRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHelper = $this->getMockBuilder(PayeverHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderRepository = $this->getMockBuilder(OrderRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderShippingPackageRepository = $this->getMockBuilder(
            OrderShippingPackageRepositoryContract::class
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->parcelServicePresetRepository = $this->getMockBuilder(
            ParcelServicePresetRepositoryContract::class
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->procedure = new ShippingEventProcedure();
    }

    public function testRun()
    {
        $this->paymentHelper->expects($this->once())
            ->method('getOrderIdByEvent')
            ->willReturn(1);
        $this->paymentContract->expects($this->once())
            ->method('getPaymentsByOrderId')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 1;
        $this->paymentHelper->expects($this->once())
            ->method('isPayeverPaymentMopId')
            ->willReturn(true);
        $this->paymentHelper->expects($this->once())
            ->method('getPaymentPropertyValue')
            ->willReturn('some-uuid');
        $this->paymentService->expects($this->once())
            ->method('getTransaction')
            ->willReturn([]);
        $this->paymentHelper->expects($this->once())
            ->method('isAllowedTransaction')
            ->willReturn(true);

        $this->orderRepository->expects($this->once())
            ->method('findOrderById')
            ->willReturn($this->getOrder());

        $this->orderShippingPackageRepository->expects($this->once())
            ->method('listOrderShippingPackages')
            ->willReturn([]);

        $this->parcelServicePresetRepository->expects($this->once())
            ->method('getPresetById')
            ->willReturn($this->getMockBuilder(ParcelServicePreset::class)
                ->disableOriginalConstructor()
                ->getMock());

        $this->paymentService->expects($this->once())
            ->method('shippingGoodsPayment')
            ->willReturn([
                'call' => [
                    'status' => 'success',
                ],
            ]);

        //setReferenceValue

        $this->procedure->run(
            $this->eventTriggered,
            $this->paymentService,
            $this->paymentContract,
            $this->paymentHelper,
            $this->orderRepository,
            $this->orderShippingPackageRepository,
            $this->parcelServicePresetRepository
        );
    }

    public function testRunCaseTransactionIsNotAllowed()
    {
        $this->paymentHelper->expects($this->once())
            ->method('getOrderIdByEvent')
            ->willReturn(1);
        $this->paymentContract->expects($this->once())
            ->method('getPaymentsByOrderId')
            ->willReturn([
                $payment = $this->getMockBuilder(Payment::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payment->mopId = 1;
        $this->paymentHelper->expects($this->once())
            ->method('isPayeverPaymentMopId')
            ->willReturn(true);
        $this->paymentHelper->expects($this->once())
            ->method('getPaymentPropertyValue')
            ->willReturn('some-uuid');
        $this->paymentService->expects($this->once())
            ->method('getTransaction')
            ->willReturn([]);
        $this->paymentHelper->expects($this->once())
            ->method('isAllowedTransaction')
            ->willReturn(false);
        $this->expectException(\Exception::class);
        $this->procedure->run(
            $this->eventTriggered,
            $this->paymentService,
            $this->paymentContract,
            $this->paymentHelper,
            $this->orderRepository,
            $this->orderShippingPackageRepository,
            $this->parcelServicePresetRepository
        );
    }

    public function testRunCaseNoOrderId()
    {
        $this->paymentHelper->expects($this->once())
            ->method('getOrderIdByEvent')
            ->willReturn(false);
        $this->expectException(\Exception::class);
        $this->procedure->run(
            $this->eventTriggered,
            $this->paymentService,
            $this->paymentContract,
            $this->paymentHelper,
            $this->orderRepository,
            $this->orderShippingPackageRepository,
            $this->parcelServicePresetRepository
        );
    }

    /**
     * Get Order.
     *
     * @return Order
     */
    private function getOrder()
    {
        $order = $this->getMockBuilder(\Payever\tests\unit\mock\Models\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $order->id = 1;
        $order->shippingProfileId = 1;
        $order->amount = $this->getMockBuilder(\Payever\tests\unit\mock\Models\OrderAmount::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->amount->grossTotal = 125;

        /** @var MockObject|OrderItem $orderItem */
        $orderItem = $this->getMockBuilder(\Payever\tests\unit\mock\Models\OrderItem::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderItem->orderItemName = 'Test';
        $orderItem->typeId = 1;
        $orderItem->itemVariationId = 11;
        $orderItem->amount = $this->getMockBuilder(\Payever\tests\unit\mock\Models\OrderItemAmount::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderItem->amount->priceGross = 125;
        $orderItem->quantity = 1;

        $order->orderItems = [
            $orderItem
        ];

        return $order;
    }
}
