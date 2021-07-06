<?php

namespace Payever\Methods;

use Payever\Services\PayeverSdkService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\Application;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;

/**
 * Class AbstractPaymentMethod
 * @package Payever\Methods
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class AbstractPaymentMethod extends PaymentMethodService
{
    use Loggable;

    /**
     * @var string
     */
    protected $methodCode;

    /**
     * @return string
     */
    public function getMethodCode()
    {
        return $this->methodCode;
    }

    /**
     * Check the configuration if the payment method is active
     * Return true if the payment method is active, else return false
     *
     * @param ConfigRepository $configRepository
     * @param BasketRepositoryContract $basketRepositoryContract
     * @param PayeverSdkService $sdkService
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function isActive(
        ConfigRepository $configRepository,
        BasketRepositoryContract $basketRepositoryContract,
        PayeverSdkService $sdkService
    ): bool {

        $activeKey = 'Payever.' . $this->getMethodCode() . '.active';
        if ($configRepository->get($activeKey) != 1) {
            return false;
        }

        /** @var Basket $basket */
        $basket = $basketRepositoryContract->load();

        /**
         * Check hiding logic
         */
        $isPaymentMethodHidden = $this->isBasketAddressesDifferent($basket)
            ? in_array($this->getMethodCode(), $sdkService->call('getShouldHideOnDifferentAddressMethods', []))
            : false;
        if ($isPaymentMethodHidden) {
            return false;
        }

        /**
         * Check currency
         */
        $allowedCurrenciesKey = 'Payever.' . $this->getMethodCode() . '.allowed_currencies';
        $allowedCurrencies = explode(",", $configRepository->get($allowedCurrenciesKey));

        if (
            !in_array($basket->currency, $allowedCurrencies)
            && !in_array('all', $allowedCurrencies)
        ) {
            return false;
        }

        /**
         * Check the minimum amount
         */
        $minAmountKey = 'Payever.' . $this->getMethodCode() . '.min_order_total';
        if (
            $configRepository->get($minAmountKey) > 0.00 &&
            $basket->basketAmount <= $configRepository->get($minAmountKey)
        ) {
            return false;
        }

        /**
         * Check the maximum amount
         */
        $maxAmountKey = 'Payever.' . $this->getMethodCode() . '.max_order_total';
        if (
            $configRepository->get($maxAmountKey) > 0.00 &&
            $configRepository->get($maxAmountKey) <= $basket->basketAmount
        ) {
            return false;
        }

        /**
         * Check country
         * @var CountryRepositoryContract $countryRepo
         */
        $countryRepo = pluginApp(CountryRepositoryContract::class);
        $country = $countryRepo->findIsoCode($basket->shippingCountryId, 'iso_code_2');

        $allowedCountriesKey = 'Payever.' . $this->getMethodCode() . '.allowed_countries';
        $allowedCountries = explode(',', $configRepository->get($allowedCountriesKey));

        if (!in_array($country, $allowedCountries) && !in_array('all', $allowedCountries)) {
            return false;
        }

        return true;
    }

    /**
     * Get additional costs for the payment method. Additional costs can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @param BasketRepositoryContract $basketRepositoryContract
     * @return float
     */
    public function getFee(
        ConfigRepository $configRepository,
        BasketRepositoryContract $basketRepositoryContract
    ): float {
        $basket = $basketRepositoryContract->load();
        $acceptedFee = $configRepository->get('Payever.' . $this->getMethodCode() . '.accept_fee');
        if (!$acceptedFee) {
            $fixedFee = $configRepository->get('Payever.' . $this->getMethodCode() . '.fee');
            $variableFee = $configRepository->get('Payever.' . $this->getMethodCode() . '.variable_fee');
            $feeAmount = $basket->basketAmount * $variableFee / 100 + $fixedFee;

            return $feeAmount;
        } else {
            return 0.00;
        }
    }

    /**
     * Get the name of the payment method. The name can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @param BasketRepositoryContract $basketRepositoryContract
     * @return string
     */
    public function getName(
        ConfigRepository $configRepository,
        BasketRepositoryContract $basketRepositoryContract
    ): string {
        $basket = $basketRepositoryContract->load();
        $titleKey = 'Payever.' . $this->getMethodCode() . '.title';
        $name = $configRepository->get($titleKey);

        if (!strlen($name)) {
            $name = 'Payever';
        }

        $acceptedFee = $configRepository->get('Payever.' . $this->getMethodCode() . '.accept_fee');
        if (!$acceptedFee) {
            $fixedFee = $configRepository->get('Payever.' . $this->getMethodCode() . '.fee');
            $variableFee = $configRepository->get('Payever.' . $this->getMethodCode() . '.variable_fee');
            if ($variableFee && $fixedFee) {
                $name .= ' (' . $variableFee . '% + ' . $fixedFee . ' ' . $basket->currency . ')';
            } elseif ($variableFee && !$fixedFee) {
                $name .= ' ( + ' . $variableFee . '%)';
            } elseif (!$variableFee && $fixedFee) {
                $name .= ' ( + ' . $fixedFee . ' ' . $basket->currency . ')';
            }
        }

        return $name;
    }

    /**
     * Get the path of the icon. The URL can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getIcon(ConfigRepository $configRepository): string
    {
        if ($configRepository->get('Payever.display_payment_icon') == 1) {
            $app = pluginApp(Application::class);
            $icon = $app->getUrlPath('Payever') . '/images/logos/' . $this->getMethodCode() . '.png';

            return $icon;
        }

        return '';
    }

    /**
     * Get the description of the payment method. The description can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getDescription(ConfigRepository $configRepository): string
    {
        if ($configRepository->get('Payever.display_payment_description') == 1) {
            $descriptionKey = 'Payever.' . $this->getMethodCode() . '.description';

            return $configRepository->get($descriptionKey) ?? '';
        }

        return '';
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @param int $orderId
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isSwitchableTo(int $orderId): bool
    {
        return false;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @param int $orderId
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isSwitchableFrom(int $orderId): bool
    {
        return true;
    }

    /**
     * @param Basket $basket
     *
     * @return bool
     */
    public function isBasketAddressesDifferent(Basket $basket): bool
    {
        static $result = null;

        if ($result === null) {
            $result = false;

            if (
                !$basket->customerShippingAddressId
                || !$basket->customerInvoiceAddressId
            ) {
                return $result;
            }

            $result = $basket->customerInvoiceAddressId !== $basket->customerShippingAddressId;
        }

        return $result;
    }
}
