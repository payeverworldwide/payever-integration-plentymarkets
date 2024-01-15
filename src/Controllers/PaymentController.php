<?php

namespace Payever\Controllers;

use Exception;
use IO\Services\NotificationService;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Payever\Services\PayeverService;
use Payever\Services\Payment\Notification\NotificationRequestProcessor;
use Payever\Traits\Logger;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Templates\Twig;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PaymentController extends Controller
{
    use Logger;

    /**
     * @var AuthHelper
     */
    private $authHelper;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var PayeverService
     */
    private $payeverService;

    /**
     * @var BasketRepositoryContract
     */
    private $basketContract;

    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * @var SessionStorageRepositoryContract
     */
    private $sessionStorageRepository;

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var PayeverSdkService
     */
    private $sdkService;

    /**
     * @var OrderRepositoryContract
     */
    private $orderContract;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var NotificationRequestProcessor
     */
    private $notificationRequestProcessor;

    /**
     * @var PendingPaymentRepositoryContract
     */
    private $pendingPaymentRepository;

    /**
     * @param AuthHelper $authHelper
     * @param Request $request
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param PayeverService $payeverService
     * @param BasketRepositoryContract $basketContract
     * @param OrderRepositoryContract $orderContract
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param SessionStorageRepositoryContract $sessionStorageRepository
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PayeverSdkService $sdkService
     * @param NotificationService $notificationService
     * @param NotificationRequestProcessor $notificationRequestProcessor
     * @param PendingPaymentRepositoryContract $pendingPaymentRepository
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        AuthHelper $authHelper,
        Request $request,
        ConfigRepository $config,
        PayeverHelper $payeverHelper,
        PayeverService $payeverService,
        BasketRepositoryContract $basketContract,
        OrderRepositoryContract $orderContract,
        FrontendSessionStorageFactoryContract $sessionStorage,
        SessionStorageRepositoryContract $sessionStorageRepository,
        PaymentMethodRepositoryContract $paymentMethodRepository,
        PayeverSdkService $sdkService,
        NotificationService $notificationService,
        NotificationRequestProcessor $notificationRequestProcessor,
        PendingPaymentRepositoryContract $pendingPaymentRepository
    ) {
        $this->authHelper = $authHelper;
        $this->request = $request;
        $this->response = pluginApp(Response::class);
        $this->config = $config;
        $this->payeverHelper = $payeverHelper;
        $this->payeverService = $payeverService;
        $this->basketContract = $basketContract;
        $this->orderContract = $orderContract;
        $this->sessionStorage = $sessionStorage;
        $this->sessionStorageRepository = $sessionStorageRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->sdkService = $sdkService;
        $this->notificationService = $notificationService;
        $this->notificationRequestProcessor = $notificationRequestProcessor;
        $this->pendingPaymentRepository = $pendingPaymentRepository;
    }

    /**
     * @return array|SymfonyResponse
     */
    public function checkoutCancelDecorator()
    {
        return $this->authHelper->processUnguarded([$this, 'checkoutCancel']);
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     *
     * @return array|SymfonyResponse
     */
    public function checkoutCancel()
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.cancelUrlWasCalled',
            'cancel url was called',
            []
        );

        $this->notificationService->warn('Payment has been canceled');

        return $this->response->redirectTo('checkout');
    }

    /**
     * @return array|SymfonyResponse
     */
    public function checkoutFailureDecorator()
    {
        return $this->authHelper->processUnguarded([$this, 'checkoutFailure']);
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     *
     * @return array|SymfonyResponse
     */
    public function checkoutFailure()
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.failureUrlWasCalled',
            'failure url was called',
            []
        );

        $this->notificationService->warn('Payment has been declined');

        $paymentId = (string) $this->request->get('payment_id');
        $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);

        if ($payeverPayment && is_numeric($payeverPayment['reference'])) {
            $this->payeverService->updateOrderStatus($payeverPayment['reference'], $payeverPayment['status']);
        }

        return $this->response->redirectTo('checkout');
    }

    /**
     * @param mixed $referenceId
     * @return SymfonyResponse
     */
    private function cancelPayment($referenceId)
    {
        if (is_numeric($referenceId)) {
            $this->payeverService->updateOrderStatus($referenceId, PayeverHelper::STATUS_CANCELLED);
        }

        return $this->checkoutCancel();
    }

    /**
     * @return array|SymfonyResponse
     */
    public function checkoutSuccessDecorator()
    {
        return $this->authHelper->processUnguarded([$this, 'checkoutSuccess']);
    }

    /**
     * Payever redirects to this page if the payment was executed correctly
     *
     * @return array|SymfonyResponse
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function checkoutSuccess()
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.successUrlWasCalled',
            'success url was called',
            []
        );

        $paymentId = (string) $this->request->get('payment_id');
        $fetchDest = $this->request->header('sec-fetch-dest');
        $this->payeverHelper->acquireLock($paymentId, $fetchDest);
        $this->sessionStorage->getPlugin()->setValue('payever_payment_id', $paymentId);
        $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);
        if (!$payeverPayment) {
            $this->notificationService->error('Unable to retrieve payever payment');
            $this->payeverHelper->unlock($paymentId);

            return $this->checkoutCancel();
        }

        // If reference equals order id
        $orderId = $reference = $payeverPayment['reference'] ?? null;
        if (is_numeric($reference)) {
            $this->payeverService->originExecutePayment($payeverPayment);
            $this->payeverHelper->unlock($paymentId);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.successfulCreatingPlentyPayment',
                'Successful Creating Plenty Payment',
                ['payeverPayment' => $payeverPayment]
            );
        } else {
            if (!$this->payeverHelper->isSuccessfulPaymentStatus($payeverPayment['status'])) {
                $this->payeverHelper->unlock($paymentId);

                return $this->cancelPayment($reference);
            }

            $update = $this->payeverService->updatePlentyPayment($paymentId, $payeverPayment['status']);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.successfulUpdatingPlentyPayment',
                'Successful Updating Plenty Payment',
                [$update]
            );

            try {
                if (!$update) {
                    $this->payeverService->prepareBasket($reference);
                    $orderData = $this->payeverService->placeOrder();
                    $orderId = $orderData->order->id;
                } elseif (!empty($update->order) && is_object($update->order) && !empty($update->order->orderId)) {
                    $orderId = $update->order->orderId;
                }
            } catch (Exception $exception) {
                $this->notificationService->error($exception->getMessage());

                $this->log(
                    'critical',
                    __METHOD__,
                    'Payever::debug::placingOrderError',
                    'Exception: ' . $exception,
                    [$exception]
                );

                return $this->checkoutCancel();
            } finally {
                $this->payeverHelper->unlock($paymentId);
            }
        }

        $orderAccessKey = $this->orderContract->generateAccessKey((int) $orderId);
        $this->sessionStorageRepository->setSessionValue(
            SessionStorageRepositoryContract::LAST_ACCESSED_ORDER,
            ['orderId' => $orderId, 'accessKey' => $orderAccessKey]
        );

        if (!empty($reference)) {
            $pendingPayment = $this->pendingPaymentRepository->getByOrderId($reference);
            if ($pendingPayment) {
                $this->pendingPaymentRepository->delete($pendingPayment);
                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.checkoutDebug',
                    sprintf('Pending payment for order %s is removed', $reference),
                    [$reference]
                );
            }
        }

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.checkoutDebug',
            sprintf('Checkout debug: %s', $reference),
            [
                'sec-fetch-dest' => $fetchDest,
                'reference' => $reference,
                'orderId' => $orderId,
                'accessKey' => $orderAccessKey,
                'pendingPaymentFound' => isset($pendingPayment) && !empty($pendingPayment),
            ]
        );

        $this->payeverHelper->unlock($paymentId);

        $this->getLogger(__METHOD__)
            ->setReferenceType('payeverLog')
            ->debug('Payever::debug.ConfirmationUrlCalling', 'Confirmation url was called');

        return $this->response->redirectTo('confirmation');
    }

    /**
     * @return array|SymfonyResponse
     */
    public function checkoutFinishDecorator()
    {
        return $this->authHelper->processUnguarded([$this, 'checkoutFinish']);
    }

    /**
     * Payever redirects to this page if the payment was executed correctly
     *
     * @return array|SymfonyResponse
     */
    public function checkoutFinish()
    {
        $orderId = $this->request->get('reference');
        $actualToken = $this->request->get('token');
        $expectedToken = hash_hmac(
            'sha256',
            $this->config->get('Payever.clientId') . $orderId,
            (string) $this->config->get('Payever.clientSecret')
        );
        $paymentId = '';
        $messageCode = 'Payever::debug.tokensAtCheckoutFinishNotMatched';
        if ($actualToken === $expectedToken) {
            $messageCode = 'Payever::debug.tokensAtCheckoutFinishMatched';
            $pendingPayment = $this->pendingPaymentRepository->getByOrderId($orderId);
            if ($pendingPayment) {
                $paymentId = $pendingPayment->payeverPaymentId;
            }
        }

        $this->log(
            'debug',
            __METHOD__,
            $messageCode,
            'Checkout finish',
            [
                'actualToken' => $actualToken,
                'expectedToken' => $expectedToken
            ]
        );

        $successUrl = $this->payeverHelper->buildSuccessURL($paymentId);

        return $this->response->redirectTo($successUrl);
    }

    /**
     * @return SymfonyResponse
     */
    public function checkoutNoticeDecorator()
    {
        return $this->authHelper->processUnguarded([$this, 'checkoutNotice']);
    }

    /**
     * @return SymfonyResponse
     */
    public function checkoutNotice()
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.noticeUrlWasCalled',
            'Notice Url Was Called',
            []
        );

        return $this->response->json($this->notificationRequestProcessor->processNotification());
    }

    /**
     * @param Twig $twig
     * @return string|SymfonyResponse
     */
    public function checkoutIframe(Twig $twig)
    {
        try {
            if ($this->sessionStorage->getPlugin()->getValue('payever_order_before_payment')) {
                $method = (string) $this->request->get('method');
                $iframeUrl = $this->payeverService->processOrderPayment($method);
            } else {
                $iframeUrl = $this->sessionStorage->getPlugin()->getValue('payever_iframe_url');
            }
        } catch (Exception $exception) {
            $this->notificationService->warn($exception->getMessage());

            return $this->response->redirectTo('checkout');
        }

        return $twig->render('Payever::checkout.iframe', ['iframe_url' => $iframeUrl]);
    }
}
