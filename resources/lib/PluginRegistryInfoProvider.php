<?php

use Payever\Sdk\Core\Enum\ChannelSet;
use Payever\Sdk\Plugins\Enum\PluginCommandNameEnum;
use Payever\Sdk\Plugins\Base\PluginRegistryInfoProviderInterface;

class PluginRegistryInfoProvider implements PluginRegistryInfoProviderInterface
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    public function getPluginVersion()
    {
        return $this->data['pluginVersion'];
    }

    /**
     * @inheritDoc
     */
    public function getCmsVersion()
    {
        return 'plentymarkets 7';
    }

    /**
     * @inheritDoc
     */
    public function getHost()
    {
        return $this->data['host'];
    }

    /**
     * @inheritDoc
     */
    public function getChannel()
    {
        return ChannelSet::CHANNEL_PLENTYMARKETS;
    }

    /**
     * @inheritDoc
     */
    public function getSupportedCommands()
    {
        return [
            PluginCommandNameEnum::SET_SANDBOX_HOST,
            PluginCommandNameEnum::SET_LIVE_HOST,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getCommandEndpoint()
    {
        return $this->data['commandEndpoint'];
    }

    /**
     * @inheritDoc
     */
    public function getBusinessIds()
    {
        return [
            $this->data['slug']
        ];
    }
}
