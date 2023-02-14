<?php

namespace Payever\tests\unit\mock\Repositories\Models;

class PaginatedResult extends \Plenty\Repositories\Models\PaginatedResult
{
    public function paginate(): \Plenty\Repositories\Models\PaginatedResult
    {
        return new PaginatedResult();
    }

    public function getPage(): int
    {
        return 1;
    }

    public function getCurrentPage(): int
    {
        return 1;
    }

    public function getTotalCount(): int
    {
        return 1;
    }

    public function isLastPage(): bool
    {
        return true;
    }

    public function getItemIndexFrom(): int
    {
        return 0;
    }

    public function getItemIndexTo(): int
    {
        return 0;
    }

    public function getLastPage(): int
    {
        return 1;
    }

    public function getResult(): array
    {
        $plugin = new \Payever\tests\unit\mock\Modules\Plugin\Models\Plugin();

        return [
            $plugin
        ];
    }

    public function setResult($result)
    {
        return null;
    }

    public function sumMetaData(string $metaColumn = "", string $groupBy = "", string $metaDataName = "")
    {
        return null;
    }

    public function rowCountMetaData(string $metaColumn = "", string $groupBy = "", string $metaDataName = "")
    {
        return null;
    }

    public function toArray(): array
    {
        return [];
    }

    public function toJson(int $options = 0): string
    {
        return '';
    }

    public function jsonSerialize()
    {
        return '';
    }
}
