<?php

namespace Payever\Repositories;

use Payever\tests\unit\mock\Models\PayeverConfig;

/**
 * @param string $abstract
 * @param array $parameters
 * @return PayeverConfig|null
 */
function pluginApp(string $abstract, array $parameters = [])
{
    $result = null;
    if ($abstract === \Payever\Models\PayeverConfig::class) {
        $result = new PayeverConfig();
    }

    return $result;
}
