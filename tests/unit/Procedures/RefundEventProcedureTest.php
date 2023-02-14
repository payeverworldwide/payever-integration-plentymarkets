<?php

namespace Payever\tests\unit\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Procedures\RefundEventProcedure;
use Payever\Services\PayeverService;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;

class RefundEventProcedureTest extends \PHPUnit\Framework\TestCase
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
     * @var RefundEventProcedure
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
        $this->procedure = new RefundEventProcedure();
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
        $payment->amount = 1.1;
        $this->paymentHelper->expects($this->once())
            ->method('isPayeverPaymentMopId')
            ->willReturn(true);
        $this->paymentHelper->expects($this->once())
            ->method('getPaymentPropertyValue')
            ->willReturn('1234-some-uuid');
        $this->paymentService->expects($this->once())
            ->method('refundPayment')
            ->willReturn([
                'result' => [
                    'status' => 'process',
                ],
            ]);
        $this->paymentContract->expects($this->once())
            ->method('updatePayment');
        $this->procedure->run(
            $this->eventTriggered,
            $this->paymentService,
            $this->paymentContract,
            $this->paymentHelper
        );
    }

    public function testRunCaseNoRefundResult()
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
        $payment->amount = 1.1;
        $this->paymentHelper->expects($this->once())
            ->method('isPayeverPaymentMopId')
            ->willReturn(true);
        $this->paymentHelper->expects($this->once())
            ->method('getPaymentPropertyValue')
            ->willReturn('1234-some-uuid');
        $this->paymentService->expects($this->once())
            ->method('refundPayment')
            ->willReturn(null);
        $this->expectException(\Exception::class);
        $this->procedure->run(
            $this->eventTriggered,
            $this->paymentService,
            $this->paymentContract,
            $this->paymentHelper
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
            $this->paymentHelper
        );
    }
}
