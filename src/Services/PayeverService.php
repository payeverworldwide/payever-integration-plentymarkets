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
        FrontendSessionStorageFactoryContract $sessionStorage
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentRepository = $paymentRepository;
        $this->payeverHelper = $payeverHelper;
        $this->addressRepo = $addressRepo;
        $this->config = $config;
        $this->sessionStorage = $sessionStorage;
        $this->contactRepository = $contactRepository;
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
        $payeverApi = $this->payeverHelper->getPayeverApi();

        if ($payeverApi->authenticationRequest() === false) {
            $errors = $payeverApi->getErrors();
            $this->returnType = 'errorCode';

            return $errors[0];
        }

        $feeAmount = $this->getFeeAmount($basket->basketAmount, $method);
        $address = $this->getAddress($this->getBillingAddress($basket));
        if (!empty($contactId) && $contactId > 0) {
            $customer = $this->contactRepository->findContactById($contactId);
            $email = $customer->email;
        } else {
            $email = $address['email'];
        }

        $paymentParameters = [
            "channel" => "plentymarkets",
            "amount" => round(($basket->basketAmount - $feeAmount), 2), // basketAmount
            "fee" => round(($basket->shippingAmount - $feeAmount), 2),
            "order_id" => $basket->id,
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
            'plugin_version' => '1.0.3',
        ];

        $this->getLogger(__METHOD__)->debug('Payever::debug.paymentParameters', $paymentParameters);

        $paymentRequest = $payeverApi->createPaymentRequest($paymentParameters);
        $errors = $payeverApi->getErrors();
        $request = $payeverApi->getRequests();
        $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentRequest', $request['createPayment']);

        if (!empty($errors) || !isset($paymentRequest->redirect_url)) {
            $this->returnType = 'errorCode';
            $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentResponse', $errors);
            $paymentContent = $errors[0];
        } else {
            $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentResponse', $paymentRequest);
            $isRedirect = $this->config->get('Payever.redirect_to_payever');
            switch ($isRedirect) {
                case 0:
                    $this->returnType = 'htmlContent';
                    $paymentContent = '<iframe style="width: 100%; height: 700px" frameborder="0" src="'.$paymentRequest->redirect_url.'"></iframe>';
                    break;
                case 1:
                    $this->returnType = 'redirectUrl';
                    $paymentContent = $paymentRequest->redirect_url;
                    break;
                case 2:
                    $this->sessionStorage->getPlugin()->setValue("payever_redirect_url", $paymentRequest->redirect_url);
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
        $payeverApi = $this->payeverHelper->getPayeverApi();
        $errors = $payeverApi->getErrors();

        if (!empty($errors)) {
            $errors = $payeverApi->getErrors();
            $this->returnType = 'errorCode';

            return $errors[0];
        }

        if (!empty($paymentId)) {
            $retrievePayment = $payeverApi->retrievePayment($paymentId);
            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentRetrieve', $retrievePayment);
            if ($retrievePayment) {
                $response_status = $retrievePayment->result->payment_details->specific_status ?? $retrievePayment->result->status;
                $response_status = $payeverApi->getPayeverStatus($response_status);
                if ($response_status == false) {
                    $response_status = $retrievePayment->result->status;
                }

                $total = $retrievePayment->result->total;
                $method = $retrievePayment->result->payment_type;
                $feeAmount = $this->getFeeAmount($total, $method);
                $total = round(($total - $feeAmount), 2);

                $executeResponse['status'] = $response_status;
                $executeResponse['currency'] = $retrievePayment->result->currency;
                $executeResponse['amount'] = $total;
                $executeResponse['entryDate'] = $retrievePayment->result->created_at;
                $executeResponse['transactionId'] = $retrievePayment->result->id;
                $executeResponse['email'] = $retrievePayment->result->customer_email;
                $executeResponse['nameOfSender'] = $retrievePayment->result->customer_name;
                $executeResponse['reference'] = $retrievePayment->result->reference;
            } else {
                $errors = $payeverApi->getErrors();
                $this->returnType = 'errorCode';

                return $errors[0];
            }
        } else {
            $this->returnType = 'errorCode';

            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);
            return "The payment ID is lost!";
        }

        // Check for errors
        if (is_array($executeResponse) && $executeResponse['error']) {
            $this->returnType = 'errorCode';
            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);
            return $executeResponse['error'] . ': '.$executeResponse['error_msg'];
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
        return $this->getPaymentDetails($paymentId);
    }

    /**
     * @param $paymentId
     * @return \stdClass
     */
    public function getPaymentDetails(string$paymentId)
    {
        $response = $this->getRetrievePayment($paymentId);
        $this->getLogger(__METHOD__)->debug('Payever::debug.getPaymentDetails', $response);

        return $response;
    }

    /**
     * Retrieve the payever payment
     *
     * @param string $paymentId
     * @return \stdClass|bool
     */
    public function getRetrievePayment(string $paymentId)
    {
        $payeverApi = $this->payeverHelper->getPayeverApi();
        $errors = $payeverApi->getErrors();

        if (!empty($errors)) {
            $errors = $payeverApi->getErrors();
            $this->getLogger(__METHOD__)->debug('Payever::debug.authenticationRequest', $errors);

            return false;
        }

        $retrievePayment = $payeverApi->retrievePayment($paymentId);

        return $retrievePayment->result;
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
        $payeverApi = $this->payeverHelper->getPayeverApi();
        $errors = $payeverApi->getErrors();

        if (!empty($errors)) {
            $errors = $payeverApi->getErrors();
            $this->getLogger(__METHOD__)->debug('Payever::debug.authenticationRequest', $errors);

            return false;
        }

        $refund = $payeverApi->refundPayment($transactionId, $amount);

        return $refund;
    }
}
