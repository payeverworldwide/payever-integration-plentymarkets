<?php

namespace Payever\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * @property string $id
 * @property string $value
 */
class PayeverConfig extends Model
{
    /**
     * @var string
     */
    protected $primaryKeyFieldName = 'id';

    /**
     * @var string
     */
    protected $primaryKeyFieldType = self::FIELD_TYPE_STRING;

    /**
     * @var bool
     */
    protected $autoIncrementPrimaryKey = false;

    /**
     * @var string
     */
    public $id = '';

    /**
     * @var string
     */
    public $value = '';

    /**
     * @var array
     */
    protected $textFields = [
        'value'
    ];

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'Payever::PayeverConfig';
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
