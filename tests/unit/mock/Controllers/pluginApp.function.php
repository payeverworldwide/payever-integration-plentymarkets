<?php

namespace Payever\Controllers;

use Payever\tests\unit\mock\Component\HttpFoundation\Response;

/**
 * @param string $abstract
 * @param array $parameters
 * @return Response|null
 */
function pluginApp(string $abstract, array $parameters = [])
{
    $result = null;
    if ($abstract === \Plenty\Plugin\Http\Response::class) {
        $result = new Response();
    }

    return $result;
}
