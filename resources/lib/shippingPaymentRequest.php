<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
return $payeverApi->getPaymentsApiClient()->shippingGoodsPaymentRequest($transactionId)->getResponseEntity()->toArray();