<?php

use Payever\Sdk\Payments\Http\RequestEntity\ClaimUploadPaymentRequest;
use Payever\Sdk\Payments\Http\ResponseEntity\ClaimUploadPaymentResponse;

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
$files = SdkRestApi::getParam('files');

$result = null;
foreach ($files as $key => $invoice) {
    $claimUploadPaymentRequest = new ClaimUploadPaymentRequest();
    $claimUploadPaymentRequest->setFileName($invoice['name']);
    $claimUploadPaymentRequest->setMimeType($invoice['type']);
    $claimUploadPaymentRequest->setBase64Content($invoice['content']);
    $claimUploadPaymentRequest->setDocumentType(ClaimUploadPaymentRequest::DOCUMENT_TYPE_INVOICE);

    /** @var ClaimUploadPaymentResponse $result */
    $result = $payeverApi
        ->getPaymentsApiClient()
        ->claimUploadPaymentRequest($transactionId, $claimUploadPaymentRequest);
}

return $result;
