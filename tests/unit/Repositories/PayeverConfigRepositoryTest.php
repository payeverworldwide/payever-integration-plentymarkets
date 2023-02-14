<?php

namespace Payever\tests\unit\Repositories;

use Payever\Contracts\PayeverConfigRepositoryContract;
use Payever\Repositories\PayeverConfigRepository;
use Payever\tests\unit\mock\Models\PayeverConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class PayeverConfigRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|DataBase
     */
    private $database;

    /**
     * @var PayeverConfigRepositoryContract
     */
    private $repository;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->database = $this->getMockBuilder(DataBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->repository = new PayeverConfigRepository($this->database);
    }

    public function testGet()
    {
        $this->database->expects($this->once())
            ->method('find')
            ->willReturn(
                $payeverConfig = $this->getMockBuilder(PayeverConfig::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertFalse($this->repository->get('some-key'));
    }

    public function testSet()
    {
        $this->assertNotEmpty($this->repository->set('some-key', 'some-value'));
    }

    public function testDelete()
    {
        $this->database->expects($this->once())
            ->method('delete')
            ->willReturn(true);
        $this->repository->delete('1');
    }
}
