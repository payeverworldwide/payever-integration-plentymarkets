<?php

use Payever\ExternalIntegration\Core\ChannelSet;
use Payever\ExternalIntegration\Payments\Configuration as PaymentsConfiguration;

class PayeverConfiguration extends PaymentsConfiguration
{
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $this
            ->setChannelSet(ChannelSet::CHANNEL_PLENTYMARKETS)
            ->setDebugMode(true)
        ;
    }
}
