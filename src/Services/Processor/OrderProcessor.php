<?php

namespace Payever\Services\Processor;

use Payever\Helper\StatusHelper;
use Payever\Services\Generator\OrderGenerator;
use Payever\Traits\Logger;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;

/**
 * Class OrderProcessor
 */
class OrderProcessor
{
    use Logger;

    /**
     * @var AuthHelper
     */
    private AuthHelper $authHelper;

    /**
     * @var OrderGenerator
     */
    private OrderGenerator $orderGenerator;

    /**
     * @var OrderTotalProcessor
     */
    private OrderTotalProcessor $orderTotalProcessor;

    /**
     * @var OrderRepositoryContract
     */
    private OrderRepositoryContract $orderRepository;

    /**
     * @var PaymentRepositoryContract
     */
    private PaymentRepositoryContract $paymentRepository;

    /**
     * @param AuthHelper $authHelper
     * @param OrderGenerator $orderGenerator
     * @param OrderTotalProcessor $orderTotalProcessor
     * @param OrderRepositoryContract $orderRepository
     * @param PaymentRepositoryContract $paymentRepository
     */
    public function __construct(
        AuthHelper $authHelper,
        OrderGenerator $orderGenerator,
        OrderTotalProcessor $orderTotalProcessor,
        OrderRepositoryContract $orderRepository,
        PaymentRepositoryContract $paymentRepository
    ) {
        $this->authHelper = $authHelper;
        $this->orderGenerator = $orderGenerator;
        $this->orderTotalProcessor = $orderTotalProcessor;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * @param int $orderId
     *
     * @return Order
     */
    public function getPlentyOrderById(int $orderId): Order
    {
        return $this->orderRepository->findOrderById($orderId);
    }

    /**
     * @param int|string $orderId
     *
     * @return int|null
     */
    public function getPlentyOrderByIdOrNull($orderId): ?int
    {
        try {
            $orderId = $this->orderRepository->findOrderById($orderId)->id;
        } catch (\Exception $exception) {
            $orderId = null;
        }

        return $orderId;
    }

    /**
     * @param array $payeverPayment
     *
     * @return int|null
     */
    public function getPlentyOrderByPayeverPayment(array $payeverPayment): ?int
    {
        $reference = $payeverPayment['reference'];

        $orderId = $this->getPlentyOrderByIdOrNull($reference);
        if ($orderId) {
            return $orderId;
        }

        // Search order by transaction id (pending order / widget order)
        /** @var Payment[] $payments */
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment['id']
        );

        return count($payments) ? $payments[0]->order->orderId : null;
    }

    /**
     * @param array $payeverPayment
     *
     * @return int
     */
    public function createPlentyOrderByPayeverPayment(array $payeverPayment): int
    {
        // Generate new order (pending/widget)
        $orderId = $this->orderGenerator->generate($payeverPayment);

        // Assign order totals
        $order = $this->getPlentyOrderById($orderId);
        $this->orderTotalProcessor->assignOrderTotalsIfNotExists($order);

        return $orderId;
    }

    /**
     * @param int $orderId
     * @param string $paymentStatus
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function updatePlentyOrderStatus(int $orderId, string $paymentStatus)
    {
        $statusId = StatusHelper::mapOrderStatus($paymentStatus);
        $this->authHelper->processUnguarded(
            function () use ($orderId, $statusId, $paymentStatus) {
                //unguarded
                $this->orderRepository->updateOrder(['statusId' => (float)$statusId], $orderId);

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.updateOrderStatus',
                    'Status of order was changed',
                    ['statusId' => $statusId, 'orderId' => $orderId]
                );

                $this->orderTotalProcessor->updateOrderSuccessTotals($orderId, $paymentStatus);
            }
        );
    }
}
