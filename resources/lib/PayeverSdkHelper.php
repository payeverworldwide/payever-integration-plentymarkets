<?php

require_once __DIR__ . '/PayeverConfiguration.php';
require_once __DIR__ . '/PayeverApi.php';
require_once __DIR__ . '/PayeverToken.php';
require_once __DIR__ . '/PayeverTokenlist.php';

class PayeverSdkHelper
{
    /**
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $slug
     * @param int $environment
     * @return \PayeverApi
     */
    public static function getPayeverApi($clientId, $clientSecret, $slug, $environment): PayeverApi
    {
        $apiMode = $environment == 1 ? PayeverConfiguration::MODE_LIVE : PayeverConfiguration::MODE_SANDBOX;
        PayeverApi::getInstance()->getConfiguration()
            ->setClientId($clientId)
            ->setClientSecret($clientSecret)
            ->setSlug($slug)
            ->setApiMode($apiMode)
            ->load()
        ;

        return PayeverApi::getInstance();
    }
}