<?php

namespace payever\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class payeverRouteServiceProvider
 * @package payever\Providers
 */
class PayeverRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        // Register payever success and cancellation URLs
        $router->get('payment/payever/checkoutSuccess', 'payever\Controllers\PaymentController@checkoutSuccess');
        $router->get('payment/payever/checkoutCancel', 'payever\Controllers\PaymentController@checkoutCancel');
        $router->get('payment/payever/checkoutFailure', 'payever\Controllers\PaymentController@checkoutFailure');
        $router->get('payment/payever/checkoutNotice', 'payever\Controllers\PaymentController@checkoutNotice');
        $router->get('payment/payever/checkoutIframe', 'payever\Controllers\PaymentController@checkoutIframe');

        // Register config routes
        $router->get('payment/payever/synchronize', 'payever\Controllers\ConfigController@synchronize');
    }
}
