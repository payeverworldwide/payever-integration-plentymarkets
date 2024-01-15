<?php

namespace Payever\Models;

use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Plugin\DataBase\Contracts\Model;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * @property int $id
 * @property int $orderId
 * @property float $capturedTotal
 * @property float $cancelledTotal
 * @property float $refundedTotal
 * @property int $manual
 * @property string $timestamp
 * @property OrderRepositoryContract $orderRepository
 *
 * @NonTableAttribute(columns={"orderRepository"})
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OrderTotal extends Model
{
    use Loggable;

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
     * @var float
     */
    public $capturedTotal;

    /**
     * @var float
     */
    public $cancelledTotal;

    /**
     * @var float
     */
    public $refundedTotal;

    /**
     * @var int
     */
    public $manual;

    /**
     * @var int
     */
    public $timestamp = 0;

    /**
     * @var OrderRepositoryContract
     */
    private OrderRepositoryContract $orderRepository;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'Payever::OrderTotal';
    }

    /**
     * @param OrderRepositoryContract $orderRepository
     */
    public function __construct(
        OrderRepositoryContract $orderRepository,
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->orderRepository->findOrderById($this->getOrderId());
    }

    /**
     * @return float
     */
    public function getTotal(): float
    {
        return $this->getOrder()->amount->grossTotal;
    }

    /**
     * Get available amount for capturing.
     *
     * @return float
     */
    public function getAvailableForCapture(): float
    {
        return $this->getTotal() - $this->getCancelledTotal() - $this->getCapturedTotal();
    }

    /**
     * Get available amount for cancelling.
     *
     * @return float
     */
    public function getAvailableForCancel(): float
    {
        return $this->getAvailableForCapture();
    }

    /**
     * Get available amount for refunding.
     *
     * @return float
     */
    public function getAvailableForRefund(): float
    {
        return $this->getCapturedTotal() - $this->getRefundedTotal();
    }

    public function getCancelledTotal(): float
    {
        return $this->cancelledTotal;
    }

    public function setCancelledTotal(float $cancelledTotal): void
    {
        $this->cancelledTotal = $cancelledTotal;
    }

    public function getCapturedTotal(): float
    {
        return $this->capturedTotal;
    }

    public function setCapturedTotal(float $capturedTotal): void
    {
        $this->capturedTotal = $capturedTotal;
    }

    public function getRefundedTotal(): float
    {
        return $this->refundedTotal;
    }

    public function setRefundedTotal(float $refundedTotal): void
    {
        $this->refundedTotal = $refundedTotal;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setManual(int $manual): void
    {
        $this->manual = $manual;
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
