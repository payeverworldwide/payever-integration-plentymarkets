<?php

namespace Payever\tests\unit\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Procedures\CancelEventProcedure;
use Payever\Services\PayeverService;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;

class CancelEventProcedureTest extends \PHPUnit\Framework\TestCase
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
     * @var CancelEventProcedure
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
        $this->procedure = new CancelEventProcedure();
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
        $this->paymentService->expects($this->once())
            ->method('cancelPayment')
            ->willReturn([
                'call' => [
                    'status' => 'success',
                ],
                'result' => [
                    'status' => 'success',
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
