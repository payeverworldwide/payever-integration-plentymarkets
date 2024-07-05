<?php

require_once __DIR__ . '/PayeverTokenlist.php';
require_once __DIR__ . '/PluginRegistryInfoProvider.php';

use Payever\Sdk\Core\ClientConfiguration;
use Payever\Sdk\Core\Enum\ChannelSet;
use Payever\Sdk\Inventory\InventoryApiClient;
use Payever\Sdk\Payments\PaymentsApiClient;
use Payever\Sdk\Plugins\PluginsApiClient;
use Payever\Sdk\Products\ProductsApiClient;
use Payever\Sdk\ThirdParty\ThirdPartyApiClient;
use Payever\Sdk\Payments\ThirdPartyPluginsApiClient;
use Payever\Sdk\Payments\Action\ActionDecider;

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
     * @throws Exception
     */
    public function __construct(array $apiData)
    {
        $this->clientConfiguration   = $this->prepareClientConfiguration($apiData);
        $this->tokenList             = new PayeverTokenList();
        $this->registryInfoProvider  = new PluginRegistryInfoProvider($apiData);
    }

    /**
     * @return PluginRegistryInfoProvider
     */
    public function getRegistryInfoProvider(): PluginRegistryInfoProvider
    {
        return $this->registryInfoProvider;
    }

    /**
     * @return PaymentsApiClient
     * @throws Exception
     */
    public function getPaymentsApiClient(): PaymentsApiClient
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
    public function getPluginsApiClient(): PluginsApiClient
    {
        return new PluginsApiClient(
            $this->registryInfoProvider,
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return ThirdPartyApiClient
     * @throws Exception
     */
    public function getThirdPartyApiClient(): ThirdPartyApiClient
    {
        return new ThirdPartyApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return ProductsApiClient
     * @throws Exception
     */
    public function getProductsApiClient(): ProductsApiClient
    {
        return new ProductsApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return InventoryApiClient
     * @throws Exception
     */
    public function getInventoryApiClient(): InventoryApiClient
    {
        return new InventoryApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @return ThirdPartyPluginsApiClient
     * @throws Exception
     */
    public function getThirdPartyPluginsApiClient(): ThirdPartyPluginsApiClient
    {
        return new ThirdPartyPluginsApiClient(
            $this->clientConfiguration,
            $this->tokenList
        );
    }

    /**
     * @param array $apiData
     * @return ClientConfiguration
     * @throws Exception
     */
    private function prepareClientConfiguration(array $apiData): ClientConfiguration
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

    /**
     * @return ActionDecider
     * @throws Exception
     */
    public function getActionDecider(): ActionDecider
    {
        return new ActionDecider($this->getPaymentsApiClient());
    }
}
