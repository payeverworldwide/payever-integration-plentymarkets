<?php //strict

namespace payever\Methods;

use payever\Methods\AbstractPaymentMethod;

/**
 * Class invoicePaymentMethod
 * @package payever\Methods
 */
class InvoicePaymentMethod extends AbstractPaymentMethod
{
    public $_methodCode = 'invoice';
}
