<?php

namespace Payever\Procedures;

use Exception;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

class CancelEventProcedure
{
    use Loggable;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @throws Exception
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper
    ) {
        $orderId = $paymentHelper->getOrderIdByEvent($eventTriggered);

        if (empty($orderId)) {
            throw new Exception('Cancel payever payment failed! The given order is invalid!');
        }

        /** @var Payment[] $payment */
        $payments = $paymentContract->getPaymentsByOrderId($orderId);

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                $transactionId = $paymentHelper->getPaymentPropertyValue(
                    $payment,
                    PaymentProperty::TYPE_TRANSACTION_ID
                );

                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.cancelData',
                    'TransactionId: ' . $transactionId
                );

                if (!empty($transactionId)) {
                    $transaction = $paymentService->getTransaction($transactionId);
                    $this->getLogger(__METHOD__)->debug('Payever::debug.transactionData', $transaction);

                    if ($paymentHelper->isAllowedTransaction($transaction, 'cancel')) {
                        // cancel the payment
                        $cancelResult = $paymentService->cancelPayment($transactionId);
                        $this->getLogger(__METHOD__)->debug('Payever::debug.cancelResponse', $cancelResult);
                        if ($cancelResult['call']['status'] == 'success') {
                            $payment->status = $paymentHelper->mapStatus($cancelResult['result']['status']);
                            // update the cancelled payment
                            $paymentContract->updatePayment($payment);
                        }
                    } else {
                        $this->getLogger(__METHOD__)->debug(
                            'Payever::debug.cancelResponse',
                            'Cancel payever payment is not allowed!'
                        );
                        throw new Exception('Cancel payever payment is not allowed!');
                    }
                }
            }
        }
    }
}
