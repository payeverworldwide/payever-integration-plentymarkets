<?php

namespace Payever\Providers\DataProviders;

use Payever\Helper\PayeverHelper;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\ConfigRepository;

class CompanySearchContainer
{
    const B2B_ALLOWED_COUNTRIES = ['DE','IT','FR','AT','NL','ES'];
    /**
     * @param Twig $twig
     * @param ConfigRepository $config
     * @param CountryRepositoryContract $countryRepositoryContract
     * @return string
     */
    public function call(
        Twig $twig,
        ConfigRepository $config,
        CountryRepositoryContract $countryRepositoryContract
    ): string
    {
        if (
            $config->get(PayeverHelper::PLUGIN_CONFIG_PREFIX . PayeverHelper::COMPANY_SEARCH_CONFIG_KEY) &&
            \Payever\Services\PayeverService::API_V3 === (int)$config->get('Payever.api_version')
        ) {
            return $twig->render(
                'Payever::customer.company_search',
                ['payeverSearchConfig' => json_encode($this->getConfig($countryRepositoryContract))]
            );
        }

        return '';
    }

    /**
     * @param CountryRepositoryContract $countryRepositoryContract
     * @return array
     */
    private function getConfig(CountryRepositoryContract $countryRepositoryContract): array
    {
        $result = ['onlyCountries' => [], 'countryMapping' => []];
        $collection = $countryRepositoryContract->getActiveCountriesList();

        foreach ($collection->all() as $item) {
            if (!in_array($item['iso_code_2'], self::B2B_ALLOWED_COUNTRIES)) {
                continue;
            }
            $result['onlyCountries'][] = strtolower($item['iso_code_2']);
            $result['countryMapping'][strtolower($item['iso_code_2'])] = $item['id'];
        }

        return $result;
    }
}
