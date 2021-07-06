<?php

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$timestamp = SdkRestApi::getParam('command_timestamp');
$commandsResponse = $payeverApi->getPluginsApiClient()->getCommands($timestamp);
/** @var \Payever\ExternalIntegration\Plugins\Http\ResponseEntity\CommandsResponseEntity $commandsResponseEntity */
$commandsResponseEntity = $commandsResponse->getResponseEntity()->toArray();

return $commandsResponseEntity['commands'];
