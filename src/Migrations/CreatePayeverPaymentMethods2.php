<?php

namespace Payever\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Payever\Helper\PayeverHelper;

class CreatePayeverPaymentMethods2
{
    /**
     *
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepositoryContract;
    /**
     *
     * @var PayeverHelper
     */
    private $paymentHelper;
    /**
     * Constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepositoryContract
     * @param PayeverHelper $paymentHelper
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepositoryContract, PayeverHelper $paymentHelper)
    {
        $this->paymentMethodRepositoryContract = $paymentMethodRepositoryContract;
        $this->paymentHelper = $paymentHelper;
    }
    /**
     * Creates the payment methods for the payever plugin.
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
