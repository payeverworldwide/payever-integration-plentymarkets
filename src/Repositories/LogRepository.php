<?php

namespace Payever\Repositories;

use Payever\Contracts\LogRepositoryContract;
use Payever\Models\Log;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class LogRepository implements LogRepositoryContract
{
    /**
     * @var DataBase
     */
    private $dataBase;

    /**
     * @param DataBase $dataBase
     */
    public function __construct(DataBase $dataBase)
    {
        $this->dataBase = $dataBase;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function create(): Log
    {
        /** @var Log $log */
        $log = pluginApp(Log::class);
        $log->timestamp = time();

        return $log;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function persist(Log $log): Log
    {
        return $this->dataBase->save($log);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function delete(Log $log): bool
    {
        return $this->dataBase->delete($log);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(string $orderId)
    {
        $query = $this->dataBase->query(Log::class);
        $query->where('orderId', '=', $orderId);
        $rows = $query->get();
        if (!empty($rows[0])) {
            return $rows[0];
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getByDate(string $from, string $to): array
    {
        $query = $this->dataBase->query(Log::class);
        $query->whereBetween('timestamp', [strtotime($from), strtotime($to)]);

        return $query->get();
    }
}
