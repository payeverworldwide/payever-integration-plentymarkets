<?php // strict

namespace Payever\Services;

use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Payever\Helper\PayeverHelper;

/**
 * Class PayeverService
 * @package Payever\Services
 */
class PayeverService
{
    use Loggable;

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepository;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepo;

    /**
     * @var PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var AddressRepositoryContract
     */
    private $addressRepo;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var string
     */
    private $returnType = '';

    /**
     * @var ContactRepositoryContract
     */
    private $contactRepository;

    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * @var PayeverSdkService
     */
    private $sdkService;

    /**
     * PayeverService constructor.
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentRepositoryContract $paymentRepository
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
     * @param OrderRepositoryContract $orderRepository
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param AddressRepositoryContract $addressRepo
     * @param ContactRepositoryContract $contactRepository
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param PayeverSdkService $sdkService
     */
    public function __construct(
        PaymentMethodRepositoryContract $paymentMethodRepository,
        PaymentRepositoryContract $paymentRepository,
        PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo,
        OrderRepositoryContract $orderRepository,
        ConfigRepository $config,
        PayeverHelper $payeverHelper,
        AddressRepositoryContract $addressRepo,
        ContactRepositoryContract $contactRepository,
        FrontendSessionStorageFactoryContract $sessionStorage,
        PayeverSdkService $sdkService
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentOrderRelationRepo = $paymentOrderRelationRepo;
        $this->paymentRepository = $paymentRepository;
        $this->payeverHelper = $payeverHelper;
        $this->addressRepo = $addressRepo;
        $this->config = $config;
        $this->sessionStorage = $sessionStorage;
        $this->contactRepository = $contactRepository;
        $this->sdkService = $sdkService;
    }

    /**
     * @param array $basketItems
     * @return array
     */
    protected function getOrderProducts(array $basketItems): array
    {
        $products = [];

        foreach ($basketItems as $basketItem) {
            $products[] = [
                'name' => 'name' . $basketItem['name'],
                'price' => $basketItem['price'],
                'quantity' => $basketItem['quantity'],
                'description' => "description",
                'thumbnail' => "",
                'url' => "",
            ];
        }

        return $products;
    }

    /**
     * @param float $total
     * @param string $method
     * @return float
     */
    public function getFeeAmount(float $total, string $method): float
    {
        $acceptedFee = $this->config->get('Payever.' . $method . '.accept_fee');
        $feeAmount = 0;
        if (!$acceptedFee) {
            $fixedFee = $this->config->get('Payever.' . $method . '.fee');
            $variableFee = $this->config->get('Payever.' . $method . '.variable_fee');
            $feeAmount = $total - (($total - $fixedFee) / ($variableFee / 100 + 1));
        }

        return $feeAmount;
    }

    /**
     *
     * @param Basket $basket
     * @return Address
     */
    private function getBillingAddress(Basket $basket): Address
    {
        $addressId = $basket->customerInvoiceAddressId;
        return $this->addressRepo->findAddressById($addressId);
    }

    /**
     *
     * @param Address $address
     * @return array
     */
    private function getAddress(Address $address): array
    {
        return [
            'city' => $address->town,
            'email' => $address->email,
            'last_name' => $address->lastName,
            'first_name' => $address->firstName,
            'phone' => $address->phone,
            'zip' => $address->postalCode,
            'street' => $address->street . ' ' . $address->houseNumber,
        ];
    }

    /**
     * Handling create payment request
     *
     * @param Basket $basket
     * @param $method
     * @return string
     */
    public function preparePayeverPayment(Basket $basket, $method): string
    {
        if ($this->config->get('Payever.order_before_payment')) {
            $this->sessionStorage->getPlugin()->setValue("payever_order_before_payment", 1);
            $redirectUrl = $this->payeverHelper->getProcessURL($method);
        } else {
            $this->sessionStorage->getPlugin()->setValue("payever_order_before_payment", 0);
            $createPaymentReponse = $this->processCreatePaymentRequest($basket, $method);
            if ($createPaymentReponse['error']) {
                $this->returnType = 'errorCode';
                return $createPaymentReponse['error_description'];
            }

            $redirectUrl = $createPaymentReponse['redirect_url'];
        }

        $checkoutMode = $this->config->get('Payever.redirect_to_payever');
        switch ($checkoutMode) {
            case 0:
                $this->returnType = 'htmlContent';
                $paymentContent = '<iframe sandbox="allow-same-origin allow-forms allow-top-navigation allow-scripts allow-modals allow-popups" style="width: 100%; height: 700px" frameborder="0" src="' . $redirectUrl . '"></iframe>';
                break;
            case 1:
                $this->returnType = 'redirectUrl';
                $paymentContent = $redirectUrl;
                break;
            case 2:
                $this->returnType = 'redirectUrl';
                $this->sessionStorage->getPlugin()->setValue("payever_iframe_url", $redirectUrl);
                $paymentContent = $this->payeverHelper->getIframeURL($method);
                break;
        }

        if (!strlen($paymentContent)) {
            $this->returnType = 'errorCode';
            $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentResponse', 'Unknown');
            return 'An unknown error occured, please try again.';
        }

        return $paymentContent;
    }

    /**
     * Create payment request
     *
     * @param Basket $basket
     * @param $method
     * @return mixed
     */
    public function processCreatePaymentRequest(Basket $basket, $method, $orderId = null)
    {
        /** @var \Plenty\Modules\Frontend\Services\AccountService $accountService */
        $accountService = pluginApp(\Plenty\Modules\Frontend\Services\AccountService::class);
        $contactId = $accountService->getAccountContactId();

        $payeverRequestParams = $this->getPayeverParams($basket);

        $feeAmount = $this->getFeeAmount($basket->basketAmount, $method);
        $address = $this->getAddress($this->getBillingAddress($basket));
        if (!empty($contactId) && $contactId > 0) {
            $customer = $this->contactRepository->findContactById($contactId);
            $email = $customer->email;
        } else {
            $email = $address['email'];
        }

        $reference = md5($basket->id . $method . time());

        $paymentParameters = [
            "channel" => "plentymarkets",
            "amount" => round(($basket->basketAmount - $feeAmount), 2), // basketAmount
            "fee" => round(($basket->shippingAmount - $feeAmount), 2),
            "order_id" => $orderId ?? $reference,
            "currency" => $basket->currency,
            "cart" => $this->getOrderProducts($payeverRequestParams['basketItems']),
            "payment_method" => $method,
            "first_name" => $address['first_name'],
            "last_name" => $address['last_name'],
            "city" => $address['city'],
            "zip" => $address['zip'],
            "street" => $address['street'],
            "country" => $payeverRequestParams['country']['isoCode2'],
            "email" => $email,
            "phone" => $address['phone'],
            "success_url" => $this->payeverHelper->getSuccessURL(),
            "failure_url" => $this->payeverHelper->getFailureURL(),
            "cancel_url" => $this->payeverHelper->getCancelURL(),
            "notice_url" => $this->payeverHelper->getNoticeURL()
        ];

        $this->getLogger(__METHOD__)->debug('Payever::debug.paymentParameters', $paymentParameters);
        $paymentRequest = $this->sdkService->call('createPaymentRequest', ["payment_parameters" => $paymentParameters]);
        $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentRequest', $paymentRequest);

        return $paymentRequest;
    }

    public function getReturnType()
    {
        return $this->returnType;
    }

    public function getPayeverPaymentId()
    {
        // Load the mandatory payever data from session
        return $this->sessionStorage->getPlugin()->getValue("payever_payment_id");
    }

    /**
     * Execute the payever payment
     *
     * @param $paymentId
     * @return array|string
     */
    public function pluginExecutePayment(string $paymentId)
    {
        $executeParams = [];
        $executeParams['paymentId'] = $paymentId;

        $executeResponse = [];
        if (!empty($paymentId)) {
            $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ["payment_id" => $paymentId]);
            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentRetrieve', $retrievePayment);
            if ($retrievePayment["error"]) {
                $this->returnType = 'errorCode';
                return $retrievePayment["error_description"];
            } else {
                $retrieveResponse = $retrievePayment["result"];

                $total = $retrieveResponse["total"];
                $method = $retrieveResponse["payment_type"];
                $feeAmount = $this->getFeeAmount($total, $method);
                $total = round(($total - $feeAmount), 2);

                $executeResponse['status'] = $retrieveResponse["status"];
                $executeResponse['currency'] = $retrieveResponse["currency"];
                $executeResponse['amount'] = $total;
                $executeResponse['entryDate'] = $retrieveResponse["created_at"];
                $executeResponse['transactionId'] = $retrieveResponse["id"];
                $executeResponse['email'] = $retrieveResponse["customer_email"];
                $executeResponse['nameOfSender'] = $retrieveResponse["customer_name"];
                $executeResponse['reference'] = $retrieveResponse["reference"];
                $executeResponse['usage_text'] = $retrieveResponse["payment_details"]["usage_text"];

                $this->sessionStorage->getPlugin()->unsetKey('payever_payment_id');
            }
        } else {
            $this->returnType = 'errorCode';
            $this->getLogger(__METHOD__)->error('Payever::debug.Error', "The payment ID is lost!");
            return "The payment ID is lost!";
        }

        $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);

        // Check for errors
        if (is_array($executeResponse) && $executeResponse['error']) {
            $this->returnType = 'errorCode';
            return $executeResponse['error'] . ': ' . $executeResponse['error_msg'];
        }

        return $executeResponse;
    }

    /**
     * Fill and return the payever parameters
     *
     * @param Basket $basket
     * @return array
     */
    private function getPayeverParams(Basket $basket = null): array
    {
        $payeverRequestParams = [];
        $payeverRequestParams['basket'] = $basket;
        /** @var \Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract $itemContract */
        $itemContract = pluginApp(\Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract::class);
        /** declarce the variable as array */
        $payeverRequestParams['basketItems'] = [];
        /** @var \Plenty\Modules\Basket\Models\BasketItem $basketItem */

        foreach ($basket->basketItems as $basketItem) {
            /** @var \Plenty\Modules\Item\Item\Models\Item $item */
            $item = $itemContract->show($basketItem->itemId);
            $basketItem = $basketItem->getAttributes();
            /** @var \Plenty\Modules\Item\Item\Models\ItemText $itemText */
            $itemText = $item->texts;
            $basketItem['name'] = $itemText->first()->name1;
            $payeverRequestParams['basketItems'][] = $basketItem;
        }

        /** @var \Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract $countryRepo */
        $countryRepo = pluginApp(\Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract::class);
        // Fill the country for payever parameters
        $country = [];
        $country['isoCode2'] = $countryRepo->findIsoCode($basket->shippingCountryId, 'iso_code_2');
        $payeverRequestParams['country'] = $country;

        return $payeverRequestParams;
    }

    /**
     * Execute payment if it doesn't exist
     *
     * @param $payeverPayment
     */
    public function originExecutePayment(array $payeverPayment): void
    {
        $mopId = $this->payeverHelper->getPaymentMopId($payeverPayment["payment_type"]);
        if (!$this->getCreatedPlentyPayment($payeverPayment, $mopId)) {
            $this->paymentMethodRepository->executePayment($mopId, $payeverPayment["reference"]);
        }
    }

    /**
     * @param array $payeverPayment
     * @param int $mopId
     * @return bool
     */
    public function getCreatedPlentyPayment(array $payeverPayment, int $mopId)
    {
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment["id"]
        );

        foreach ($payments as $payment) {
            if ((int)$payment->mopId == $mopId) {
                return $payment;
            }
        }

        return false;
    }

    public function isAssignedPlentyPayment(int $orderId, int $mopId)
    {
        $payments = $this->paymentRepository->getPaymentsByOrderId($orderId);
        foreach ($payments as $payment) {
            if ((int)$payment->mopId == $mopId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $paymentId
     * @return array
     */
    public function handlePayeverPayment(string $paymentId)
    {
        $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ["payment_id" => $paymentId]);
        $this->getLogger(__METHOD__)->debug('Payever::debug.getPaymentDetails', $retrievePayment);

        return $retrievePayment["result"];
    }

    /**
     * @param array $payeverPayment
     * @param bool $notificationTime
     * @return bool|Payment
     */
    public function createAndUpdatePlentyPayment(array $payeverPayment)
    {
        $mopId = $this->payeverHelper->getPaymentMopId($payeverPayment["payment_type"]);
        $plentyPayment = $this->getCreatedPlentyPayment($payeverPayment, $mopId);
        if (!$plentyPayment) {
            $payeverPaymentData = $this->pluginExecutePayment($payeverPayment["id"]);

            if ($this->getReturnType() == 'errorCode') {
                return false;
            }

            // Create a plentymarkets payment from the payever execution params
            $plentyPayment = $this->createPlentyPayment($payeverPaymentData, $mopId);
        }

        $orderId = $payeverPayment["reference"];
        if (!$this->isAssignedPlentyPayment($orderId, $mopId)) {
            if ($plentyPayment instanceof Payment) {
                // Assign the payment to an order in plentymarkets
                $this->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId, $payeverPayment['status']);
            }
        } else {
            $this->updateOrderStatus($orderId, $payeverPayment['status']);
        }

        return $plentyPayment;
    }

    /**
     * @param array $payeverPayment
     * @param int $mopId
     * @return Payment
     */
    public function createPlentyPayment(array $payeverPayment, int $mopId): Payment
    {
        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);
        $payment->mopId = (int)$mopId;
        $payment->transactionType = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status = $this->payeverHelper->mapStatus($payeverPayment['status']);
        $payment->currency = $payeverPayment['currency'];
        $payment->amount = $payeverPayment['amount'];
        $payment->receivedAt = date("Y-m-d H:i:s", strtotime($payeverPayment['entryDate']));
        $paymentProperty = [];
        $bookingText = !empty($payeverPayment['usage_text']) ? 'Payment reference: ' . $payeverPayment['usage_text'] : '';
        $bookingText .= 'TransactionID: ' . (string)$payeverPayment['transactionId'];
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_BOOKING_TEXT,
            $bookingText
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment['transactionId']
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_REFERENCE_ID,
            $payeverPayment['reference']
        );

        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(PaymentProperty::TYPE_ORIGIN,
            Payment::ORIGIN_PLUGIN);
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(PaymentProperty::TYPE_PAYMENT_TEXT,
            $payeverPayment['usage_text']);
        $payment->properties = $paymentProperty;
        //$payment->regenerateHash = true;
        $payment = $this->paymentRepository->createPayment($payment);

        return $payment;
    }

    /**
     * @param string $transactionId
     * @param string $status
     * @param bool|Date $notificationTime
     * @return bool|Payment
     */
    public function updatePlentyPayment(string $transactionId, string $status, $notificationTime = false)
    {
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $transactionId
        );

        $state = $this->payeverHelper->mapStatus($status);
        foreach ($payments as $payment) {
            if ($notificationTime) {
                if (strtotime($notificationTime) > strtotime($payment->receivedAt)) {
                    $payment->receivedAt = $notificationTime;
                } else {
                    return false;
                }
            }

            $orderId = $payment->order->orderId;
            $this->updateOrderStatus($orderId, $status);

            /* @var Payment $payment */
            if ($payment->status != $state) {
                $payment->status = $state;
            }

            $this->paymentRepository->updatePayment($payment);

            return $payment;
        }

        return false;
    }

    /**
     * Assigns plenty payment to plenty order
     *
     * @param Payment $payment
     * @param int $orderId
     * @param $paymentStatus
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId, $paymentStatus)
    {
        $authHelper = pluginApp(AuthHelper::class);
        $authHelper->processUnguarded(
            function () use ($orderId, $payment) {
                // Get the order by the given order ID
                $order = $this->orderRepository->findOrderById($orderId);

                // Check whether the order truly exists in plentymarkets
                if (!is_null($order) && $order instanceof Order) {
                    // Assign the given payment to the given order
                    $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);
                }

                $transactionId = $this->payeverHelper->getPaymentPropertyValue($payment,
                    PaymentProperty::TYPE_TRANSACTION_ID);
                $this->getLogger(__METHOD__)->debug('Payever::debug.assignPlentyPaymentToPlentyOrder',
                    'Transaction ' . $transactionId . ' was assigned to the order #' . $orderId);
            }
        );

        $this->updateOrderStatus($orderId, $paymentStatus);
    }

    /**
     * Update order status by order id
     *
     * @param int $orderId
     * @param string $paymentStatus
     */
    public function updateOrderStatus(int $orderId, string $paymentStatus)
    {
        try {
            /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $statusId = $this->payeverHelper->mapOrderStatus($paymentStatus);
            $authHelper->processUnguarded(
                function () use ($orderId, $statusId) {
                    //unguarded
                    $order = $this->orderRepository->findOrderById($orderId);
                    if (!is_null($order) && $order instanceof Order) {
                        $status['statusId'] = (float)$statusId;
                        $this->orderRepository->updateOrder($status, $orderId);
                        $this->getLogger(__METHOD__)->debug('Payever::debug.updateOrderStatus',
                            'Status of order ' . $orderId . ' was changed to ' . $statusId);
                    }
                }
            );
        } catch (\Exception $exception) {
            $this->getLogger(__METHOD__)->error('Payever::updateOrderStatus', $exception);
        }
    }


    /**
     * Refund the given payment
     *
     * @param string $transactionId
     * @param float $amount
     * @return bool|mixed
     */
    public function refundPayment(string $transactionId, float $amount)
    {
        return $this->sdkService->call('refundPaymentRequest',
            ["transaction_id" => $transactionId, "amount" => $amount]);
    }

    /**
     * Returns transaction data
     *
     * @param string $transactionId
     * @return bool|mixed
     */
    public function getTransaction(string $transactionId)
    {
        return $this->sdkService->call('getTransactionRequest', ["transaction_id" => $transactionId]);
    }

    /**
     * Cancel the given payment
     *
     * @param string $transactionId
     * @return bool|mixed
     */
    public function cancelPayment(string $transactionId)
    {
        return $this->sdkService->call('cancelPaymentRequest', ["transaction_id" => $transactionId]);
    }

    /**
     * Cancel the given payment
     *
     * @param string $transactionId
     * @return bool|mixed
     */
    public function shippingGoodsPayment(string $transactionId, array $data = [])
    {
        return $this->sdkService->call('shippingPaymentRequest', ["transaction_id" => $transactionId]);
    }
}
