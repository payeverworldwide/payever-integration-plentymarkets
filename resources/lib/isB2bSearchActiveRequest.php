<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

return $payeverApi
    ->getPaymentsApiClient()
    ->isB2bSearchActive();
