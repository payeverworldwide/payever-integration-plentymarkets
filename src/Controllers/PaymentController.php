<?php //strict
namespace Payever\Controllers;

use IO\Services\NotificationService;
use IO\Services\OrderService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Payever\Services\PayeverService;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Log\Loggable;

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

    /** @var NotificationService */
    private $notificationService;

    /**
     * PaymentController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param PayeverService $payeverService
     * @param BasketRepositoryContract $basketContract
     * @param OrderRepositoryContract $orderContract
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param PayeverSdkService $sdkService
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
        PaymentMethodRepositoryContract $paymentMethodRepository,
        PayeverSdkService $sdkService,
        NotificationService $notificationService
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->payeverHelper = $payeverHelper;
        $this->payeverService = $payeverService;
        $this->basketContract = $basketContract;
        $this->orderContract = $orderContract;
        $this->sessionStorage = $sessionStorage;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->sdkService = $sdkService;
        $this->notificationService = $notificationService;
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
            if (is_numeric($payeverPayment["reference"])) {
                $this->payeverService->originExecutePayment($payeverPayment);
                $this->payeverHelper->unlock($paymentId);
            } else {
                if (!$this->payeverHelper->isSuccessfulPaymentStatus($payeverPayment['status'])) {
                    return $this->cancelPayment($payeverPayment["reference"]);
                }

                $update = $this->payeverService->updatePlentyPayment($paymentId, $payeverPayment["status"]);
                $this->getLogger(__METHOD__)->debug('Payever::debug.successfulUpdatingPlentyPayment', $update);

                try {
                    if (!$update) {
                        $this->placeOrder($orderService);
                    }
                } catch (\Exception $exception) {
                    $this->notificationService->error($exception->getMessage());
                    $this->getLogger(__METHOD__)->critical('Payever::placingOrderError', $exception);

                    return $this->checkoutCancel();
                } finally {
                    $this->payeverHelper->unlock($paymentId);
                }
            }
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

    public function checkoutNotice()
    {
        try {
            $requestContent = json_decode($this->request->getContent(), true);
            $notificationTime = array_key_exists('created_at', $requestContent) ? date("Y-m-d H:i:s",
                strtotime($requestContent['created_at'])) : false;

            $this->getLogger(__METHOD__)->debug('Payever::debug.noticeUrlWasCalled', $requestContent);
            $paymentId = $this->request->get('payment_id');

            $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);
            $this->getLogger(__METHOD__)->debug('Payever::debug.retrievingPaymentForNotifications', $payeverPayment);
            if (is_numeric($payeverPayment["reference"])) {
                $update = $this->payeverService->createAndUpdatePlentyPayment($payeverPayment);
            } else {
                $update = $this->payeverService->updatePlentyPayment($paymentId, $payeverPayment["status"],
                    $notificationTime);
            }

            $this->getLogger(__METHOD__)->debug('Payever::debug.updatingPlentyPaymentForNotifications', $update);

            die(json_encode(['result' => 'success', 'message' => 'Order was updated']));
        } catch (\Exception $exception) {
            $this->getLogger(__METHOD__)->error('Payever::notification_error', $exception);
            die(json_encode(['result' => 'error', 'message' => $exception->getMessage()]));
        }
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
        $createPaymentReponse = $this->payeverService->processCreatePaymentRequest($basket, $method,
            $orderData->order->id);

        if ($createPaymentReponse['error']) {
            $this->notificationService->warn("Creating payment has been declined");
            return $this->response->redirectTo('checkout');
        }

        if ($redirect) {
            return $this->response->redirectTo($createPaymentReponse['redirect_url']);
        }

        return $createPaymentReponse['redirect_url'];
    }
}
