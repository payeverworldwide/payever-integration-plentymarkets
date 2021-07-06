<?php

namespace Payever\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * @property int $id
 * @property string $orderID
 * @property string $payeverPaymentId
 * @property array $data
 * @property string $timestamp
 */
class PendingPayment extends Model
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
     * @var string
     */
    public $orderId;

    /**
     * @var string
     */
    public $payeverPaymentId;

    /**
     * @var array
     */
    public $data = [];

    /**
     * @var int
     */
    public $timestamp = 0;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'Payever::PendingPayment';
    }
}
