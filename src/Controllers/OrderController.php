<?php

namespace Payever\Controllers;

use Payever\Helper\OrderItemsManager;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Class OrderController
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
     * @return SymfonyResponse
     */
    public function getOrderTotals(): SymfonyResponse
    {
        $page = $this->request->get('page');
        $itemsPerPage = $this->request->get('itemsPerPage');

        $orderTotals = $this->orderItemsManager->getOrderTotals($page, $itemsPerPage);

        return $this->response->json($orderTotals);
    }

    /**
     * @return SymfonyResponse
     */
    public function getOrderTotalItems(): SymfonyResponse
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
