<?php

namespace Payever\Tests;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Mink;
use Behat\MinkExtension\Context\MinkAwareContext;
use Payever\Stub\BehatExtension\Context\PluginAwareContext;
use Payever\Stub\BehatExtension\ServiceContainer\PluginConnectorInterface;

class BackendContext implements PluginAwareContext, MinkAwareContext
{
    /** @var Mink */
    private $mink;

    /** @var array */
    private $minkConfig;

    /** @var PrestaPluginConnector */
    private $connector;

    /** @var array */
    private $extensionConfig;

    /** @var FrontendContext */
    private $frontend;

    /** @var string */
    private $backendUrl;

    /** @var string */
    private $backendUsername;

    /** @var string */
    private $backendPassword;

    /**
     * {@inheritDoc}
     */
    public function setUrl($url)
    {
        $this->backendUrl = $url;
    }

    /**
     * {@inheritDoc}
     */
    public function setUsername($username)
    {
        $this->backendUsername = $username;
    }

    /**
     * {@inheritDoc}
     */
    public function setPassword($password)
    {
        $this->backendPassword = $password;
    }

    /**
     * {@inheritDoc}
     */
    public function setPluginConnector(PluginConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    /**
     * {@inheritDoc}
     */
    public function setExtensionConfig(array $config)
    {
        $this->extensionConfig = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function setMink(Mink $mink)
    {
        $this->mink = $mink;
    }

    /**
     * {@inheritDoc}
     */
    public function setMinkParameters(array $parameters)
    {
        $this->minkConfig = $parameters;
    }

    /**
     * @BeforeScenario
     */
    public function beforeScenario(BeforeScenarioScope $scope)
    {
        $this->frontend = $scope->getEnvironment()->getContext(FrontendContext::class);
    }

    /**
     * @Given /^(?:|I )see dashboard$/
     */
    public function seeDashboard()
    {
        $this->frontend->waitTillElementExists('.theme-core');
    }
}
