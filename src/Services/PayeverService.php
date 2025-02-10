<?php

namespace Payever\Services;

use Exception;
use IO\Services\OrderService;
use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Helper\CompanySearchHelper;
use Payever\Helper\PayeverHelper;
use Payever\Helper\RoutesHelper;
use Payever\Repositories\CustomerCompanyAddressRepository;
use Payever\Traits\Logger;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Payever\Helper\StatusHelper;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
class PayeverService
{
    use Logger;

    const MR_SALUTATION = 'mr';
    const MS_SALUTATION = 'ms';
    const MS_COMPANY = '';
    const MS_PERSON = '';

    const IFRAME_MODE = 0;
    const REDIRECT_MODE = 1;
    const REDIRECT_AND_IFRAME_MODE = 2;

    const PLENTY_FEMALE_SALUTATION = 'female';
    const PLENTY_MALE_SALUTATION = 'male';
    const PLENTY_COMPANY_SALUTATION = 'company';
    const PLENTY_PERSON_SALUTATION = 'diverse';

    const ACTION_CANCEL = 'cancel';
    const ACTION_REFUND = 'refund';
    const ACTION_SHIPPING_GOODS = 'shipping_goods';
    const ACTION_CLAIM = 'claim';
    const ACTION_CLAIM_UPLOAD = 'claim_upload';
    const API_V2 = 2;
    const API_V3 = 3;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;

    /**
     * @var ItemRepositoryContract
     */
    private $itemRepository;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var AddressRepositoryContract
     */
    private $addressRepo;

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
     * @var BasketRepositoryContract
     */
    private $basketRepository;

    /**
     * @var PendingPaymentRepositoryContract
     */
    private $pendingPaymentRepository;

    /**
     * @var string
     */
    private $returnType = '';

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
     * @var RoutesHelper
     */
    private $routesHelper;

    /** @var CustomerCompanyAddressRepository  */
    private $customerCompanyAddressRepository;

    /** @var CompanySearchHelper  */
    private $companySearchHelper;

    /**
     * @param AccountService $accountService
     * @param CountryRepositoryContract $countryRepository
     * @param ItemRepositoryContract $itemRepository
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param AddressRepositoryContract $addressRepo
     * @param ContactRepositoryContract $contactRepository
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param PayeverSdkService $sdkService
     * @param BasketRepositoryContract $basketRepository
     * @param PendingPaymentRepositoryContract $pendingPaymentRepository
     * @param OrderTotalRepositoryContract $orderTotalRepository
     * @param OrderTotalItemRepositoryContract $orderTotalItemRepository
     * @param PayeverHelper $paymentHelper
     * @param PaymentRepositoryContract $paymentContract
     * @param RoutesHelper $routesHelper
     * @param CustomerCompanyAddressRepository $customerCompanyAddressRepository
     * @param CompanySearchHelper $companySearchHelper
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        AccountService $accountService,
        CountryRepositoryContract $countryRepository,
        ItemRepositoryContract $itemRepository,
        ConfigRepository $config,
        PayeverHelper $payeverHelper,
        AddressRepositoryContract $addressRepo,
        ContactRepositoryContract $contactRepository,
        FrontendSessionStorageFactoryContract $sessionStorage,
        PayeverSdkService $sdkService,
        BasketRepositoryContract $basketRepository,
        PendingPaymentRepositoryContract $pendingPaymentRepository,
        OrderTotalRepositoryContract $orderTotalRepository,
        OrderTotalItemRepositoryContract $orderTotalItemRepository,
        PayeverHelper $paymentHelper,
        PaymentRepositoryContract $paymentContract,
        RoutesHelper $routesHelper,
        CustomerCompanyAddressRepository $customerCompanyAddressRepository,
        CompanySearchHelper $companySearchHelper
    ) {
        $this->accountService = $accountService;
        $this->countryRepository = $countryRepository;
        $this->itemRepository = $itemRepository;
        $this->payeverHelper = $payeverHelper;
        $this->addressRepo = $addressRepo;
        $this->config = $config;
        $this->sessionStorage = $sessionStorage;
        $this->contactRepository = $contactRepository;
        $this->sdkService = $sdkService;
        $this->basketRepository = $basketRepository;
        $this->pendingPaymentRepository = $pendingPaymentRepository;
        $this->orderTotalRepository = $orderTotalRepository;
        $this->orderTotalItemRepository = $orderTotalItemRepository;
        $this->paymentHelper = $paymentHelper;
        $this->paymentContract = $paymentContract;
        $this->routesHelper = $routesHelper;
        $this->customerCompanyAddressRepository = $customerCompanyAddressRepository;
        $this->companySearchHelper = $companySearchHelper;
    }

    /**
     * @param float $total
     * @param string $method
     * @return float
     * @codeCoverageIgnore
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
     * @param string $method
     * @return bool
     */
    public function isRedirectMethod(string $method): bool
    {
        if ($this->config->has('Payever.' . $method . '.redirect_method')) {
            return (bool)$this->config->get('Payever.' . $method . '.redirect_method');
        }

        return false;
    }

    /**
     * @param string $method
     * @return bool
     */
    public function isSubmitMethod(string $method): bool
    {
        if ($this->config->has('Payever.' . $method . '.is_submit_method')) {
            return (bool)$this->config->get('Payever.' . $method . '.is_submit_method');
        }

        return false;
    }

    /**
     * @param string $method
     * @return bool
     */
    public function isB2BMethod(string $method): bool
    {
        if ($this->config->has('Payever.' . $method . '.is_b2b_method')) {
            return (bool)$this->config->get('Payever.' . $method . '.is_b2b_method');
        }

        return false;
    }

    /**
     * @param int $addressId
     * @return array
     */
    private function getAddress(int $addressId): array
    {
        $address = $this->addressRepo->findAddressById($addressId);
        $country = $this->countryRepository->findIsoCode($address->countryId, 'iso_code_2');

        return [
            'city' => $address->town,
            'email' => $address->email,
            'salutation' => $this->getSalutation((string)$address->gender),
            'last_name' => $address->lastName,
            'first_name' => $address->firstName,
            'phone' => $address->phone,
            'zip' => $address->postalCode,
            'street' => $address->street . ' ' . $address->houseNumber,
            'country' => $country,
            'company' => $address->companyName
        ];
    }

    /**
     * @param string $salutation
     * @return bool|string
     */
    private function getSalutation(string $salutation)
    {
        $salutation = strtolower($salutation);
        switch ($salutation) {
            case self::PLENTY_FEMALE_SALUTATION:
                return self::MS_SALUTATION;
            case self::PLENTY_MALE_SALUTATION:
                return self::MR_SALUTATION;
            case self::PLENTY_PERSON_SALUTATION:
                return self::MS_PERSON;
            default:
                return self::MS_COMPANY;
        }
    }

    /**
     * Handling create payment request
     *
     * @param Basket $basket
     * @param string $method
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function preparePayeverPayment(Basket $basket, string $method): string
    {
        try {
            if ($this->config->get('Payever.order_before_payment')) {
                $this->sessionStorage->getPlugin()->setValue('payever_order_before_payment', 1);
                $redirectUrl = $this->processOrderPayment($method);
            } else {
                // @codeCoverageIgnoreStart
                $this->sessionStorage->getPlugin()->setValue('payever_order_before_payment', 0);
                $createPaymentResponse = $this->processCreatePaymentRequest($basket, $method);
                if ($createPaymentResponse['error']) {
                    $this->returnType = 'errorCode';

                    return $this->payeverHelper->retrieveErrorMessageFromSdkResponse($createPaymentResponse);
                }

                $redirectUrl = $createPaymentResponse['redirect_url'];
                // @codeCoverageIgnoreEnd
            }

            if ($this->isSubmitMethod($method)) {
                $this->returnType = 'redirectUrl';

                return $redirectUrl;
            }

            $checkoutMode = $this->isRedirectMethod($method)
                ? self::REDIRECT_MODE
                : $this->config->get('Payever.redirect_to_payever');
            switch ($checkoutMode) {
                case self::IFRAME_MODE:
                    $this->returnType = 'htmlContent';
                    // @codingStandardsIgnoreStart
                    $paymentContent = <<<HTML
<iframe allow="payment" sandbox="allow-same-origin allow-forms allow-top-navigation allow-scripts allow-modals allow-popups"
 style="width: 100%; height: 700px" frameborder="0" src="$redirectUrl"></iframe>
HTML;
                    // @codingStandardsIgnoreEnd
                    break;
                case self::REDIRECT_MODE:
                    $this->returnType = 'redirectUrl';
                    $paymentContent = $redirectUrl;
                    break;
                case self::REDIRECT_AND_IFRAME_MODE:
                    $this->returnType = 'redirectUrl';
                    $this->sessionStorage->getPlugin()->setValue('payever_iframe_url', $redirectUrl);
                    $paymentContent = $this->routesHelper->getIframeURL($method);
                    break;
            }

            if (empty($paymentContent) || !strlen($paymentContent)) {
                throw new Exception('An unknown error occurred, please try again.');
            }
        } catch (Exception $exception) {
            $this->returnType = 'errorCode';
            $paymentContent = $exception->getMessage();

            $this->log(
                'error',
                'PayeverService::preparePayeverPayment',
                'Payever::debug.createPaymentResponse',
                'Exception: ' . $exception->getMessage(),
                []
            );
        }

        return $paymentContent;
    }

    /**
     * Create payment request
     *
     * @param Basket $basket
     * @param string $method
     * @param null $orderId
     * @param string $shippingProvider
     * @param string $shippingProfileName
     * @return mixed
     */
    public function processCreatePaymentRequest(
        Basket $basket,
        string $method,
        $orderId = null,
        string $shippingProvider = '',
        string $shippingProfileName = ''
    ) {
        $this->log(
            'info',
            __METHOD__,
            'Payever::debug.processCreatePaymentRequest',
            'createPaymentRequest start'
        );

        $contactId = $this->accountService->getAccountContactId();
        $feeAmount = $this->getFeeAmount($basket->basketAmount, $method);

        $billingAddress = $this->getAddress($basket->customerInvoiceAddressId);
        $shippingAddress = $this->getAddress($basket->customerShippingAddressId
            ?? $basket->customerInvoiceAddressId);

        $email = $billingAddress['email'];
        if (!empty($contactId) && $contactId > 0) {
            $customer = $this->contactRepository->findContactById($contactId);
            $email = $customer->email;
        }

        $orderId = $orderId ?? md5($basket->id . $method . time());
        $paymentParameters = [
            'amount' => round(($basket->basketAmount - $feeAmount), 2), // basketAmount
            'fee' => round(($basket->shippingAmount - $feeAmount), 2),
            'order_id' => $orderId,
            'currency' => $basket->currency,
            'cart' => $this->getBasketItems($basket),
            'payment_method' => $method,
            'locale' => explode('_', $this->sessionStorage->getLocaleSettings()->language)[0],
            'email' => $email,
            'phone' => $billingAddress['phone'],
            'shipping_address' => $shippingAddress,
            'success_url' => $this->routesHelper->getSuccessURL(),
            'pending_url' => $this->routesHelper->getPendingURL(),
            'failure_url' => $this->routesHelper->getFailureURL(),
            'cancel_url' => $this->routesHelper->getCancelURL(),
            'notice_url' => $this->routesHelper->getNoticeURL(),
            'shipping_method' => $shippingProvider,
            'shipping_title' => $shippingProvider . ' - ' . $shippingProfileName,
            'client_ip' => $this->payeverHelper->getClientIP()
        ];
        $payeverPaymentId = '';

        $paymentParameters['billing_address'] = $billingAddress;
        $paymentParameters['force_redirect'] = $this->isRedirectMethod($method);

        if (!empty($billingAddress['company'])) {
            // Set company details for company address type
            $addressHash = $this->companySearchHelper->generateAddressHash(
                $billingAddress['company'],
                $billingAddress['email'],
                $billingAddress['city'],
                $billingAddress['zip']
            );
            $paymentParameters['company_address_hash'] = $addressHash;

            $company = $this->customerCompanyAddressRepository->getByAddressHash($addressHash);

            if (!empty($company)) {
                $paymentParameters['company'] = json_decode($company->getCompany(), true);
            }
        }

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.paymentParameters',
            'Payment request',
            ['paymentParameters' => $paymentParameters]
        );

        $paymentResponse = $this->createPayment($method, $paymentParameters);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.processCreatePaymentRequest',
            'createPaymentRequest Response',
            ['paymentResponse' => $paymentResponse]
        );

        $pendingPayment = $this->pendingPaymentRepository->getByOrderId($orderId);
        if (!$pendingPayment) {
            // @codeCoverageIgnoreStart
            $pendingPayment = $this->pendingPaymentRepository->create();
            $pendingPayment->orderId = $orderId;
            $pendingPayment->payeverPaymentId = $payeverPaymentId;
            $pendingPayment->data = $basket->toArray();

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.checkoutDebug',
                'Pending payment',
                ['pendingPaymentToPersist' => $pendingPayment]
            );

            $this->pendingPaymentRepository->persist($pendingPayment);
            // @codeCoverageIgnoreEnd
        }

        return $paymentResponse;
    }

    /**
     * @param string $method
     * @return mixed
     * @throws Exception
     */
    public function processOrderPayment(string $method)
    {
        $this->sessionStorage->getPlugin()->unsetKey('payever_payment_id');
        $basket = $this->basketRepository->load();

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.placeOrderCalling',
            'PlaceOrder was called',
            []
        );

        $orderData = $this->getOrderService()->placeOrder();
        $createPaymentResponse = $this->processCreatePaymentRequest(
            $basket,
            $method,
            $orderData->order->id,
            $orderData->shippingProvider,
            $orderData->shippingProfileName
        );

        if (!empty($createPaymentResponse['error'])) {
            throw new Exception('Creating payment has been declined');
        }

        $this->log(
            'debug',
            __METHOD__ . ' $orderData',
            'Payever::debug.processOrderPaymentAfterResponse',
            'Process order payment',
            $orderData
        );

        $this->log(
            'debug',
            __METHOD__ . ' $basket',
            'Payever::debug.basic',
            'Basket data',
            $basket
        );

        $this->log(
            'debug',
            __METHOD__ . ' $createPaymentResponse',
            'Payever::debug.basic',
            'Create Payment Response',
            $createPaymentResponse
        );

        $orderTotal = $this->orderTotalRepository->create($orderData->order->id);

        if (is_array($orderData->order->orderItems) || $orderData->order->orderItems instanceof \Traversable) {
            foreach ($orderData->order->orderItems as $orderItem) {
                $this->orderTotalItemRepository->create($orderItem);
            }
        }

        $this->log(
            'debug',
            __METHOD__ . ' $orderTotal',
            'Payever::debug.orderTotal',
            'Order total',
            ['orderTotal' => $orderTotal]
        );

        return $createPaymentResponse['redirect_url'] ??
            $this->getRedirectUrlByThePaymentStatus($createPaymentResponse);
    }

    /**
     * @return string
     */
    public function getReturnType(): string
    {
        return $this->returnType;
    }

    /**
     * Get Basket items
     *
     * @param Basket $basket
     * @return array
     */
    private function getBasketItems(Basket $basket): array
    {
        $basketItems = [];

        /** @var BasketItem $basketItem */
        foreach ($basket->basketItems as $basketItem) {
            if ($basketItem instanceof BasketItem) {
                // @codeCoverageIgnoreStart
                $basketItemPrice = $basketItem->price + $basketItem->attributeTotalMarkup;

                /** @var \Plenty\Modules\Item\Item\Models\Item $item */
                $item = $this->itemRepository->show(
                    $basketItem->itemId,
                    ['*'],
                    $this->sessionStorage->getLocaleSettings()->language
                );

                /** @var \Plenty\Modules\Item\Item\Models\ItemText $itemText */
                $itemText = $item->texts;

                $basketItem = [
                    'name' => utf8_encode($itemText->first()->name1),
                    'price' => $basketItemPrice,
                    'unit_price' => $basketItemPrice,
                    'total_amount' => $basketItemPrice * (int) $basketItem->quantity,
                    'quantity' => (int)$basketItem->quantity,
                    'identifier' => (string)$basketItem->variationId,
                    'vatRate' => (float)$basketItem->vat,
                    'description' => '',
                    'thumbnail' => '',
                    'url' => '',
                ];

                $basketItems[] = $basketItem;
                // @codeCoverageIgnoreEnd
            }
        }

        return $basketItems;
    }

    /**
     * @param string $paymentId
     * @return array
     */
    public function handlePayeverPayment(string $paymentId): array
    {
        $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ['payment_id' => $paymentId]);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.getPaymentDetails',
            'RetrievePayment Response',
            ['retrievePayment' => $retrievePayment]
        )->setReferenceValue($paymentId);

        return $retrievePayment['result'] ?? [];
    }

    /**
     * Refund the given payment
     *
     * @param string $transactionId
     * @param float $amount
     * @param string|null $identifier
     * @return bool|mixed
     */
    public function refundPayment(string $transactionId, float $amount, string $identifier = null)
    {
        return $this->sdkService->call(
            'refundPaymentRequest',
            [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'identifier' => $identifier,
            ]
        );
    }

    /**
     * @param string $transactionId
     * @param array $items
     * @param float|null $deliveryFee
     * @param string|null $identifier
     * @return mixed
     */
    public function refundItemsPayment(
        string $transactionId,
        array $items,
        float $deliveryFee = null,
        string $identifier = null
    ) {
        return $this->sdkService->call(
            'refundItemsPaymentRequest',
            [
                'transaction_id' => $transactionId,
                'items' => $items,
                'deliveryFee' => $deliveryFee,
                'identifier' => $identifier,
            ]
        );
    }

    /**
     * Returns transaction data
     *
     * @param string $transactionId
     * @return bool|mixed
     */
    public function getTransaction(string $transactionId)
    {
        return $this->sdkService->call(
            'getTransactionRequest',
            [
                'transaction_id' => $transactionId,
            ]
        );
    }

    /**
     * Cancel the given payment
     *
     * @param string $transactionId
     * @param float|null $amount
     * @param string|null $identifier
     * @return bool|mixed
     */
    public function cancelPayment(string $transactionId, float $amount = null, string $identifier = null)
    {
        return $this->sdkService->call(
            'cancelPaymentRequest',
            [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'identifier' => $identifier,
            ]
        );
    }

    /**
     * @param string $transactionId
     * @param array $items
     * @param float|null $deliveryFee
     * @param string|null $identifier
     * @return mixed
     */
    public function cancelItemsPayment(
        string $transactionId,
        array $items,
        float $deliveryFee = null,
        string $identifier = null
    ) {
        return $this->sdkService->call(
            'cancelItemsPaymentRequest',
            [
                'transaction_id' => $transactionId,
                'items' => $items,
                'deliveryFee' => $deliveryFee,
                'identifier' => $identifier,
            ]
        );
    }

    /**
     * Claim the given payment
     *
     * @param string $transactionId
     * @param bool $isDisputed
     * @return bool|mixed
     */
    public function claimPayment(string $transactionId, bool $isDisputed)
    {
        return $this->sdkService->call(
            'claimPaymentRequest',
            [
                'transaction_id' => $transactionId,
                'is_disputed' => $isDisputed,
            ]
        );
    }

    /**
     * Claim upload the given payment
     *
     * @param string $transactionId
     * @param array $files
     * @return bool|mixed
     */
    public function claimUploadPayment(string $transactionId, array $files)
    {
        return $this->sdkService->call(
            'claimUploadPaymentRequest',
            [
                'transaction_id' => $transactionId,
                'files' => $files,
            ]
        );
    }

    /**
     * Capture the given payment
     *
     * @param string $transactionId Transaction ID
     * @param float|null $amount Order amount
     * @param array $paymentItems Payment items
     * @param float $deliveryFee Delivery fee
     * @param string $reason Reason
     * @param string|null $carrier Carrier
     * @param string|null $trackingNumber Tracking number
     * @param string|null $trackingUrl Tracking url
     * @param string|null $identifier Tracking url
     * @return array|null
     */
    public function shippingGoodsPayment(
        string $transactionId,
        $amount,
        array $paymentItems,
        $deliveryFee,
        $reason,
        $carrier,
        $trackingNumber,
        $trackingUrl,
        $identifier = null
    ): array|null {
        $this->log(
            'debug',
            'ShippingEventProcedure::run',
            'Payever::debug.shippingPaymentRequest',
            'Shipping Payment Request',
            [
                'transactionId' => $transactionId,
                'amount' => $amount,
                'paymentItems' => $paymentItems,
                'deliveryFee' => $deliveryFee,
                'reason' => $reason,
                'carrier' => $carrier,
                'trackingNumber' => $trackingNumber,
                'trackingUrl' => $trackingUrl,
                'shippingDate' => date('Y-m-d\TH:i:sO'),
                'identifier' => $identifier,
            ]
        )->setReferenceValue($transactionId);

        return $this->sdkService->call(
            'shippingPaymentRequest',
            [
                'transactionId' => $transactionId,
                'amount' => $amount,
                'paymentItems' => $paymentItems,
                'deliveryFee' => $deliveryFee,
                'reason' => $reason,
                'carrier' => $carrier,
                'trackingNumber' => $trackingNumber,
                'trackingUrl' => $trackingUrl,
                'shippingDate' => date('Y-m-d\TH:i:sO'),
                'identifier' => $identifier,
            ]
        );
    }

    /**
     * @return OrderService|mixed
     * @codeCoverageIgnore
     */
    protected function getOrderService()
    {
        return null === $this->orderService
            ? $this->orderService = pluginApp(OrderService::class)
            : $this->orderService;
    }

    /**
     * @param OrderService|mixed $orderService
     * @return $this
     * @internal
     */
    public function setOrderService($orderService): self
    {
        $this->orderService = $orderService;

        return $this;
    }

    /**
     * @param $transactionId
     * @param $transactionAction
     * @param bool $throwException
     * @return bool|array|null
     */
    public function isActionAllowed($transactionId, $transactionAction, bool $throwException = true): bool|array|null
    {
        return $this->sdkService->call('isActionAllowed', [
            'transactionId' => $transactionId,
            'transactionAction' => $transactionAction,
            'throwException' => $throwException,
        ]);
    }

    /**
     * @param $transactionId
     * @param $transactionAction
     * @param bool $throwException
     * @return bool|array|null
     */
    public function isPartialActionAllowed(
        $transactionId,
        $transactionAction,
        bool $throwException = true
    ): bool|array|null {
        return $this->sdkService->call('isPartialActionAllowed', [
            'transactionId' => $transactionId,
            'transactionAction' => $transactionAction,
            'throwException' => $throwException,
        ]);
    }

    /**
     * @param $orderId
     * @return array|null
     */
    public function getActions($orderId): array|null
    {
        // todo: handle if payever payment more than 1
        $payments = $this->paymentContract->getPaymentsByOrderId($orderId);

        $actions = [];
        foreach ($payments as $payment) {
            if (!$this->paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                continue;
            }

            $transactionId = $this->paymentHelper->getPaymentPropertyValue(
                $payment,
                PaymentProperty::TYPE_TRANSACTION_ID
            );

            if (empty($transactionId)) {
                continue;
            }

            $transaction = $this->getTransaction($transactionId);

            $actions = $transaction['result']['actions'];
        }

        return $actions;
    }

    /**
     * Get required actions: cancel, refund, shipping_goods, claim, claim_upload
     * @param $orderId
     * @return array
     */
    public function getRequiredActions($orderId): array
    {
        $actions = $this->getActions($orderId);
        $allowedActions = [
            self::ACTION_CANCEL,
            self::ACTION_REFUND,
            self::ACTION_SHIPPING_GOODS,
            self::ACTION_CLAIM,
            self::ACTION_CLAIM_UPLOAD,
        ];

        $requiredActions = [];
        foreach ($actions as $action) {
            if (in_array($action['action'], $allowedActions)) {
                $requiredActions[] = $action;
            }
        }

        return $requiredActions;
    }

    /**
     * @param $orderId
     * @return string[]
     */
    public function getTransactionsId($orderId): array
    {
        $payments = $this->paymentContract->getPaymentsByOrderId($orderId);

        $transactions = [];
        foreach ($payments as $payment) {
            if (!$this->paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                continue;
            }

            $transactionId = $this->paymentHelper->getPaymentPropertyValue(
                $payment,
                PaymentProperty::TYPE_TRANSACTION_ID
            );

            if (empty($transactionId)) {
                continue;
            }

            $transactions[] = $transactionId;
        }

        return $transactions;
    }

    /**
     * @param $orderId
     * @param bool $onlyWithTransactionId
     * @return Payment[]
     */
    public function getPayeverPaymentsByOrderId($orderId, bool $onlyWithTransactionId = true): array
    {
        $payments = $this->paymentContract->getPaymentsByOrderId($orderId);

        $payeverPayments = [];
        foreach ($payments as $payment) {
            if (!$this->paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                continue;
            }

            $transactionId = $this->paymentHelper->getPaymentPropertyValue(
                $payment,
                PaymentProperty::TYPE_TRANSACTION_ID
            );

            if ($onlyWithTransactionId && empty($transactionId)) {
                continue;
            }

            $payeverPayments[] = $payment;
        }

        return $payeverPayments;
    }

    /**
     * @param Payment $payment
     * @return string
     */
    public function getPaymentTransactionId(Payment $payment): string
    {
        return $this->paymentHelper->getPaymentPropertyValue(
            $payment,
            PaymentProperty::TYPE_TRANSACTION_ID
        );
    }

    /**
     * @param $method
     * @param $paymentParameters
     * @return mixed
     */
    private function createPayment($method, $paymentParameters)
    {
        if (self::API_V2 === (int)$this->config->get('Payever.api_version')) {
            return $this->sdkService->call(
                'createPaymentV2Request',
                ['payment_parameters' => $paymentParameters]
            );
        }

        if ($this->isSubmitMethod($method)) {
            return $this->sdkService->call(
                'submitPaymentV3Request',
                ['payment_parameters' => $paymentParameters]
            );
        }

        return $this->sdkService->call(
            'createPaymentV3Request',
            ['payment_parameters' => $paymentParameters]
        );
    }

    /**
     * @param $createPaymentResponse
     * @return string
     */
    private function getRedirectUrlByThePaymentStatus($createPaymentResponse)
    {
        $redirectUrl = $this->routesHelper->getSuccessURL();
        $paymentStatus = $createPaymentResponse['result']['status'];
        $paymentId = $createPaymentResponse['result']['id'];

        if (StatusHelper::STATUS_DECLINED === $paymentStatus || StatusHelper::STATUS_FAILED === $paymentStatus) {
            $redirectUrl = $this->routesHelper->getFailureURL();
        }

        if (StatusHelper::STATUS_CANCELLED === $paymentStatus) {
            $redirectUrl = $this->routesHelper->getCancelURL();
        }

        return \str_replace('--PAYMENT-ID--', $paymentId, $redirectUrl);
    }
}
