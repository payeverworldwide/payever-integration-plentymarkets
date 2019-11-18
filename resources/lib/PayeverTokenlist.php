<?php

use Payever\ExternalIntegration\Core\Authorization\OauthToken;
use Payever\ExternalIntegration\Core\Authorization\OauthTokenList;

class PayeverTokenList extends OauthTokenList
{
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $savedTokens = $this->getTokenStorage();

        if (is_array($savedTokens)) {
            foreach ($savedTokens as $name => $token) {
                $this->add(
                    $name,
                    $this->create()->load($token)
                );
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $savedTokens = array();

        /** @var OauthToken $token */
        foreach ($this->getAll() as $name => $token) {
            $savedTokens[$name] = $token->getParams();
        }
    }

    /**
     * @return array|mixed
     */
    private function getTokenStorage()
    {
        return [];
    }
}

