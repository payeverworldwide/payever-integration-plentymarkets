<?php

namespace Payever\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * @property int $id
 * @property string $addressHash
 * @property string $company
 */
class CustomerCompanyAddress extends Model
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
    public $addressHash;

    /**
     * @var string
     */
    public $company;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'Payever::CustomerCompanyAddress';
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAddressHash(): string
    {
        return $this->addressHash;
    }

    /**
     * @return string
     */
    public function getCompany(): string
    {
        return $this->company;
    }

    /**
     * @param $hash
     * @return $this
     */
    public function setAddressHash($hash): self
    {
        $this->addressHash = $hash;

        return $this;
    }

    /**
     * @param $company
     * @return $this
     */
    public function setCompany($company): self
    {
        $this->company = $company;

        return $this;
    }
}
