<?php

use Payever\Sdk\Payments\Http\RequestEntity\PaymentItemEntity;

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transaction_id');
$items = SdkRestApi::getParam('items');
$deliveryFee = SdkRestApi::getParam('deliveryFee');
$identifier = SdkRestApi::getParam('identifier');

$paymentItems = [];
foreach ($items as $item) {
    /** @var array{identifier: string, name: string, price: float, quantity: int} $item */
    $paymentEntity = new PaymentItemEntity();
    $paymentEntity->setIdentifier($item['identifier'])
        ->setName($item['name'])
        ->setPrice($item['price'])
        ->setQuantity($item['quantity']);

    $paymentItems[] = $paymentEntity;
}

return $payeverApi
    ->getPaymentsApiClient()
    ->cancelItemsPaymentRequest($transactionId, $paymentItems, $deliveryFee, $identifier)
    ->getResponseEntity()
    ->toArray();
