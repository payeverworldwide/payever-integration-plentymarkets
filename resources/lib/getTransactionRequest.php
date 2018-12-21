<?php

require_once __DIR__ . '/PayeverSdkHelper.php';
$payeverApi = PayeverSdkHelper::getPayeverApi(SdkRestApi::getParam('clientId'), SdkRestApi::getParam('clientSecret'), SdkRestApi::getParam('slug'), SdkRestApi::getParam('environment'));

$transactionId = SdkRestApi::getParam('transaction_id');

return $payeverApi->getTransactionRequest($transactionId)->getResponseEntity()->toArray();
