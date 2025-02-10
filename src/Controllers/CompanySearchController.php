<?php

namespace Payever\Controllers;

use Payever\Repositories\CustomerCompanyAddressRepository;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Log\Loggable;
use Payever\Services\PayeverSdkService;
use Payever\Helper\CompanySearchHelper;

/**
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
class CompanySearchController extends Controller
{
    use Loggable;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var PayeverSdkService
     */
    private $sdkService;

    /**
     * @var CountryRepositoryContract
     */
    private $countryRepositoryContract;

    /** @var CustomerCompanyAddressRepository  */
    private $customerCompanyAddressRepository;

    /** @var CompanySearchHelper  */
    private $companySearchHelper;

    /**
     * @param Request $request
     * @param PayeverSdkService $sdkService
     * @param CountryRepositoryContract $countryRepositoryContract
     * @param CustomerCompanyAddressRepository $customerCompanyAddressRepository
     * @param CompanySearchHelper $companySearchHelper
     */
    public function __construct(
        Request $request,
        PayeverSdkService $sdkService,
        CountryRepositoryContract $countryRepositoryContract,
        CustomerCompanyAddressRepository $customerCompanyAddressRepository,
        CompanySearchHelper $companySearchHelper
    ) {
        $this->request = $request;
        $this->sdkService = $sdkService;
        $this->countryRepositoryContract = $countryRepositoryContract;
        $this->customerCompanyAddressRepository = $customerCompanyAddressRepository;
        $this->companySearchHelper = $companySearchHelper;
    }

    /**
     * @return void
     */
    public function companySearch()
    {
        $params = [
            'company' => $this->request->get('term'),
            'country' => $this->request->get('country')
        ];
        $searchResponse = $this->sdkService->call('findCompany', $params);

        $result = '';

        if (!empty($searchResponse) && !empty($searchResponse['result'])) {
            $result = json_encode($searchResponse['result']);
        }

        print $result;
        exit();
    }

    /**
     * @return void
     */
    public function company()
    {
        $addressHash = $this->companySearchHelper->generateAddressHash(
            $this->request->get('company'),
            $this->request->get('email'),
            $this->request->get('town'),
            $this->request->get('zip')
        );

        $company = $this->customerCompanyAddressRepository->getByAddressHash($addressHash);

        if (empty($company)) {
            $company = $this->customerCompanyAddressRepository->create();
        }
        $customerData = $this->request->get('companyData');
        $companyEntity = [
            'name'       => $customerData['name'] ?? '',
            'externalId' => $customerData['id'] ?? '',
            'taxId'      => $customerData['vat_id'] ?? '',
        ];
        $company
            ->setCompany(json_encode($companyEntity))
            ->setAddressHash($addressHash)
        ;
        $company = $this->customerCompanyAddressRepository->persist($company);

        $result = [
            'id' => $company->getId(),
            'addressHash' => $addressHash
        ];

        print json_encode($result);
        exit();
    }
}
