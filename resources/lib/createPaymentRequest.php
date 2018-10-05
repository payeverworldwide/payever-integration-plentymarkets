<?php

require_once __DIR__ . '/PayeverSdkHelper.php';
$payeverApi = PayeverSdkHelper::getPayeverApi(SdkRestApi::getParam('clientId'), SdkRestApi::getParam('clientSecret'), SdkRestApi::getParam('slug'), SdkRestApi::getParam('environment'));

$params = SdkRestApi::getParam('payment_parameters');
return $payeverApi->createPaymentRequest($params)->getResponseEntity()->toArray();
