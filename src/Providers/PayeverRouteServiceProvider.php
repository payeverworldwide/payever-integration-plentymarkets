<?php

namespace Payever\Providers;

use Payever\Controllers\ActionController;
use Payever\Controllers\OrderController;
use Payever\Controllers\PaymentController;
use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\Routing\Router;
use Payever\Controllers\LogController;

class PayeverRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router, ApiRouter $apiRouter)
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

        // Register Logs Controller /rest/payever/logs
        $apiRouter->version(['v1'], ['namespace' => 'Payever\Controllers'], function ($apiRouter) {
            $apiRouter->get('payever/logs', 'LogController@showLogs');
        });

        $router->get('payment/payever/logs', LogController::class . '@downloadLogs');

        // Register config routes
        $router->post('payment/payever/synchronize', 'Payever\Controllers\ConfigController@synchronize');
        $router->get('payment/payever/executeCommand', 'Payever\Controllers\ConfigController@executeCommand');

        // Register order routes
        $router->post(
            'order/payever/action',
            ActionController::class . '@actionsHandler'
        );

        $router->get(
            'order/payever/totals',
            OrderController::class . '@getOrderTotals'
        );

        $router->get(
            'order/payever/items',
            OrderController::class . '@getOrderTotalItems'
        );
    }
}
