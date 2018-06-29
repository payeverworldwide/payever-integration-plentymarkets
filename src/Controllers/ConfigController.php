<?php //strict
namespace payever\Controllers;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;

use payever\Helper\PayeverHelper;
use payever\Api\PayeverApi;

/**
 * Class SettingsController
 * @package payever\Controllers
 */
class ConfigController extends Controller
{
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;
    /**
     * @var ConfigRepository
     */
    private $config;
    /**
     * @var payeverHelper
     */
    private $payeverHelper;
    /**
     * @var payeverApi
     */
    private $payeverApi;

    /**
     * SettingsController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param ConfigRepository $config
     * @param PayeverHelper $payeverHelper
     * @param PayeverApi $payeverApi
     */
    public function __construct(
        Request $request,
        Response $response,
        ConfigRepository $config,
        PayeverHelper $payeverHelper,
        PayeverApi $payeverApi
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->payeverHelper = $payeverHelper;
        $this->payeverApi = $payeverApi;
    }

    /**
     * Collect data from Payever merchant account config
     * and map it to plugin config fields
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function synchronize()
    {
        static $fieldsMap = [
            // plugin config key => api result key
            'title' => 'name',
            'description' => 'description_offer',
            'variable_fee' => 'variable_fee',
            'fee' => 'fixed_fee',
            'min_order_total' => 'min',
            'max_order_total' => 'max',
        ];

        $config = $this->config;
        $api = $this->payeverHelper->getPayeverApi();
        $paymentOptions = $api->getListPaymentOptions($config->get('payever.slug'), 'other_shopsystem');
        $updatedConfig = [];

        if ($paymentOptions) {
            foreach ($paymentOptions->result as $optionData) {
                $optionData = (array) $optionData;
                $methodKey = $optionData['payment_method'];
                $updatedConfig["{$methodKey}.active"] = $optionData['status'] == 'active' ? 1 : 0;

                foreach ($fieldsMap as $pluginKey => $apiKey) {
                    $updatedConfig["{$methodKey}.{$pluginKey}"] = strip_tags($optionData[$apiKey]);
                }
            }
        }

        /**
         * Payment options API method doesn't return inactive options,
         * so we need to disable them manually
         */
        foreach ($this->payeverHelper->getMethodsMetaData() as $methodKey => $data) {
            $methodConfigKey = strtolower($methodKey) . ".active";
            $updatedConfig[$methodConfigKey] = isset($updatedConfig[$methodConfigKey])
                ? $updatedConfig[$methodConfigKey]
                : 0;
        }

        return $this->response->json([
            'result' => $updatedConfig,
            'errors' => $api->getErrors(),
        ]);
    }
}
