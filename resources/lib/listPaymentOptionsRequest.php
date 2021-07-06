<?php

require_once __DIR__ . '/PayeverSdkProvider.php';

$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));
$result = [];
try {
    $result = $payeverApi->getPaymentsApiClient()->listPaymentOptionsRequest()->getResponseEntity()->toArray();
} catch (\Exception $e) {
    $message = $e->getMessage();
    if (401 == $e->getCode()) {
        $message = <<<TXT
Could not synch - please check if the credentials you entered are correct and match the mode (live/sandbox)
TXT;
    }
    $result = array_merge(
        $result,
        [
            'error' => true,
            'error_msg' => $message,
        ]
    );
}

return $result;
