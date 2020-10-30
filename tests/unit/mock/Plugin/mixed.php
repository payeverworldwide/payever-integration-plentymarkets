<?php

namespace Plenty\Plugin;

class mixed
{
    /**
     * @var string
     */
    protected $data;

    /**
     * @param string|null $data
     */
    public function __construct(string $data = null)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->data;
    }
}
