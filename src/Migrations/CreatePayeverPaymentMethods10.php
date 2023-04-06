<?php

namespace Payever\Migrations;

use Payever\Helper\PayeverHelper;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;

class CreatePayeverPaymentMethods10
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
     * @param PaymentMethodRepositoryContract $paymentMethodRepositoryContract
     * @param PayeverHelper $paymentHelper
     */
    public function __construct(
        PaymentMethodRepositoryContract $paymentMethodRepositoryContract,
        PayeverHelper $paymentHelper
    ) {
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
            } else {
                $paymentData['id'] = $foundMethods[$paymentKey];
                $paymentData['pluginKey'] = PayeverHelper::PLUGIN_KEY;
                $paymentData['paymentKey'] = $paymentKey;
                $paymentData['name'] = 'payever: ' . $paymentData['name'];
                $this->paymentMethodRepositoryContract->updateName($paymentData);
            }
        }
    }
}
