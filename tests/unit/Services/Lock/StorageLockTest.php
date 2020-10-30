<?php

namespace Payever\tests\unit\Services\Lock;

use Payever\Services\Lock\StorageLock;
use PHPUnit\Framework\MockObject\MockObject;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;

class StorageLockTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|StorageRepositoryContract
     */
    private $storageRepository;

    /**
     * @var StorageLock
     */
    private $lock;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->storageRepository = $this->getMockBuilder(StorageRepositoryContract::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->lock = new StorageLock($this->storageRepository);
    }

    public function testAcquireLock()
    {
        $this->storageRepository->expects($this->once())
            ->method('doesObjectExist')
            ->willReturn(false);
        $this->lock->acquireLock('some-payment-id');
    }

    public function testReleaseLock()
    {
        $this->storageRepository->expects($this->once())
            ->method('deleteObject');
        $this->lock->releaseLock('some-payment-id');
    }

    public function testGetLockName()
    {
        $this->assertNotEmpty($this->lock->getLockName('some-payment-id'));
    }

    public function testLock()
    {
        $this->storageRepository->expects($this->once())
            ->method('uploadObject');
        $this->lock->lock('some-payment-id.lock');
    }

    public function testUnlock()
    {
        $this->storageRepository->expects($this->once())
            ->method('deleteObject');
        $this->lock->unlock('some-payment-id.lock');
    }

    public function testWaitForUnlock()
    {
        $this->storageRepository->expects($this->at(0))
            ->method('doesObjectExist')
            ->willReturn(true);
        $this->storageRepository->expects($this->at(1))
            ->method('doesObjectExist')
            ->willReturn(false);
        $this->lock->waitForUnlock('some-payment-id.lock');
    }

    public function testIsLocked()
    {
        $this->storageRepository->expects($this->once())
            ->method('doesObjectExist')
            ->willReturn(true);
        $this->assertTrue($this->lock->isLocked('some-payment-id.lock'));
    }
}
