<?php

use Payever\Sdk\Payments\Http\RequestEntity\ClaimPaymentRequest;

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
$isDisputed = SdkRestApi::getParam('is_disputed');

$claimPaymentRequest = new ClaimPaymentRequest();
$claimPaymentRequest->setIsDisputed($isDisputed);

return $payeverApi
    ->getPaymentsApiClient()
    ->claimPaymentRequest($transactionId, $claimPaymentRequest)
    ->getResponseEntity()
    ->toArray();
