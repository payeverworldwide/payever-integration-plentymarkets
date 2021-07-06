<?php

namespace Payever\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

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
            'Payever\Controllers\PaymentController@checkoutSuccessDecorator'
        );
        $router->get(
            'payment/payever/checkoutFinish',
            'Payever\Controllers\PaymentController@checkoutFinishDecorator'
        );
        $router->get(
            'payment/payever/checkoutCancel',
            'Payever\Controllers\PaymentController@checkoutCancelDecorator'
        );
        $router->get(
            'payment/payever/checkoutFailure',
            'Payever\Controllers\PaymentController@checkoutFailureDecorator'
        );
        $router->post(
            'payment/payever/checkoutNotice',
            'Payever\Controllers\PaymentController@checkoutNoticeDecorator'
        );
        $router->get('payment/payever/checkoutIframe', 'Payever\Controllers\PaymentController@checkoutIframe');

        // Register config routes
        $router->post('payment/payever/synchronize', 'Payever\Controllers\ConfigController@synchronize');
        $router->get('payment/payever/executeCommand', 'Payever\Controllers\ConfigController@executeCommand');
    }
}
