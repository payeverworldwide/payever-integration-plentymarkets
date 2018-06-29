<?php //strict

namespace Payever\Providers;

use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Events\Dispatcher;
use Payever\Helper\PayeverHelper;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Payever\Services\PayeverService;
use Payever\Procedures\RefundEventProcedure;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;

/**
 * Class PayeverServiceProvider
 * @package Payever\Providers
 */
class PayeverServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->getApplication()->register(PayeverRouteServiceProvider::class);
        $this->getApplication()->bind(RefundEventProcedure::class);
    }

    /**
     * Boot additional services for the payment method
     *
     * @param PayeverHelper $paymentHelper
     * @param PaymentMethodContainer $payContainer
     * @param Dispatcher $eventDispatcher
     */
    public function boot(
        PayeverHelper $paymentHelper,
        PaymentMethodContainer $payContainer,
        Dispatcher $eventDispatcher,
        PayeverService $payeverService,
        BasketRepositoryContract $basket,
        EventProceduresService $eventProceduresService
    ) {
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

        $payeverMops = $paymentHelper->getMopKeyToIdMap();

        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(
            GetPaymentMethodContent::class,
            function (GetPaymentMethodContent $event) use ($payeverMops, $basket, $payeverService) {
                $mop = $event->getMop();

                if (in_array($mop, $payeverMops)) {
                    $methodKey = array_search($mop, $payeverMops);
                    $event->setValue($payeverService->preparePayeverPayment($basket->load(), strtolower($methodKey)));
                    $event->setType($payeverService->getReturnType());
                }
            }
        );

        // Listen for the event that executes the payment
        $eventDispatcher->listen(
            ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $payeverMops, $payeverService) {
                if (in_array($event->getMop(), $payeverMops)) {
                    // Execute the payment
                    $payeverPaymentData = $payeverService->executePayment();
                    // Check whether the payever payment has been executed successfully
                    if ($payeverService->getReturnType() != 'errorCode') {
                        // Create a plentymarkets payment from the payever execution params
                        $plentyPayment = $paymentHelper->createPlentyPayment($payeverPaymentData, $event->getMop());
                        if ($plentyPayment instanceof Payment) {
                            // Assign the payment to an order in plentymarkets
                            $paymentHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $event->getOrderId());
                            $event->setType('success');
                            $event->setValue('The Payment has been executed successfully!');
                        }
                    } else {
                        $event->setType('error');
                        $event->setValue('The payever-Payment could not be executed!');
                    }
                }
            }
        );
    }
}
