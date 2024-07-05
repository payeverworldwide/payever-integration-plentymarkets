<?php

namespace Payever\Services\Processor;

use Payever\Helper\PayeverHelper;
use Payever\Helper\StatusHelper;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;

/**
 * Class CheckoutProcessor
 */
class CheckoutProcessor
{
    use Logger;

    /**
     * @var PayeverHelper
     */
    private PayeverHelper $payeverHelper;

    /**
     * @var PayeverService
     */
    private PayeverService $payeverService;

    /**
     * @var OrderProcessor
     */
    private OrderProcessor $orderProcessor;

    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @param PayeverHelper $payeverHelper
     * @param PayeverService $payeverService
     * @param OrderProcessor $orderProcessor
     * @param PaymentProcessor $paymentProcessor
     */
    public function __construct(
        PayeverHelper $payeverHelper,
        PayeverService $payeverService,
        OrderProcessor $orderProcessor,
        PaymentProcessor $paymentProcessor
    ) {
        $this->payeverHelper = $payeverHelper;
        $this->payeverService = $payeverService;
        $this->orderProcessor = $orderProcessor;
        $this->paymentProcessor = $paymentProcessor;
    }

    /**
     * @param string $paymentId
     * @param string|null $fetchDest
     * @param string|null $notificationTime
     *
     * @return int
     * @throws \Exception
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function processCheckout(string $paymentId, string $fetchDest = null, string $notificationTime = null): int
    {
        $this->payeverHelper->acquireLock($paymentId, $fetchDest);

        try {
            $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);
            if (!$payeverPayment) {
                throw new \Exception('Unable to retrieve payever payment');
            }

            // Get order-id or create plenty order by the payever payment
            $orderId = $this->orderProcessor->getPlentyOrderByPayeverPayment($payeverPayment);
            if (!$orderId) {
                // Check if the order status is successful
                if (!StatusHelper::isSuccessfulPaymentStatus($payeverPayment['status'])) {
                    throw new \Exception('The order status is not successful.');
                }

                // Generate new order (pending order/widget order)
                $orderId = $this->orderProcessor->createPlentyOrderByPayeverPayment($payeverPayment);
            }

            // Get plenty payment record or create plenty payment by the payever payment
            $payment = $this->paymentProcessor->getPlentyPaymentByPayeverPayment($payeverPayment);
            if (!$payment) {
                $payment = $this->paymentProcessor->createPlentyPaymentByPayeverPayment($payeverPayment, $orderId);
            }

            // Assign the plenty payment to the plenty order
            if (!$this->paymentProcessor->isAssignedPlentyPaymentToOrder($payment, $orderId)) {
                $order = $this->orderProcessor->getPlentyOrderById($orderId);
                $this->paymentProcessor->assignPlentyPaymentToPlentyOrder($payment, $order);
            }

            // Updated the plenty order status by payever payment
            $status = $payeverPayment['status'];
            $this->orderProcessor->updatePlentyOrderStatus($orderId, $status);
            $this->paymentProcessor->updatePlentyPaymentStatus($paymentId, $status, $notificationTime);

            $this->payeverHelper->unlock($paymentId);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.successfulUpdatingPlentyPayment',
                'Successful Updating Plenty Payment',
            )->setReferenceValue($orderId);

            return $orderId;
        } catch (\Exception $e) {
            $this->payeverHelper->unlock($paymentId);

            $this->log(
                'critical',
                __METHOD__,
                'Payever::debug::placingOrderError',
                'Exception: ' . $e->getMessage(),
                [$e]
            )->setReferenceValue($paymentId);

            throw $e;
        }
    }
}
