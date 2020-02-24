<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

return $payeverApi->getPluginsApiClient()->getRegistryInfoProvider()->getSupportedCommands();
