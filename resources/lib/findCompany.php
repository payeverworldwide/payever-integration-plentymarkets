<?php

use Payever\Sdk\Payments\Http\RequestEntity\CompanySearch\CompanyEntity;
use Payever\Sdk\Payments\Http\RequestEntity\CompanySearchRequest;
use Payever\Sdk\Payments\Http\RequestEntity\CompanySearch\AddressEntity;

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$company = SdkRestApi::getParam('company');
$country = SdkRestApi::getParam('country');

$companySearchEntity = new CompanyEntity();
$companySearchEntity->setName($company);
$companySearchRequest = new CompanySearchRequest();

if (!empty($country)) {
    $companyAddressEntity = new AddressEntity();
    $companyAddressEntity->setCountry($country);
    $companySearchRequest->setAddress($companyAddressEntity);
}

$companySearchRequest->setCompany($companySearchEntity);

return $payeverApi
    ->getPaymentsApiClient()
    ->searchCompany($companySearchRequest)
    ->getResponseEntity()
    ->toArray();
