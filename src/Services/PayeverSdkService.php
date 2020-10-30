<?php // strict

namespace Payever\Services;

use Payever\Helper\PayeverHelper;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;

class PayeverSdkService
{
    /**
     * @var LibraryCallContract
     */
    private $libCall;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var PayeverHelper
     */
    private $payeverHelper;

    /**
     * PayeverSdkService constructor.
     *
     * @param LibraryCallContract $libCall
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     */
    public function __construct(LibraryCallContract $libCall, ConfigRepository $config, PayeverHelper $payeverHelper)
    {
        $this->libCall = $libCall;
        $this->config = $config;
        $this->payeverHelper = $payeverHelper;
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function call(string $method, array $parameters)
    {
        if (!isset($parameters['sdkData'])) {
            $parameters['sdkData'] = [
                'clientId' => $this->config->get('Payever.clientId'),
                'clientSecret' => $this->config->get('Payever.clientSecret'),
                'slug' => $this->config->get('Payever.slug'),
                'environment' => $this->config->get('Payever.environment')
            ];
        }

        $parameters['sdkData']['customSandboxUrl'] = $this->payeverHelper->getCustomSandboxUrl();
        $parameters['sdkData']['customLiveUrl'] = $this->payeverHelper->getCustomLiveUrl();
        $parameters['sdkData']['host'] = $this->payeverHelper->getBaseUrl();
        $parameters['sdkData']['commandEndpoint'] = $this->payeverHelper->getCommandEndpoint();
        $parameters['sdkData']['pluginVersion'] = '1.11.0';

        return $this->libCall->call('Payever::' . $method, $parameters);
    }
}
