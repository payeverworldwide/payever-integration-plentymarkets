<?php

namespace Payever\Tests\BehatExtension\Listener;

use Payever\Tests\PlentyConnector;
use Behat\Testwork\EventDispatcher\Event\AfterSuiteTested;
use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTested;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginListener implements EventSubscriberInterface
{
    /** @var PlentyConnector */
    private $connector;

    /** @var array */
    private $extensionConfig;

    /**
     * @param PlentyConnector $connector
     * @param array $extensionConfig
     */
    public function __construct($connector, array $extensionConfig)
    {
        $this->connector = $connector;
        $this->extensionConfig = $extensionConfig;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            BeforeSuiteTested::BEFORE => ['setup', 10],
            AfterSuiteTested::AFTER => ['cleanup', 10],
        ];
    }

    /**
     * @return void
     */
    public function setup()
    {
        //
    }

    /**
     * @return void
     */
    public function cleanup()
    {
        //
    }

    /**
     * @return void
     */
    private function removeStubProducts()
    {
        //
    }
}
