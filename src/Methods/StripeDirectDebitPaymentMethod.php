<?php

namespace Payever\Methods;

/**
 * Class StripeDirectDebitPaymentMethod
 * @package Payever\Methods
 */
class StripeDirectDebitPaymentMethod extends AbstractPaymentMethod
{
    /**
     * @var string
     */
    public $methodCode = 'stripe_directdebit';
}
