<?php

namespace Payever\Controllers;

use Payever\Services\FinanceExpress\ShippingQuoteService;
use Payever\Traits\Logger;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Class WidgetController
 */
class WidgetController extends Controller
{
    use Logger;

    /**
     * Payever calculate shipping costs
     *
     * @param Request $request
     * @param ShippingQuoteService $shippingQuoteService
     *
     * @return SymfonyResponse
     */
    public function shippingQuote(ShippingQuoteService $shippingQuoteService, Request $request): SymfonyResponse
    {
        $shippingMethodsData = [];
        try {
            $widgetCart = $request->get('widgetCart');
            $payload = json_decode($request->getContent(), true);

            if (!$widgetCart) {
                throw new \Exception('Empty cart was provided.');
            }

            $widgetCart = json_decode(base64_decode($widgetCart), true);
            if (!$widgetCart) {
                throw new \Exception('Empty cart was provided.');
            }

            $shippingMethodsData = $shippingQuoteService->estimate($payload, $widgetCart);
        } catch (\Exception $e) {
            $this->log(
                'critical',
                __METHOD__,
                'Payever::debug::quoteCallbackError',
                'Exception: ' . $e->getMessage(),
                [$e]
            );
        }

        $response = pluginApp(Response::class);

        return $response->json(['shippingMethods' => $shippingMethodsData]);
    }
}
