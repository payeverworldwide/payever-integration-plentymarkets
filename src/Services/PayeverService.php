<?php // strict

namespace Payever\Services;

use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;
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

    private $paymentMethodRepository;
    private $paymentRepository;
    private $payeverHelper;
    private $addressRepo;
    private $config;
    private $returnType = '';
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
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param AddressRepositoryContract $addressRepo
     */
    public function __construct(
        PaymentMethodRepositoryContract $paymentMethodRepository,
        PaymentRepositoryContract $paymentRepository,
        ConfigRepository $config,
        PayeverHelper $payeverHelper,
        AddressRepositoryContract $addressRepo,
        ContactRepositoryContract $contactRepository,
        FrontendSessionStorageFactoryContract $sessionStorage,
        PayeverSdkService $sdkService
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
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
    protected function getOrderProducts(array $basketItems):array
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
     * Get the payever payment content
     *
     * @param Basket $basket
     * @return string
     */
    public function getPaymentContent(Basket $basket, $method):string
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
            "order_id" => $reference,
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
            "notice_url" => $this->payeverHelper->getNoticeURL(),
            'plugin_version' => '1.3.0'
        ];

        $this->getLogger(__METHOD__)->debug('Payever::debug.paymentParameters', $paymentParameters);
        $paymentRequest = $this->sdkService->call('createPaymentRequest', ["payment_parameters" => $paymentParameters]);
        $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentRequest', $paymentRequest);

        if ($paymentRequest['error']) {
            $this->returnType = 'errorCode';
            $paymentContent = $paymentRequest['error_description'];
        } else {
            $isRedirect = $this->config->get('Payever.redirect_to_payever');
            switch ($isRedirect) {
                case 0:
                    $this->returnType = 'htmlContent';
                    $paymentContent = '<iframe sandbox="allow-same-origin allow-forms allow-top-navigation allow-scripts allow-modals allow-popups" style="width: 100%; height: 700px" frameborder="0" src="'. $paymentRequest['redirect_url'] .'"></iframe>';
                    break;
                case 1:
                    $this->returnType = 'redirectUrl';
                    $paymentContent = $paymentRequest['redirect_url'];
                    break;
                case 2:
                    $this->sessionStorage->getPlugin()->setValue("payever_redirect_url", $paymentRequest['redirect_url']);
                    $this->returnType = 'redirectUrl';
                    $paymentContent = $this->payeverHelper->getIframeURL();
                    break;
            }
        }

        if (!strlen($paymentContent)) {
            $this->returnType = 'errorCode';
            $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentResponse', 'Unknown');
            return 'An unknown error occured, please try again.';
        }

        return $paymentContent;
    }

    /**
     * @param float $total
     * @param string $method
     * @return float
     */
    public function getFeeAmount(float $total, string $method):float
    {
        $acceptedFee = $this->config->get('Payever.'.$method.'.accept_fee');
        $feeAmount = 0;
        if (!$acceptedFee) {
            $fixedFee = $this->config->get('Payever.'.$method.'.fee');
            $variableFee = $this->config->get('Payever.'.$method.'.variable_fee');
            $feeAmount = $total - (($total - $fixedFee) / ($variableFee / 100 + 1));
        }

        return $feeAmount;
    }

    /**
     *
     * @param Basket $basket
     * @return Address
     */
    private function getBillingAddress(Basket $basket):Address
    {
        $addressId = $basket->customerInvoiceAddressId;
        return $this->addressRepo->findAddressById($addressId);
    }

    /**
     *
     * @param Address $address
     * @return array
     */
    private function getAddress(Address $address):array
    {
        return [
            'city' => $address->town,
            'email' => $address->email,
            'last_name' => $address->lastName,
            'first_name' => $address->firstName,
            'phone' => $address->phone,
            'zip' => $address->postalCode,
            'street' => $address->street.' '.$address->houseNumber,
        ];
    }

    /**
     * @param Basket $basket
     * @return string
     */
    public function preparePayeverPayment(Basket $basket, $method):string
    {
        return $this->getPaymentContent($basket, $method);
    }

    public function getReturnType()
    {
        return $this->returnType;
    }

    /**
     * Execute the payever payment
     *
     * @return array|string
     */
    public function executePayment()
    {
        // Load the mandatory payever data from session
        $paymentId = $this->sessionStorage->getPlugin()->getValue("payever_payment_id");
        $executeParams = [];
        $executeParams['paymentId'] = $paymentId;

        $executeResponse = [];

        if (!empty($paymentId)) {
            $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ["payment_id" => $paymentId]);
            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentRetrieve', $retrievePayment);
            if($retrievePayment["error"]) {
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

            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);
            $this->getLogger(__METHOD__)->error('Payever::debug.Error', "The payment ID is lost!");
            return "The payment ID is lost!";
        }

        // Check for errors
        if (is_array($executeResponse) && $executeResponse['error']) {
            $this->returnType = 'errorCode';
            $this->getLogger(__METHOD__)->error('Payever::debug.Error', $executeResponse['error'] . ': '. $executeResponse['error_msg']);
            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);
            return $executeResponse['error'] . ': '. $executeResponse['error_msg'];
        }

        $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);

        return $executeResponse;
    }

    /**
     * Fill and return the payever parameters
     *
     * @param Basket $basket
     * @return array
     */
    private function getPayeverParams(Basket $basket = null):array
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
     * @param $paymentId
     * @return \stdClass
     */
    public function handlePayeverPayment(string $paymentId)
    {
        $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ["payment_id" => $paymentId]);
        $this->getLogger(__METHOD__)->debug('Payever::debug.getPaymentDetails', $retrievePayment);

        return $retrievePayment["result"];
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
        return $this->sdkService->call('refundPaymentRequest', ["transaction_id" => $transactionId, "amount" => $amount]);
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
