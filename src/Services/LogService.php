<?php

namespace Payever\Services;

use Payever\Contracts\LogRepositoryContract;

class LogService
{
    private $logRepositoryContract;

    public function __construct(LogRepositoryContract $logRepositoryContract)
    {
        $this->logRepositoryContract = $logRepositoryContract;
    }

    /**
     * @param $from
     * @param $to
     * @return array
     */
    public function getEntries($from, $to)
    {
        $entries = $this->logRepositoryContract->getByDate($from, $to);

        foreach ($entries as $key => $entry) {
            $entry = (array) $entry;

            $entry['created_at'] = date('Y-m-d\TH:i:sP', $entry['timestamp']);
            unset($entry['id'], $entry['timestamp']);

            $entries[$key] = $entry;
        }

        return $entries;
    }
}
