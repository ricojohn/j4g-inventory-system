<?php

namespace App\Services;

use App\Enums\CustomerOrderStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Support\ProductCellLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerOrderService
{
    public function __construct(
        private InventoryService $inventoryService,
        private ProductCellLookup $productCellLookup,
    ) {}

    public function reserveOrder(CustomerOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->load('items.cell.color.product');
            $this->productCellLookup->ensureActiveProducts($order->items->map(fn (CustomerOrderItem $item) => $item->cell));

            foreach ($order->items as $item) {
                if (in_array($item->status, ['fulfilled', 'cancelled'], true)) {
                    continue;
                }

                $cell = $item->cell->fresh();
                $available = $this->inventoryService->getAvailableStock($cell);
                $needed = $item->quantity_ordered - $item->quantity_reserved;
                $toReserve = min($available, $needed);

                if ($toReserve > 0) {
                    $this->inventoryService->reserve(
                        $cell,
                        $toReserve,
                        "Reserved for {$order->order_number}"
                    );
                    $item->quantity_reserved += $toReserve;
                }

                $item->status = $this->resolveItemReserveStatus($item);
                $item->save();
            }

            $this->syncOrderStatus($order);
        });
    }

    /**
     * @return Collection<int, CustomerOrderItem>
     */
    public function getShortageItems(CustomerOrder $order): Collection
    {
        return $order->items()
            ->get()
            ->filter(fn (CustomerOrderItem $item) => $item->quantity_reserved < $item->quantity_ordered)
            ->each(function (CustomerOrderItem $item): void {
                $item->shortage_qty = $item->quantity_ordered - $item->quantity_reserved;
            })
            ->values();
    }

    public function fulfillOrder(CustomerOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->load('items.cell.color.product');
            $this->productCellLookup->ensureActiveProducts($order->items->map(fn (CustomerOrderItem $item) => $item->cell));

            foreach ($order->items as $item) {
                if ($item->status === 'fulfilled') {
                    continue;
                }

                if ($item->quantity_reserved > 0) {
                    $cell = $item->cell->fresh();
                    $this->inventoryService->release(
                        $cell,
                        $item->quantity_reserved,
                        "Fulfilling {$order->order_number}"
                    );
                    $this->inventoryService->stockOut(
                        $cell->fresh(),
                        $item->quantity_reserved,
                        "Fulfilled {$order->order_number}"
                    );
                }

                $item->update([
                    'status' => 'fulfilled',
                    'quantity_reserved' => 0,
                ]);
            }

            $order->update(['status' => CustomerOrderStatus::Fulfilled]);
        });
    }

    public function cancelOrder(CustomerOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->load('items.cell.color.product');
            $this->productCellLookup->ensureActiveProducts($order->items->map(fn (CustomerOrderItem $item) => $item->cell));

            foreach ($order->items as $item) {
                if (in_array($item->status, ['reserved', 'partially_reserved'], true) && $item->quantity_reserved > 0) {
                    $this->inventoryService->release(
                        $item->cell->fresh(),
                        $item->quantity_reserved,
                        "Cancelled {$order->order_number}"
                    );
                }

                $item->update([
                    'status' => 'cancelled',
                    'quantity_reserved' => 0,
                ]);
            }

            $order->update(['status' => CustomerOrderStatus::Cancelled]);
        });
    }

    public function syncOrderStatus(CustomerOrder $order): void
    {
        /** @var Collection<int, CustomerOrderItem> $items */
        $items = $order->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        if ($items->every(fn (CustomerOrderItem $item) => $item->status === 'fulfilled')) {
            $order->status = CustomerOrderStatus::Fulfilled;
        } elseif ($items->every(fn (CustomerOrderItem $item) => $item->status === 'cancelled')) {
            $order->status = CustomerOrderStatus::Cancelled;
        } elseif ($items->every(fn (CustomerOrderItem $item) => $item->status === 'reserved')) {
            $order->status = CustomerOrderStatus::Reserved;
        } else {
            $order->status = CustomerOrderStatus::PartiallyReserved;
        }

        $order->save();
    }

    private function resolveItemReserveStatus(CustomerOrderItem $item): string
    {
        if ($item->quantity_reserved >= $item->quantity_ordered) {
            return 'reserved';
        }

        if ($item->quantity_reserved > 0) {
            return 'partially_reserved';
        }

        return 'pending';
    }
}
