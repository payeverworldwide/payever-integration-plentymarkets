<?php

namespace Payever\Repositories;

use Payever\Contracts\OrderTotalItemRepositoryContract;
use Payever\Models\OrderTotalItem;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class OrderTotalItemRepository implements OrderTotalItemRepositoryContract
{
    /**
     * @var DataBase
     */
    private $dataBase;

    /**
     * @param DataBase $dataBase
     */
    public function __construct(DataBase $dataBase)
    {
        $this->dataBase = $dataBase;
    }

    /**
     * @inheritDoc
     */
    public function create($orderItem): OrderTotalItem
    {
        /** @var OrderTotalItem $orderTotalItem */
        $orderTotalItem = pluginApp(OrderTotalItem::class);

        $quantity = $orderItem->quantity;
        $unitPrice = $orderItem->amounts[0]->priceGross;
        $totalPrice = $quantity * $unitPrice;

        $orderTotalItem->orderId = $orderItem->orderId;
        $orderTotalItem->itemId = $orderItem->id;
        $orderTotalItem->name = $orderItem->orderItemName;
        $orderTotalItem->identifier = $orderItem->itemVariationId; // todo: check this attr
        $orderTotalItem->quantity = $quantity;
        $orderTotalItem->unitPrice = $unitPrice;
        $orderTotalItem->totalPrice = $totalPrice;
        $orderTotalItem->qtyCaptured = 0;
        $orderTotalItem->qtyCancelled = 0;
        $orderTotalItem->qtyRefunded = 0;
        $orderTotalItem->itemType = $orderItem->typeId;
        $orderTotalItem->timestamp = time();

        $this->dataBase->save($orderTotalItem);

        return $orderTotalItem;
    }

    /**
     * @inheritDoc
     */
    public function persist(OrderTotalItem $orderTotalItem): OrderTotalItem
    {
        /** @var OrderTotalItem $result */
        $result = $this->dataBase->save($orderTotalItem);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function delete(OrderTotalItem $orderTotalItem): bool
    {
        return $this->dataBase->delete($orderTotalItem);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(string $orderId): array
    {
        $query = $this->dataBase->query(OrderTotalItem::class);
        $query->where('orderId', '=', $orderId);
        return $query->get();
    }

    /**
     * @inheritDoc
     */
    public function findById(string $id): OrderTotalItem|null
    {
        $query = $this->dataBase->query(OrderTotalItem::class);
        $query->where('id', '=', $id);

        $result = $query->get();

        return $result[0] ?? null;
    }

    /**
     * @param $orderId
     * @param $productIdentifier
     * @return OrderTotalItem[]
     */
    public function loadByProductIdentifier($orderId, $productIdentifier): array
    {
        $result = [];

        $items = $this->getByOrderId($orderId);
        foreach ($items as $item) {
            if ($item->getIdentifier() === $productIdentifier) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param $orderId
     * @param $type
     * @return OrderTotalItem[]
     */
    public function getByItemType($orderId, $type): array
    {
        $query = $this->dataBase->query(OrderTotalItem::class);
        $query
            ->where('itemType', '=', $type)
            ->where('orderId', '=', $orderId);

        return $query->get();
    }
}
