<?php

namespace Payever\Services\Processor;

use Payever\Helper\PayeverHelper;
use Payever\Helper\StatusHelper;
use Payever\Traits\Logger;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;

/**
 * Class PaymentProcessor
 */
class PaymentProcessor
{
    use Logger;

    /**
     * @var AuthHelper
     */
    private AuthHelper $authHelper;

    /**
     * @var PayeverHelper
     */
    private PayeverHelper $payeverHelper;

    /**
     * @var PaymentRepositoryContract
     */
    private PaymentRepositoryContract $paymentRepository;

    /**
     * @var PaymentOrderRelationRepositoryContract
     */
    private PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo;

    /**
     * @param AuthHelper $authHelper
     * @param PayeverHelper $payeverHelper
     * @param PaymentRepositoryContract $paymentRepository
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
     */
    public function __construct(
        AuthHelper $authHelper,
        PayeverHelper $payeverHelper,
        PaymentRepositoryContract $paymentRepository,
        PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
    ) {
        $this->authHelper = $authHelper;
        $this->payeverHelper = $payeverHelper;
        $this->paymentRepository = $paymentRepository;
        $this->paymentOrderRelationRepo = $paymentOrderRelationRepo;
    }

    /**
     * @param array $payeverPayment
     *
     * @return Payment
     */
    public function getPlentyPaymentByPayeverPayment(array $payeverPayment): ?Payment
    {
        $mopId = (int)$this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);

        /** @var Payment[] $payments */
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment['id']
        );

        foreach ($payments as $payment) {
            if ((int)$payment->mopId == $mopId) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * @param array $payeverPayment
     * @param int $orderId
     *
     * @return Payment
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function createPlentyPaymentByPayeverPayment(array $payeverPayment, int $orderId): Payment
    {
        $mopId = (int) $this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);

        $bookingText = 'TransactionID: ' . $payeverPayment['id'];
        if (isset($payeverPayment['payment_details']['usage_text'])) {
            $bookingText .= 'Payment reference: ' . $payeverPayment['payment_details']['usage_text'];
        }

        $paymentProperty = [];
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_BOOKING_TEXT,
            $bookingText
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment['id']
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_REFERENCE_ID,
            $orderId
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_ORIGIN,
            Payment::ORIGIN_PLUGIN
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_PAYMENT_TEXT,
            $payeverPayment['payment_details']['usage_text'] ?? null
        );

        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);
        $payment->mopId = $mopId;
        $payment->transactionType = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status = StatusHelper::mapPaymentStatus($payeverPayment['status']);
        $payment->currency = $payeverPayment['currency'];
        $payment->amount = $payeverPayment['total'];
        $payment->receivedAt = date('Y-m-d H:i:s', strtotime($payeverPayment['created_at']));
        $payment->properties = $paymentProperty;

        $payment = $this->paymentRepository->createPayment($payment);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.createPlentyPaymentByPayeverPayment',
            'Successful Creating Plenty Payment',
            ['payeverPayment' => $payeverPayment]
        )->setReferenceValue($orderId);

        return $payment;
    }

    /**
     * @param Payment $payment
     * @param int $orderId
     *
     * @return bool
     */
    public function isAssignedPlentyPaymentToOrder(Payment $payment, int $orderId): bool
    {
        $orderPayments = $this->paymentRepository->getPaymentsByOrderId($orderId);

        /** @var Payment $payment */
        foreach ($orderPayments as $orderPayment) {
            if ($orderPayment->mopId == $payment->mopId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assigns plenty payment to plenty order
     *
     * @param Payment $payment
     * @param Order $order
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, Order $order)
    {
        $this->authHelper->processUnguarded(
            function () use ($order, $payment) {
                // Assign the given payment to the given order
                $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);

                $transactionId = $this->payeverHelper->getPaymentPropertyValue(
                    $payment,
                    PaymentProperty::TYPE_TRANSACTION_ID
                );

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.assignPlentyPaymentToPlentyOrder',
                    'Transaction was assigned to the order',
                    ['transactionId' => $transactionId, 'orderId' => $order->id]
                )->setReferenceValue($order->id);
            }
        );
    }

    /**
     * @param string $paymentId
     * @param string $status
     * @param string|null $notificationTime
     * @return bool|Payment
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function updatePlentyPaymentStatus(string $paymentId, string $status, string $notificationTime = null)
    {
        /** @var Payment[] $payments */
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $paymentId
        );

        $state = StatusHelper::mapPaymentStatus($status);
        foreach ($payments as $payment) {
            if ($notificationTime) {
                if (strtotime($notificationTime) > strtotime($payment->receivedAt)) {
                    $payment->receivedAt = $notificationTime;
                } else {
                    return false;
                }
            }

            if ($payment->status != $state) {
                $payment->status = $state;
                $this->paymentRepository->updatePayment($payment);

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.updatePlentyPaymentStatus',
                    'Status of payment was changed',
                    ['paymentId' => $paymentId, 'status' => $status]
                );
            }

            return $payment;
        }

        return false;
    }
}
