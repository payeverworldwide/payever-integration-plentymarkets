<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

use Payever\Sdk\Payments\Http\RequestEntity\CreatePaymentRequest;

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$params         = SdkRestApi::getParam('payment_parameters');
$paymentRequest = new CreatePaymentRequest();
// set order data
$paymentRequest
    ->setAmount($params['amount'])
    ->setFee($params['fee'])
    ->setOrderId($params['order_id'])
    ->setCurrency($params['currency'])
    ->setPaymentMethod($params['payment_method'])
    ->setCart(json_encode($params['cart']));

// set billing data
if ($params['salutation']) {
    $paymentRequest->setSalutation($params['salutation']);
}

$paymentRequest->setFirstName($params['first_name'])
    ->setLastName($params['last_name'])
    ->setCity($params['city'])
    ->setZip($params['zip'])
    ->setStreet($params['street'])
    ->setStreetNumber('')
    ->setCountry($params['country'])
    ->setEmail($params['email'])
    ->setPhone($params['phone']);

// set plugin version
$apiData = SdkRestApi::getParam('sdkData');
$paymentRequest->setPluginVersion($apiData['pluginVersion']);
$paymentRequest->setShippingAddress(json_encode($params['shipping_address']));

$paymentRequest->setSuccessUrl($params['success_url'])
    ->setFailureUrl($params['failure_url'])
    ->setCancelUrl($params['cancel_url'])
    ->setNoticeUrl($params['notice_url']);

return $payeverApi->getPaymentsApiClient()->createPaymentRequest($paymentRequest)->getResponseEntity()->toArray();
