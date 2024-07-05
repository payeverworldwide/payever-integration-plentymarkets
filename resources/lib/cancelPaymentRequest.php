<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
$amount = SdkRestApi::getParam('amount');
$identifier = SdkRestApi::getParam('identifier');

return $payeverApi
    ->getPaymentsApiClient()
    ->cancelPaymentRequest($transactionId, $amount, $identifier)
    ->getResponseEntity()
    ->toArray();
