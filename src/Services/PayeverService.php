<?php

namespace Payever\Services;

use Exception;
use IO\Models\LocalizedOrder;
use IO\Services\OrderService;
use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Contracts\OrderTotalRepositoryContract;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Helper\PayeverHelper;
use Payever\Traits\Logger;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use Payever\Services\PayeverSdkService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class PayeverService
{
    use Logger;

    const MR_SALUTATION = 'mr';
    const MS_SALUTATION = 'ms';

    const IFRAME_MODE = 0;
    const REDIRECT_MODE = 1;
    const REDIRECT_AND_IFRAME_MODE = 2;

    const PLENTY_FEMALE_SALUTATION = 'female';
    const PLENTY_MALE_SALUTATION = 'male';

    const ACTION_CANCEL = 'cancel';
    const ACTION_REFUND = 'refund';
    const ACTION_SHIPPING_GOODS = 'shipping_goods';

    /**
     * @var AuthHelper
     */
    private $authHelper;

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
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     * @var PaymentOrderRelationRepositoryContract
     */
    private $paymentOrderRelationRepo;

    /**
     * @var OrderRepositoryContract
     */
    private $orderRepository;

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
     * @var BasketItemRepositoryContract
     */
    private $basketItemRepository;

    /**
     * @var PendingPaymentRepositoryContract
     */
    private $pendingPaymentRepository;

    /**
     * @var Checkout Checkout
     */
    private $checkout;

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
     * @param AuthHelper $authHelper
     * @param AccountService $accountService
     * @param CountryRepositoryContract $countryRepository
     * @param ItemRepositoryContract $itemRepository
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
     * @param BasketRepositoryContract $basketRepository
     * @param BasketItemRepositoryContract $basketItemRepository
     * @param PendingPaymentRepositoryContract $pendingPaymentRepository
     * @param Checkout $checkout
     * @param OrderTotalRepositoryContract $orderTotalRepository
     * @param OrderTotalItemRepositoryContract $orderTotalItemRepository
     * @param PayeverHelper $paymentHelper
     * @param PaymentRepositoryContract $paymentContract
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        AuthHelper $authHelper,
        AccountService $accountService,
        CountryRepositoryContract $countryRepository,
        ItemRepositoryContract $itemRepository,
        PaymentMethodRepositoryContract $paymentMethodRepository,
        PaymentRepositoryContract $paymentRepository,
        PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo,
        OrderRepositoryContract $orderRepository,
        ConfigRepository $config,
        PayeverHelper $payeverHelper,
        AddressRepositoryContract $addressRepo,
        ContactRepositoryContract $contactRepository,
        FrontendSessionStorageFactoryContract $sessionStorage,
        PayeverSdkService $sdkService,
        BasketRepositoryContract $basketRepository,
        BasketItemRepositoryContract $basketItemRepository,
        PendingPaymentRepositoryContract $pendingPaymentRepository,
        Checkout $checkout,
        OrderTotalRepositoryContract $orderTotalRepository,
        OrderTotalItemRepositoryContract $orderTotalItemRepository,
        PayeverHelper $paymentHelper,
        PaymentRepositoryContract $paymentContract,
    ) {
        $this->authHelper = $authHelper;
        $this->accountService = $accountService;
        $this->countryRepository = $countryRepository;
        $this->itemRepository = $itemRepository;
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
        $this->basketRepository = $basketRepository;
        $this->basketItemRepository = $basketItemRepository;
        $this->pendingPaymentRepository = $pendingPaymentRepository;
        $this->checkout = $checkout;
        $this->orderTotalRepository = $orderTotalRepository;
        $this->orderTotalItemRepository = $orderTotalItemRepository;
        $this->paymentHelper = $paymentHelper;
        $this->paymentContract = $paymentContract;
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
    public function isSubmitMethod(string $method): bool
    {
        if ($this->config->has('Payever.' . $method . '.redirect_method')) {
            return (bool)$this->config->get('Payever.' . $method . '.redirect_method');
        }

        return false;
    }

    /**
     * @param Basket $basket
     * @return Address
     */
    private function getBillingAddress(Basket $basket): Address
    {
        $addressId = $basket->customerInvoiceAddressId;

        return $this->addressRepo->findAddressById($addressId);
    }

    /**
     * @param Basket $basket
     * @return Address
     */
    private function getShippingAddress(Basket $basket): Address
    {
        $addressId = $basket->customerShippingAddressId ?? $basket->customerInvoiceAddressId;

        return $this->addressRepo->findAddressById($addressId);
    }

    /**
     * @param Address $address
     * @return array
     */
    private function getAddress(Address $address): array
    {
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
            'country' => $country
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
            default:
                return false;
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

            $checkoutMode = $this->isSubmitMethod($method)
                ? self::REDIRECT_MODE
                : $this->config->get('Payever.redirect_to_payever');
            switch ($checkoutMode) {
                case self::IFRAME_MODE:
                    $this->returnType = 'htmlContent';
                    $paymentContent = <<<HTML
<iframe allow="payment" sandbox="allow-same-origin allow-forms allow-top-navigation allow-scripts allow-modals allow-popups"
 style="width: 100%; height: 700px" frameborder="0" src="$redirectUrl"></iframe>
HTML;
                    break;
                case self::REDIRECT_MODE:
                    $this->returnType = 'redirectUrl';
                    $paymentContent = $redirectUrl;
                    break;
                case self::REDIRECT_AND_IFRAME_MODE:
                    $this->returnType = 'redirectUrl';
                    $this->sessionStorage->getPlugin()->setValue('payever_iframe_url', $redirectUrl);
                    $paymentContent = $this->payeverHelper->getIframeURL($method);
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
     * @return mixed
     */
    public function processCreatePaymentRequest(Basket $basket, string $method, $orderId = null)
    {
        $contactId = $this->accountService->getAccountContactId();
        $feeAmount = $this->getFeeAmount($basket->basketAmount, $method);
        $address = $this->getAddress($this->getBillingAddress($basket));
        $shippingAddress = $this->getAddress($this->getShippingAddress($basket));
        if (!empty($contactId) && $contactId > 0) {
            $customer = $this->contactRepository->findContactById($contactId);
            $email = $customer->email;
        } else {
            $email = $address['email'];
        }

        $orderId = $orderId ?? md5($basket->id . $method . time());
        $paymentParameters = [
            'amount' => round(($basket->basketAmount - $feeAmount), 2), // basketAmount
            'fee' => round(($basket->shippingAmount - $feeAmount), 2),
            'order_id' => $orderId,
            'currency' => $basket->currency,
            'cart' => $this->getBasketItems($basket),
            'payment_method' => $method,
            'email' => $email,
            'phone' => $address['phone'],
            'shipping_address' => $shippingAddress,
            'success_url' => $this->payeverHelper->getSuccessURL(),
            'failure_url' => $this->payeverHelper->getFailureURL(),
            'cancel_url' => $this->payeverHelper->getCancelURL(),
            'notice_url' => $this->payeverHelper->getNoticeURL(),
        ];
        $payeverPaymentId = '';

        $paymentParameters['billing_address'] = $address;
        $paymentParameters['force_redirect'] = $this->isSubmitMethod($method);

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.paymentParameters',
            'Payment request',
            ['paymentParameters' => $paymentParameters]
        );

        $paymentResponse = $this->sdkService->call(
            'createPaymentV2Request',
            ['payment_parameters' => $paymentParameters]
        );

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.processCreatePaymentRequest',
            'createPaymentV2Request Response',
            ['paymentResponse' => $paymentResponse]
        );

        $pendingPayment = $this->pendingPaymentRepository->getByOrderId($orderId);
        if (!$pendingPayment) {
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
        }

        return $paymentResponse;
    }

    /**
     * @param mixed $orderId
     */
    public function prepareBasket($orderId)
    {
        $basket = $this->basketRepository->load();
        if (count($basket->basketItems) == 0) {
            $pendingPayment = $this->pendingPaymentRepository->getByOrderId($orderId);
            if ($pendingPayment) {
                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.checkoutDebug',
                    sprintf('Pending payment for order %s is loaded', $orderId),
                    []
                )->setReferenceValue($orderId);

                $sessionId = $basket->sessionId;
                $data = $pendingPayment->data;
                $basketData = $data;

                unset($basketData['basketItems']);
                $basketData['id'] = $basket->id;
                $basketData['sessionId'] = $sessionId;

                $this->basketRepository->save($basketData);

                if (is_array($data['basketItems'])) {
                    foreach ($data['basketItems'] as $basketItem) {
                        $this->basketItemRepository->addBasketItem([
                            'basketId' => $basket->id,
                            'sessionId' => $sessionId,
                            'variationId' => $basketItem['variationId'],
                            'quantity' => $basketItem['quantity'],
                        ]);
                    }
                }

                $this->checkout->setShippingCountryId($basketData['shippingCountryId']);
                $this->checkout->setPaymentMethodId($basketData['methodOfPaymentId']);
                $this->checkout->setShippingProfileId($basketData['shippingProfileId']);
                $this->checkout->setCurrency($basketData['currency']);
                $this->checkout->setBasketReferrerId($basketData['referrerId']);

                if ($basketData['customerInvoiceAddressId']) {
                    $this->checkout->setCustomerInvoiceAddressId($basketData['customerInvoiceAddressId']);
                }

                if ($basketData['customerShippingAddressId']) {
                    $this->checkout->setCustomerShippingAddressId($basketData['customerShippingAddressId']);
                }
            }
        }
    }

    /**
     * @param bool $executePayment
     * @return LocalizedOrder
     * @throws Exception
     */
    public function placeOrder($executePayment = true)
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.placeOrderCalling',
            sprintf('PlaceOrder was called, with executePayment = %s', $executePayment),
            []
        );

        $orderData = $this->getOrderService()->placeOrder();

        if ($executePayment) {
            $orderId = $orderData->order->id;
            $mopId = $orderData->order->methodOfPaymentId;

            $paymentResult = $this->getOrderService()->executePayment($orderId, $mopId);

            if ($paymentResult['type'] === 'error') {
                // send errors
                throw new Exception($paymentResult['value']);
            }
        }

        return $orderData;
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

        $orderData = $this->placeOrder(false);
        $createPaymentResponse = $this->processCreatePaymentRequest(
            $basket,
            $method,
            $orderData->order->id
        );

        if ($createPaymentResponse['error']) {
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

        foreach ($orderData->order->orderItems as $orderItem) {
            $this->orderTotalItemRepository->create($orderItem);
        }

        $this->log(
            'debug',
            __METHOD__ . ' $orderTotal',
            'Payever::debug.orderTotal',
            'Order total',
            ['orderTotal' => $orderTotal]
        );

        return $createPaymentResponse['redirect_url'];
    }

    /**
     * @return string
     */
    public function getReturnType(): string
    {
        return $this->returnType;
    }

    /**
     * Load the mandatory payever data from session
     *
     * @return mixed
     */
    public function getPayeverPaymentId()
    {
        return $this->sessionStorage->getPlugin()->getValue('payever_payment_id');
    }

    /**
     * Execute the payever payment
     *
     * @param string $paymentId
     * @return array|string
     */
    public function pluginExecutePayment(string $paymentId)
    {
        $executeResponse = [];
        if (!empty($paymentId)) {
            $retrievePayment = $this->sdkService->call('retrievePaymentRequest', ['payment_id' => $paymentId]);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.executePaymentRetrieve',
                'Retrieve payment',
                ['retrievePayment' => $retrievePayment]
            );

            if (!empty($retrievePayment['error'])) {
                $this->returnType = 'errorCode';

                return $this->payeverHelper->retrieveErrorMessageFromSdkResponse($retrievePayment);
            } else {
                $retrieveResponse = $retrievePayment['result'];

                $total = $retrieveResponse['total'];
                $method = $retrieveResponse['payment_type'];
                $feeAmount = $this->getFeeAmount($total, $method);
                $total = round(($total - $feeAmount), 2);

                $executeResponse['status'] = $retrieveResponse['status'];
                $executeResponse['currency'] = $retrieveResponse['currency'];
                $executeResponse['amount'] = $total;
                $executeResponse['entryDate'] = $retrieveResponse['created_at'];
                $executeResponse['transactionId'] = $retrieveResponse['id'];
                $executeResponse['email'] = $retrieveResponse['customer_email'];
                $executeResponse['nameOfSender'] = $retrieveResponse['customer_name'];
                $executeResponse['reference'] = $retrieveResponse['reference'];
                $executeResponse['usage_text'] = $retrieveResponse['payment_details']['usage_text'];

                $this->sessionStorage->getPlugin()->unsetKey('payever_payment_id');
            }
        } else {
            $this->returnType = 'errorCode';

            $this->log(
                'error',
                __METHOD__,
                'Payever::debug.Error',
                'The payment ID is lost!',
                []
            );

            return 'The payment ID is lost!';
        }

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.executePaymentResponse',
            'pluginExecutePayment Response',
            ['executeResponse' => $executeResponse]
        );

        // Check for errors
        // @codeCoverageIgnoreStart
        if (is_array($executeResponse) && !empty($executeResponse['error'])) {
            $this->returnType = 'errorCode';

            return $executeResponse['error'] . ': ' . $executeResponse['error_msg'];
        }
        // @codeCoverageIgnoreEnd

        return $executeResponse;
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
                    'quantity' => (int)$basketItem->quantity,
                    'identifier' => (string)$basketItem->variationId,
                    'vatRate' => (float)$basketItem->vat,
                    'description' => '',
                    'thumbnail' => '',
                    'url' => '',
                ];

                $basketItems[] = $basketItem;
            }
        }

        return $basketItems;
    }

    /**
     * Execute payment if it doesn't exist
     *
     * @param array $payeverPayment
     */
    public function originExecutePayment(array $payeverPayment)
    {
        $mopId = (int)$this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);
        if (!$this->getCreatedPlentyPayment($payeverPayment, $mopId)) {
            $this->paymentMethodRepository->executePayment($mopId, (int)$payeverPayment['reference']);
        }
    }

    /**
     * @param array $payeverPayment
     * @param int $mopId
     * @return Payment|bool
     */
    public function getCreatedPlentyPayment(array $payeverPayment, int $mopId)
    {
        $payments = $this->paymentRepository->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment['id']
        );
        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ((int)$payment->mopId == $mopId) {
                return $payment;
            }
        }

        return false;
    }

    /**
     * @param int $orderId
     * @param int $mopId
     * @return bool
     */
    public function isAssignedPlentyPayment(int $orderId, int $mopId): bool
    {
        $payments = $this->paymentRepository->getPaymentsByOrderId($orderId);
        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ((int)$payment->mopId == $mopId) {
                return true;
            }
        }

        return false;
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
        );

        return $retrievePayment['result'] ?? [];
    }

    /**
     * @param array $payeverPayment
     * @return bool|Payment
     */
    public function createAndUpdatePlentyPayment(array $payeverPayment)
    {
        $mopId = (int)$this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);
        $plentyPayment = $this->getCreatedPlentyPayment($payeverPayment, $mopId);
        if (!$plentyPayment) {
            $payeverPaymentData = $this->pluginExecutePayment($payeverPayment['id']);

            if ($this->getReturnType() == 'errorCode') {
                return false;
            }

            // Create a plentymarkets payment from the payever execution params
            $plentyPayment = $this->createPlentyPayment($payeverPaymentData, $mopId);
        }

        $orderId = (int)$payeverPayment['reference'];
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
        $payment->receivedAt = date('Y-m-d H:i:s', strtotime($payeverPayment['entryDate']));
        $paymentProperty = [];
        $bookingText = !empty($payeverPayment['usage_text'])
            ? 'Payment reference: ' . $payeverPayment['usage_text']
            : '';
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

        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_ORIGIN,
            Payment::ORIGIN_PLUGIN
        );
        $paymentProperty[] = $this->payeverHelper->getPaymentProperty(
            PaymentProperty::TYPE_PAYMENT_TEXT,
            $payeverPayment['usage_text']
        );
        $payment->properties = $paymentProperty;
        $payment = $this->paymentRepository->createPayment($payment);

        return $payment;
    }

    /**
     * @param string $transactionId
     * @param string $status
     * @param bool|string $notificationTime
     * @return bool|Payment
     */
    public function updatePlentyPayment(string $transactionId, string $status, $notificationTime = false)
    {
        /** @var Payment[] $payments */
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
        $this->authHelper->processUnguarded(
            function () use ($orderId, $payment) {
                // Get the order by the given order ID
                $order = $this->orderRepository->findOrderById($orderId);

                // Check whether the order truly exists in plentymarkets
                if (!is_null($order) && $order instanceof Order) {
                    // Assign the given payment to the given order
                    $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);
                }

                $transactionId = $this->payeverHelper->getPaymentPropertyValue(
                    $payment,
                    PaymentProperty::TYPE_TRANSACTION_ID
                );

                $this->log(
                    'debug',
                    __METHOD__,
                    'Payever::debug.assignPlentyPaymentToPlentyOrder',
                    'Transaction ' . $transactionId . ' was assigned to the order #' . $orderId,
                    ['transactionId' => $transactionId, 'orderId' => $orderId]
                );
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
            $statusId = $this->payeverHelper->mapOrderStatus($paymentStatus);
            $this->authHelper->processUnguarded(
                function () use ($orderId, $statusId, $paymentStatus) {
                    //unguarded
                    $order = $this->orderRepository->findOrderById($orderId);
                    if (!is_null($order) && $order instanceof Order) {
                        $status = [
                            'statusId' => (float)$statusId,
                        ];
                        $this->orderRepository->updateOrder($status, $orderId);

                        $this->log(
                            'debug',
                            __METHOD__,
                            'Payever::debug.updateOrderStatus',
                            'Status of order ' . $orderId . ' was changed to ' . $statusId,
                            ['statusId' => $statusId, 'orderId' => $orderId]
                        );

                        $orderTotal = $this->orderTotalRepository->getByOrderId($orderId);

                        if (
                            PayeverHelper::PLENTY_ORDER_SUCCESS == $statusId
                            && $orderTotal->capturedTotal == 0
                            && $paymentStatus !== PayeverHelper::STATUS_ACCEPTED
                        ) {
                            $orderTotalItems = $this->orderTotalItemRepository->getByOrderId($orderId);

                            $amount = 0;
                            foreach ($orderTotalItems as $orderTotalItem) {
                                $amount += $orderTotalItem->totalPrice;

                                $orderTotalItem->qtyCaptured = $orderTotalItem->quantity;

                                $this->orderTotalItemRepository->persist($orderTotalItem);
                            }

                            $orderTotal->capturedTotal = $amount;

                            $this->orderTotalRepository->persist($orderTotal);
                        }
                    }
                }
            );
        } catch (Exception $exception) {
            $this->log(
                'error',
                __METHOD__,
                'Payever::debug.updateOrderStatus',
                'Exception: ' . $exception,
                []
            );
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
        return $this->sdkService->call(
            'refundPaymentRequest',
            ['transaction_id' => $transactionId, 'amount' => $amount]
        );
    }

    /**
     * @param string $transactionId
     * @param array $items
     * @param float|null $deliveryFee
     * @return mixed
     */
    public function refundItemsPayment(string $transactionId, array $items, float $deliveryFee = null): mixed
    {
        return $this->sdkService->call(
            'refundItemsPaymentRequest',
            ['transaction_id' => $transactionId, 'items' => $items, 'deliveryFee' => $deliveryFee]
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
        return $this->sdkService->call('getTransactionRequest', ['transaction_id' => $transactionId]);
    }

    /**
     * Cancel the given payment
     *
     * @param string $transactionId
     * @param float $amount
     * @return bool|mixed
     */
    public function cancelPayment(string $transactionId, float $amount = null)
    {
        return $this->sdkService->call(
            'cancelPaymentRequest',
            ['transaction_id' => $transactionId, 'amount' => $amount]
        );
    }

    /**
     * @param string $transactionId
     * @param array $items
     * @param float|null $deliveryFee
     * @return mixed
     */
    public function cancelItemsPayment(string $transactionId, array $items, float $deliveryFee = null): mixed
    {
        return $this->sdkService->call(
            'cancelItemsPaymentRequest',
            ['transaction_id' => $transactionId, 'items' => $items, 'deliveryFee' => $deliveryFee]
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
        $trackingUrl
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
                'shippingDate' => date('Y-m-d\TH:i:sO')
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
                'shippingDate' => date('Y-m-d\TH:i:sO')
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
            'throwException' => $throwException
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
            'throwException' => $throwException
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
     * Get required actions: cancel, refund, shipping_goods
     * @param $orderId
     * @return array
     */
    public function getRequiredActions($orderId): array
    {
        $actions = $this->getActions($orderId);

        $requiredActions = [];
        foreach ($actions as $action) {
            if (
                $action['action'] === self::ACTION_CANCEL
                || $action['action'] === self::ACTION_REFUND
                || $action['action'] === self::ACTION_SHIPPING_GOODS
            ) {
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
}
