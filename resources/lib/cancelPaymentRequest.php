<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('apiKeys'));

$transactionId = SdkRestApi::getParam('transaction_id');
return $payeverApi->getPaymentsApiClient()->cancelPaymentRequest($transactionId)->getResponseEntity()->toArray();
