<?php
namespace Payever\Repositories;

use Payever\Contracts\PayeverConfigRepositoryContract;
use Payever\Models\PayeverConfig;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
/**
 * Class PayeverConfigRepository
 *
 * @package Payever\Repositories
 */
class PayeverConfigRepository implements PayeverConfigRepositoryContract
{
    /**
     * @var DataBase
     */
    private $database;
    /**
     * PayeverConfigRepository constructor.
     *
     * @param DataBase $dataBase
     */
    public function __construct(DataBase $dataBase)
    {
        $this->database = $dataBase;
    }

    /**
     * Gets payever config value by key.
     *
     * @param string $id
     * @return bool|string
     */
    public function get(string $id)
    {
        $payeverConfig = $this->database->find(PayeverConfig::class, $id);

        if ($payeverConfig instanceof PayeverConfig) {
            return $payeverConfig->getValue();
        }

        return false;
    }

    /**
     * Updates payever config value
     *
     * @param string $id
     * @param string $value
     *
     * @return PayeverConfig
     */
    public function set(string $id, string $value)
    {
        /** @var PayeverConfig $payeverConfig */
        $payeverConfig = $this->get($id);
        if($payeverConfig instanceof PayeverConfig) {
            $payeverConfig->id = $id;
            $payeverConfig->value = $value;
        } else {
            /** @var PayeverConfig $payeverConfig */
            $payeverConfig = pluginApp(PayeverConfig::class);
            $payeverConfig->id = $id;
            $payeverConfig->value = $value;
        }

        $payeverConfig = $this->database->save($payeverConfig);
        return $payeverConfig;
    }
    /**
     * Deletes payever config value.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id)
    {
        /** @var PayeverConfig $payeverConfig */
        $payeverConfig = pluginApp(PayeverConfig::class);
        $payeverConfig->id = $id;
        return $this->database->delete($payeverConfig);
    }
}
