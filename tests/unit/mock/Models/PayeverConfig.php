<?php

namespace Payever\tests\unit\mock\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class PayeverConfig extends Model
{
    /**
     * @var string
     */
    public $value = '';

    /**
     * @inheritDoc
     */
    public function getTableName(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function attributesToArray(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAttribute(string $key)
    {
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValue(string $key)
    {
    }

    /**
     * @inheritDoc
     */
    public function hasGetMutator(string $key): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function setAttribute(string $key, $value): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasSetMutator(string $key): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fillJsonAttribute(string $key, $value): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fromJson(string $value, bool $asObject = false)
    {
    }

    /**
     * @inheritDoc
     */
    public function fromDateTime($value): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDates(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function setDateFormat(string $format): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasCast(string $key, $types = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getCasts(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function setRawAttributes(array $attributes, bool $sync = false): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOriginal(string $key = null, $default = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function only($attributes): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function syncOriginal(): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function syncOriginalAttribute(string $attribute): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function syncChanges(): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isDirty($attributes = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isClean($attributes = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function wasChanged($attributes = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getDirty(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getChanges(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function cacheMutatedAttributes(string $class)
    {
    }

    /**
     * @inheritDoc
     */
    public function relationLoaded()
    {
    }

    /**
     * @inheritDoc
     */
    public function getMutatedAttributes(): array
    {
    }

    public function getValue()
    {
        return $this->value;
    }
}
