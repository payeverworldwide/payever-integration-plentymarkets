<?php

namespace Payever\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Class PayeverConfig
 *
 * @property string $id
 * @property string $value
 */
class PayeverConfig extends Model
{
    protected $primaryKeyFieldName = "id";
    protected $primaryKeyFieldType = self::FIELD_TYPE_STRING;
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

    public function getValue()
    {
        return $this->value;
    }
}