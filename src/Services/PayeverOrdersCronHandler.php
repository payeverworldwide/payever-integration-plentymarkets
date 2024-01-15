<?php

namespace Payever\Services;

use Payever\Helper\PayeverHelper;
use Payever\Traits\Logger;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class PayeverOrdersCronHandler extends CronHandler
{
    use Logger;

    /**
     * @var AuthHelper
     */
    private $authHelper;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepositoryContract;

    /**
     * @var PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @param AuthHelper $authHelper
     * @param OrderRepositoryContract $orderRepositoryContract
     * @param PayeverHelper $payeverHelper
     * @param ConfigRepository $config
     */
    public function __construct(
        AuthHelper $authHelper,
        OrderRepositoryContract $orderRepositoryContract,
        PayeverHelper $payeverHelper,
        ConfigRepository $config
    ) {
        $this->authHelper = $authHelper;
        $this->orderRepositoryContract = $orderRepositoryContract;
        $this->payeverHelper = $payeverHelper;
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function handle()
    {
        if (!$this->config->get('Payever.order_before_payment')) {
            return;
        }

        $this->authHelper->processUnguarded([$this, 'execute']);
    }

    /**
     * @return void
     */
    public function execute()
    {
        $now = date(\DateTime::W3C);
        $dateTo = date(\DateTime::W3C, strtotime('-8 hour', strtotime($now)));

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.cancelingOrdersDateTo',
            'Canceling Orders Date To',
            [
                'dateTo' => $dateTo,
            ]
        );

        $this->orderRepositoryContract->setFilters([
            'statusFrom' => PayeverHelper::PLENTY_ORDER_PROCESSING,
            'statusTo' => PayeverHelper::PLENTY_ORDER_PROCESSING,
            'paymentStatus' => 'unpaid',
            'createdAtTo' => $dateTo,
        ]);

        $orders = $this->orderRepositoryContract->searchOrders();
        foreach ($orders->getResult() as $order) {
            $orderModel = $this->orderRepositoryContract->findOrderById($order['id']);
            if ($this->payeverHelper->isPayeverPaymentMopId($orderModel->methodOfPaymentId)) {
                $this->orderRepositoryContract->updateOrder(
                    ['statusId' => (float) PayeverHelper::PLENTY_ORDER_CANCELLED],
                    $orderModel->id
                );

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.autoOrderCanceling',
                    sprintf('Order #%s has been cancelled', $orderModel->id),
                    []
                );
            }
        }
    }
}
