<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
$items = SdkRestApi::getParam('items');
$deliveryFee = SdkRestApi::getParam('deliveryFee');

return $payeverApi
    ->getPaymentsApiClient()
    ->refundItemsPaymentRequest($transactionId, $items, $deliveryFee)
    ->getResponseEntity()->toArray();
