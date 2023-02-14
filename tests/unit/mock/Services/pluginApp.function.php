<?php

namespace Payever\Services;

use Payever\tests\unit\mock\Modules\Payment\Models\Payment;

/**
 * @param string $abstract
 * @param array $parameters
 * @return Payment|null
 */
function pluginApp(string $abstract, array $parameters = [])
{
    $result = null;
    if ($abstract === \Plenty\Modules\Payment\Models\Payment::class) {
        $result = new Payment();
    }

    return $result;
}
