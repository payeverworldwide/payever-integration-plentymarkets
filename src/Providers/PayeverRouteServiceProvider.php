<?php

namespace Payever\Providers;

use Payever\Controllers\ActionController;
use Payever\Controllers\CallbackController;
use Payever\Controllers\ConfigController;
use Payever\Controllers\LogController;
use Payever\Controllers\NoticeController;
use Payever\Controllers\OrderController;
use Payever\Controllers\PaymentController;
use Payever\Controllers\WidgetController;
use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\Routing\Router;

class PayeverRouteServiceProvider extends RouteServiceProvider
{
    const CALLBACK_SUCCESS_URL = '/payment/payever/checkoutSuccess';
    const CALLBACK_PENDING_URL = '/payment/payever/checkoutPending';
    const CALLBACK_FAILURE_URL = '/payment/payever/checkoutFailure';
    const CALLBACK_CANCEL_URL = '/payment/payever/checkoutCancel';
    const CALLBACK_STATUS_URL = '/payment/payever/checkoutStatus';

    const NOTICE_URL = '/payment/payever/checkoutNotice';
    const PAYMENT_IFRAME_URL = '/payment/payever/checkoutIframe';

    const WIDGET_QUOTE_URL = '/widget/payever/checkoutQuote';

    const CONFIG_COMMAND_URL = '/payment/payever/executeCommand';
    const CONFIG_SYNC_URL = '/payment/payever/synchronize';

    const LOG_DOWNLOAD_URL = '/payment/payever/logs';
    const LOG_REST_SHOW_URL = '/payever/logs';

    const ORDER_ACTIONS_URL = '/order/payever/action';
    const ORDER_TOTALS_URL = '/order/payever/totals';
    const ORDER_ITEMS_URL = '/order/payever/items';

    /**
     * @param Router $router
     * @param ApiRouter $apiRouter
     */
    public function map(Router $router, ApiRouter $apiRouter)
    {
        // Register payever callback urls
        $router->get(self::CALLBACK_SUCCESS_URL, CallbackController::class . '@checkoutSuccess');
        $router->get(self::CALLBACK_PENDING_URL, CallbackController::class . '@checkoutPending');
        $router->get(self::CALLBACK_FAILURE_URL, CallbackController::class . '@checkoutFailure');
        $router->get(self::CALLBACK_CANCEL_URL, CallbackController::class . '@checkoutCancel');
        $router->get(self::CALLBACK_STATUS_URL, CallbackController::class . '@checkoutStatus');

        // Register payever payment urls
        $router->get(self::PAYMENT_IFRAME_URL, PaymentController::class . '@checkoutIframe');

        // Register payever notice urls
        $router->post(self::NOTICE_URL, NoticeController::class . '@checkoutNotice');

        // Register payever widget payment urls
        $router->get(self::WIDGET_QUOTE_URL, WidgetController::class . '@shippingQuote');
        $router->post(self::WIDGET_QUOTE_URL, WidgetController::class . '@shippingQuote');

        // Register Logs Controller /rest/payever/logs
        $router->get(self::LOG_DOWNLOAD_URL, LogController::class . '@downloadLogs');
        $apiRouter->version(['v1'], ['namespace' => 'Payever\Controllers'], function ($apiRouter) {
            // @codeCoverageIgnoreStart
            $apiRouter->get(self::LOG_REST_SHOW_URL, 'LogController@showLogs');
            // @codeCoverageIgnoreEnd
        });

        // Register config routes
        $router->post(self::CONFIG_SYNC_URL, ConfigController::class . '@synchronize');
        $router->get(self::CONFIG_COMMAND_URL, ConfigController::class . '@executeCommand');

        // Register order routes
        $router->post(self::ORDER_ACTIONS_URL, ActionController::class . '@actionsHandler');
        $router->get(self::ORDER_TOTALS_URL, OrderController::class . '@getOrderTotals');
        $router->get(self::ORDER_ITEMS_URL, OrderController::class . '@getOrderTotalItems');
    }
}
