<?php

namespace Payever\tests\unit\Assistants\SettingsHandlers;

use Payever\Assistants\SettingsHandlers\PayeverAssistantSettingsHandler;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;

class PayeverAssistantSettingsHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var WebstoreRepositoryContract
     */
    private $webstoreRepository;

    /**
     * @var ConfigurationRepositoryContract
     */
    private $configRepository;

    /**
     * @var PluginRepositoryContract
     */
    private $pluginRepository;

    /**
     * @var PayeverAssistantSettingsHandler
     */
    private $assistantSettingsHandler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->webstoreRepository = $this->getMockBuilder(WebstoreRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configRepository = $this->getMockBuilder(ConfigurationRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pluginRepository = $this->getMockBuilder(PluginRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configRepository->expects($this->any())
            ->method('saveConfiguration')
            ->willReturn([]);

        $this->pluginRepository->expects($this->any())
            ->method('searchPlugins')
            ->willReturn(new \Payever\tests\unit\mock\Repositories\Models\PaginatedResult());

        $this->assistantSettingsHandler = new PayeverAssistantSettingsHandler(
            $this->webstoreRepository,
            $this->configRepository,
            $this->pluginRepository
        );
    }

    public function testStructure()
    {
        $parameters = [
            'data' => ['store' => 1],
            'optionId' => 1,
        ];
        $this->assertTrue($this->assistantSettingsHandler->handle($parameters));
    }
}
