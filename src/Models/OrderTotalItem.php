<?php

namespace Payever\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * @property int $id
 * @property int $orderId
 * @property string $itemType
 * @property int $itemId
 * @property string $name
 * @property string $identifier
 * @property string $quantity
 * @property float $unitPrice
 * @property float $totalPrice
 * @property int $qtyCaptured
 * @property int $qtyCancelled
 * @property int $qtyRefunded
 * @property string $timestamp
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OrderTotalItem extends Model
{
    /**
     * @var string
     */
    protected $primaryKeyFieldName = 'id';

    /**
     * @var string
     */
    protected $primaryKeyFieldType = self::FIELD_TYPE_INT;

    /**
     * @var bool
     */
    protected $autoIncrementPrimaryKey = true;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $orderId;

    /**
     * @var string
     */
    public $itemType;

    /**
     * @var int
     */
    public $itemId;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $identifier;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @var float
     */
    public $unitPrice;

    /**
     * @var float
     */
    public $totalPrice;

    /**
     * @var int
     */
    public $qtyCaptured;

    /**
     * @var int
     */
    public $qtyCancelled;

    /**
     * @var int
     */
    public $qtyRefunded;

    /**
     * @var int
     */
    public $timestamp = 0;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'Payever::OrderTotalItem';
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getQtyRefunded(): int
    {
        return $this->qtyRefunded;
    }

    public function setQtyRefunded(int $qtyRefunded): void
    {
        $this->qtyRefunded = $qtyRefunded;
    }

    public function setQtyCaptured(int $qtyCaptured): void
    {
        $this->qtyCaptured = $qtyCaptured;
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
    public function getAttribute($key)
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValue($key)
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function hasGetMutator($key): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function setAttribute($key, $value): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasSetMutator($key): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function fillJsonAttribute($key, $value): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fromJson($value, $asObject = false)
    {
        return;
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
    public function setDateFormat($format): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasCast($key, $types = null): bool
    {
        return true;
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
    public function setRawAttributes($attributes, $sync = false): Model
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOriginal($key = null, $default = null)
    {
        return;
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
    public function syncOriginalAttribute($attribute): Model
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
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isClean($attributes = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function wasChanged($attributes = null): bool
    {
        return true;
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
    public function getMutatedAttributes(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function cacheMutatedAttributes($class)
    {
        return;
    }

    public function relationLoaded()
    {
        return;
    }
}
