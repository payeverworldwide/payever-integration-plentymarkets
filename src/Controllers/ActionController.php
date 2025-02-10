<?php

namespace Payever\Controllers;

use Exception;
use Payever\Helper\PayeverHelper;
use Payever\Helper\PaymentActionManager;
use Payever\Models\PaymentAction;
use Payever\Services\PayeverService;
use Payever\Services\Payment\PaymentActionService;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;

/**
 * Class ActionController
 */
class ActionController extends Controller
{
    use Loggable;

    /**
     * Actions from frontend
     */
    const ACTION_SHIPPING = "shipping";
    const ACTION_REFUND = "refund";
    const ACTION_CANCEL = "cancel";
    const ACTION_SHIPPING_AMOUNT = "shipping_amount";
    const ACTION_REFUND_AMOUNT = "refund_amount";
    const ACTION_CANCEL_AMOUNT = "cancel_amount";
    const ACTION_CLAIM = "claim";
    const ACTION_CLAIM_UPLOAD = "claim_upload";

    const LOGGER_CODE = 'Payever::debug.actionsHandler';

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var OrderRepositoryContract
     */
    private OrderRepositoryContract $orderRepository;

    /**
     * @var PayeverHelper
     */
    private PayeverHelper $paymentHelper;

    /**
     * @var PaymentActionManager
     */
    private PaymentActionManager $paymentActionManager;

    /**
     * @var PayeverService
     */
    private PayeverService $paymentService;

    /**
     * @var PaymentActionService
     */
    private PaymentActionService $actionService;

    /**
     * @param Request $request
     * @param OrderRepositoryContract $orderRepository
     * @param PayeverHelper $paymentHelper
     * @param PaymentActionManager $paymentActionManager
     * @param PayeverService $paymentService
     * @param PaymentActionService $actionService
     */
    public function __construct(
        Request $request,
        OrderRepositoryContract $orderRepository,
        PayeverHelper $paymentHelper,
        PaymentActionManager $paymentActionManager,
        PayeverService $paymentService,
        PaymentActionService $actionService
    ) {
        parent::__construct();
        $this->request = $request;
        $this->response = pluginApp(Response::class);
        $this->orderRepository = $orderRepository;
        $this->paymentHelper = $paymentHelper;
        $this->paymentActionManager = $paymentActionManager;
        $this->paymentService = $paymentService;
        $this->actionService = $actionService;
    }

    /**
     * @throws Exception
     */
    public function actionsHandler()
    {
        $this->getLogger(__METHOD__)
            ->setReferenceType('payeverLog')
            ->debug(self::LOGGER_CODE, $this->request->all());

        $orderId = $this->request->get('order_id');
        $action = $this->request->get('action');
        $amount = round($this->request->get('amount'), 2);
        $items = $this->request->get('items');

        $order = $this->orderRepository->findOrderById($orderId);

        $payments = $this->paymentService->getPayeverPaymentsByOrderId($orderId);

        $this->getLogger(__METHOD__ . ' $payments')
            ->setReferenceType('payeverLog')
            ->debug(self::LOGGER_CODE, $payments);

        foreach ($payments as $payment) {
            $transactionId = $this->paymentService->getPaymentTransactionId($payment);

            $this->getLogger(__METHOD__ . ' [START]')
                ->setReferenceType('payeverLog')
                ->debug(self::LOGGER_CODE, [
                    'action' => $action,
                    'transactionId' => $transactionId,
                    'order' => $order
                ]);

            $this->paymentHelper->lockAndBlock(PayeverHelper::ACTION_PREFIX . $transactionId);

            $identifier = $this->paymentActionManager->generateIdentifier();
            $this->paymentActionManager->addAction(
                $order->id,
                $identifier,
                $action,
                PaymentAction::SOURCE_EXTERNAL,
                $amount
            );

            switch ($action) {
                case self::ACTION_SHIPPING:
                    $this->actionService->shipGoodsTransaction($order, $transactionId, $items, $identifier);
                    break;
                case self::ACTION_REFUND:
                    $this->actionService->refundItemTransaction($order, $transactionId, $items, $identifier);
                    break;
                case self::ACTION_CANCEL:
                    $this->actionService->cancelItemTransaction($order, $transactionId, $items, $identifier);
                    break;
                case self::ACTION_SHIPPING_AMOUNT:
                    $this->actionService->shippingTransaction($order, $transactionId, $amount, $identifier);
                    break;
                case self::ACTION_REFUND_AMOUNT:
                    $this->actionService->refundTransaction($order, $transactionId, $amount, $identifier);
                    break;
                case self::ACTION_CANCEL_AMOUNT:
                    $this->actionService->cancelTransaction($order, $transactionId, $amount, $identifier);
                    break;
                case self::ACTION_CLAIM:
                    $isDisputed = (bool) $this->request->get('is_disputed');
                    $this->actionService->claimTransaction($order, $transactionId, $isDisputed);
                    break;
                case self::ACTION_CLAIM_UPLOAD:
                    $files = $this->request->get('claim_upload_files');
                    $this->actionService->claimUploadTransaction($order, $transactionId, $files);
                    break;
                default:
                    $this->getLogger(__METHOD__ . ' [SKIP]')
                        ->setReferenceType('payeverLog')
                        ->debug(self::LOGGER_CODE, [
                            'action' => $action,
                        ]);
            }

            $this->paymentHelper->unlock(PayeverHelper::ACTION_PREFIX . $transactionId);
        }

        return $this->response->json(['status' => 'success', 'message' => 'Action <' . $action . '> performed']);
    }
}
