<?php

namespace Payever\Controllers;

use Payever\Helper\OrderItemsManager;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OrderController extends Controller
{
    use Loggable;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var OrderItemsManager
     */
    private OrderItemsManager $orderItemsManager;

    /**
     * @param Request $request
     * @param OrderItemsManager $orderItemsManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Request $request,
        OrderItemsManager $orderItemsManager
    ) {
        parent::__construct();
        $this->request = $request;
        $this->response = pluginApp(Response::class);
        $this->orderItemsManager = $orderItemsManager;
    }

    /**
     * @return array|\Symfony\Component\HttpFoundation\Response
     */
    public function getOrderTotals(): array|\Symfony\Component\HttpFoundation\Response
    {
        $page = $this->request->get('page');
        $itemsPerPage = $this->request->get('itemsPerPage');

        $orderTotals = $this->orderItemsManager->getOrderTotals($page, $itemsPerPage);

        return $this->response->json($orderTotals);
    }

    /**
     * @return array|\Symfony\Component\HttpFoundation\Response
     */
    public function getOrderTotalItems(): array|\Symfony\Component\HttpFoundation\Response
    {
        $orderId = $this->request->get('orderId');
        $action = $this->request->get('action');
        if (!$orderId || !$action) {
            return $this->response->json();
        }

        $orderTotalItems = $this->orderItemsManager->getOrderTotalItems($orderId, $action);

        return $this->response->json($orderTotalItems);
    }
}
