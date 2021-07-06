<?php

namespace Payever\Methods;

/**
 * Class StripePaymentMethod
 * @package Payever\Methods
 */
class StripePaymentMethod extends AbstractPaymentMethod
{
    /**
     * @var string
     */
    public $methodCode = 'stripe';
}
