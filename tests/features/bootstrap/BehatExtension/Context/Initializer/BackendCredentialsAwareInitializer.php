<?php

namespace Payever\Tests\BehatExtension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;

class BackendCredentialsAwareInitializer implements ContextInitializer
{
    /** @var string */
    protected $url;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /**
     * @param string $url
     * @param string $username
     * @param string $password
     */
    public function __construct($url, $username, $password)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * {@inheritDoc}
     */
    public function initializeContext(Context $context)
    {
        $context->setUrl($this->url);
        $context->setUsername($this->username);
        $context->setPassword($this->password);
    }
}
