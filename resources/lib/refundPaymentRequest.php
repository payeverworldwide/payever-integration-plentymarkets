<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
$amount = SdkRestApi::getParam('amount');

return $payeverApi->getPaymentsApiClient()->refundPaymentRequest($transactionId, $amount)->getResponseEntity()->toArray();

