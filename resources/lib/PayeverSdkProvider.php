<?php

require_once __DIR__ . '/PayeverTokenlist.php';
require_once __DIR__ . '/PluginRegistryInfoProvider.php';

use Payever\ExternalIntegration\Core\ClientConfiguration;
use Payever\ExternalIntegration\Core\Enum\ChannelSet;
use Payever\ExternalIntegration\Inventory\InventoryApiClient;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Payever\ExternalIntegration\Products\ProductsApiClient;
use Payever\ExternalIntegration\ThirdParty\ThirdPartyApiClient;

class PayeverSdkProvider
{
    /** @var ClientConfiguration */
    private $clientConfiguration;

    /** @var PayeverTokenList */
    private $tokenList;

    /** @var PluginRegistryInfoProvider  */
    private $registryInfoProvider;

    /**
     * @param array $apiData
     */
    public function __construct($apiData)
    {
        $this->clientConfiguration   = $this->prepareClientConfiguration($apiData);
        $this->tokenList             = new PayeverTokenList();
        $this->registryInfoProvider  = new PluginRegistryInfoProvider($apiData);
    }

    /**
     * @inheritdoc
     */
    public function getRegistryInfoProvider()
    {
        return $this->registryInfoProvider;
    }

    /**
     * @return PaymentsApiClient
     * @throws \Exception
     */
    public function getPaymentsApiClient()
    {
        return new PaymentsApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return PluginsApiClient
     * @throws Exception
     */
    public function getPluginsApiClient()
    {
        return new PluginsApiClient(
            $this->registryInfoProvider,
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return ThirdPartyApiClient
     * @throws \Exception
     */
    public function getThirdPartyApiClient()
    {
        return new ThirdPartyApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return ProductsApiClient
     * @throws \Exception
     */
    public function getProductsApiClient()
    {
        return new ProductsApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return InventoryApiClient
     * @throws \Exception
     */
    public function getInventoryApiClient()
    {
        return new InventoryApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @param $apiData
     * @return ClientConfiguration
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     */
    private function prepareClientConfiguration($apiData)
    {
        $clientConfiguration = new ClientConfiguration();
        $apiMode             = $apiData['environment'] == 1
            ? ClientConfiguration::API_MODE_LIVE
            : ClientConfiguration::API_MODE_SANDBOX;

        $clientConfiguration->setChannelSet(ChannelSet::CHANNEL_PLENTYMARKETS)
                            ->setApiMode($apiMode)
                            ->setClientId($apiData['clientId'])
                            ->setClientSecret($apiData['clientSecret'])
                            ->setBusinessUuid($apiData['slug']);

        if (!empty($apiData['customSandboxUrl'])) {
            $clientConfiguration->setCustomSandboxUrl($apiData['customSandboxUrl']);
        }

        if (!empty($apiData['customLiveUrl'])) {
            $clientConfiguration->setCustomLiveUrl($apiData['customSandboxUrl']);
        }

        return $clientConfiguration;
    }
}
