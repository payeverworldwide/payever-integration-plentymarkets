<?php

namespace Payever\Services;

use Payever\Helper\PayeverHelper;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class PayeverOrdersCronHandler extends CronHandler
{
    use Loggable;

    /**
     * @var AuthHelper
     */
    private $authHelper;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepositoryContract;

    /**
     * @var payeverHelper
     */
    private $payeverHelper;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * PayeverOrdersCronHandler constructor.
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

    public function handle()
    {
        if (!$this->config->get('Payever.order_before_payment')) {
            return;
        }

        $this->authHelper->processUnguarded(
            function () {
                $now = date(\DateTime::W3C);
                $dateTo = date(\DateTime::W3C, strtotime('-8 hour', strtotime($now)));
                $this->getLogger(__METHOD__)->debug('Payever::debug.deletingOrdersDateTo', $dateTo);
                $this->orderRepositoryContract->setFilters([
                    'statusFrom' => PayeverHelper::PLENTY_ORDER_PROCESSING,
                    'statusTo' => PayeverHelper::PLENTY_ORDER_PROCESSING,
                    'paymentStatus' => 'unpaid',
                    'createdAtTo' => $dateTo
                ]);

                $orders = $this->orderRepositoryContract->searchOrders();
                foreach ($orders->getResult() as $order) {
                    $orderModel = $this->orderRepositoryContract->findOrderById($order['id']);
                    if ($this->payeverHelper->isPayeverPaymentMopId($orderModel->methodOfPaymentId)) {
                        $this->orderRepositoryContract->deleteOrder($orderModel->id);
                        $this->getLogger(__METHOD__)->debug('Payever::debug.orderDeleting',
                            "Order #" . $orderModel->id . " has been deleted");
                    }
                }
            }
        );
    }
}
