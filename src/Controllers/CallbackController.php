<?php

namespace Payever\Controllers;

use IO\Services\NotificationService;
use IO\Services\OrderService;
use Payever\Helper\RoutesHelper;
use Payever\Helper\StatusHelper;
use Payever\Services\PayeverSdkService;
use Payever\Services\PayeverService;
use Payever\Services\Payment\FailureStatusHandler;
use Payever\Services\Processor\CheckoutProcessor;
use Payever\Traits\Logger;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Class CallbackController
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CallbackController extends Controller
{
    use Logger;

    /**
     * Prefix for Santander methods
     */
    const SANTANDER_PREFIX = 'santander';

    /**
     * @var PayeverService
     */
    private PayeverService $payeverService;

    /**
     * @var FailureStatusHandler
     */
    private FailureStatusHandler $failureStatusHandler;

    /**
     * @var NotificationService
     */
    private NotificationService $notificationService;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var PayeverSdkService
     */
    private $sdkService;

    /**
     * @param PayeverService $payeverService
     * @param FailureStatusHandler $failureStatusHandler
     * @param NotificationService $notificationService
     * @param Request $request
     */
    public function __construct(
        PayeverService $payeverService,
        FailureStatusHandler $failureStatusHandler,
        NotificationService $notificationService,
        Request $request,
        PayeverSdkService $sdkService
    ) {
        parent::__construct();

        $this->payeverService = $payeverService;
        $this->failureStatusHandler = $failureStatusHandler;
        $this->notificationService = $notificationService;
        $this->request = $request;
        $this->sdkService = $sdkService;
        $this->response = pluginApp(Response::class);
    }

    /**
     * Payever redirects to this page if the payment was executed correctly
     *
     * @param AuthHelper $authHelper
     * @param CheckoutProcessor $checkoutProcessor
     * @param OrderRepositoryContract $orderContract
     * @param SessionStorageRepositoryContract $sessionStorageRepository
     *
     * @return SymfonyResponse
     */
    public function checkoutSuccess(
        AuthHelper $authHelper,
        CheckoutProcessor $checkoutProcessor,
        OrderRepositoryContract $orderContract,
        SessionStorageRepositoryContract $sessionStorageRepository
    ): SymfonyResponse {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.successUrlWasCalled',
            'success url was called',
            []
        );

        $paymentId = $this->getPaymentId();
        $fetchDest = $this->request->header('sec-fetch-dest');

        try {
            $orderId = $checkoutProcessor->processCheckout($paymentId, $fetchDest);

            $orderAccessKey = $authHelper->processUnguarded(
                function () use ($orderId, $orderContract) {
                    return $orderContract->generateAccessKey($orderId);
                }
            );

            $sessionStorageRepository->setSessionValue(
                SessionStorageRepositoryContract::LAST_ACCESSED_ORDER,
                ['orderId' => $orderId, 'accessKey' => $orderAccessKey]
            );

            return $this->response->redirectTo('confirmation');
        } catch (\Exception $e) {
            $this->notificationService->error($e->getMessage());

            return $this->checkoutCancel();
        }
    }

    /**
     * Payever redirects to this page if the payment was executed in pending status
     *
     * @param OrderService $orderService
     * @param CheckoutProcessor $checkoutProcessor
     * @param RoutesHelper $routesHelper
     * @param Twig $twig
     *
     * @return string|SymfonyResponse
     */
    public function checkoutPending(
        OrderService $orderService,
        CheckoutProcessor $checkoutProcessor,
        RoutesHelper $routesHelper,
        Twig $twig
    ) {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.pendingUrlWasCalled',
            'pending url was called',
            []
        );

        $paymentId = $this->getPaymentId();
        $fetchDest = $this->request->header('sec-fetch-dest');

        try {
            $orderId = $checkoutProcessor->processCheckout($paymentId, $fetchDest);
            $order = $orderService->findOrderById($orderId);

            return $twig->render('Payever::Checkout.Pending', [
                'data' => $order->toArray(),
                'statusUrl' => $routesHelper->getStatusURL([RoutesHelper::REQUEST_PAYMENT_ID => $paymentId]),
                'isLoanTransaction' => $routesHelper->getStatusURL([RoutesHelper::REQUEST_PAYMENT_ID => $paymentId]),
            ]);
        } catch (\Exception $e) {
            $this->notificationService->error($e->getMessage());

            return $this->checkoutCancel();
        }
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     *
     * @return SymfonyResponse
     */
    public function checkoutCancel(): SymfonyResponse
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.cancelUrlWasCalled',
            'cancel url was called',
            []
        );

        $this->notificationService->warn('Payment has been canceled');

        $paymentId = $this->getPaymentId();
        $redirectUrl = $paymentId ? $this->failureStatusHandler->getFailureUrl($paymentId) : 'checkout';

        return $this->response->redirectTo($redirectUrl);
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     *
     * @return SymfonyResponse
     */
    public function checkoutFailure(): SymfonyResponse
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.failureUrlWasCalled',
            'failure url was called',
            []
        );

        $this->notificationService->warn('Payment has been declined');

        $paymentId = $this->getPaymentId();
        $redirectUrl = $paymentId ? $this->failureStatusHandler->getFailureUrl($paymentId) : 'checkout';

        return $this->response->redirectTo($redirectUrl);
    }

    /**
     * @param RoutesHelper $routesHelper
     *
     * @return SymfonyResponse
     */
    public function checkoutStatus(RoutesHelper $routesHelper): SymfonyResponse
    {
        $data = [];
        $paymentId = $this->getPaymentId();

        try {
            $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);
            if (!$payeverPayment) {
                throw new \Exception('Unable to retrieve payever payment');
            }

            $params = [RoutesHelper::REQUEST_PAYMENT_ID => $paymentId];
            switch ($payeverPayment['status']) {
                case StatusHelper::STATUS_PAID:
                case StatusHelper::STATUS_ACCEPTED:
                    $data['redirect_url'] = $routesHelper->getSuccessURL($params);
                    break;
                case StatusHelper::STATUS_FAILED:
                    $data['redirect_url'] =  $routesHelper->getFailureURL($params);
                    break;
                case StatusHelper::STATUS_CANCELLED:
                    $data['redirect_url'] = $routesHelper->getCancelURL($params);
                    break;
            }
        } catch (\Exception $e) {
            $data['error'] = $e->getMessage();
        }

        return $this->response->json($data);
    }

    /**
     * @return string
     */
    private function getPaymentId(): string
    {
        // Get payment id for widget
        $paymentId = (string)$this->request->get(RoutesHelper::REQUEST_PAYMENT_REFERENCE);
        if ($paymentId) {
            return $paymentId;
        }

        return (string)$this->request->get(RoutesHelper::REQUEST_PAYMENT_ID);
    }

    /**
     * @return bool
     */
    private function isSantanderMethod()
    {
        $paymentId = $this->getPaymentId();

        try {
            $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ['payment_id' => $paymentId]);
            $method = $retrievePayment['result']['paymentType'];
        } catch (\Exception $e) {
            return false;
        }

        return strpos($method, self::SANTANDER_PREFIX) !== false;
    }
}
