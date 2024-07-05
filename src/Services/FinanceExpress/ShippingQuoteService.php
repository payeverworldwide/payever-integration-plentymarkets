<?php

namespace Payever\Services\FinanceExpress;

use IO\Services\CheckoutService;
use Payever\Services\Generator\OrderGenerator;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;

/**
 * Class ShippingQuoteService
 */
class ShippingQuoteService
{
    /**
     * @var OrderGenerator
     */
    private OrderGenerator $orderGenerator;

    /**
     * @var BasketRepositoryContract
     */
    private BasketRepositoryContract $basketRepository;

    /**
     * @var BasketItemRepositoryContract
     */
    private BasketItemRepositoryContract $basketItemRepository;

    /**
     * @var Checkout
     */
    private Checkout $checkout;

    /**
     * @param OrderGenerator $orderGenerator
     * @param BasketRepositoryContract $basketRepository
     * @param BasketItemRepositoryContract $basketItemRepository
     * @param Checkout $checkout
     */
    public function __construct(
        OrderGenerator $orderGenerator,
        BasketRepositoryContract $basketRepository,
        BasketItemRepositoryContract $basketItemRepository,
        Checkout $checkout
    ) {
        $this->orderGenerator = $orderGenerator;
        $this->basketRepository = $basketRepository;
        $this->basketItemRepository = $basketItemRepository;
        $this->checkout = $checkout;
    }

    /**
     * @param array $payload
     * @param array $widgetCart
     *
     * @return array[]
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function estimate(array $payload, array $widgetCart): array
    {
        if (!isset($payload['shipping'])) {
            throw new \Exception('Payload is empty.');
        }

        if (!$widgetCart) {
            throw new \Exception('Widget cart is empty.');
        }

        $this->basketRepository->deleteBasket();
        $basket = $this->basketRepository->load(true);
        foreach ($widgetCart as $basketItem) {
            $this->basketItemRepository->addBasketItem([
                'basketId' => $basket->id,
                'sessionId' => $basket->sessionId,
                'variationId' => $basketItem['identifier'],
                'quantity' => $basketItem['quantity'],
            ]);
        }

        $address = $this->orderGenerator->registerCustomerAddressFromPayever([
            'first_name' => $payload['shipping']['shippingAddress']['firstName'],
            'last_name' => $payload['shipping']['shippingAddress']['lastName'],
            'country_name' => $payload['shipping']['shippingAddress']['country'],
            'street' => $payload['shipping']['shippingAddress']['line1'],
            'city' => $payload['shipping']['shippingAddress']['city'],
            'zip_code' => $payload['shipping']['shippingAddress']['zipCode'],
        ], $payload['shopperEmail']);

        $this->checkout->setCustomerShippingAddressId($address->id);
        $this->checkout->setCustomerInvoiceAddressId($address->id);
        $this->checkout->setShippingCountryId($address->countryId);
        $this->checkout->setCurrency($payload['currency']);
        $this->checkout->setBasketReferrerId($basket->referrerId);

        /** @var CheckoutService $checkoutService */
        $checkoutService = pluginApp(CheckoutService::class);
        $shippingProfilesList = $checkoutService->getShippingProfileList();

        $shippingMethodsData = [];
        foreach ($shippingProfilesList as $shippingProfile) {
            $shippingMethodsData[] = [
                'price' => number_format($shippingProfile['shippingAmount'], 2, '.', ''),
                'name' => $shippingProfile['parcelServiceName'],
            ];
        }

        return $shippingMethodsData;
    }
}
