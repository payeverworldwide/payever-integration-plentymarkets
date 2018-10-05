<?php

use Payever\ExternalIntegration\Core\Base\IToken;
use Payever\ExternalIntegration\Core\Authorization\TokenList as CoreTokenList;

class PayeverTokenList extends CoreTokenList
{
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $savedTokens = $this->getTokenStorage();

        foreach ($savedTokens as $name => $token) {
            $this->add(
                $name,
                $this->create()->load($token)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $savedTokens = array();

        /** @var PluginToken $token */
        foreach ($this->getAll() as $name => $token) {
            $savedTokens[$name] = $token->getParams();
        }
    }

    /**
     * Returns new PayeverToken
     *
     * @return IToken
     *
     * @throws \Exception
     */
    public function create()
    {
        return new PayeverToken();
    }

    /**
     * @return array|mixed
     */
    private function getTokenStorage()
    {
        return array();
    }
}
