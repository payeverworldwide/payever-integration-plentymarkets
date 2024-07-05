<?php

use Payever\Sdk\Payments\Http\RequestEntity\ShippingDetailsEntity;
use Payever\Sdk\Payments\Http\RequestEntity\ShippingGoodsPaymentRequest;

require_once __DIR__ . '/PayeverSdkProvider.php';
$payeverApi = new PayeverSdkProvider(SdkRestApi::getParam('sdkData'));

$transactionId = SdkRestApi::getParam('transactionId');
$amount = SdkRestApi::getParam('amount');
$paymentItems = SdkRestApi::getParam('paymentItems');
$deliveryFee = SdkRestApi::getParam('deliveryFee');
$reason = SdkRestApi::getParam('reason');
$carrier = SdkRestApi::getParam('carrier');
$trackingNumber = SdkRestApi::getParam('trackingNumber');
$trackingUrl = SdkRestApi::getParam('trackingUrl');
$shippingDate = SdkRestApi::getParam('shippingDate');
$identifier = SdkRestApi::getParam('identifier');

$shippingGoodsRequestEntity = new ShippingGoodsPaymentRequest();
$shippingGoodsRequestEntity->setReason($reason);

// Add payment items
$orderAmount = 0;

if ($paymentItems) {
    foreach ($paymentItems as $paymentItem) {
        $orderAmount += ($paymentItem['price'] * $paymentItem['quantity']);
    }

    if ($deliveryFee > 0) {
        $orderAmount += $deliveryFee;
    }

    // Verify order totals to avoid of the rounding issue
    if (round($orderAmount, 2) === round($amount, 2)) {
        $shippingGoodsRequestEntity->setPaymentItems($paymentItems);

        if ($deliveryFee > 0) {
            $shippingGoodsRequestEntity->setDeliveryFee($deliveryFee);
        }
    } else {
        $shippingGoodsRequestEntity->setAmount($amount);
    }
} else {
    $shippingGoodsRequestEntity->setAmount($amount);
}

// Add shipping details
if ($carrier) {
    $shippingDetailsEntity = new ShippingDetailsEntity();
    $shippingDetailsEntity->setShippingCarrier($carrier)
        ->setShippingMethod($carrier)
        ->setShippingDate($shippingDate);

    if (!empty($trackingNumber)) {
        $shippingDetailsEntity->setTrackingNumber($trackingNumber);
    }

    if (!empty($trackingUrl)) {
        $shippingDetailsEntity->setTrackingUrl($trackingUrl);
    }

    $shippingGoodsRequestEntity->setShippingDetails($shippingDetailsEntity);
}

return $payeverApi
    ->getPaymentsApiClient()
    ->shippingGoodsPaymentRequest($transactionId, $shippingGoodsRequestEntity, $identifier)
    ->getResponseEntity()
    ->toArray();
