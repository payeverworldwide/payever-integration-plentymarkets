<?php

namespace Payever\tests\unit\mock\Plenty\Plugin\Log;

class LoggerFactory
{
    function getLogger()
    {
        return new Logger();
    }
}
