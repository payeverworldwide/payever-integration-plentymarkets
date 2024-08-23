<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

use Payever\Sdk\Payments\Http\RequestEntity\SubmitPaymentRequestV3;
use Payever\Sdk\Payments\Http\MessageEntity\CompanyEntity;
use Payever\Sdk\Payments\Http\MessageEntity\PurchaseEntity;
use Payever\Sdk\Payments\Http\MessageEntity\ShippingOptionEntity;
use Payever\Sdk\Payments\Http\MessageEntity\ChannelEntity;
use Payever\Sdk\Payments\Http\MessageEntity\UrlsEntity;
use Payever\Sdk\Payments\Http\MessageEntity\CustomerEntity;
use Payever\Sdk\Payments\Enum\Status;

const CUSTOMER_PERSON_ACCOUNT_TYPE = 'person';
const CUSTOMER_ORGANIZATION_ACCOUNT_TYPE = 'organization';

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$params         = SdkRestApi::getParam('payment_parameters');
$paymentRequest = new SubmitPaymentRequestV3();
$apiData = SdkRestApi::getParam('sdkData');

$channelEntity = new ChannelEntity();
$channelEntity->setName('plentymarkets')
    ->setType('ecommerce')
    ->setSource($apiData['pluginVersion']);

$customerEntity = new CustomerEntity();
$customerEntity->setType(CUSTOMER_PERSON_ACCOUNT_TYPE)
    ->setPhone($params['phone'])
    ->setEmail($params['email']);

if (isset($params['company'])) {
    $customerEntity->setType(CUSTOMER_ORGANIZATION_ACCOUNT_TYPE);
}

$urlsEntity = new UrlsEntity;
$urlsEntity->setSuccess($params['success_url'])
    ->setFailure($params['failure_url'])
    ->setCancel($params['cancel_url'])
    ->setNotification($params['notice_url'])
    ->setPending($params['notice_url']);

$purchaseEntity = new PurchaseEntity();
$purchaseEntity->setAmount($params['amount'])
    ->setDeliveryFee($params['fee'])
    ->setCurrency($params['currency']);

// set order data
$paymentRequest
    ->setChannel($channelEntity)
    ->setPaymentMethod($params['payment_method'])
    ->setPluginVersion($apiData['pluginVersion'])
    ->setLocale($params['locale'])
    ->setClientIp($params['client_ip'])
    ->setReference($params['order_id'])
    ->setUrls($urlsEntity)
    ->setPurchase($purchaseEntity)
    ->setCustomer($customerEntity)
    ->setPaymentData(json_encode(['force_redirect' => (bool)$params['force_redirect']]))
    ->setShippingAddress(json_encode($params['shipping_address']))
    ->setBillingAddress(json_encode($params['billing_address']))
    ->setCart(json_encode($params['cart']));

$shippingOptionEntity = new ShippingOptionEntity();
$shippingOptionEntity->setName($params['shipping_title'])
    ->setCarrier($params['shipping_method'])
    ->setPrice($params['fee'])
    ->setTaxAmount(0)
    ->setTaxRate(0);

if (isset($params['company'])) {
    $companyEntity = new CompanyEntity();
    if (!empty($params['company']['vat_id'])) {
        $companyEntity->setTaxId($params['company']['vat_id']);
    }
    $companyEntity->setExternalId($params['company']['id']);
    $paymentRequest->setCompany($companyEntity);
}

$paymentRequest->setShippingOption($shippingOptionEntity);

if (!$paymentRequest->isValid()) {
    throw new \Exception("Request not valid:", json_encode($paymentRequest->toArray()));
}

$result = $payeverApi
    ->getPaymentsApiClient()
    ->submitPaymentRequestV3($paymentRequest)
    ->getResponseEntity()
    ->toArray();

if (isset($result['result']) && $result['result']['status'] == Status::STATUS_DECLINED) {
    throw new Exception('Creating payment has been declined');
}

return $result;
