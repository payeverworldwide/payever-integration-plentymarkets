<?php

require_once __DIR__ . '/PayeverTokenlist.php';

use Payever\ExternalIntegration\Core\ClientConfiguration;
use Payever\ExternalIntegration\Core\Enum\ChannelSet;
use Payever\ExternalIntegration\Core\Logger\FileLogger;
use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Core\Lock\FileLock;
use Payever\ExternalIntegration\Inventory\InventoryApiClient;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\ExternalIntegration\Products\ProductsApiClient;
use Payever\ExternalIntegration\ThirdParty\ThirdPartyApiClient;
use Psr\Log\LoggerInterface;

class PayeverSdkProvider
{
    /** @var ClientConfiguration */
    private $clientConfiguration;

    /** @var TokenList */
    private $tokenList;

    /** @var LoggerInterface */
    private $logger;

    private $locker;

    public function __construct($apiData)
    {
        $this->clientConfiguration = $this->prepareClientConfiguration($apiData);
        $this->tokenList           = new PayeverTokenList();
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
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    private function prepareLogger($logFile)
    {
        return new FileLogger(
            $logFile,
            $this->settingsService->getSettings()->getLogging()
        );
    }

    /**
     * @return LockInterface
     */
    public function getLocker()
    {
        return $this->locker;
    }

    private function prepareLocker($lockerPath)
    {
        return new FileLock($lockerPath);
    }

    /**
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

        return $clientConfiguration;
    }
}