<?php //strict
namespace Payever\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverSdkService;
use Plenty\Plugin\Log\Loggable;

/**
 * Class SettingsController
 * @package Payever\Controllers
 */
class ConfigController extends Controller
{
    use Loggable;

    const SET_SANDBOX_HOST = 'set-sandbox-host';
    const SET_LIVE_HOST = 'set-live-host';
    const SET_COMMAND_POLLING_DELAY = 'set-command-polling-delay';

    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;
    /**
     * @var ConfigurationRepositoryContract
     */
    private $config;
    /**
     * @var payeverHelper
     */
    private $payeverHelper;
    /**
     * @var PayeverSdkService
     */
    private $sdkService;

    /**
     * ConfigController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param ConfigurationRepositoryContract $config
     * @param PayeverHelper $payeverHelper
     * @param PayeverSdkService $sdkService
     */
    public function __construct(
        Request $request,
        Response $response,
        ConfigurationRepositoryContract $config,
        PayeverHelper $payeverHelper,
        PayeverSdkService $sdkService
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->payeverHelper = $payeverHelper;
        $this->sdkService = $sdkService;
    }

    /**
     * Collect data from Payever merchant account config
     * and map it to plugin config fields
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function synchronize()
    {
        $pluginSetId = $this->request->get('pluginSetId');
        $pluginsConfig = $this->config->export($pluginSetId);

        static $fieldsMap = [
            // plugin config key => api result key
            'title' => 'name',
            'description' => 'description_offer',
            'variable_fee' => 'variable_fee',
            'fee' => 'fixed_fee',
            'min_order_total' => 'min',
            'max_order_total' => 'max',
        ];

        $apiParameters = [];
        $apiParameters['sdkData'] = [
            'clientId' => $pluginsConfig['Payever']['clientId'],
            'clientSecret' => $pluginsConfig['Payever']['clientSecret'],
            'slug' => $pluginsConfig['Payever']['slug'],
            'environment' => (int) $pluginsConfig['Payever']['environment']
        ];

        $paymentOptions = $this->sdkService->call('listPaymentOptionsRequest', $apiParameters);
        $updatedConfig = [];

        if ($paymentOptions['result']) {
            foreach ($paymentOptions['result'] as $optionData) {
                $optionData = (array) $optionData;
                $methodKey = $optionData['payment_method'];
                $updatedConfig["{$methodKey}.active"] = $optionData['status'];

                foreach ($fieldsMap as $pluginKey => $apiKey) {
                    $updatedConfig["{$methodKey}.{$pluginKey}"] = strip_tags($optionData[$apiKey]);
                }

                $updatedConfig["{$methodKey}.allowed_countries"] = implode(",", $optionData['options']['countries']);
                $updatedConfig["{$methodKey}.allowed_currencies"] = implode(",", $optionData['options']['currencies']);
            }
        }

        /**
         * Payment options API method doesn't return inactive options,
         * so we need to disable them manually
         */
        foreach ($this->payeverHelper->getMethodsMetaData() as $methodKey => $data) {
            $methodConfigKey = strtolower($methodKey) . ".active";
            $updatedConfig[$methodConfigKey] = $updatedConfig[$methodConfigKey] ?? 0;
        }

        return $this->response->json([
            'result' => $updatedConfig,
            'errors' => $paymentOptions["error_description"]
        ]);
    }

    public function executeCommand() {
        try {
            $timestamt = $this->payeverHelper->getCommandTimestamt();
            $this->sdkService->call('registerPlugin', []);
            $commandsList = $this->sdkService->call('getPluginCommands', ['command_timestamt' => $timestamt]);
            $supportedCommands = $this->sdkService->call('getSupportedPluginCommands', []);

            foreach ($commandsList as $command) {
                if ($this->isCommandSupported($command['name'], $supportedCommands)) {
                    $this->getLogger(__METHOD__)->debug('Payever::debug.executeCommand', $command);

                    $this->execute($command['name'], $command['value']);
                    $this->sdkService->call('acknowledgePluginCommand', ['commandId' => $command['id']]);
                } else {
                    $this->getLogger(__METHOD__)->error(
                        sprintf(
                            'Plugin command %s with value %s is not supported',
                            $command['name'],
                            $command['value']
                        )
                    );
                }
            }

            $this->payeverHelper->setCommandTimestamt(time());
        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->getLogger(__METHOD__)->error('The executing commands failed.', $unexpectedValueException);
        } catch (\Exception $exception) {
            $this->getLogger(__METHOD__)->error('The executing commands failed.', $exception);
        }
    }

    /**
     * @param $commandName
     * @param $commandValue
     */
    private function execute($commandName, $commandValue)
    {
        switch ($commandName) {
            case self::SET_SANDBOX_HOST:
                $this->assertApiHostValid($commandValue);
                $this->payeverHelper->setCustomSandboxUrl($commandValue);
                break;
            case self::SET_LIVE_HOST:
                $this->assertApiHostValid($commandValue);
                $this->payeverHelper->setCustomLiveUrl($commandValue);
                break;
            default:
                throw new \UnexpectedValueException(
                    sprintf(
                        'Command %s with value %s is not supported',
                        $commandName,
                        $commandValue
                    )
                );
        }
    }

    /**
     * @param $host
     *
     * @throws \UnexpectedValueException
     */
    private function assertApiHostValid($host)
    {
        if ( ! filter_var($host, FILTER_VALIDATE_URL)) {
            throw new \UnexpectedValueException(sprintf('Command value %s is not a valid URL', $host));
        }
    }

    /**
     * @param $commandName
     * @param $supportedCommands
     *
     * @return bool
     */
    private function isCommandSupported($commandName, $supportedCommands)
    {
        return in_array($commandName, $supportedCommands);
    }
}
