<?php // strict

namespace Payever\Services;

use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;

class PayeverSdkService
{
    /**
     *
     * @var LibraryCallContract
     */
    private $libCall;

    /**
     *
     * @var ConfigRepository
     */
    private $config;

    /**
     *
     * @param LibraryCallContract $libCall
     * @param ConfigRepository $config
     */
    public function __construct(LibraryCallContract $libCall, ConfigRepository $config)
    {
        $this->libCall = $libCall;
        $this->config = $config;
    }

    /**
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function call(string $method, array $parameters)
    {
        if (!isset($parameters['apiKeys'])) {
            $parameters['apiKeys'] = [
                'clientId' => $this->config->get('Payever.clientId'),
                'clientSecret' => $this->config->get('Payever.clientSecret'),
                'slug' => $this->config->get('Payever.slug'),
                'environment' => $this->config->get('Payever.environment')
            ];
        }

        return $this->libCall->call('Payever::' . $method, $parameters);
    }
}
