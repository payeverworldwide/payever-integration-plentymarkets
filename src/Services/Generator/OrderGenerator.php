<?php

namespace Payever\Services\Generator;

use IO\Services\OrderService;
use Payever\Contracts\PendingPaymentRepositoryContract;
use Payever\Helper\PayeverHelper;
use Payever\Traits\Logger;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Models\Country;

/**
 * OrderGenerator
 */
class OrderGenerator
{
    use Logger;

    /**
     * @var BasketRepositoryContract
     */
    private BasketRepositoryContract $basketRepository;

    /**
     * @var BasketItemRepositoryContract
     */
    private BasketItemRepositoryContract $basketItemRepository;

    /**
     * @var CountryRepositoryContract
     */
    private CountryRepositoryContract $countryRepository;

    /**
     * @var AddressRepositoryContract
     */
    private AddressRepositoryContract $addressRepository;

    /**
     * @var PendingPaymentRepositoryContract
     */
    private PendingPaymentRepositoryContract $pendingPaymentRepository;

    /**
     * @var PayeverHelper
     */
    private PayeverHelper $payeverHelper;

    /**
     * @var Checkout
     */
    private Checkout $checkout;

    /**
     * @var OrderService
     */
    private OrderService $orderService;

    /**
     * @param BasketRepositoryContract $basketRepository
     * @param BasketItemRepositoryContract $basketItemRepository
     * @param CountryRepositoryContract $countryRepository
     * @param AddressRepositoryContract $addressRepository
     * @param PendingPaymentRepositoryContract $pendingPaymentRepository
     * @param PayeverHelper $payeverHelper
     * @param Checkout $checkout
     * @param OrderService $orderService
     */
    public function __construct(
        BasketRepositoryContract $basketRepository,
        BasketItemRepositoryContract $basketItemRepository,
        CountryRepositoryContract $countryRepository,
        AddressRepositoryContract $addressRepository,
        PendingPaymentRepositoryContract $pendingPaymentRepository,
        PayeverHelper $payeverHelper,
        Checkout $checkout,
        OrderService $orderService
    ) {
        $this->basketRepository = $basketRepository;
        $this->basketItemRepository = $basketItemRepository;
        $this->countryRepository = $countryRepository;
        $this->addressRepository = $addressRepository;
        $this->pendingPaymentRepository = $pendingPaymentRepository;
        $this->payeverHelper = $payeverHelper;
        $this->checkout = $checkout;
        $this->orderService = $orderService;
    }

    /**
     * Generated new plenty order
     *
     * @param array $payeverPayment
     *
     * @return int
     */
    public function generate(array $payeverPayment): int
    {
        $reference = $payeverPayment['reference'];

        // Delete current basket and create a new one
        $this->basketRepository->deleteBasket();

        // Check if pending basket exists in db, if not, create a new basket by payever payment
        $pendingPayment = $this->pendingPaymentRepository->getByOrderId($reference);
        $pendingPayment
            ? $this->preparePendingBasketByReference($pendingPayment->data, $reference)
            : $this->prepareNewBasketByPayeverPayment($payeverPayment);

        $orderData = $this->orderService->placeOrder();
        $this->basketRepository->deleteBasket();

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.successfulCreatingPlentyOrder',
            'Successful Creating Plenty Order',
            ['payeverPayment' => $payeverPayment]
        )->setReferenceValue($orderData->order->id);

        if ($pendingPayment) {
            $this->pendingPaymentRepository->delete($pendingPayment);
            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.pendingPaymentRemoved',
                'Pending payment for order is removed',
                [$reference]
            )->setReferenceValue($orderData->order->id);
        }

        return $orderData->order->id;
    }

    /**
     * Register customer address with payever address
     *
     * @param array $payeverAddress
     * @param string $email
     * @return Address
     */
    public function registerCustomerAddressFromPayever(array $payeverAddress, string $email): Address
    {
        /** @var Address $address */
        $address = pluginApp(Address::class);
        $address->name2 = $payeverAddress['first_name'];
        $address->name3 = $payeverAddress['last_name'];

        /** @var Country $country */
        $country = $this->countryRepository->getCountryByIso($payeverAddress['country_name'], 'isoCode2');
        preg_match('/^([^\d]*[^\d\s]) *(\d.*)$/', $payeverAddress['street'], $parsedAddress);

        $address->address1 = $parsedAddress[1];
        $address->address2 = $parsedAddress[2];

        $address->town = $payeverAddress['city'];
        $address->postalCode = $payeverAddress['zip_code'];
        $address->countryId = $country->id;

        /** @var AddressOption $addressOption */
        $addressOption = pluginApp(AddressOption::class);
        $addressOption->typeId = AddressOption::TYPE_EMAIL;
        $addressOption->value = $email;

        $address->options->push($addressOption->toArray());

        return $this->addressRepository->createAddress($address->toArray());
    }

    /**
     * Register customer address with payever address
     *
     * @param array $payeverPayment
     * @return Basket
     */
    public function prepareBasketItemsByPayeverPayment(array $payeverPayment): Basket
    {
        $basket = $this->basketRepository->load(true);

        $basketData = $basket->toArray();
        unset($basketData['basketItems']);

        $this->basketRepository->save($basketData);

        foreach ($payeverPayment['items'] as $basketItem) {
            $this->basketItemRepository->addBasketItem([
                'basketId' => $basket->id,
                'sessionId' => $basket->sessionId,
                'variationId' => $basketItem['identifier'],
                'quantity' => $basketItem['quantity'],
            ]);
        }

        return $basket;
    }

    /**
     * Fill the basket with stored order data
     *
     * @param array $basketData
     * @param string $reference
     */
    private function preparePendingBasketByReference(array $basketData, string $reference)
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.preparePendingBasketByReference',
            'Pending payment for order is loaded',
            []
        )->setReferenceValue($reference);

        $basketItems = $basketData['basketItems'];
        $basket = $this->basketRepository->load(true);

        $basketData['id'] = $basket->id;
        $basketData['sessionId'] = $basket->sessionId;
        unset($basketData['basketItems']);

        $this->basketRepository->save($basketData);

        if (is_array($basketItems)) {
            foreach ($basketItems as $basketItem) {
                $this->basketItemRepository->addBasketItem([
                    'basketId' => $basket->id,
                    'sessionId' => $basket->sessionId,
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

        if (isset($basketData['customerInvoiceAddressId']) && $basketData['customerInvoiceAddressId']) {
            $this->checkout->setCustomerInvoiceAddressId($basketData['customerInvoiceAddressId']);
        }

        if (isset($basketData['customerShippingAddressId']) && $basketData['customerShippingAddressId']) {
            $this->checkout->setCustomerShippingAddressId($basketData['customerShippingAddressId']);
        }
    }

    /**
     * Fill the basket with data from the payever payment
     *
     * @param array $payeverPayment
     */
    private function prepareNewBasketByPayeverPayment(array $payeverPayment)
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.prepareNewBasketByPayeverPayment',
            'New payment for order is loaded',
            ['payeverPayment' => $payeverPayment]
        );

        $mopId = (int)$this->payeverHelper->getPaymentMopId($payeverPayment['payment_type']);
        $basket = $this->basketRepository->load(true);

        foreach ($payeverPayment['items'] as $basketItem) {
            $this->basketItemRepository->addBasketItem([
                'basketId' => $basket->id,
                'sessionId' => $basket->sessionId,
                'variationId' => $basketItem['identifier'],
                'quantity' => $basketItem['quantity'],
            ]);
        }

        $shippingAddress = $this->registerCustomerAddressFromPayever(
            $payeverPayment['shipping_address'],
            $payeverPayment['customer_email']
        );

        $billingAddress = $this->registerCustomerAddressFromPayever(
            $payeverPayment['address'],
            $payeverPayment['customer_email']
        );

        $this->checkout->setCustomerShippingAddressId($shippingAddress->id);
        $this->checkout->setCustomerInvoiceAddressId($billingAddress->id);

        $this->checkout->setShippingCountryId($shippingAddress->countryId);
        $this->checkout->setPaymentMethodId($mopId);
        $this->checkout->setCurrency($payeverPayment['currency']);
        $this->checkout->setBasketReferrerId($basket->referrerId);
    }
}
