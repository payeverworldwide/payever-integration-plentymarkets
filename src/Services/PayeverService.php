<?php

namespace Payever\Services;

use IO\Models\LocalizedOrder;
use IO\Services\OrderService;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Helper\PayeverHelper;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
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

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PayeverService
{
    use Loggable;

    const MR_SALUTATION = 'mr';
    const MS_SALUTATION = 'ms';

    const IFRAME_MODE = 0;
    const REDIRECT_MODE = 1;
    const REDIRECT_AND_IFRAME_MODE = 2;

    const PLENTY_FEMALE_SALUTATION = 'female';
    const PLENTY_MALE_SALUTATION = 'male';

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
        Checkout $checkout
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
                'price' => floatval($basketItem['price']),
                'quantity' => intval($basketItem['quantity']),
                'sku' => $basketItem['sku'],
                'vatRate' => floatval($basketItem['vat']),
                'description' => 'description',
                'thumbnail' => '',
                'url' => '',
            ];
        }

        return $products;
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
            return (bool) $this->config->get('Payever.' . $method . '.redirect_method');
        }

        return false;
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
     * @param Address $address
     * @return array
     */
    private function getAddress(Address $address): array
    {
        return [
            'city' => $address->town,
            'email' => $address->email,
            'salutation' => $this->getSalutation((string) $address->gender),
            'last_name' => $address->lastName,
            'first_name' => $address->firstName,
            'phone' => $address->phone,
            'zip' => $address->postalCode,
            'street' => $address->street . ' ' . $address->houseNumber,
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
<iframe sandbox="allow-same-origin allow-forms allow-top-navigation allow-scripts allow-modals allow-popups"
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
                throw new \Exception('An unknown error occurred, please try again.');
            }
        } catch (\Exception $exception) {
            $this->returnType = 'errorCode';
            $paymentContent = $exception->getMessage();
            $this->getLogger(__METHOD__)->error('Payever::debug.createPaymentResponse', $exception->getMessage());
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

        $payeverRequestParams = $this->getPayeverParams($basket);
        $feeAmount = $this->getFeeAmount($basket->basketAmount, $method);
        $address = $this->getAddress($this->getBillingAddress($basket));
        if (!empty($contactId) && $contactId > 0) {
            $customer = $this->contactRepository->findContactById($contactId);
            $email = $customer->email;
        } else {
            $email = $address['email'];
        }

        $orderId = $orderId ?? md5($basket->id . $method . time());

        $paymentParameters = [
            'channel' => 'plentymarkets',
            'amount' => round(($basket->basketAmount - $feeAmount), 2), // basketAmount
            'fee' => round(($basket->shippingAmount - $feeAmount), 2),
            'order_id' => $orderId,
            'currency' => $basket->currency,
            'cart' => $this->getOrderProducts($payeverRequestParams['basketItems']),
            'payment_method' => $method,
            'salutation' => $address['salutation'],
            'first_name' => $address['first_name'],
            'last_name' => $address['last_name'],
            'city' => $address['city'],
            'zip' => $address['zip'],
            'street' => $address['street'],
            'country' => $payeverRequestParams['country']['isoCode2'],
            'email' => $email,
            'phone' => $address['phone'],
            'success_url' => $this->payeverHelper->getSuccessURL(),
            'failure_url' => $this->payeverHelper->getFailureURL(),
            'cancel_url' => $this->payeverHelper->getCancelURL(),
            'notice_url' => $this->payeverHelper->getNoticeURL(),
        ];

        $payeverPaymentId = '';
        if ($this->isSubmitMethod($method)) {
            $paymentParameters['finish_url'] = sprintf(
                '%s?reference=%s&token=%s',
                $this->payeverHelper->getFinishURL(),
                $orderId,
                hash_hmac(
                    'sha256',
                    $this->config->get('Payever.clientId') . $orderId,
                    (string) $this->config->get('Payever.clientSecret')
                )
            );
            $this->getLogger(__METHOD__)->debug('Payever::debug.submitPaymentParameters', $paymentParameters);
            $paymentResponse = $this->sdkService->call(
                'submitPaymentRequest',
                ['payment_parameters' => $paymentParameters]
            );
            $this->getLogger(__METHOD__)->debug('Payever::debug.submitPaymentResponse', $paymentResponse);

            if (!$paymentResponse['error']) {
                $payeverPaymentId = $paymentResponse['result']['id'];
                $paymentResponse['redirect_url'] = $paymentResponse['result']['payment_details']['redirect_url'];
            }
        } else {
            $this->getLogger(__METHOD__)->debug('Payever::debug.paymentParameters', $paymentParameters);
            $paymentResponse = $this->sdkService->call(
                'createPaymentRequest',
                ['payment_parameters' => $paymentParameters]
            );
            $this->getLogger(__METHOD__)->debug('Payever::debug.createPaymentResponse', $paymentResponse);
        }
        $pendingPayment = $this->pendingPaymentRepository->getByOrderId($orderId);
        if (!$pendingPayment) {
            $pendingPayment = $this->pendingPaymentRepository->create();
            $pendingPayment->orderId = $orderId;
            $pendingPayment->payeverPaymentId = $payeverPaymentId;
            $pendingPayment->data = $basket->toArray();
            $this->getLogger(__METHOD__)->debug(
                'Payever::debug.checkoutDebug',
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
                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.checkoutDebug',
                    sprintf('Pending payment for order %s is loaded', $orderId)
                );
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
     * @throws \Exception
     */
    public function placeOrder($executePayment = true)
    {
        $this->getLogger(__METHOD__)->debug(
            'Payever::debug.placeOrderCalling',
            "PlaceOrder was called, with executePayment = $executePayment"
        );
        $orderData = $this->getOrderService()->placeOrder();

        if ($executePayment) {
            $orderId = $orderData->order->id;
            $mopId = $orderData->order->methodOfPaymentId;

            $paymentResult = $this->getOrderService()->executePayment($orderId, $mopId);

            if ($paymentResult['type'] === 'error') {
                // send errors
                throw new \Exception($paymentResult['value']);
            }
        }

        return $orderData;
    }

    /**
     * @param string $method
     * @return mixed
     * @throws \Exception
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
            throw new \Exception('Creating payment has been declined');
        }

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
            $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentRetrieve', $retrievePayment);
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
            $this->getLogger(__METHOD__)->error('Payever::debug.Error', 'The payment ID is lost!');

            return 'The payment ID is lost!';
        }
        $this->getLogger(__METHOD__)->debug('Payever::debug.executePaymentResponse', $executeResponse);
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
     * Fill and return the payever parameters
     *
     * @param Basket $basket
     * @return array
     */
    private function getPayeverParams(Basket $basket = null): array
    {
        $payeverRequestParams = [];
        $payeverRequestParams['basket'] = $basket;
        /** declarce the variable as array */
        $payeverRequestParams['basketItems'] = [];

        /** @var \Plenty\Modules\Basket\Models\BasketItem $basketItem */
        foreach ($basket->basketItems as $basketItem) {
            /** @var \Plenty\Modules\Item\Item\Models\Item $item */
            $item = $this->itemRepository->show($basketItem->itemId);
            $basketItem = $basketItem->getAttributes();
            /** @var \Plenty\Modules\Item\Item\Models\ItemText $itemText */
            $itemText = $item->texts;
            $basketItem['name'] = $itemText->first()->name1;
            $basketItem['sku'] = (string) $basketItem->variationId;
            $payeverRequestParams['basketItems'][] = $basketItem;
        }
        // Fill the country for payever parameters
        $country = [];
        $country['isoCode2'] = $this->countryRepository->findIsoCode($basket->shippingCountryId, 'iso_code_2');
        $payeverRequestParams['country'] = $country;

        return $payeverRequestParams;
    }

    /**
     * Execute payment if it doesn't exist
     *
     * @param array $payeverPayment
     */
    public function originExecutePayment(array $payeverPayment)
    {
        $mopId = (int) $this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);
        if (!$this->getCreatedPlentyPayment($payeverPayment, $mopId)) {
            $this->paymentMethodRepository->executePayment($mopId, (int) $payeverPayment['reference']);
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
        $this->getLogger(__METHOD__)->debug('Payever::debug.getPaymentDetails', $retrievePayment);

        return $retrievePayment['result'] ?? [];
    }

    /**
     * @param array $payeverPayment
     * @return bool|Payment
     */
    public function createAndUpdatePlentyPayment(array $payeverPayment)
    {
        $mopId = (int) $this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);
        $plentyPayment = $this->getCreatedPlentyPayment($payeverPayment, $mopId);
        if (!$plentyPayment) {
            $payeverPaymentData = $this->pluginExecutePayment($payeverPayment['id']);

            if ($this->getReturnType() == 'errorCode') {
                return false;
            }

            // Create a plentymarkets payment from the payever execution params
            $plentyPayment = $this->createPlentyPayment($payeverPaymentData, $mopId);
        }

        $orderId = (int) $payeverPayment['reference'];
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
                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.assignPlentyPaymentToPlentyOrder',
                    'Transaction ' . $transactionId . ' was assigned to the order #' . $orderId
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
                function () use ($orderId, $statusId) {
                    //unguarded
                    $order = $this->orderRepository->findOrderById($orderId);
                    if (!is_null($order) && $order instanceof Order) {
                        $status = [
                            'statusId' => (float)$statusId,
                        ];
                        $this->orderRepository->updateOrder($status, $orderId);
                        $this->getLogger(__METHOD__)->debug(
                            'Payever::debug.updateOrderStatus',
                            'Status of order ' . $orderId . ' was changed to ' . $statusId
                        );
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
        return $this->sdkService->call(
            'refundPaymentRequest',
            ['transaction_id' => $transactionId, 'amount' => $amount]
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
     * @return bool|mixed
     */
    public function cancelPayment(string $transactionId)
    {
        return $this->sdkService->call('cancelPaymentRequest', ['transaction_id' => $transactionId]);
    }

    /**
     * Cancel the given payment
     *
     * @param string $transactionId
     * @return bool|mixed
     */
    public function shippingGoodsPayment(string $transactionId)
    {
        return $this->sdkService->call('shippingPaymentRequest', ['transaction_id' => $transactionId]);
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
}
