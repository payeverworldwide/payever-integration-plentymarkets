<?php //strict

namespace Payever\Methods;

use Payever\Methods\AbstractPaymentMethod;

/**
 * Class InstantPaymentMethod
 * @package Payever\Methods
 */
class InstantPaymentMethod extends AbstractPaymentMethod
{
    public $_methodCode = 'instant_payment';
}
