<?php

namespace Payever\Controllers;

use Exception;
use IO\Services\NotificationService;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Class PaymentController
 */
class PaymentController extends Controller
{
    use Logger;

    /**
     * @param Request $request
     * @param PayeverService $payeverService
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param NotificationService $notificationService
     * @param Twig $twig
     *
     * @return string|SymfonyResponse
     */
    public function checkoutIframe(
        Request $request,
        PayeverService $payeverService,
        FrontendSessionStorageFactoryContract $sessionStorage,
        NotificationService $notificationService,
        Twig $twig
    ) {
        try {
            $iframeUrl = $sessionStorage->getPlugin()->getValue('payever_iframe_url');

            if ($sessionStorage->getPlugin()->getValue('payever_order_before_payment')) {
                $method = (string)$request->get('method');
                $iframeUrl = $payeverService->processOrderPayment($method);
            }

        } catch (Exception $exception) {
            $response = pluginApp(Response::class);
            $notificationService->warn($exception->getMessage());

            return $response->redirectTo('checkout');
        }

        if ($payeverService->isSubmitMethod($method)) {
            $response = pluginApp(Response::class);

            return $response->redirectTo($iframeUrl);
        }

        return $twig->render('Payever::Checkout.Iframe', ['iframe_url' => $iframeUrl]);
    }
}
