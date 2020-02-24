<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$paymentId = SdkRestApi::getParam('payment_id');
return $payeverApi->getPaymentsApiClient()->retrievePaymentRequest($paymentId)->getResponseEntity()->toArray();
