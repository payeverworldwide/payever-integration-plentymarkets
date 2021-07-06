<?php

namespace Payever\Services\Lock;

use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Plenty\Plugin\Log\Loggable;

class StorageLock
{
    use Loggable;

    const SLEEP_SECONDS = 1;
    const DEFAULT_TIMEOUT = 60;

    /**
     * @var StorageRepositoryContract
     */
    private $storageRepository;

    /**
     * @param StorageRepositoryContract $storageRepository
     */
    public function __construct(StorageRepositoryContract $storageRepository)
    {
        $this->storageRepository = $storageRepository;
    }

    /**
     * @param string $name
     * @param int $timeout
     */
    public function acquireLock(string $name, int $timeout = self::DEFAULT_TIMEOUT)
    {
        $lockName = $this->getLockName($name);
        $this->waitForUnlock($lockName, $timeout);
        $this->releaseLock($name);
        $this->lock($lockName);
    }

    /**
     * @param string $name
     */
    public function releaseLock(string $name)
    {
        $lockName = $this->getLockName($name);
        $this->unlock($lockName);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getLockName(string $name)
    {
        return "$name.lock";
    }

    /**
     * @param string $lockName
     */
    public function lock(string $lockName)
    {
        $this->storageRepository->uploadObject('Payever', $lockName, '');
        $this->getLogger(__METHOD__)->debug('Payever::debug.lockAndBlock', $lockName);
    }

    /**
     * @param string $lockName
     */
    public function unlock(string $lockName)
    {
        $this->storageRepository->deleteObject('Payever', $lockName);
        $this->getLogger(__METHOD__)->debug('Payever::debug.unlock', $lockName);
    }

    /**
     * @param string $lockName
     * @param int $timeout
     */
    public function waitForUnlock(string $lockName, int $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.waitForUnlock', "start $lockName");
        $waitingTime = 0;
        while ($this->isLocked($lockName) && $waitingTime <= $timeout) {
            $waitingTime += self::SLEEP_SECONDS;
            sleep(self::SLEEP_SECONDS);
        }
        $this->getLogger(__METHOD__)->debug('Payever::debug.waitForUnlock', "finish $lockName");
    }

    /**
     * @param string $lockName
     * @return bool
     */
    public function isLocked(string $lockName): bool
    {
        return $this->storageRepository->doesObjectExist('Payever', $lockName);
    }
}
