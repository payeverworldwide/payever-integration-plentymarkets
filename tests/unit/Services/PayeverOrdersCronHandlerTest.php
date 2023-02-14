<?php

namespace Payever\tests\unit\Services;

use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverOrdersCronHandler;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\mixed;
use Plenty\Repositories\Models\PaginatedResult;

class PayeverOrdersCronHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|AuthHelper
     */
    private $authHelper;

    /**
     * @var MockObject|OrderRepositoryContract
     */
    private $orderRepositoryContract;

    /**
     * @var MockObject|PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var MockObject|ConfigRepository
     */
    private $config;

    /**
     * @var PayeverOrdersCronHandler
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
        $this->orderRepositoryContract = $this->getMockBuilder(OrderRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverHelper = $this->getMockBuilder(PayeverHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->getMockBuilder(ConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new PayeverOrdersCronHandler(
            $this->authHelper,
            $this->orderRepositoryContract,
            $this->payeverHelper,
            $this->config
        );
    }

    public function testHandle()
    {
        $this->config->expects($this->at(0))
            ->method('get')
            ->willReturn(new mixed());
        $this->authHelper->expects($this->once())
            ->method('processUnguarded');
        $this->handler->handle();
    }

    public function testExecute()
    {
        $this->orderRepositoryContract->expects($this->once())
            ->method('searchOrders')
            ->willReturn(
                $result = $this->getMockBuilder(PaginatedResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $result->expects($this->once())
            ->method('getResult')
            ->willReturn([
                [
                    'id' => 1,
                ]
            ]);
        $this->orderRepositoryContract->expects($this->once())
            ->method('findOrderById')
            ->willReturn(
                $orderModel = $this->getMockBuilder(Order::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderModel->methodOfPaymentId = 1;
        $orderModel->id = 1;
        $this->payeverHelper->expects($this->once())
            ->method('isPayeverPaymentMopId')
            ->willReturn(true);
        $this->orderRepositoryContract->expects($this->once())
            ->method('updateOrder');
        $this->handler->execute();
    }
}
