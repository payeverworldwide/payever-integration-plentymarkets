<?php

namespace Payever\Assistants\SettingsHandlers;

use Plenty\Modules\Plugin\Models\Plugin;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;

class PayeverAssistantSettingsHandler implements WizardSettingsHandler
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

    public function __construct(
        WebstoreRepositoryContract $webstoreRepository,
        ConfigurationRepositoryContract $configRepository,
        PluginRepositoryContract $pluginRepository
    ) {
        $this->webstoreRepository = $webstoreRepository;
        $this->configRepository = $configRepository;
        $this->pluginRepository = $pluginRepository;
    }

    /**
     * @param array $parameters
     * @return bool
     */
    public function handle(array $parameters): bool
    {
        $data = $parameters['data'];
        $optionId = $parameters['optionId'];
        $webstoreId = $data['store'];

        if (!is_numeric($webstoreId) || $webstoreId <= 0) {
            $webstoreId = $this->getWebstore($optionId)->storeIdentifier;
        }

        $this->saveSettings($webstoreId, $data);

        return true;
    }

    /**
     * @param $webstoreId
     * @return Webstore
     */
    private function getWebstore($webstoreId)
    {
        return $this->webstoreRepository->findByStoreIdentifier($webstoreId);
    }

    /**
     * Save Settings.
     *
     * @param $webstoreId
     * @param array $data
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function saveSettings($webstoreId, array $data)
    {
        $pluginId = null;
        $pluginResult = $this->pluginRepository->searchPlugins(['name' => 'Payever'], 1)->getResult();
        $plugin = $pluginResult[0];
        if ($plugin instanceof Plugin) {
            $pluginId = (int) $plugin->id;
        }

        $configuration = [];
        foreach ($data as $key => $value) {
            if ((strpos($key, 'allowed_countries') !== false) || //phpcs:ignore
                (strpos($key, 'allowed_currencies') !== false)
            ) {
                $value = implode($value, ',');
            }

            $configuration[] = [
                'key' => $key,
                'value' => $value
            ];
        }

        $this->configRepository->saveConfiguration($pluginId, $configuration);
    }
}
