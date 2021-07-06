<?php

namespace Payever\Contracts;

use Payever\Models\PayeverConfig;

/**
 * Class PayeverConfigRepositoryContract
 * @package Payever\Contracts
 */
interface PayeverConfigRepositoryContract
{
    /**
     * @param string $id
     * @param string $value
     *
     * @return PayeverConfig
     */
    public function set(string $id, string $value);

    /**
     * @param string $id
     *
     * @return PayeverConfig
     */
    public function get(string $id);

    /**
     * @param string $id
     *
     * @return bool
     */
    public function delete(string $id);
}
