<?php

namespace Payever\Methods;

use Payever\Services\PayeverSdkService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\Application;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class AllianzTradePayPaymentMethod
 * @package Payever\Methods
 */
class AllianzTradePayPaymentMethod extends AbstractPaymentMethod
{
    /**
     * @var string
     */
    public $methodCode = 'allianz_trade_b2b_bnpl';

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

        if (\Payever\Services\PayeverService::API_V2 === (int)$configRepository->get('Payever.api_version')) {
            return false;
        }

        return parent::isActive($configRepository, $basketRepositoryContract, $sdkService);
    }
}
