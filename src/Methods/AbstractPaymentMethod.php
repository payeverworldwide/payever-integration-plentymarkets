<?php //strict

namespace payever\Methods;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Plugin\Log\Loggable;

/**
 * Class AbstractPaymentMethod
 * @package payever\Methods
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
     * @return bool
     */
    public function isActive(
        ConfigRepository $configRepository,
        BasketRepositoryContract $basketRepositoryContract
    ):bool {

        $activeKey = 'payever.'.$this->getMethodCode().'.active';
        if ($configRepository->get($activeKey) != 1) {
            /** @var bool $active */
            return false;
        }

        /** @var Basket $basket */
        $basket = $basketRepositoryContract->load();

        /**
         * Check currency
         */
        $paymentDetails = $this->getPayeverPaymentDetails();
        $allowed_currency = $paymentDetails[$this->getMethodCode()]['currency'];
        if (!in_array($basket->currency, $allowed_currency) && !in_array(
            'all',
            $allowed_currency
        )
        ) {
            return false;
        }

        /**
         * Check the minimum amount
         */
        $minAmountKey = 'payever.'.$this->getMethodCode().'.min_order_total';
        if ($configRepository->get($minAmountKey) > 0.00 &&
            $basket->basketAmount <= $configRepository->get($minAmountKey)
        ) {
            return false;
        }

        /**
         * Check the maximum amount
         */
        $maxAmountKey = 'payever.'.$this->getMethodCode().'.max_order_total';
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
        $acceptedFee = $configRepository->get('payever.'.$this->getMethodCode().'.accept_fee');
        if (!$acceptedFee) {
            $fixedFee = $configRepository->get('payever.'.$this->getMethodCode().'.fee');
            $variableFee = $configRepository->get('payever.'.$this->getMethodCode().'.variable_fee');
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
        $titleKey = 'payever.'.$this->getMethodCode().'.title';
        $name = $configRepository->get($titleKey);

        if (!strlen($name)) {
            $name = 'payever';
        }

        $acceptedFee = $configRepository->get('payever.'.$this->getMethodCode().'.accept_fee');
        if (!$acceptedFee) {
            $fixedFee = $configRepository->get('payever.'.$this->getMethodCode().'.fee');
            $variableFee = $configRepository->get('payever.'.$this->getMethodCode().'.variable_fee');
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
        if ($configRepository->get('payever.display_payment_icon') == 1) {
            $logo = 'plenty/ui-plugin/production/payever/images/logos/'.$this->getMethodCode().'.png';

            return $logo;
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
        if ($configRepository->get('payever.display_payment_description') == 1) {
            $descriptionKey = 'payever.'.$this->getMethodCode().'.description';

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
    public function isSwitchableTo($orderId)
    {
        return false;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @param int $orderId
     * @return bool
     */
    public function isSwitchableFrom($orderId)
    {
        return true;
    }

    /**
     * @return array
     */
    public function getPayeverPaymentDetails()
    {
        return array(
            'paymill_directdebit' => array(
                'currency' => array('all'),
                'countries' => array('DE'),
            ),
            'sofort' => array(
                'currency' => array('EUR'),
                'countries' => array('BE', 'NL', 'CH', 'HU', 'DE', 'AT', 'SK', 'GB', 'IT', 'PL', 'ES'),
            ),
            'paymill_creditcard' => array(
                'currency' => array('all'),
                'countries' => array('all'),
            ),
            'santander_installment' => array(
                'currency' => array('EUR'),
                'countries' => array('DE'),
            ),
            'santander_installment_no' => array(
                'currency' => array('NOK'),
                'countries' => array('NO'),
            ),
            'santander_installment_dk' => array(
                'currency' => array('DKK'),
                'countries' => array('DK'),
            ),
            'santander_installment_se' => array(
                'currency' => array('SEK'),
                'countries' => array('SE'),
            ),
            'santander_invoice_no' => array(
                'currency' => array('NOK'),
                'countries' => array('NO'),
            ),
            'santander_invoice_de' => array(
                'currency' => array('EUR'),
                'countries' => array('DE'),
            ),
            'paypal' => array(
                'currency' => array('all'),
                'countries' => array('all'),
            ),
            'stripe' => array(
                'currency' => array('all'),
                'countries' => array('all'),
            ),
            'payex_faktura' => array(
                'currency' => array('SEK', 'NOK'),
                'countries' => array('SE', 'NO'),
            ),
            'payex_creditcard' => array(
                'currency' => array('all'),
                'countries' => array('all'),
            ),
        );
    }
}
