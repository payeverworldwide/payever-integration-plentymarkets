<?php

namespace Payever\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class payeverRouteServiceProvider
 * @package Payever\Providers
 */
class PayeverRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        // Register payever success and cancellation URLs
        $router->get('payment/payever/checkoutSuccess', 'Payever\Controllers\PaymentController@checkoutSuccess');
        $router->get('payment/payever/checkoutCancel', 'Payever\Controllers\PaymentController@checkoutCancel');
        $router->get('payment/payever/checkoutFailure', 'Payever\Controllers\PaymentController@checkoutFailure');
        $router->get('payment/payever/checkoutNotice', 'Payever\Controllers\PaymentController@checkoutNotice');
        $router->get('payment/payever/checkoutIframe', 'Payever\Controllers\PaymentController@checkoutIframe');

        // Register config routes
        $router->get('payment/payever/synchronize', 'Payever\Controllers\ConfigController@synchronize');
        $router->get('payment/payever/executeCommand', 'Payever\Controllers\ConfigController@executeCommand');
    }
}
