<?php

namespace App\Support;

use App\Enums\CustomerOrderStatus;
use App\Enums\SupplierOrderStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use Illuminate\Support\Collection;

class OrderOpsPresenter
{
    /**
     * @return array{label: string, tag: string, priority: string, href: string|null}
     */
    public function nextAction(CustomerOrder $order): array
    {
        if ($order->status === CustomerOrderStatus::Cancelled) {
            return [
                'label' => 'No action',
                'tag' => 'Cancelled',
                'priority' => 'low',
                'href' => null,
            ];
        }

        if ($order->status === CustomerOrderStatus::Fulfilled) {
            return [
                'label' => 'Order complete',
                'tag' => 'Done',
                'priority' => 'low',
                'href' => null,
            ];
        }

        $shortageQty = $this->shortageQuantity($order);
        $hasDraftPo = $order->supplierOrder?->status === SupplierOrderStatus::Draft;

        if ($shortageQty > 0 && ! $order->supplier_order_id) {
            return [
                'label' => "Resolve material shortage ({$shortageQty} pcs)",
                'tag' => 'Shortage',
                'priority' => 'high',
                'href' => route('orders.show', $order).'?tab=items',
            ];
        }

        if ($hasDraftPo) {
            return [
                'label' => 'Confirm draft purchase order',
                'tag' => 'PO draft',
                'priority' => 'high',
                'href' => $order->supplierOrder
                    ? route('supplier-orders.show', $order->supplierOrder)
                    : null,
            ];
        }

        if ($shortageQty > 0 && $order->supplierOrder) {
            return [
                'label' => 'Receive stock against PO',
                'tag' => 'PO partial',
                'priority' => 'medium',
                'href' => route('supplier-orders.show', $order->supplierOrder),
            ];
        }

        if ($order->status->allowsFulfill()) {
            return [
                'label' => 'Fulfill order',
                'tag' => 'Ready',
                'priority' => 'medium',
                'href' => route('orders.show', $order),
            ];
        }

        return [
            'label' => 'Review order',
            'tag' => 'Follow-up',
            'priority' => 'low',
            'href' => route('orders.show', $order),
        ];
    }

    /**
     * @return list<array{key: string, label: string, ready: bool, detail: string|null}>
     */
    public function readinessChecklist(CustomerOrder $order): array
    {
        $ordered = (int) ($order->items_sum_quantity_ordered ?? $order->items->sum('quantity_ordered'));
        $reserved = (int) ($order->items_sum_quantity_reserved ?? $order->items->sum('quantity_reserved'));
        $hasCustomer = filled($order->customer_name);
        $stockReady = $ordered > 0 && $reserved >= $ordered;

        return [
            [
                'key' => 'customer',
                'label' => 'Customer and lineup complete',
                'ready' => $hasCustomer && $ordered > 0,
                'detail' => null,
            ],
            [
                'key' => 'stock',
                'label' => 'Stock reserved',
                'ready' => $stockReady,
                'detail' => "{$reserved}/{$ordered}",
            ],
            [
                'key' => 'po',
                'label' => 'Procurement clear',
                'ready' => $this->shortageQuantity($order) === 0,
                'detail' => $order->supplierOrder?->po_number,
            ],
        ];
    }

    /**
     * @return array{title: string, subtitle: string, tag: string, href: string}|null
     */
    public function attentionItem(CustomerOrder $order): ?array
    {
        $action = $this->nextAction($order);
        $shortageQty = $this->shortageQuantity($order);
        $hasDraftPo = $order->supplierOrder?->status === SupplierOrderStatus::Draft;

        if ($shortageQty === 0 && ! $hasDraftPo) {
            return null;
        }

        return [
            'title' => $action['label'],
            'subtitle' => $order->customer_name.' · '.$order->order_number,
            'tag' => $action['tag'],
            'href' => $action['href'] ?? route('orders.show', $order),
            'priority' => $action['priority'],
        ];
    }

    public function shortageQuantity(CustomerOrder $order): int
    {
        /** @var Collection<int, CustomerOrderItem> $items */
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        return (int) $items->sum(
            fn (CustomerOrderItem $item) => max(0, $item->quantity_ordered - $item->quantity_reserved)
        );
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, tag: string, href: string, priority: string}>
     */
    public function attentionFeed(int $limit = 10): Collection
    {
        return CustomerOrder::query()
            ->with(['supplierOrder', 'items'])
            ->whereNotIn('status', [
                CustomerOrderStatus::Fulfilled->value,
                CustomerOrderStatus::Cancelled->value,
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CustomerOrder $order) => $this->attentionItem($order))
            ->filter()
            ->take($limit)
            ->values();
    }
}
