<?php //strict
namespace Payever\Controllers;

use IO\Services\NotificationService;
use IO\Services\OrderService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
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

        return $this->response->redirectTo('checkout');
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

            $payment = $this->payeverService->handlePayeverPayment($paymentId);

            if (!$payment) {
                $this->notificationService->error("Couldn't retrieve payever payment");
                return $this->checkoutFailure();
            }

            if (!$this->payeverHelper->isSuccessfulPaymentStatus($payment['status'])) {
                return $this->checkoutCancel();
            }

            $update = $this->payeverHelper->updatePlentyPayment($paymentId, $payment["status"]);
            $this->getLogger(__METHOD__)->debug('Payever::debug.successfulUpdatingPlentyPayment', $update);

            try {
                if (!$update) {
                    $this->placeOrder($orderService);
                }
            } catch (\Exception $exception) {
                $this->notificationService->error($exception->getMessage());
                $this->getLogger(__METHOD__)->critical('Payever::placingOrderError', $exception);

                return $this->checkoutFailure();
            } finally {
                $this->payeverHelper->unlock($paymentId);
            }
        }

        $this->payeverHelper->waitForUnlock($paymentId);

        $this->getLogger(__METHOD__)->debug('Payever::debug.ConfirmationUrlCalling', "Confirmation url was called");

        return $this->response->redirectTo('confirmation');
    }

    private function placeOrder(OrderService $orderService)
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.placeOrderCalling', "PlaceOrder was called");

        $orderData = $orderService->placeOrder();
        $orderId = $orderData->order->id;
        $mopId = $orderData->order->methodOfPaymentId;

        $paymentResult = $orderService->executePayment($orderId, $mopId);

        if ($paymentResult["type"] === "error") {
            // send errors
            $this->notificationService->error($paymentResult["value"]);
        }
    }

    public function checkoutNotice()
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.noticeUrlWasCalled', "notice url was called");
        $paymentId = $this->request->get('payment_id');

        $payment = $this->payeverService->handlePayeverPayment($paymentId);
        $this->getLogger(__METHOD__)->debug('Payever::debug.retrievingPaymentForNotifications', $payment);
        $update = $this->payeverHelper->updatePlentyPayment($paymentId, $payment["status"]);
        $this->getLogger(__METHOD__)->debug('Payever::debug.updatingPlentyPaymentForNotifications', $update);
    }

    public function checkoutIframe(Twig $twig):string
    {
        $iframeUrl = $this->sessionStorage->getPlugin()->getValue("payever_redirect_url");
        return $twig->render('Payever::checkout.iframe', ["iframe_url" => $iframeUrl]);
    }
}
