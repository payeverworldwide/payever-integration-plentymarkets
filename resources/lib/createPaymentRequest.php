<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

use Payever\ExternalIntegration\Payments\Http\RequestEntity\CreatePaymentRequest;

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('apiKeys'));

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
$paymentRequest->setPluginVersion($params['plugin_version']);

$paymentRequest->setSuccessUrl($params['success_url'])
               ->setFailureUrl($params['failure_url'])
               ->setCancelUrl($params['cancel_url'])
               ->setNoticeUrl($params['notice_url']);

return $payeverApi->getPaymentsApiClient()->createPaymentRequest($paymentRequest)->getResponseEntity()->toArray();
