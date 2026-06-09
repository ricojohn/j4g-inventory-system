<?php

namespace App\Services;

use App\Enums\CustomerOrderStatus;
use App\Enums\SupplierOrderStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Support\ProductCellLookup;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierOrderService
{
    public function __construct(
        private InventoryService $inventoryService,
        private CustomerOrderService $customerOrderService,
        private ProductCellLookup $productCellLookup,
    ) {}

    /**
     * @param  array<int, int>  $receivedQtys
     * @return array{reserved_orders: int}
     */
    public function receiveItems(SupplierOrder $po, array $receivedQtys): array
    {
        return DB::transaction(function () use ($po, $receivedQtys) {
            $po->load('items.cell.color.product');

            foreach ($po->items as $item) {
                if (! array_key_exists($item->id, $receivedQtys)) {
                    continue;
                }

                $qty = (int) $receivedQtys[$item->id];

                if ($qty <= 0) {
                    continue;
                }

                $remaining = $item->quantity_ordered - $item->quantity_received;

                if ($qty > $remaining) {
                    throw new InvalidArgumentException("Receive quantity exceeds remaining for item {$item->id}.");
                }

                $this->productCellLookup->ensureActiveProducts([$item->cell]);

                $this->inventoryService->stockIn(
                    $item->cell->fresh(),
                    $qty,
                    "Received on {$po->po_number}"
                );

                $item->quantity_received += $qty;
                $item->save();
            }

            $this->syncPoStatus($po);
            $reservedCount = $this->reserveWaitingOrders($po);

            return [
                'reserved_orders' => $reservedCount,
            ];
        });
    }

    public function syncPoStatus(SupplierOrder $po): void
    {
        $po->load('items');
        $items = $po->items;

        if ($items->isEmpty()) {
            return;
        }

        if ($items->every(fn (SupplierOrderItem $item) => $item->quantity_received >= $item->quantity_ordered)) {
            $po->status = SupplierOrderStatus::Received;
        } elseif ($items->contains(fn (SupplierOrderItem $item) => $item->quantity_received > 0)) {
            $po->status = SupplierOrderStatus::PartiallyReceived;
        }

        $po->save();
    }

    public function reserveWaitingOrders(SupplierOrder $po): int
    {
        $cellIds = $po->items()->pluck('product_color_size_id')->unique()->values()->all();

        if ($cellIds === []) {
            return 0;
        }

        $orderIds = CustomerOrderItem::query()
            ->whereIn('product_color_size_id', $cellIds)
            ->whereIn('status', ['pending', 'partially_reserved'])
            ->orderBy('created_at')
            ->pluck('customer_order_id')
            ->unique()
            ->values();

        $count = 0;

        foreach ($orderIds as $orderId) {
            $order = CustomerOrder::query()->find($orderId);

            if ($order && ! in_array($order->status, [CustomerOrderStatus::Fulfilled, CustomerOrderStatus::Cancelled], true)) {
                $this->customerOrderService->reserveOrder($order);
                $count++;
            }
        }

        return $count;
    }
}
