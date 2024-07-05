<?php

namespace Payever\Services\FinanceExpress;

use Payever\Helper\RoutesHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Plugin\ConfigRepository;

/**
 * Class ExpressWidgetService
 */
class ExpressWidgetService
{
    const WIDGET_PREFIX_CART = 'fe_widget_cart_';
    const WIDGET_PREFIX_PRODUCT = 'fe_widget_cart_';
    const LIVE_WIDGET_JS = 'https://widgets.payever.org/finance-express/widget.min.js';
    const STAGE_WIDGET_JS = 'https://widgets.staging.devpayever.com/finance-express/widget.min.js';

    /**
     * @var ConfigRepository
     */
    private ConfigRepository $config;

    /**
     * @var BasketRepositoryContract
     */
    private BasketRepositoryContract $basketRepositoryContract;

    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private FrontendSessionStorageFactoryContract $sessionStorage;

    /**
     * @var ItemRepositoryContract
     */
    private ItemRepositoryContract $itemRepository;

    /**
     * @var RoutesHelper
     */
    private RoutesHelper $routesHelper;

    /**
     * @param ConfigRepository $config
     * @param BasketRepositoryContract $basketRepositoryContract
     * @param ItemRepositoryContract $itemRepository
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param RoutesHelper $routesHelper
     */
    public function __construct(
        ConfigRepository $config,
        BasketRepositoryContract $basketRepositoryContract,
        ItemRepositoryContract $itemRepository,
        FrontendSessionStorageFactoryContract $sessionStorage,
        RoutesHelper $routesHelper
    ) {
        $this->config = $config;
        $this->basketRepositoryContract = $basketRepositoryContract;
        $this->itemRepository = $itemRepository;
        $this->sessionStorage = $sessionStorage;
        $this->routesHelper = $routesHelper;
    }

    /**
     * @return bool
     */
    public function isEnabledOnProductPage(): bool
    {
        return $this->config->get('Payever.fe_widget_display_on_product');
    }

    /**
     * @return bool
     */
    public function isEnabledOnCartPage(): bool
    {
        return $this->config->get('Payever.fe_widget_display_on_cart');
    }

    /**
     * @param string $reference
     *
     * @return bool
     */
    public static function isWidgetCartPayment(string $reference): bool
    {
        return strpos($reference, self::WIDGET_PREFIX_CART) !== false;
    }

    /**
     * @param string $reference
     *
     * @return bool
     */
    public static function isWidgetProductPayment(string $reference): bool
    {
        return strpos($reference, self::WIDGET_PREFIX_PRODUCT) !== false;
    }

    /**
     * Returns widget js file url
     *
     * @return string
     */
    public function getWidgetJsUrl(): string
    {
        return $this->config->get('Payever.environment')
            ? self::LIVE_WIDGET_JS
            : self::STAGE_WIDGET_JS;
    }

    /**
     * @param array $product
     *
     * @return string
     */
    public function getWidgetAttributesProductPage(array $product): string
    {
        $basketItemPrice = round($product['prices']['default']['baseSinglePrice'], 2);

        $basketItems = [
            [
                'name' => utf8_encode($product['texts']['name1']),
                'description' => utf8_encode($product['texts']['description']),
                'identifier' => (string)$product['variation']['id'],
                'amount' => $basketItemPrice,
                'price' => $basketItemPrice,
                'quantity' => 1,
                'thumbnail' => $product['images']['all'][0]['urlPreview'] ?? '',
                'unit' => 'EACH',
            ],
        ];

        return $this->getWidgetAttributes($basketItemPrice, $basketItems, self::WIDGET_PREFIX_PRODUCT);
    }

    /**
     * @return string
     */
    public function getWidgetAttributesCartPage(): string
    {
        $basket = $this->basketRepositoryContract->load();

        $basketItems = [];

        /** @var BasketItem $basketItem */
        foreach ($basket->basketItems as $basketItem) {
            if ($basketItem instanceof BasketItem) {
                $basketItemPrice = $basketItem->price + $basketItem->attributeTotalMarkup;

                /** @var \Plenty\Modules\Item\Item\Models\Item $item */
                $item = $this->itemRepository->show(
                    $basketItem->itemId,
                    [],
                    $this->sessionStorage->getLocaleSettings()->language
                );

                /** @var \Plenty\Modules\Item\Item\Models\ItemText $itemText */
                $itemText = $item->texts;

                $basketItem = [
                    'name' => utf8_encode($itemText->first()->name1),
                    'description' => utf8_encode($itemText->first()->description),
                    'identifier' => (string)$basketItem->variationId,
                    'amount' => $basketItemPrice * $basketItem->quantity,
                    'price' => $basketItemPrice,
                    'quantity' => (int)$basketItem->quantity,
                    'unit' => 'EACH',
                ];

                $basketItems[] = $basketItem;
            }
        }

        return $this->getWidgetAttributes($basket->basketAmount, $basketItems, self::WIDGET_PREFIX_CART);
    }

    /**
     * @param float $amount
     * @param array $basketItems
     * @param string $prefix
     *
     * @return string
     */
    private function getWidgetAttributes(float $amount, array $basketItems, string $prefix): string
    {
        $widgetCart = base64_encode(json_encode($basketItems));
        $dataCart = htmlspecialchars(json_encode($basketItems), ENT_QUOTES, 'UTF-8');

        $data = [
            'data-widgetId' => $this->config->get('Payever.fe_widget_id'),
            'data-checkoutId' => $this->config->get('Payever.fe_widget_checkout_id'),
            'data-business' => $this->config->get('Payever.slug'),
            'data-theme' => $this->config->get('Payever.fe_widget_theme'),
            'data-type' => 'button',
            'data-reference' => uniqid($prefix),
            'data-amount' => $amount,
            'data-cart' => $dataCart,
            'data-successurl' => $this->routesHelper->getSuccessURL(),
            'data-pendingurl' => $this->routesHelper->getPendingURL(),
            'data-cancelurl' => $this->routesHelper->getCancelURL(),
            'data-failureurl' => $this->routesHelper->getFailureURL(),
            'data-noticeurl' => $this->routesHelper->getNoticeURL(),
            'data-quotecallbackurl' => $this->routesHelper->getQuoteURL(['widgetCart' => $widgetCart]),
        ];

        $attributes = '';
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $attributes .= ' ' . $key . '="' . $value . '" ';
            }
        }

        return $attributes;
    }
}
