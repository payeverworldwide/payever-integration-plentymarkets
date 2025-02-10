<?php

namespace Payever\Contracts;

use Payever\Models\CustomerCompanyAddress;

interface CustomerCompanyAddressContract
{
    /**
     * @return Log
     */
    public function create(): CustomerCompanyAddress;

    /**
     * @param Log $log
     * @return Log
     */
    public function persist(CustomerCompanyAddress $log): CustomerCompanyAddress;

    /**
     * @param Log $log
     * @return bool
     */
    public function delete(CustomerCompanyAddress $log): bool;

    /**
     * @param string $hash
     * @return CustomerCompanyAddress[]|null
     */
    public function getByAddressHash(string $hash);
}
