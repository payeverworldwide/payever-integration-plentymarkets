<?php //strict
namespace Payever\Controllers;

use IO\Services\NotificationService;
use IO\Services\OrderService;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Payever\Services\PayeverService;
use Payever\Services\Payment\Notification\NotificationRequestProcessor;
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

/**
 * Class PaymentController
 * @package Payever\Controllers
 */
class PaymentController extends Controller
{
    use Loggable;

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
     * @var payeverHelper
     */
    private $payeverHelper;

    /**
     * @var payeverService
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
     * @param Request $request
     * @param Response $response
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param PayeverService $payeverService
     * @param BasketRepositoryContract $basketContract
     * @param OrderRepositoryContract $orderContract
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param SessionStorageRepositoryContract $sessionStorageRepository
     * @param PayeverSdkService $sdkService
     * @param NotificationRequestProcessor $notificationRequestProcessor
     */
    public function __construct(
        Request $request,
        Response $response,
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
        NotificationRequestProcessor $notificationRequestProcessor
    ) {
        $this->request = $request;
        $this->response = $response;
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
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     */
    public function checkoutCancel()
    {
        $this->notificationService->warn("Payment has been canceled");

        return $this->response->redirectTo('checkout');
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     */
    public function checkoutFailure()
    {
        $this->notificationService->warn("Payment has been declined");

        $paymentId = $this->request->get('payment_id');
        $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);

        if ($payeverPayment && is_numeric($payeverPayment["reference"])) {
            $this->payeverService->updateOrderStatus($payeverPayment["reference"], $payeverPayment["status"]);
        }

        return $this->response->redirectTo('checkout');
    }

    private function cancelPayment($referenceId)
    {
        if (is_numeric($referenceId)) {
            $this->payeverService->updateOrderStatus($referenceId, PayeverHelper::STATUS_CANCELLED);
        }

        return $this->checkoutCancel();
    }

    /**
     * Payever redirects to this page if the payment was executed correctly
     *
     * @param OrderService $orderService
     *
     * @return mixed
     */
    public function checkoutSuccess(OrderService $orderService)
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.successUrlWasCalled', "success url was called");
        $paymentId = $this->request->get('payment_id');

        /**
         * Randomize waiting time before lock to assure no double-lock cases
         */
        $wait = rand(0, 3);
        sleep($wait);

        if (!$this->payeverHelper->isLocked($paymentId)) {
            $this->payeverHelper->lockAndBlock($paymentId);
            $this->sessionStorage->getPlugin()->setValue('payever_payment_id', $paymentId);

            $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);

            if (!$payeverPayment) {
                $this->notificationService->error("Couldn't retrieve payever payment");
                return $this->checkoutCancel();
            }

            // If reference equals order id
            if (is_numeric($payeverPayment['reference'])) {
                $orderId = $payeverPayment['reference'];
                $this->payeverService->originExecutePayment($payeverPayment);
                $this->payeverHelper->unlock($paymentId);
                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.successfulCreatingPlentyPayment',
                    ['payeverPayment' => $payeverPayment]
                );
            } else {
                if (!$this->payeverHelper->isSuccessfulPaymentStatus($payeverPayment['status'])) {
                    return $this->cancelPayment($payeverPayment["reference"]);
                }

                $update = $this->payeverService->updatePlentyPayment($paymentId, $payeverPayment["status"]);
                $this->getLogger(__METHOD__)->debug('Payever::debug.successfulUpdatingPlentyPayment', $update);

                try {
                    if (!$update) {
                        $orderData = $this->placeOrder($orderService);
                        $orderId = $orderData->order->id;
                    }
                } catch (\Exception $exception) {
                    $this->notificationService->error($exception->getMessage());
                    $this->getLogger(__METHOD__)->critical('Payever::placingOrderError', $exception);

                    return $this->checkoutCancel();
                } finally {
                    $this->payeverHelper->unlock($paymentId);
                }
            }
            $orderAccessKey = $this->orderContract->generateAccessKey($orderId);
            $this->sessionStorageRepository->setSessionValue(
                SessionStorageRepositoryContract::LAST_ACCESSED_ORDER,
                ['orderId' => $orderId, 'accessKey' => $orderAccessKey]
            );
        }

        $this->payeverHelper->waitForUnlock($paymentId);

        $this->getLogger(__METHOD__)->debug('Payever::debug.ConfirmationUrlCalling', "Confirmation url was called");

        return $this->response->redirectTo('confirmation');
    }

    private function placeOrder(OrderService $orderService, $executePayment = true)
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.placeOrderCalling',
            "PlaceOrder was called, with executePayment = $executePayment");
        $orderData = $orderService->placeOrder();

        if ($executePayment) {
            $orderId = $orderData->order->id;
            $mopId = $orderData->order->methodOfPaymentId;

            $paymentResult = $orderService->executePayment($orderId, $mopId);

            if ($paymentResult["type"] === "error") {
                // send errors
                $this->notificationService->error($paymentResult["value"]);
            }
        }

        return $orderData;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkoutNotice()
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.noticeUrlWasCalled');
        return $this->response->json($this->notificationRequestProcessor->processNotification());
    }

    /**
     * @param Twig $twig
     * @param OrderService $orderService
     * @param BasketRepositoryContract $basketRepo
     * @return mixed
     */
    public function checkoutIframe(Twig $twig, OrderService $orderService, BasketRepositoryContract $basketRepo)
    {
        if ($this->sessionStorage->getPlugin()->getValue("payever_order_before_payment")) {
            $iframeUrl = $this->processCheckout($orderService, $basketRepo, false);
        } else {
            $iframeUrl = $this->sessionStorage->getPlugin()->getValue("payever_iframe_url");
        }

        return $twig->render('Payever::checkout.iframe', ["iframe_url" => $iframeUrl]);
    }

    /**
     * @param OrderService $orderService
     * @param BasketRepositoryContract $basketRepo
     * @param bool $redirect
     * @return mixed
     */
    public function processCheckout(OrderService $orderService, BasketRepositoryContract $basketRepo, $redirect = true)
    {
        $this->sessionStorage->getPlugin()->unsetKey('payever_payment_id');
        $method = $this->request->get('method');
        $basket = $basketRepo->load();

        $orderData = $this->placeOrder($orderService, false);
        $createPaymentResponse = $this->payeverService->processCreatePaymentRequest(
            $basket,
            $method,
            $orderData->order->id
        );

        if ($createPaymentResponse['error']) {
            $this->notificationService->warn("Creating payment has been declined");
            return $this->response->redirectTo('checkout');
        }

        if ($redirect) {
            return $this->response->redirectTo($createPaymentResponse['redirect_url']);
        }

        return $createPaymentResponse['redirect_url'];
    }
}
