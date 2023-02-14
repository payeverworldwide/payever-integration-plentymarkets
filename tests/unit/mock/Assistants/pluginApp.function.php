<?php

namespace Payever\Assistants;

use Payever\tests\unit\mock\Plenty\Plugin\Application;

/**
 * @param string $abstract
 * @param array $parameters
 * @return Application|null
 */
function pluginApp(string $abstract, array $parameters = [])
{
    $result = null;
    if ($abstract === \Plenty\Plugin\Application::class) {
        $result = new Application();
    }

    return $result;
}
