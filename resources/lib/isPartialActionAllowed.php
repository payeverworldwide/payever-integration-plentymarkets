<?php

use Payever\ExternalIntegration\Payments\Action\ActionDecider;

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transactionId');
$transactionAction = SdkRestApi::getParam('transactionAction');
$throwException = SdkRestApi::getParam('throwException');

$actionDecider = new ActionDecider($payeverApi->getPaymentsApiClient());

return $actionDecider->isPartialActionAllowed($transactionId, $transactionAction, $throwException);
