<?php

namespace Payever\Api;

class PayeverApi
{
    protected $_client_id;
    protected $_client_secret;
    protected $_lastAuthenticationResponse;
    protected $_lastCreatePaymentResponse;
    protected $_lastRetrievePaymentResponse;
    protected $_lastRefundPaymentResponse;
    protected $_mode;
    protected $_errors;
    protected $_debug;
    protected $_requests;

    const SANDBOX_MODE = 0;
    const LIVE_MODE = 1;
    const STAGE_MODE = 2;
    const SHOWROOM_MODE = 3;

    const API_URL_LIVE = 'https://mein.payever.de/';
    const API_URL_SANDBOX = 'https://sandbox.payever.de/';
    const API_URL_STAGE = 'https://stage.payever.de/';
    const API_URL_SHOWROOM = 'https://showroom1.payever.de/';

    const API_SUB_URL_AUTH = 'oauth/v2/token';
    const API_SUB_CREATE_PAYMENT = 'api/payment';
    const API_SUB_RETRIEVE_PAYMENT = 'api/payment';
    const API_SUB_REFUND_PAYMENT = 'api/payment/refund';
    const API_SUB_LIST_PAYMENT_OPTIONS = 'api/shop';
    const API_GRAND_TYPE = 'http://www.payever.de/api/payment';
    const API_CREATE_PAYMENT = 'API_CREATE_PAYMENT';
    const API_PAYMENT_INFO = 'API_PAYMENT_INFO';

    const ERROR_WRONG_CLIENT_DATA = 'API ERROR: Wrong client id or client secret';
    const ERROR_FAILED_JSON_DECODE = 'JSON DECODE ERROR: ';
    const ERROR_EMPTY_ACCESS_TOKEN = 'Empty access token ';

    const STATUS_NEW = 'STATUS_NEW';
    const STATUS_IN_PROCESS = 'STATUS_IN_PROCESS';
    const STATUS_ACCEPTED = 'STATUS_ACCEPTED';
    const STATUS_FAILED = 'STATUS_FAILED';
    const STATUS_DECLINED = 'STATUS_DECLINED';
    const STATUS_IN_COLLECTION = 'STATUS_IN_COLLECTION';
    const STATUS_LATEPAYMENT = 'STATUS_LATEPAYMENT';
    const STATUS_SANTANDER_IN_PROCESS = 'STATUS_SANTANDER_IN_PROCESS';
    const STATUS_SANTANDER_SHOP_TEMPORARY_APPROVED = 'STATUS_SANTANDER_SHOP_TEMPORARY_APPROVED';

    private static $statuses = [
        'STATUS_IN_PROCESS' => [
            'STATUS_SANTANDER_IN_PROGRESS',
            'STATUS_SANTANDER_IN_PROCESS',
            'STATUS_SANTANDER_DEFERRED',
            'STATUS_SANTANDER_IN_DECISION',
            'STATUS_SANTANDER_DECISION_NEXT_WORKING_DAY',
            'STATUS_SANTANDER_SHOP_TEMPORARY_APPROVED',
            'STATUS_SANTANDER_APPROVED_WITH_REQUIREMENTS',
            'PENDING',
        ],
        'STATUS_ACCEPTED' => [
            'STATUS_SANTANDER_APPROVED',
            'STATUS_SANTANDER_ACCOUNT_OPENED',
            'STATUS_INVOICE_INCOLLECTION',
            'STATUS_INVOICE_LATEPAYMENT',
            'STATUS_INVOICE_REMINDER',
        ],
        'STATUS_PAID' => '',
        'STATUS_DECLINED' => [
            'STATUS_SANTANDER_DECLINED',
            'STATUS_SANTANDER_AUTOMATIC_DECLINE',
        ],
        'STATUS_REFUNDED' => '',
        'STATUS_FAILED' => [
            'STATUS_SANTANDER_CANCELLED',
            'STATUS_SANTANDER_CANCELLED_ANOTHER',
            'STATUS_SANTANDER_IN_CANCELLATION',
        ],
        'STATUS_CANCELLED' => [
            'STATUS_INVOICE_CANCELLATION',
        ],
        'STATUS_NEW' => '',
    ];

    /**
     * @param $client_id
     * @param $client_secret
     * @throws self::ERROR_WRONG_CLIENT_DATA
     */
    public function set($client_id, $client_secret, $mode = self::SANDBOX_MODE)
    {
        if (!empty($client_id) && !empty($client_secret)) {
            $this->_client_id = $client_id;
            $this->_client_secret = $client_secret;
            $this->_mode = $mode;
            $this->setDebug(true);
        } else {
            $this->addError(self::ERROR_WRONG_CLIENT_DATA);
        }

        return $this;
    }

    public function getBaseURL()
    {

        switch ($this->_mode) {
            case 0:
                return self::API_URL_SANDBOX;
                break;
            case 1:
                return self::API_URL_LIVE;
                break;
            case 2:
                return self::API_URL_STAGE;
                break;
            case 3:
                return self::API_URL_SHOWROOM;
        }
    }

    public function getAuthenticationURL()
    {
        return $this->getBaseURL().self::API_SUB_URL_AUTH;
    }

    public function getCreatePaymentURL()
    {
        return $this->getBaseURL().self::API_SUB_CREATE_PAYMENT;
    }

    public function getRetrievePaymentURL($paymentId)
    {
        return $this->getBaseURL().self::API_SUB_RETRIEVE_PAYMENT.'/'.$paymentId;
    }

    public function getRefundPaymentURL($paymentId)
    {
        return $this->getBaseURL().self::API_SUB_REFUND_PAYMENT.'/'.$paymentId;
    }

    public function getListPaymentOptionsURL($slug, $channel)
    {
        return $this->getBaseURL().self::API_SUB_LIST_PAYMENT_OPTIONS.'/'.$slug.'/'.'payment-options'.'/'.$channel;
    }

    public function jsonSaveDecode($stringJson)
    {
        $ret = json_decode($stringJson);

        /*if (function_exists('json_last_error')) {
            if (json_last_error() != JSON_ERROR_NONE) {
                $this->addError(self::ERROR_FAILED_JSON_DECODE
                    . ' ' . json_last_error()
                    // . ' ' . json_last_error_msg()
                    );
            }
        }*/

        if (!empty($ret)) {
            return $ret;
        } else {
            return false;
        }
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    public function authenticationRequest($scope = self::API_CREATE_PAYMENT)
    {
        $url = $this->getAuthenticationURL();

        $postData = [
            'client_id' => $this->_client_id,
            'client_secret' => $this->_client_secret,
            'grant_type' => "http://www.payever.de/api/payment",
            'scope' => $scope,
        ];

        $this->addRequest($postData, 'authentication');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($postData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, 1.1);

        $responseJSON = curl_exec($ch);
        /*if (curl_errno($ch)) {
            $this->addError(curl_error($ch));
        } else {
            curl_close($ch);
        }*/

        if (!is_string($responseJSON) || !strlen($responseJSON)) {
            $this->addError("Authentication failed");
            $this->_lastAuthenticationResponse = false;
        } else {
            $authenticationResponse = $this->jsonSaveDecode($responseJSON);

            if (!empty($authenticationResponse->error)) {
                $this->addError($authenticationResponse->error.' : '.$authenticationResponse->error_description);
                $this->_lastAuthenticationResponse = false;
            } else {
                $this->_lastAuthenticationResponse = $authenticationResponse;
            }
        }

        return $this->_lastAuthenticationResponse;
    }

    /**
     * Check if we have existing token and create it if not
     *
     * @return bool
     */
    protected function checkAuthorization()
    {
        if (!$this->_lastAuthenticationResponse && !$this->authenticationRequest()) {
            return false;
        }
        return true;
    }

    public function addError($error)
    {
        $this->_errors[] = $error;

        return $this;
    }

    public function setDebug($debug_mode = true)
    {
        $this->_debug = $debug_mode;

        return $this;
    }

    public function addRequest($request, $key)
    {
        if ($this->_debug) {
            $this->_requests[$key] = $request;
        }

        return $this;
    }

    public function getRequests()
    {
        return $this->_requests;
    }

    public function createPaymentRequest($orderData)
    {
        if (!$this->checkAuthorization()) {
            return false;
        }

        $url = $this->getCreatePaymentURL();

        if (!empty($this->_lastAuthenticationResponse) && $accessToken = $this->_lastAuthenticationResponse->access_token) {
            $orderData['access_token'] = $accessToken;
            $orderData['cart'] = json_encode($orderData['cart']);
            //$fieldsString = http_build_query($orderData);
            $this->addRequest($orderData, 'createPayment');

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$accessToken]);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POST, count($orderData));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $orderData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            //curl_setopt($ch,CURLOPT_FOLLOWLOCATION , true);

            $result = curl_exec($ch);
            curl_close($ch);
            $createPaymentResponse = $this->jsonSaveDecode($result);

            if (!empty($createPaymentResponse->error)) {
                $this->addError($createPaymentResponse->error.' : '.$createPaymentResponse->error_description);
                $this->_lastCreatePaymentResponse = false;
            } else {
                $this->_lastCreatePaymentResponse = $createPaymentResponse;
            }

            return $this->_lastCreatePaymentResponse;
        } else {
            $this->addError(self::ERROR_EMPTY_ACCESS_TOKEN);

            return false;
        }
    }

    public function retrievePayment($paymentId)
    {
        if (!$this->checkAuthorization()) {
            return false;
        }

        $url = $this->getRetrievePaymentURL($paymentId);
        $this->addRequest($url, 'retrievePayment');

        if (!empty($this->_lastAuthenticationResponse) && $accessToken = $this->_lastAuthenticationResponse->access_token) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$accessToken]);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, 1.1);

            //execute post
            $result = curl_exec($ch);
            curl_close($ch);

            $lastRetrievePaymentResponse = $this->jsonSaveDecode($result);

            if (!empty($lastRetrievePaymentResponse->error)) {
                $this->addError(
                    $lastRetrievePaymentResponse->error.' : '.$lastRetrievePaymentResponse->error_description
                );
                $this->_lastRetrievePaymentResponse = false;
            } else {
                $this->_lastRetrievePaymentResponse = $lastRetrievePaymentResponse;
            }

            return $this->_lastRetrievePaymentResponse;
        }
    }

    public function refundPayment($paymentId, $amountArray)
    {
        if (!$this->checkAuthorization()) {
            return false;
        }

        $url = $this->getRefundPaymentURL($paymentId);
        $this->addRequest($url, 'refundPayment');

        if (!empty($this->_lastAuthenticationResponse) && $accessToken = $this->_lastAuthenticationResponse->access_token) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$accessToken]);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POST, count($amountArray));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $amountArray);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, 1.1);

            //execute post
            $result = curl_exec($ch);
            curl_close($ch);

            $lastRefundPaymentResponse = $this->jsonSaveDecode($result);

            if (!empty($lastRefundPaymentResponse->error)) {
                $this->addError($lastRefundPaymentResponse->error.' : '.$lastRefundPaymentResponse->error_description);
                $this->_lastRefundPaymentResponse = false;
            } else {
                $this->_lastRefundPaymentResponse = $lastRefundPaymentResponse;
            }

            return $this->_lastRefundPaymentResponse;
        }
    }

    public function getListPaymentOptions($slug, $channel)
    {
        $url = $this->getListPaymentOptionsURL($slug, $channel);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, 1.1);

        //execute post
        $result = curl_exec($ch);
        curl_close($ch);

        $lastRetrievePaymentResponse = $this->jsonSaveDecode($result);

        if (!empty($lastRetrievePaymentResponse->error)) {
            $this->addError($lastRetrievePaymentResponse->error.' : '.$lastRetrievePaymentResponse->error_description);
            $this->_lastRetrievePaymentResponse = false;
        } elseif (isset($lastRetrievePaymentResponse->call->status) && $lastRetrievePaymentResponse->call->status == 'failed') {
            $this->addError("The channel 'WooCommerce' is not subscribed");
            $this->_lastRetrievePaymentResponse = false;
        } else {
            $this->_lastRetrievePaymentResponse = $lastRetrievePaymentResponse;
        }

        return $this->_lastRetrievePaymentResponse;
    }

    public function getPayeverStatus($response_status)
    {
        $statuses_array = self::$statuses;

        foreach ($statuses_array as $key => $status) {
            if ($key === $response_status || (is_array($status) && in_array($response_status, $status))) {
                return $key;
            }
        }

        return false;
    }
}
