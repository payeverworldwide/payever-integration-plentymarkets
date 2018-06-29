<?php //strict

namespace Payever\Methods;

use Payever\Methods\AbstractPaymentMethod;

/**
 * Class stripePaymentMethod
 * @package Payever\Methods
 */
class StripePaymentMethod extends AbstractPaymentMethod
{
    public $_methodCode = 'stripe';
}
