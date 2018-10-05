<?php //strict

namespace Payever\Methods;

use Payever\Helper\PayeverHelper;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Application;

/**
 * Class AbstractPaymentMethod
 * @package Payever\Methods
 */
class AbstractPaymentMethod extends PaymentMethodService
{
    use Loggable;

    protected $_methodCode;

    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    /**
     * Check the configuration if the payment method is active
     * Return true if the payment method is active, else return false
     *
     * @param ConfigRepository $configRepository
     * @param BasketRepositoryContract $basketRepositoryContract
     * @param PayeverHelper $helper
     * @return bool
     */
    public function isActive(
        ConfigRepository $configRepository,
        BasketRepositoryContract $basketRepositoryContract,
        PayeverHelper $helper
    ):bool {

        $activeKey = 'Payever.'.$this->getMethodCode().'.active';
        if ($configRepository->get($activeKey) != 1) {
            return false;
        }

        /** @var Basket $basket */
        $basket = $basketRepositoryContract->load();

        /**
         * Check hiding logic
         */
        if ($helper->isPaymentMethodHidden($this->getMethodCode(), $basket)) {
            return false;
        }

        /**
         * Check currency
         */
        $paymentDetails = $this->getPayeverPaymentDetails();
        $allowed_currency = $paymentDetails[$this->getMethodCode()]['currency'];

        if (
            !in_array($basket->currency, $allowed_currency)
            && !in_array('all', $allowed_currency)
        ) {
            return false;
        }

        /**
         * Check the minimum amount
         */
        $minAmountKey = 'Payever.'.$this->getMethodCode().'.min_order_total';
        if ($configRepository->get($minAmountKey) > 0.00 &&
            $basket->basketAmount <= $configRepository->get($minAmountKey)
        ) {
            return false;
        }

        /**
         * Check the maximum amount
         */
        $maxAmountKey = 'Payever.'.$this->getMethodCode().'.max_order_total';
        if ($configRepository->get($maxAmountKey) > 0.00 &&
            $configRepository->get($maxAmountKey) <= $basket->basketAmount
        ) {
            return false;
        }

        $countryRepo = pluginApp(\Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract::class);
        $country = $countryRepo->findIsoCode($basket->shippingCountryId, 'iso_code_2');

        if (!in_array($country, $paymentDetails[$this->getMethodCode()]['countries'])
            && !in_array('all', $paymentDetails[$this->getMethodCode()]['countries'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get additional costs for the payment method. Additional costs can be entered in the config.json.
     *
     * @param BasketRepositoryContract $basketRepositoryContract
     * @return float
     */
    public function getFee(ConfigRepository $configRepository, BasketRepositoryContract $basketRepositoryContract):float
    {
        $basket = $basketRepositoryContract->load();
        $acceptedFee = $configRepository->get('Payever.'.$this->getMethodCode().'.accept_fee');
        if (!$acceptedFee) {
            $fixedFee = $configRepository->get('Payever.'.$this->getMethodCode().'.fee');
            $variableFee = $configRepository->get('Payever.'.$this->getMethodCode().'.variable_fee');
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
     * @return string
     */
    public function getName(
        ConfigRepository $configRepository,
        BasketRepositoryContract $basketRepositoryContract
    ):string {
        $basket = $basketRepositoryContract->load();
        $titleKey = 'Payever.'.$this->getMethodCode().'.title';
        $name = $configRepository->get($titleKey);

        if (!strlen($name)) {
            $name = 'Payever';
        }

        $acceptedFee = $configRepository->get('Payever.'.$this->getMethodCode().'.accept_fee');
        if (!$acceptedFee) {
            $fixedFee = $configRepository->get('Payever.'.$this->getMethodCode().'.fee');
            $variableFee = $configRepository->get('Payever.'.$this->getMethodCode().'.variable_fee');
            if ($variableFee && $fixedFee) {
                $name .= ' ('.$variableFee.'% + '.$fixedFee.' '.$basket->currency.')';
            } elseif ($variableFee && !$fixedFee) {
                $name .= ' ( + '.$variableFee.'%)';
            } elseif (!$variableFee && $fixedFee) {
                $name .= ' ( + '.$fixedFee.' '.$basket->currency.')';
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
    public function getIcon(ConfigRepository $configRepository):string
    {
        if ($configRepository->get('Payever.display_payment_icon') == 1) {
            $app = pluginApp(Application::class);
            $icon = $app->getUrlPath('Payever').'/images/logos/'.$this->getMethodCode().'.png';
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
    public function getDescription(ConfigRepository $configRepository):string
    {
        if ($configRepository->get('Payever.display_payment_description') == 1) {
            $descriptionKey = 'Payever.'.$this->getMethodCode().'.description';

            return $configRepository->get($descriptionKey);
        } else {
            return '';
        }
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @param int $orderId
     * @return bool
     */
    public function isSwitchableTo(int $orderId):bool
    {
        return false;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @param int $orderId
     * @return bool
     */
    public function isSwitchableFrom(int $orderId):bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getPayeverPaymentDetails():array
    {
        return [
            'paymill_directdebit' => [
                'currency' => ['all'],
                'countries' => ['DE'],
            ],
            'sofort' => [
                'currency' => ['EUR'],
                'countries' => ['BE', 'NL', 'CH', 'HU', 'DE', 'AT', 'SK', 'GB', 'IT', 'PL', 'ES'],
            ],
            'paymill_creditcard' => [
                'currency' => ['all'],
                'countries' => ['all'],
            ],
            'santander_installment' => [
                'currency' => ['EUR'],
                'countries' => ['DE'],
            ],
            'santander_installment_no' => [
                'currency' => ['NOK'],
                'countries' => ['NO'],
            ],
            'santander_installment_dk' => [
                'currency' => ['DKK'],
                'countries' => ['DK'],
            ],
            'santander_installment_se' => [
                'currency' => ['SEK'],
                'countries' => ['SE'],
            ],
            'santander_invoice_no' => [
                'currency' => ['NOK'],
                'countries' => ['NO'],
            ],
            'santander_invoice_de' => [
                'currency' => ['EUR'],
                'countries' => ['DE'],
            ],
            'santander_factoring_de' => [
                'currency' => ['EUR'],
                'countries' => ['DE'],
            ],
            'paypal' => [
                'currency' => ['all'],
                'countries' => ['all'],
            ],
            'stripe' => [
                'currency' => ['all'],
                'countries' => ['all'],
            ],
            'payex_faktura' => [
                'currency' => ['SEK', 'NOK'],
                'countries' => ['SE', 'NO'],
            ],
            'payex_creditcard' => [
                'currency' => ['all'],
                'countries' => ['all'],
            ],
        ];
    }
}
