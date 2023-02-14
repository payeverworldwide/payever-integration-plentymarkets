<?php

namespace Payever\tests\unit\Assistants;

use ReflectionClass;
use Payever\Assistants\PayeverAssistant;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Order\Currency\Contracts\CurrencyRepositoryContract;
use Plenty\Plugin\ConfigRepository;

class PayeverAssistantTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var WebstoreRepositoryContract
     */
    private $webstoreRepository;

    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;

    /**
     * @var CurrencyRepositoryContract
     */
    private $currencyRepository;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @var PayeverAssistant
     */
    private $assistant;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->webstoreRepository = $this->getMockBuilder(WebstoreRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->countryRepository = $this->getMockBuilder(CountryRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->currencyRepository = $this->getMockBuilder(CurrencyRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configRepository = $this->getMockBuilder(ConfigRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->webstoreRepository->expects($this->any())
            ->method('loadAll' )
            ->willReturn([new \Payever\tests\unit\mock\Modules\System\Models\Webstore()]);

        $countries = $this->getMockBuilder(\Illuminate\Support\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $countries->expects($this->any())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([new \Payever\tests\unit\mock\Modules\Order\Shipping\Countries\Models\Country()]));

        $this->countryRepository->expects($this->any())
            ->method('getCountriesList')
            ->willReturn($countries);

        $this->countryRepository->expects($this->any())
            ->method('getCountryById')
            ->willReturn(new \Payever\tests\unit\mock\Modules\Order\Shipping\Countries\Models\Country());

        $currencies = $this->getMockBuilder(\Illuminate\Support\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $currencies->expects($this->any())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([new \Payever\tests\unit\mock\Modules\Order\Currency\Models\Currency()]));

        $this->currencyRepository->expects($this->any())
            ->method('getCurrencyList')
            ->willReturn($currencies);

        $this->assistant = new PayeverAssistant(
            $this->webstoreRepository,
            $this->countryRepository,
            $this->currencyRepository,
            $this->configRepository
        );
    }

    public function testStructure()
    {
        $reflection = new ReflectionClass(get_class($this->assistant));
        $method = $reflection->getMethod('structure');
        $method->setAccessible(true);

        $this->assertIsArray($method->invoke($this->assistant, 'structure'));
    }
}
