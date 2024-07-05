<?php

namespace Payever\Helper;

use Payever\Providers\PayeverRouteServiceProvider;
use Plenty\Modules\Helper\Services\WebstoreHelper;

/**
 * Class RoutesHelper
 */
class RoutesHelper
{
    const REQUEST_PAYMENT_ID = 'payment_id';
    const REQUEST_PAYMENT_REFERENCE = 'reference';

    /**
     * @var WebstoreHelper
     */
    private WebstoreHelper $webStoreHelper;

    /**
     * RoutesHelper constructor.
     *
     * @param WebstoreHelper $webStoreHelper
     */
    public function __construct(WebstoreHelper $webStoreHelper)
    {
        $this->webStoreHelper = $webStoreHelper;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        $webStoreConfig = $this->webStoreHelper->getCurrentWebstoreConfiguration();
        if (is_null($webStoreConfig)) {
            return 'error';
        }

        return (string) $webStoreConfig->domainSsl;
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getSuccessURL(array $params = []): string
    {
        $params = array_merge([self::REQUEST_PAYMENT_ID => '--PAYMENT-ID--'], $params);

        return $this->getUrl(PayeverRouteServiceProvider::CALLBACK_SUCCESS_URL, $params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getPendingURL(array $params = []): string
    {
        $params = array_merge([self::REQUEST_PAYMENT_ID => '--PAYMENT-ID--'], $params);

        return $this->getUrl(PayeverRouteServiceProvider::CALLBACK_PENDING_URL, $params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getCancelURL(array $params = []): string
    {
        $params = array_merge([self::REQUEST_PAYMENT_ID => '--PAYMENT-ID--'], $params);

        return $this->getUrl(PayeverRouteServiceProvider::CALLBACK_CANCEL_URL, $params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getFailureURL(array $params = []): string
    {
        $params = array_merge([self::REQUEST_PAYMENT_ID => '--PAYMENT-ID--'], $params);

        return $this->getUrl(PayeverRouteServiceProvider::CALLBACK_FAILURE_URL, $params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getStatusURL(array $params = []): string
    {
        return $this->getUrl(PayeverRouteServiceProvider::CALLBACK_STATUS_URL, $params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getNoticeURL(array $params = []): string
    {
        $params = array_merge([self::REQUEST_PAYMENT_ID => '--PAYMENT-ID--'], $params);

        return $this->getUrl(PayeverRouteServiceProvider::NOTICE_URL, $params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getQuoteURL(array $params = []): string
    {
        return $this->getUrl(PayeverRouteServiceProvider::WIDGET_QUOTE_URL, $params);
    }

    /**
     * @param string $method
     * @return string
     */
    public function getIframeURL(string $method): string
    {
        return $this->getUrl(PayeverRouteServiceProvider::PAYMENT_IFRAME_URL, ['method' => $method]);
    }

    /**
     * @return string
     */
    public function getCommandEndpoint(): string
    {
        return $this->getUrl(PayeverRouteServiceProvider::CONFIG_COMMAND_URL);
    }

    /**
     * @param string $url
     * @param array $params
     *
     * @return string
     */
    public function getUrl(string $url, array $params = []): string
    {
        $query = $params ? '?' . http_build_query($params) : '';

        return $this->getBaseUrl() . $url . $query;
    }
}
