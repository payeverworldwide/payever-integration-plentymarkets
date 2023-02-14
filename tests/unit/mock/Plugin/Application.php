<?php

namespace Payever\tests\unit\mock\Plenty\Plugin;

class Application extends \Plenty\Plugin\Application
{
    public function register(string $providerClassName)
    {
        // TODO: Implement register() method.
    }

    public function bind(string $abstract, string $concrete = null, bool $shared = false)
    {
        // TODO: Implement bind() method.
    }

    public function singleton(string $abstract, string $concrete = null)
    {
        // TODO: Implement singleton() method.
    }

    public function make(string $abstract, array $parameters = [])
    {
        // TODO: Implement make() method.
    }

    public function makeWith(string $abstract, array $parameters = [])
    {
        // TODO: Implement makeWith() method.
    }

    public function abort(int $code, string $message = "", array $headers = [])
    {
        // TODO: Implement abort() method.
    }

    public function getWebstoreId(): int
    {
        return 1;
    }

    public function getPlentyId(): int
    {
        return 1;
    }

    public function isAdminPreview(): bool
    {
        return false;
    }

    public function isTemplateSafeMode(): bool
    {
        return false;
    }

    public function isBackendRequest(): bool
    {
        return false;
    }

    public function getPluginSetId(): int
    {
        return 0;
    }

    public function getUrlPath(string $pluginName = ""): string
    {
        return '';
    }

    public function getCdnDomain(): string
    {
        return '';
    }

    public function getPlentyHash(): string
    {
        return '';
    }
}
