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

use Exception;
use Payever\Contracts\ActionHistoryRepositoryContract;
use Payever\Helper\OrderItemsManager;
use Payever\Helper\PayeverHelper;
use Payever\Models\ActionHistory;
use Payever\Services\PayeverService;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PaymentActionService
{
    use Loggable;

    const ACTION_CANCEL = 'cancel';
    const ACTION_REFUND = 'refund';
    const ACTION_SHIPPING_GOODS = 'shipping_goods';

    private PayeverService $paymentService;
    private PayeverHelper $paymentHelper;
    private OrderItemsManager $orderItemsManager;
    private ActionHistoryRepositoryContract $actionHistoryRepository;

    public function __construct(
        PayeverService $paymentService,
        PayeverHelper $paymentHelper,
        OrderItemsManager $orderItemsManager,
        ActionHistoryRepositoryContract $actionHistoryRepository
    ) {
        $this->paymentService = $paymentService;
        $this->paymentHelper = $paymentHelper;
        $this->orderItemsManager = $orderItemsManager;
        $this->actionHistoryRepository = $actionHistoryRepository;
    }

    public function shipGoodsTransaction(Order $order, $transactionId, array $items): void
    {
        try {
            if (count($items) === 0) {
                throw new Exception('Product items are missing.');
            }

            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_SHIPPING_GOODS);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new Exception($message);
            }

            $isPartialAllowActionResponse = $this->paymentService
                ->isPartialActionAllowed($transactionId, self::ACTION_SHIPPING_GOODS);
            if (!empty($isPartialAllowActionResponse['error'])) {
                $message = $this->paymentHelper
                    ->retrieveErrorMessageFromSdkResponse($isPartialAllowActionResponse);
                throw new Exception($message);
            }

            $amount = $this->orderItemsManager->getPaymentAmountByItems($items, true);
            $paymentItems = $this->orderItemsManager->getPaymentItemsForAction($items);
            $deliveryFee = $this->orderItemsManager->getDeliveryFee($items);

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionShipping', [
                    'amount' => $amount,
                    'paymentItems' => $paymentItems,
                    'deliveryFee' => $deliveryFee
                ]);

            $shippingResult = $this->paymentService->shippingGoodsPayment(
                $transactionId,
                $amount,
                $paymentItems,
                $deliveryFee,
                'Shipping goods',
                null,
                null,
                null
            );

            if (!empty($shippingResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($shippingResult);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionShipping', [
                    'shippingResult' => $shippingResult,
                ]);

            $status = $shippingResult['call']['status'];

            if ($status === 'success') {
                $orderTotal = $this->orderItemsManager->shipOrderItems($order->id, $items);

                if (isset($shippingResult['result']['status'])) {
                    $this->paymentService
                        ->updatePlentyPayment($transactionId, $shippingResult['result']['status']);
                }

                // Save in history
                $actionHistory = $this->actionHistoryRepository->create();
                $actionHistory->setAction(self::ACTION_SHIPPING_GOODS);
                $actionHistory->setOrderId($order->id);
                $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
                $actionHistory->setAmount($amount);
                $actionHistory->setTimestamp(time());
                $this->actionHistoryRepository->persist($actionHistory);

                $this->getLogger(__METHOD__ . ' [SUCCESS] ' . self::ACTION_SHIPPING_GOODS)
                    ->setReferenceType('payeverLog')
                    ->debug('Payever::debug.paymentActionShipping', $orderTotal);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Shipping request failed: %s', $exception->getMessage()));
        }
    }

    public function refundItemTransaction(Order $order, $transactionId, array $items): void
    {
        try {
            if (count($items) === 0) {
                throw new Exception('Product items are missing.');
            }

            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_REFUND);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new Exception($message);
            }

            $isPartialAllowActionResponse = $this->paymentService
                ->isPartialActionAllowed($transactionId, self::ACTION_REFUND);
            if (!empty($isPartialAllowActionResponse['error'])) {
                $message = $this->paymentHelper
                    ->retrieveErrorMessageFromSdkResponse($isPartialAllowActionResponse);
                throw new Exception($message);
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
                ->debug('Payever::debug.paymentActionRefund', [
                    'paymentItems' => $paymentItems,
                    'deliveryFee' => $deliveryFee
                ]);

            $refundResult = $this->paymentService
                ->refundItemsPayment($transactionId, $paymentItems, $deliveryFee);

            if (!empty($refundResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($refundResult);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionRefund', [
                    'refundResult' => $refundResult,
                ]);

            $status = $refundResult['call']['status'];

            if ($status === 'success') {
                $orderTotal = $this->orderItemsManager->refundOrderItems($order->id, $items);

                if (isset($refundResult['result']['status'])) {
                    $this->paymentService
                        ->updatePlentyPayment($transactionId, $refundResult['result']['status']);
                }

                // Save in history
                $actionHistory = $this->actionHistoryRepository->create();
                $actionHistory->setAction(self::ACTION_REFUND);
                $actionHistory->setOrderId($order->id);
                $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
                $actionHistory->setAmount($amount);
                $actionHistory->setTimestamp(time());
                $this->actionHistoryRepository->persist($actionHistory);

                $this->getLogger(__METHOD__ . ' [SUCCESS] ' . self::ACTION_REFUND)
                    ->setReferenceType('payeverLog')
                    ->debug('Payever::debug.paymentActionRefund', $orderTotal);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Items Refund request failed: %s', $exception->getMessage()));
        }
    }

    public function cancelItemTransaction(Order $order, $transactionId, array $items): void
    {
        try {
            if (count($items) === 0) {
                throw new Exception('Product items are missing.');
            }

            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_CANCEL);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new Exception($message);
            }

            $isPartialAllowActionResponse = $this->paymentService
                ->isPartialActionAllowed($transactionId, self::ACTION_CANCEL);
            if (!empty($isPartialAllowActionResponse['error'])) {
                $message = $this->paymentHelper
                    ->retrieveErrorMessageFromSdkResponse($isPartialAllowActionResponse);
                throw new Exception($message);
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
                ->debug('Payever::debug.paymentActionCancel', [
                    'paymentItems' => $paymentItems,
                    'deliveryFee' => $deliveryFee
                ]);

            $cancelResult = $this->paymentService
                ->cancelItemsPayment($transactionId, $paymentItems, $deliveryFee);

            if (!empty($cancelResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($cancelResult);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionCancel', [
                    'cancelResult' => $cancelResult,
                ]);

            $status = $cancelResult['call']['status'];

            if ($status === 'success') {
                $orderTotal = $this->orderItemsManager->cancelOrderItems($order->id, $items);

                if (isset($cancelResult['result']['status'])) {
                    $this->paymentService
                        ->updatePlentyPayment($transactionId, $cancelResult['result']['status']);
                }

                // Save in history
                $actionHistory = $this->actionHistoryRepository->create();
                $actionHistory->setAction(self::ACTION_CANCEL);
                $actionHistory->setOrderId($order->id);
                $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
                $actionHistory->setAmount($amount);
                $actionHistory->setTimestamp(time());
                $this->actionHistoryRepository->persist($actionHistory);

                $this->getLogger(__METHOD__ . ' [SUCCESS] ' . self::ACTION_CANCEL)
                    ->setReferenceType('payeverLog')
                    ->debug('Payever::debug.paymentActionCancel', $orderTotal);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Items Cancel request failed: %s', $exception->getMessage()));
        }
    }

    public function shippingTransaction(Order $order, $transactionId, $amount): void
    {
        try {
            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_SHIPPING_GOODS);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionShipping', [
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
                null
            );

            if (!empty($shippingResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($shippingResult);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__ . ' $shippingResult')
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionShipping', [
                    'shippingResult' => $shippingResult,
                ]);

            $status = $shippingResult['call']['status'];

            if ($status === 'success') {
                $orderTotal = $this->orderItemsManager->addCapturedAmount($order->id, $amount, true);

                if (isset($shippingResult['result']['status'])) {
                    $this->paymentService
                        ->updatePlentyPayment($transactionId, $shippingResult['result']['status']);
                }

                // Save in history
                $actionHistory = $this->actionHistoryRepository->create();
                $actionHistory->setAction(self::ACTION_SHIPPING_GOODS);
                $actionHistory->setOrderId($order->id);
                $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
                $actionHistory->setAmount($amount);
                $actionHistory->setTimestamp(time());
                $this->actionHistoryRepository->persist($actionHistory);

                $this->getLogger(__METHOD__ . ' [SUCCESS] ' . self::ACTION_SHIPPING_GOODS)
                    ->setReferenceType('payeverLog')
                    ->debug('Payever::debug.paymentActionShipping', $orderTotal);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Shipping goods action failed: %s', $exception->getMessage()));
        }
    }

    public function refundTransaction(Order $order, $transactionId, $amount): void
    {
        try {
            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_REFUND);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionRefund', [
                    'transactionId' => $transactionId,
                    'amount' => $amount,
                ]);


            $refundResult = $this->paymentService->refundPayment($transactionId, $amount);

            if (!empty($refundResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($refundResult);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__ . ' $refundResult')
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionRefund', [
                    'refundResult' => $refundResult,
                ]);

            $status = $refundResult['call']['status'];

            if ($status === 'success') {
                $orderTotal = $this->orderItemsManager->addRefundedAmount($order->id, $amount, true);

                if (isset($refundResult['result']['status'])) {
                    $this->paymentService
                        ->updatePlentyPayment($transactionId, $refundResult['result']['status']);
                }

                // Save in history
                $actionHistory = $this->actionHistoryRepository->create();
                $actionHistory->setAction(self::ACTION_REFUND);
                $actionHistory->setOrderId($order->id);
                $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
                $actionHistory->setAmount($amount);
                $actionHistory->setTimestamp(time());
                $this->actionHistoryRepository->persist($actionHistory);

                $this->getLogger(__METHOD__ . ' [SUCCESS] ' . self::ACTION_REFUND)
                    ->setReferenceType('payeverLog')
                    ->debug('Payever::debug.paymentActionRefund', [
                        'orderTotal' => $orderTotal,
                        'actionHistory' => $actionHistory
                    ]);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Refund request failed: %s', $exception->getMessage()));
        }
    }

    public function cancelTransaction(Order $order, $transactionId, $amount): void
    {
        try {
            $isAllowActionResponse = $this->paymentService
                ->isActionAllowed($transactionId, self::ACTION_CANCEL);
            if (!empty($isAllowActionResponse['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($isAllowActionResponse);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__)
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionCancel', [
                    'transactionId' => $transactionId,
                    'amount' => $amount,
                ]);

            $cancelResult = $this->paymentService->cancelPayment($transactionId, $amount);

            if (!empty($cancelResult['error'])) {
                $message = $this->paymentHelper->retrieveErrorMessageFromSdkResponse($cancelResult);
                throw new Exception($message);
            }

            $this->getLogger(__METHOD__ . ' $cancelResult')
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.paymentActionCancel', [
                    'cancelResult' => $cancelResult,
                ]);

            $status = $cancelResult['call']['status'];

            if ($status === 'success') {
                $orderTotal = $this->orderItemsManager->addCancelledAmount($order->id, $amount, true);

                if (isset($cancelResult['result']['status'])) {
                    $this->paymentService
                        ->updatePlentyPayment($transactionId, $cancelResult['result']['status']);
                }

                // Save in history
                $actionHistory = $this->actionHistoryRepository->create();
                $actionHistory->setAction(self::ACTION_CANCEL);
                $actionHistory->setOrderId($order->id);
                $actionHistory->setSource(ActionHistory::SOURCE_ADMIN);
                $actionHistory->setAmount($amount);
                $actionHistory->setTimestamp(time());
                $this->actionHistoryRepository->persist($actionHistory);

                $this->getLogger(__METHOD__ . ' [SUCCESS] ' . self::ACTION_CANCEL)
                    ->setReferenceType('payeverLog')
                    ->debug('Payever::debug.paymentActionCancel', [
                        'orderTotal' => $orderTotal,
                        'actionHistory' => $actionHistory
                    ]);
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Cancel failed: %s', $exception->getMessage()));
        }
    }
}
