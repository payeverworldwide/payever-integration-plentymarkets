<?php

namespace Payever\Tests;

class PlentyConnector
{
    /** @var string */
    private $directory;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $backendPath;

    /** @var string */
    private $backendUsername;

    /** @var string */
    private $backendPassword;

    /**
     * @param string $prestaDir
     */
    public function __construct($directory)
    {
        if (!is_readable($directory)) {
            throw new \UnexpectedValueException("$directory directory is not readable");
        }

        $this->directory = $directory;
    }

    /**
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        if (empty($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = str_replace(['https://', 'http://', '/'], '', $this->baseUrl);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setPath($path)
    {
        $this->backendPath = $path;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath()
    {
        return $this->backendPath;
    }

    /**
     * {@inheritDoc}
     */
    public function setUsername($username)
    {
        $this->backendUsername = $username;
    }

    /**
     * {@inheritDoc}
     */
    public function getUsername()
    {
        return $this->backendUsername;
    }

    /**
     * {@inheritDoc}
     */
    public function setPassword($password)
    {
        $this->backendPassword = $password;
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword()
    {
        return $this->backendPassword;
    }
}
