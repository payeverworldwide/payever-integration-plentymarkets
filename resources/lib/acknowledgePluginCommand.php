<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$commandId = SdkRestApi::getParam('commandId');
return $payeverApi->getPluginsApiClient()->acknowledgePluginCommand($commandId);
