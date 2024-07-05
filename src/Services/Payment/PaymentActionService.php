<?php

/**
 * payever GmbH
 *
 * NOTICE OF LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade payever Shopware package
 * to newer versions in the future.
 *
 * @category    Payever
 * @author      payever GmbH <service@payever.de>
 * @copyright   Copyright (c) 2021 payever GmbH (http://www.payever.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Payever\Services\Payment;

use Payever\Contracts\ActionHistoryRepositoryContract;
use Payever\Helper\OrderItemsManager;
use Payever\Helper\PayeverHelper;
use Payever\Models\ActionHistory;
use Payever\Services\PayeverService;
use Payever\Services\Processor\OrderProcessor;
use Payever\Services\Processor\PaymentProcessor;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentActionService
 */
class PaymentActionService
{
    use Loggable;

    const ACTION_CANCEL = 'cancel';
    const ACTION_REFUND = 'refund';
    const ACTION_SHIPPING_GOODS = 'shipping_goods';

    const LOGGER_CANCEL_CODE = 'Payever::debug.paymentActionCancel';
    const LOGGER_REFUND_CODE = 'Payever::debug.paymentActionRefund';
    const LOGGER_SHIPPING_CODE = 'Payever::debug.paymentActionShipping';

    /**
     * @var PayeverService
     */
    private PayeverService $paymentService;

    /**
     * @var PayeverHelper
     */
    private PayeverHelper $paymentHelper;

    /**
     * @var OrderProcessor
     */
    private OrderProcessor $orderProcessor;

    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var OrderItemsManager
     */
    private OrderItemsManager $orderItemsManager;

    /**
     * @var ActionHistoryRepositoryContract
     */
    private ActionHistoryRepositoryContract $actionHistoryRepository;

    public function __construct(
        PayeverService $paymentService,
        PayeverHelper $paymentHelper,
        OrderProcessor $orderProcessor,
        PaymentProcessor $paymentProcessor,
        OrderItemsManager $orderItemsManager,
        ActionHistoryRepositoryContract $actionHistoryRepository
    ) {
        $this->paymentService = $paymentService;
        $this->paymentHelper = $paymentHelper;
        $this->orderProcessor = $orderProcessor;
        $this->paymentProcessor = $paymentProcessor;
        $this->orderItemsManager = $orderItemsManager;
        $this->actionHistoryRepository = $actionHistoryRepository;
    }

    public function shipGoodsTransaction(Order $order, $transactionId, array $items, string $identifier = null): void
    {
        try {
            if (empty($items)) {
                throw new \InvalidArgumentException('Product items for shipping are missing.');
            }

            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_SHIPPING_GOODS);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $isPartialAllowActionResponse = $this->paymentService
                ->isPartialActionAllowed($transactionId, self::ACTION_SHIPPING_GOODS);
            if (!empty($isPartialAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isPartialAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $amount = $this->orderItemsManager->getPaymentAmountByItems($items, true);
            $paymentItems = $this->orderItemsManager->getPaymentItemsForAction($items);
            $deliveryFee = $this->orderItemsManager->getDeliveryFee($items);

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_SHIPPING_CODE, [
                    'amount' => $amount,
                    'paymentItems' => $paymentItems,
                    'deliveryFee' => $deliveryFee,
                ]);

            $shippingResult = $this->paymentService->shippingGoodsPayment(
                $transactionId,
                $amount,
                $paymentItems,
                $deliveryFee,
                'Shipping goods',
                null,
                null,
                null,
                $identifier
            );

            if (!empty($shippingResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($shippingResult);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_SHIPPING_CODE, [
                    'shippingResult' => $shippingResult,
                ]);

            $status = $shippingResult['call']['status'];

            if ($status !== 'success') {
                return;
            }

            $orderTotal = $this->orderItemsManager->shipOrderItems($order->id, $items);

            if (isset($shippingResult['result']['status'])) {
                // Updated the plenty order status by payever payment
                $this->orderProcessor->updatePlentyOrderStatus($order->id, $shippingResult['result']['status']);
                $this->paymentProcessor->updatePlentyPaymentStatus($transactionId, $shippingResult['result']['status']);
            }

            // Save in history
            $this->addActionHistory($order, $amount, self::ACTION_SHIPPING_GOODS);

            $this->getLogger(__METHOD__ . ' [SHIPPING SUCCESS] ' . self::ACTION_SHIPPING_GOODS)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_SHIPPING_CODE, $orderTotal);
        } catch (\Exception $exception) {
            throw new \BadMethodCallException(sprintf('Shipping request failed: %s', $exception->getMessage()));
        }
    }

    public function refundItemTransaction(Order $order, $transactionId, array $items, string $identifier = null): void
    {
        try {
            if (empty($items)) {
                throw new \InvalidArgumentException('Product items for refund are missing.');
            }

            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_REFUND);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $isPartialAllowActionResponse = $this->paymentService
                ->isPartialActionAllowed($transactionId, self::ACTION_REFUND);
            if (!empty($isPartialAllowActionResponse['error'])) {
                $message = $this->paymentHelper
                    ->retrieveErrorMessageFromSdkResponse($isPartialAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $paymentItems = $this->orderItemsManager->getPaymentItemsForAction($items);
            $deliveryFee = $this->orderItemsManager->getDeliveryFee($items);

            // Calculate amount
            $amount = $deliveryFee;
            foreach ($paymentItems as $item) {
                /** @var array{identifier: string, name: string, price: float, quantity: int} $item */
                $amount += ($item['price'] * $item['quantity']);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_REFUND_CODE, [
                    'paymentItems' => $paymentItems,
                    'deliveryFee' => $deliveryFee,
                ]);

            $refundResult = $this->paymentService
                ->refundItemsPayment($transactionId, $paymentItems, $deliveryFee, $identifier);

            if (!empty($refundResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($refundResult);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_REFUND_CODE, [
                    'refundResult' => $refundResult,
                ]);

            $status = $refundResult['call']['status'];

            if ($status !== 'success') {
                return;
            }

            $orderTotal = $this->orderItemsManager->refundOrderItems($order->id, $items);

            if (isset($refundResult['result']['status'])) {
                $this->orderProcessor->updatePlentyOrderStatus($order->id, $refundResult['result']['status']);
                $this->paymentProcessor->updatePlentyPaymentStatus($transactionId, $refundResult['result']['status']);
            }

            // Save in history
            $this->addActionHistory($order, $amount, self::ACTION_REFUND);

            $this->getLogger(__METHOD__ . ' [REFUND SUCCESS] ' . self::ACTION_REFUND)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_REFUND_CODE, $orderTotal);
        } catch (\Exception $exception) {
            throw new \BadMethodCallException(sprintf('Items Refund request failed: %s', $exception->getMessage()));
        }
    }

    public function cancelItemTransaction(Order $order, $transactionId, array $items, string $identifier = null): void
    {
        try {
            if (empty($items)) {
                throw new \InvalidArgumentException('Product items for cancellation are missing.');
            }

            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_CANCEL);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $isPartialAllowActionResponse = $this->paymentService
                ->isPartialActionAllowed($transactionId, self::ACTION_CANCEL);
            if (!empty($isPartialAllowActionResponse['error'])) {
                $message = $this->paymentHelper
                    ->retrieveErrorMessageFromSdkResponse($isPartialAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $paymentItems = $this->orderItemsManager->getPaymentItemsForAction($items);
            $deliveryFee = $this->orderItemsManager->getDeliveryFee($items);

            // Calculate amount
            $amount = $deliveryFee;
            foreach ($paymentItems as $item) {
                /** @var array{identifier: string, name: string, price: float, quantity: int} $item */
                $amount += ($item['price'] * $item['quantity']);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CANCEL_CODE, [
                    'paymentItems' => $paymentItems,
                    'deliveryFee' => $deliveryFee,
                ]);

            $cancelResult = $this->paymentService
                ->cancelItemsPayment($transactionId, $paymentItems, $deliveryFee, $identifier);

            if (!empty($cancelResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($cancelResult);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CANCEL_CODE, [
                    'cancelResult' => $cancelResult,
                ]);

            $status = $cancelResult['call']['status'];

            if ($status !== 'success') {
                return;
            }

            $orderTotal = $this->orderItemsManager->cancelOrderItems($order->id, $items);

            if (isset($cancelResult['result']['status'])) {
                $this->orderProcessor->updatePlentyOrderStatus($order->id, $cancelResult['result']['status']);
                $this->paymentProcessor->updatePlentyPaymentStatus($transactionId, $cancelResult['result']['status']);
            }

            // Save in history
            $this->addActionHistory($order, $amount, self::ACTION_CANCEL);

            $this->getLogger(__METHOD__ . ' [CANCEL SUCCESS] ' . self::ACTION_CANCEL)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CANCEL_CODE, $orderTotal);
        } catch (\Exception $exception) {
            throw new \BadMethodCallException(sprintf('Items Cancel request failed: %s', $exception->getMessage()));
        }
    }

    public function shippingTransaction(Order $order, $transactionId, $amount, string $identifier = null): void
    {
        try {
            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_SHIPPING_GOODS);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_SHIPPING_CODE, [
                    'amount' => $amount,
                ]);

            $shippingResult = $this->paymentService->shippingGoodsPayment(
                $transactionId,
                $amount,
                [],
                0,
                'Ship amount',
                null,
                null,
                null,
                $identifier
            );

            if (!empty($shippingResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($shippingResult);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__ . ' $shippingResult')
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_SHIPPING_CODE, [
                    'shippingResult' => $shippingResult,
                ]);

            $status = $shippingResult['call']['status'];

            if ($status !== 'success') {
                return;
            }

            $orderTotal = $this->orderItemsManager->addCapturedAmount($order->id, $amount, true);

            if (isset($shippingResult['result']['status'])) {
                $this->orderProcessor->updatePlentyOrderStatus($order->id, $shippingResult['result']['status']);
                $this->paymentProcessor->updatePlentyPaymentStatus($transactionId, $shippingResult['result']['status']);
            }

            // Save in history
            $this->addActionHistory($order, $amount, self::ACTION_SHIPPING_GOODS);

            $this->getLogger(__METHOD__ . ' [SHIPPING SUCCESS] ' . self::ACTION_SHIPPING_GOODS)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_SHIPPING_CODE, $orderTotal);
        } catch (\Exception $exception) {
            throw new \BadMethodCallException(sprintf('Shipping goods action failed: %s', $exception->getMessage()));
        }
    }

    public function refundTransaction(Order $order, $transactionId, $amount, string $identifier = null): void
    {
        try {
            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_REFUND);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_REFUND_CODE, [
                    'transactionId' => $transactionId,
                    'amount' => $amount,
                ]);


            $refundResult = $this->paymentService->refundPayment($transactionId, $amount, $identifier);

            if (!empty($refundResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($refundResult);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__ . ' $refundResult')
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_REFUND_CODE, [
                    'refundResult' => $refundResult,
                ]);

            $status = $refundResult['call']['status'];

            if ($status !== 'success') {
                return;
            }

            $orderTotal = $this->orderItemsManager->addRefundedAmount($order->id, $amount, true);

            if (isset($refundResult['result']['status'])) {
                $this->orderProcessor->updatePlentyOrderStatus($order->id, $refundResult['result']['status']);
                $this->paymentProcessor->updatePlentyPaymentStatus($transactionId, $refundResult['result']['status']);
            }

            // Save in history
            $actionHistory = $this->addActionHistory($order, $amount, self::ACTION_REFUND);

            $this->getLogger(__METHOD__ . ' [REFUND SUCCESS] ' . self::ACTION_REFUND)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_REFUND_CODE, [
                    'orderTotal' => $orderTotal,
                    'actionHistory' => $actionHistory
                ]);
        } catch (\Exception $exception) {
            throw new \BadMethodCallException(sprintf('Refund request failed: %s', $exception->getMessage()));
        }
    }

    public function cancelTransaction(Order $order, $transactionId, $amount, string $identifier = null): void
    {
        try {
            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_CANCEL);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CANCEL_CODE, [
                    'transactionId' => $transactionId,
                    'amount' => $amount,
                ]);

            $cancelResult = $this->paymentService->cancelPayment($transactionId, $amount, $identifier);

            if (!empty($cancelResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($cancelResult);
                throw new \BadMethodCallException($message);
            }

            $this->getLogger(__METHOD__ . ' $cancelResult')
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CANCEL_CODE, [
                    'cancelResult' => $cancelResult,
                ]);

            $status = $cancelResult['call']['status'];

            if ($status !== 'success') {
                return;
            }

            $orderTotal = $this->orderItemsManager->addCancelledAmount($order->id, $amount, true);

            if (isset($cancelResult['result']['status'])) {
                $this->orderProcessor->updatePlentyOrderStatus($order->id, $cancelResult['result']['status']);
                $this->paymentProcessor->updatePlentyPaymentStatus($transactionId, $cancelResult['result']['status']);
            }

            // Save in history
            $actionHistory = $this->addActionHistory($order, $amount, self::ACTION_CANCEL);

            $this->getLogger(__METHOD__ . ' [CANCEL SUCCESS] ' . self::ACTION_CANCEL)
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CANCEL_CODE, [
                    'orderTotal' => $orderTotal,
                    'actionHistory' => $actionHistory
                ]);
        } catch (\Exception $exception) {
            throw new \BadMethodCallException(sprintf('Cancel failed: %s', $exception->getMessage()));
        }
    }

    /**
     * @param Order $order
     * @param $amount
     * @param string $action
     * @return ActionHistory
     */
    private function addActionHistory(Order $order, $amount, string $action): ActionHistory
    {
        // Save in history
        $actionHistory = $this->actionHistoryRepository->create();
        $actionHistory->setAction($action);
        $actionHistory->setOrderId($order->id);
        $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
        $actionHistory->setAmount($amount);
        $actionHistory->setTimestamp(time());
        $this->actionHistoryRepository->persist($actionHistory);

        return $actionHistory;
    }
}
