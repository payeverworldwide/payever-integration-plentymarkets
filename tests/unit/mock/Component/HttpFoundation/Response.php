<?php

namespace Payever\tests\unit\mock\Component\HttpFoundation;

class Response extends \Symfony\Component\HttpFoundation\Response
{
    /**
     * @param mixed ...$args
     * @return array
     */
    public function json(...$args): array
    {
        return $args;
    }
}
