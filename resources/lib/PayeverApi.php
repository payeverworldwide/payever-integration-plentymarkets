<?php

use Payever\ExternalIntegration\Payments\Api as PaymentsApi;

class PayeverApi extends PaymentsApi
{
    protected function loadConfiguration()
    {
        $this->configuration = new PayeverConfiguration();
        $this
            ->getConfiguration()
            ->load();

        return $this;
    }

    protected function loadTokens()
    {
        $this->tokens = new PayeverTokenList();
        $this
            ->getTokens()
            ->load($this->getConfiguration()->getClientId());
    }
}
