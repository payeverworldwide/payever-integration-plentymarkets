<?php

namespace Plenty\Plugin\Log;

use Payever\tests\unit\mock\Plenty\Plugin\Log\LoggerFactory;

/**
 * @param string $abstract
 * @param array $parameters
 * @return LoggerFactory|null
 */
function pluginApp(string $abstract, array $parameters = [])
{
    $result = null;
    if ($abstract === \Plenty\Plugin\Log\LoggerFactory::class) {
        $result = new LoggerFactory();
    }

    return $result;
}
