<?php

namespace Payever\tests\unit\mock\Modules\Order\Shipping\Countries\Models;

class Country extends \Plenty\Modules\Order\Shipping\Countries\Models\Country
{
    public $id = 1;
    public $name = 'Germany';
    public $shippingDestinationId;
    public $active = 1;
    public $storehouseId;
    public $isoCode2 = 'DE';
    public $isoCode3;
    public $lang;
    public $isCountryStateMandatory;
    public $states;
    public $names;
    public $vatCodes;
    public $region;
}
