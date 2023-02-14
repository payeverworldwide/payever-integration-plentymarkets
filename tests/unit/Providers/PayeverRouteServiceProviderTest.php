<?php

namespace Payever\tests\unit\Providers;

use Payever\Providers\PayeverRouteServiceProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Plugin\Routing\Router;

class PayeverRouteServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|Router
     */
    private $router;

    /**
     * @var PayeverRouteServiceProvider
     */
    private $provider;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->router = $this->getMockBuilder(Router::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->provider = new PayeverRouteServiceProvider();
    }

    public function testMap()
    {
        $this->router->expects($this->atLeastOnce())
            ->method('get');
        $this->router->expects($this->atLeastOnce())
            ->method('post');
        $this->provider->map($this->router);
    }
}
