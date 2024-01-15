<?php

namespace Payever\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * @property int $id
 * @property string $level
 * @property int $orderId
 * @property string $message
 * @property array $data
 * @property int $timestamp
 */
class Log extends Model
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
    public $level;

    /**
     * @var int
     */
    public $orderId = 0;

    /**
     * @var string
     */
    public $message;

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
        return 'Payever::Log';
    }
}
