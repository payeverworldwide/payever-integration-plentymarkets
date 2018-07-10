<?php //strict
namespace Payever\Controllers;

use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Payever\Services\PayeverService;
use Payever\Helper\PayeverHelper;
use Payever\Api\PayeverApi;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentController
 * @package Payever\Controllers
 */
class PaymentController extends Controller
{
    use Loggable;

    const LOCK_TIME_SLEEP = 5; //sec
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
     * @var payeverApi
     */
    private $payeverApi;
    /**
     * @var OrderRepositoryContract
     */
    private $orderContract;

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
     * @param PayeverApi $payeverApi
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
        PayeverApi $payeverApi
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->payeverHelper = $payeverHelper;
        $this->payeverService = $payeverService;
        $this->basketContract = $basketContract;
        $this->orderContract = $orderContract;
        $this->sessionStorage = $sessionStorage;
        $this->payeverApi = $payeverApi;
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     */
    public function checkoutCancel()
    {
        return $this->response->redirectTo('checkout');
    }

    /**
     * Payever redirects to this page if the payment could not be executed or other problems occurred
     */
    public function checkoutFailure()
    {
        return $this->response->redirectTo('checkout');
    }

    /**
     * Payever redirects to this page if the payment was executed correctly
     */
    public function checkoutSuccess()
    {
        $update = false;
        $paymentId = $this->request->get('payment_id');
        $this->waitIfSessionIsset($paymentId);

        $payment = $this->payeverService->handlePayeverPayment($paymentId);
        if ($payment) {
            $this->sessionStorage->getPlugin()->setValue('payever_payment_id', $paymentId);
            $update = $this->payeverHelper->updatePlentyPayment($paymentId, $payment->status);
            $this->getLogger(__METHOD__)->debug('Payever::debug.successfulUpdatingPlentyPayment', $update);
        }

        if ($update) {
            return $this->response->redirectTo('confirmation');
        } else {
            if ($payment->status == 'STATUS_PAID'
                || $payment->status == 'STATUS_ACCEPTED'
                || $payment->status == 'STATUS_IN_PROCESS'
            ) {
                return $this->response->redirectTo('place-order');
            } else {
                return $this->checkoutCancel();
            }
        }
    }

    public function checkoutNotice()
    {
        $paymentId = $this->request->get('payment_id');

        $payment = $this->payeverService->handlePayeverPayment($paymentId);
        $this->getLogger(__METHOD__)->debug('Payever::debug.retrievingPaymentForNotifications', $payment);
        $update = $this->payeverHelper->updatePlentyPayment($paymentId, $payment->status);
        $this->getLogger(__METHOD__)->debug('Payever::debug.updatingPlentyPaymentForNotifications', $update);
    }

    public function checkoutIframe(Twig $twig):string
    {
        $iframeUrl = $this->sessionStorage->getPlugin()->getValue("payever_redirect_url");
        return $twig->render('Payever::checkout.iframe', ["iframe_url" => $iframeUrl]);
    }

    public function waitIfSessionIsset($paymentId)
    {
        if ($this->sessionStorage->getPlugin()->getValue('payever_payment_id') == $paymentId) {
            sleep(self::LOCK_TIME_SLEEP);
        }
    }
}
