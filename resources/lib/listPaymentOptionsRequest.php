<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('apiKeys'));
return $payeverApi->getPaymentsApiClient()->listPaymentOptionsRequest()->getResponseEntity()->toArray();
