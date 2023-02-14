<?php

namespace Payever\tests\unit\mock\Plenty\Plugin\Log;

class Logger
{
    /**
     * @param mixed ...$args
     */
    public function debug(...$args)
    {
    }

    /**
     * @param mixed ...$args
     */
    public function critical(...$args)
    {
    }

    /**
     * @param mixed ...$args
     */
    public function error(...$args)
    {
    }

    /**
     * @param string $referenceType
     *
     * @return $this
     */
    public function setReferenceType($referenceType)
    {
        return $this;
    }

    /**
     * The reference value.
     */
    public function setReferenceValue($referenceValue)
    {
        return $this;
    }
}
