<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$timestamt = SdkRestApi::getParam('command_timestamt');
$commandsResponse = $payeverApi->getPluginsApiClient()->getCommands($timestamt);
/** @var CommandsResponseEntity $commandsResponseEntity */
$commandsResponseEntity = $commandsResponse->getResponseEntity()->toArray();

return $commandsResponseEntity['commands'];
