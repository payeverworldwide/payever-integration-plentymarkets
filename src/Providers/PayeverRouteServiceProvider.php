<?php

namespace Payever\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;
use Payever\Controllers\PaymentController;

class PayeverRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        // Register payever success and cancellation URLs
        $router->get(
            'payment/payever/checkoutSuccess',
            PaymentController::class . '@checkoutSuccessDecorator'
        );

        $router->get(
            'payment/payever/checkoutFinish',
            PaymentController::class . '@checkoutFinishDecorator'
        );

        $router->get(
            'payment/payever/checkoutCancel',
            PaymentController::class . '@checkoutCancelDecorator'
        );

        $router->get(
            'payment/payever/checkoutFailure',
            PaymentController::class . '@checkoutFailureDecorator'
        );

        $router->post(
            'payment/payever/checkoutNotice',
            PaymentController::class . '@checkoutNoticeDecorator'
        );

        $router->get(
            'payment/payever/checkoutIframe',
            PaymentController::class . '@checkoutIframe'
        );

        // Register config routes
        $router->post('payment/payever/synchronize', 'Payever\Controllers\ConfigController@synchronize');
        $router->get('payment/payever/executeCommand', 'Payever\Controllers\ConfigController@executeCommand');
    }
}
