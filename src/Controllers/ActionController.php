<?php

namespace Payever\Controllers;

use Exception;
use Payever\Contracts\ActionHistoryRepositoryContract;
use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Helper\OrderItemsManager;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Payever\Services\Payment\PaymentActionService;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
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
     * @var PaymentMethodRepositoryContract
     */
    private PaymentMethodRepositoryContract $paymentMethodRepository;

    /**
     * @var OrderTotalRepositoryContract
     */
    private OrderTotalRepositoryContract $orderTotalRepository;

    /**
     * @var OrderTotalItemRepositoryContract
     */
    private OrderTotalItemRepositoryContract $orderTotalItemRepository;

    /**
     * @var PayeverHelper
     */
    private PayeverHelper $paymentHelper;

    /**
     * @var PaymentRepositoryContract
     */
    private PaymentRepositoryContract $paymentContract;

    /**
     * @var PayeverService
     */
    private PayeverService $paymentService;

    /**
     * @var OrderItemsManager
     */
    private OrderItemsManager $orderItemsManager;

    /**
     * @var ActionHistoryRepositoryContract
     */
    private ActionHistoryRepositoryContract $actionHistoryRepository;

    /**
     * @var OrderShippingPackageRepositoryContract
     */
    private OrderShippingPackageRepositoryContract $orderShippingPackageRepository;

    /**
     * @var ParcelServicePresetRepositoryContract
     */
    private ParcelServicePresetRepositoryContract $parcelServicePresetRepository;

    /**
     * @var PaymentActionService
     */
    private PaymentActionService $actionService;

    /**
     * @param Request $request
     * @param OrderRepositoryContract $orderRepository
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param OrderTotalRepositoryContract $orderTotalRepository
     * @param OrderTotalItemRepositoryContract $orderTotalItemRepository
     * @param PayeverHelper $paymentHelper
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverService $paymentService
     * @param OrderItemsManager $orderItemsManager
     * @param ActionHistoryRepositoryContract $actionHistoryRepository
     * @param OrderShippingPackageRepositoryContract $orderShippingPackageRepository
     * @param ParcelServicePresetRepositoryContract $parcelServicePresetRepository
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Request $request,
        OrderRepositoryContract $orderRepository,
        PaymentMethodRepositoryContract $paymentMethodRepository,
        OrderTotalRepositoryContract $orderTotalRepository,
        OrderTotalItemRepositoryContract $orderTotalItemRepository,
        PayeverHelper $paymentHelper,
        PaymentRepositoryContract $paymentContract,
        PayeverService $paymentService,
        OrderItemsManager $orderItemsManager,
        ActionHistoryRepositoryContract $actionHistoryRepository,
        OrderShippingPackageRepositoryContract $orderShippingPackageRepository,
        ParcelServicePresetRepositoryContract $parcelServicePresetRepository,
        PaymentActionService $actionService
    ) {
        parent::__construct();
        $this->request = $request;
        $this->response = pluginApp(Response::class);
        $this->orderRepository = $orderRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderTotalRepository = $orderTotalRepository;
        $this->orderTotalItemRepository = $orderTotalItemRepository;
        $this->paymentHelper = $paymentHelper;
        $this->paymentContract = $paymentContract;
        $this->paymentService = $paymentService;
        $this->orderItemsManager = $orderItemsManager;
        $this->actionHistoryRepository = $actionHistoryRepository;
        $this->orderShippingPackageRepository = $orderShippingPackageRepository;
        $this->parcelServicePresetRepository = $parcelServicePresetRepository;
        $this->actionService = $actionService;
    }

    /**
     * @throws Exception
     */
    public function actionsHandler()
    {
        $this->getLogger(__METHOD__)
            ->setReferenceType('payeverLog')
            ->debug('Payever::debug.actionsHandler', $this->request->all());

        $orderId = $this->request->get('order_id');
        $action = $this->request->get('action');
        $amount = round($this->request->get('amount'), 2);
        $items = $this->request->get('items');

        $order = $this->orderRepository->findOrderById($orderId);

        $payments = $this->paymentService->getPayeverPaymentsByOrderId($orderId);

        $this->getLogger(__METHOD__ . ' $payments')
            ->setReferenceType('payeverLog')
            ->debug('Payever::debug.actionsHandler', $payments);

        foreach ($payments as $payment) {
            $transactionId = $this->paymentService->getPaymentTransactionId($payment);

            $this->getLogger(__METHOD__ . ' [START]')
                ->setReferenceType('payeverLog')
                ->debug('Payever::debug.actionsHandler', [
                    'action' => $action,
                    'transactionId' => $transactionId,
                    'order' => $order
                ]);

            $this->paymentHelper->lockAndBlock(PayeverHelper::ACTION_PREFIX . $transactionId);

            switch ($action) {
                case self::ACTION_SHIPPING:
                    $this->actionService->shipGoodsTransaction($order, $transactionId, $items);
                    break;
                case self::ACTION_REFUND:
                    $this->actionService->refundItemTransaction($order, $transactionId, $items);
                    break;
                case self::ACTION_CANCEL:
                    $this->actionService->cancelItemTransaction($order, $transactionId, $items);
                    break;
                case self::ACTION_SHIPPING_AMOUNT:
                    $this->actionService->shippingTransaction($order, $transactionId, $amount);
                    break;
                case self::ACTION_REFUND_AMOUNT:
                    $this->actionService->refundTransaction($order, $transactionId, $amount);
                    break;
                case self::ACTION_CANCEL_AMOUNT:
                    $this->actionService->cancelTransaction($order, $transactionId, $amount);
                    break;
            }

            $this->paymentHelper->unlock(PayeverHelper::ACTION_PREFIX . $transactionId);
        }

        return $this->response->json('Action <' . $action . '> performed');
    }
}
