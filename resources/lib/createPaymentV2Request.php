<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

use Payever\ExternalIntegration\Payments\Http\RequestEntity\CreatePaymentV2Request;

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$params = SdkRestApi::getParam('payment_parameters');
$paymentRequest = new CreatePaymentV2Request();
$apiData = SdkRestApi::getParam('sdkData');
// set order data
$paymentRequest
    ->setChannel(json_encode(['name' => 'plentymarkets', 'source' => $apiData['pluginVersion']]))
    ->setAmount($params['amount'])
    ->setFee($params['fee'])
    ->setOrderId($params['order_id'])
    ->setCurrency($params['currency'])
    ->setPaymentMethod($params['payment_method'])
    ->setCart(json_encode($params['cart']));

$paymentRequest->setPaymentData(json_encode(['force_redirect' => (bool)$params['force_redirect']]));

$paymentRequest->setEmail($params['email'])
    ->setPhone($params['phone']);

$paymentRequest->setShippingAddress(json_encode($params['shipping_address']));
$paymentRequest->setBillingAddress(json_encode($params['billing_address']));

$paymentRequest->setSuccessUrl($params['success_url'])
    ->setFailureUrl($params['failure_url'])
    ->setCancelUrl($params['cancel_url'])
    ->setNoticeUrl($params['notice_url']);

return $payeverApi->getPaymentsApiClient()->createPaymentV2Request($paymentRequest)->getResponseEntity()->toArray();
