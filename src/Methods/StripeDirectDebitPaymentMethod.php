<?php //strict

namespace Payever\Methods;

use Payever\Methods\AbstractPaymentMethod;

/**
 * Class StripeDirectDebitPaymentMethod
 * @package Payever\Methods
 */
class StripeDirectDebitPaymentMethod extends AbstractPaymentMethod
{
    public $_methodCode = 'stripe_directdebit';
}
