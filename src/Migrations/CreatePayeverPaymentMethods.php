<?php

namespace payever\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use payever\Helper\PayeverHelper;

class CreatePayeverPaymentMethods
{
    /**
     *
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepositoryContract;
    /**
     *
     * @var PaymentHelper
     */
    private $paymentHelper;
    /**
     * Constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepositoryContract
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepositoryContract, PayeverHelper $paymentHelper)
    {
        $this->paymentMethodRepositoryContract = $paymentMethodRepositoryContract;
        $this->paymentHelper = $paymentHelper;
    }
    /**
     * Creates the payment methods for the Wallee plugin.
     */
    public function run()
    {
        $foundMethods = $this->paymentHelper->getMopKeyToIdMap();

        foreach ($this->paymentHelper->getMethodsMetaData() as $paymentKey => $paymentData) {
            if (!isset($foundMethods[$paymentKey])) {
                $paymentData['pluginKey'] = PayeverHelper::PLUGIN_KEY;
                $paymentData['paymentKey'] = $paymentKey;
                $this->paymentMethodRepositoryContract->createPaymentMethod($paymentData);
            }
        }
    }
}
