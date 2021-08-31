<?php

namespace Payever\Providers;

use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Helper\PayeverHelper;
use Payever\Procedures\CancelEventProcedure;
use Payever\Procedures\RefundEventProcedure;
use Payever\Procedures\ShippingEventProcedure;
use Payever\Repositories\PendingPaymentRepository;
use Payever\Services\PayeverCronHandler;
use Payever\Services\PayeverOrdersCronHandler;
use Payever\Services\PayeverService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Order\Pdf\Events\OrderPdfGenerationEvent;
use Plenty\Modules\Order\Pdf\Models\OrderPdfGeneration;
use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\ServiceProvider;

/**
 * @codeCoverageIgnore
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayeverServiceProvider extends ServiceProvider
{
    use Loggable;

    /**
     * @return void
     */
    public function register()
    {
        $this->getApplication()->register(PayeverRouteServiceProvider::class);
        $this->getApplication()->bind(RefundEventProcedure::class);
        $this->getApplication()->bind(CancelEventProcedure::class);
        $this->getApplication()->bind(ShippingEventProcedure::class);
        $this->getApplication()->singleton(
            PendingPaymentRepositoryContract::class,
            PendingPaymentRepository::class
        );
    }

    /**
     * Boot additional services for the payment method
     *
     * @param PayeverHelper $paymentHelper
     * @param PaymentMethodContainer $payContainer
     * @param Dispatcher $eventDispatcher
     * @param PayeverService $payeverService
     * @param BasketRepositoryContract $basket
     * @param EventProceduresService $eventProceduresService
     * @param CronContainer $cronContainer
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function boot(
        PayeverHelper $paymentHelper,
        PaymentMethodContainer $payContainer,
        Dispatcher $eventDispatcher,
        PayeverService $payeverService,
        BasketRepositoryContract $basket,
        EventProceduresService $eventProceduresService,
        CronContainer $cronContainer
    )
    {
        $cronContainer->add(CronContainer::DAILY, PayeverCronHandler::class);
        $cronContainer->add(CronContainer::HOURLY, PayeverOrdersCronHandler::class);
        /*
         * register the payment method in the payment method container
         */
        $methodsData = $paymentHelper->getMethodsMetaData();
        $rebuildEventClasses = [AfterBasketChanged::class, AfterBasketItemAdd::class, AfterBasketCreate::class];

        foreach ($methodsData as $methodKey => $methodData) {
            $payContainer->register(
                PayeverHelper::PLUGIN_KEY . '::' . $methodKey,
                $methodData['class'],
                $rebuildEventClasses
            );
        }

        // Register Refund Event Procedure
        $eventProceduresService->registerProcedure(PayeverHelper::PLUGIN_KEY, ProcedureEntry::PROCEDURE_GROUP_ORDER, [
            'de' => 'Rückzahlung der payever-Zahlung',
            'en' => 'Refund the payever payment'
        ], 'Payever\Procedures\RefundEventProcedure@run');

        // Register Cancel Event Procedure
        $eventProceduresService->registerProcedure(PayeverHelper::PLUGIN_KEY, ProcedureEntry::PROCEDURE_GROUP_ORDER, [
            'de' => 'Stornieren sie die auszahlung',
            'en' => 'Cancel the payever payment'
        ], 'Payever\Procedures\CancelEventProcedure@run');

        // Register Shipping Event Procedure
        $eventProceduresService->registerProcedure(PayeverHelper::PLUGIN_KEY, ProcedureEntry::PROCEDURE_GROUP_ORDER, [
            'de' => 'Versand der Auszahlung',
            'en' => 'Shipping the payever payment'
        ], 'Payever\Procedures\ShippingEventProcedure@run');

        $payeverMops = $paymentHelper->getMopKeyToIdMap();

        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(
            GetPaymentMethodContent::class,
            function (GetPaymentMethodContent $event) use ($payeverMops, $basket, $payeverService) {
                $mop = $event->getMop();

                if (in_array($mop, $payeverMops)) {
                    $methodKey = array_search($mop, $payeverMops);
                    //$payeverService->prepareOrder();
                    $event->setValue($payeverService->preparePayeverPayment($basket->load(), strtolower($methodKey)));
                    $event->setType($payeverService->getReturnType());
                }
            }
        );

        // Listen for the event that executes the payment
        $eventDispatcher->listen(
            ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $payeverMops, $payeverService) {
                $this->getLogger(__METHOD__)->debug('Payever::debug.ExecutePayment', $payeverService);
                $payeverPaymentId = $payeverService->getPayeverPaymentId();
                if (!in_array($event->getMop(), $payeverMops) || !$payeverPaymentId) {
                    return;
                }

                // Execute the payment
                $payeverPaymentData = $payeverService->pluginExecutePayment($payeverPaymentId);

                $this->getLogger(__METHOD__)->debug('Payever::debug.payeverExecutePayment', $payeverPaymentData);

                // Check whether the payever payment has been executed successfully
                if ($payeverService->getReturnType() == 'errorCode') {
                    $event->setType('error');
                    $event->setValue('The payever-Payment could not be executed!');

                    return;
                }

                // Create a plentymarkets payment from the payever execution params
                $plentyPayment = $payeverService->createPlentyPayment($payeverPaymentData, $event->getMop());
                $this->getLogger(__METHOD__)->debug('Payever::debug.createPlentyPayment', $plentyPayment);

                if ($plentyPayment instanceof Payment) {
                    // Assign the payment to an order in plentymarkets
                    $payeverService->assignPlentyPaymentToPlentyOrder(
                        $plentyPayment,
                        $event->getOrderId(),
                        $payeverPaymentData['status']
                    );
                    $event->setType('success');
                    $event->setValue('The Payment has been executed successfully!');
                }
            }
        );

        $eventDispatcher->listen(OrderPdfGenerationEvent::class,
            function (OrderPdfGenerationEvent $event) use ($paymentHelper, $payeverMops, $payeverService) {
                if ($event->getDocType() === 'invoice') {
                    $order            = $event->getOrder();

                    $this->getLogger(__METHOD__)->debug('Payever::debug.StartOrderPdfGenerationEvent', $order);

                    if (!in_array($order->methodOfPaymentId, $payeverMops)) {
                        return;
                    }

                    $payeverPaymentId = null;
                    foreach ($order->payments as $payment) {
                        if ((int)$payment->mopId === (int)$order->methodOfPaymentId) {
                            $this->getLogger(__METHOD__)->debug('Payever::debug.OrderPdfGenerationEventPayment', $payment);
                            $payeverPaymentId = $paymentHelper->getPaymentPropertyValue($payment, PaymentProperty::TYPE_TRANSACTION_ID);
                        }
                    }

                    if (!$payeverPaymentId) {
                        return;
                    }

                    $payeverTransactionResponse = $payeverService->handlePayeverPayment($payeverPaymentId);
                    $this->getLogger(__METHOD__)->debug('Payever::debug.OrderPdfGenerationEventPayeverResponse', $payeverTransactionResponse);

                    if (array_key_exists('usage_text', $payeverTransactionResponse['payment_details'])) {
                        /** @var OrderPdfGeneration $generation */
                        $generation         = pluginApp(OrderPdfGeneration::class);
                        $generation->advice = $paymentHelper->translate('Payever::Backend.accountUsage') . $payeverTransactionResponse['payment_details']['usage_text'];
                        $generation->advice = $paymentHelper->translate('Payever::Backend.pdfInfoText') . "\n\n" .
                            $paymentHelper->translate('Payever::Backend.accountHolder') . "\n" .
                            $paymentHelper->translate('Payever::Backend.accountIban') . "\n" .
                            $paymentHelper->translate('Payever::Backend.accountBic') . "\n" .
                            $paymentHelper->translate('Payever::Backend.orderTotalAmount'). " " . $order->amounts[0]->grossTotal . " " . $order->amounts[0]->currency . "\n\n" .
                            $paymentHelper->translate('Payever::Backend.accountUsage') . " " . $payeverTransactionResponse['payment_details']['usage_text'];

                        $event->addOrderPdfGeneration($generation);
                    }
                }
            }
        );
    }
}
