<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

$sdkData = SdkRestApi::getParam('sdkData');
$params = SdkRestApi::getParam('payment_parameters');

$payeverApi = new PayeverSdkProvider($sdkData);
return $payeverApi->getThirdPartyPluginsApiClient()->validateToken(
    $sdkData['slug'],
    $params['authorization']
);
