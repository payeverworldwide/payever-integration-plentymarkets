<?php

namespace Payever\Helper;

use Payever\tests\unit\mock\Modules\Payment\Models\PaymentProperty;

/**
 * @param string $abstract
 * @param array $parameters
 * @return PaymentProperty|null
 */
function pluginApp(string $abstract, array $parameters = [])
{
    $result = null;
    if ($abstract === \Plenty\Modules\Payment\Models\PaymentProperty::class) {
        $result = new PaymentProperty();
    }

    return $result;
}
