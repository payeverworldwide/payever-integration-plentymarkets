<?php //strict

namespace payever\Methods;

use payever\Methods\AbstractPaymentMethod;

/**
 * Class stripePaymentMethod
 * @package payever\Methods
 */
class StripePaymentMethod extends AbstractPaymentMethod
{
    public $_methodCode = 'stripe';
}
