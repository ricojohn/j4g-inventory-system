<?php

namespace App\Services\Ai\Assistance;

use App\Enums\CustomerOrderStatus;
use App\Enums\ProductionStage;
use App\Enums\SupplierOrderStatus;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Models\SupplierOrder;
use Carbon\Carbon;
use InvalidArgumentException;

class AiAssistanceToolRegistry
{
    public const MAX_ROWS = 50;

    public const MAX_ROUNDS = 5;

    /**
     * OpenAI-style tool schemas.
     *
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function schemas(): array
    {
        return [
            $this->schema('inventory_summary', 'Summarize inventory SKU counts: total, in stock, low stock, and out of stock.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->schema('low_stock_items', 'List products/colors/sizes that are at or below reorder level but still have available stock.', [
                'type' => 'object',
                'properties' => [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Optional product or color name filter.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows to return (default 25, max 50).',
                    ],
                ],
            ]),
            $this->schema('out_of_stock_items', 'List products/colors/sizes with zero or negative available stock.', [
                'type' => 'object',
                'properties' => [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Optional product or color name filter.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows to return (default 25, max 50).',
                    ],
                ],
            ]),
            $this->schema('order_stats', 'Count customer orders by status and production stage for a date range.', [
                'type' => 'object',
                'properties' => [
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Start date YYYY-MM-DD. Defaults to 30 days ago.',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'End date YYYY-MM-DD. Defaults to today.',
                    ],
                ],
            ]),
            $this->schema('recent_orders', 'List the most recent customer orders with customer name and totals.', [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows to return (default 15, max 50).',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional order status filter.',
                    ],
                ],
            ]),
            $this->schema('finance_summary', 'Summarize payments recorded and outstanding balances for a date range.', [
                'type' => 'object',
                'properties' => [
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Start date YYYY-MM-DD. Defaults to 30 days ago.',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'End date YYYY-MM-DD. Defaults to today.',
                    ],
                ],
            ]),
            $this->schema('supplier_order_stats', 'Count supplier purchase orders by status for a date range.', [
                'type' => 'object',
                'properties' => [
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Start date YYYY-MM-DD. Defaults to 30 days ago.',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'End date YYYY-MM-DD. Defaults to today.',
                    ],
                ],
            ]),
            $this->schema('production_pipeline', 'Count open customer orders by production stage.', [
                'type' => 'object',
                'properties' => (object) [],
            ]),
            $this->schema('search_products', 'Search products and return color/size stock cells matching the query.', [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Product name or code to search.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows to return (default 20, max 50).',
                    ],
                ],
                'required' => ['query'],
            ]),
            $this->schema('search_customers', 'Search customers by name, handle, or contact and include recent order counts.', [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Customer name, handle, or contact to search.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Max rows to return (default 20, max 50).',
                    ],
                ],
                'required' => ['query'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments = []): array
    {
        return match ($name) {
            'inventory_summary' => $this->inventorySummary(),
            'low_stock_items' => $this->lowStockItems($arguments),
            'out_of_stock_items' => $this->outOfStockItems($arguments),
            'order_stats' => $this->orderStats($arguments),
            'recent_orders' => $this->recentOrders($arguments),
            'finance_summary' => $this->financeSummary($arguments),
            'supplier_order_stats' => $this->supplierOrderStats($arguments),
            'production_pipeline' => $this->productionPipeline(),
            'search_products' => $this->searchProducts($arguments),
            'search_customers' => $this->searchCustomers($arguments),
            default => throw new InvalidArgumentException("Unknown AI assistance tool: {$name}."),
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    private function schema(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventorySummary(): array
    {
        $cells = ProductColorSize::query()
            ->whereHas('color.product', fn ($query) => $query->where('status', 'active'))
            ->get(['current_stock', 'reserved_quantity', 'reorder_level']);

        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($cells as $cell) {
            $available = $cell->available_stock;

            if ($available <= 0) {
                $outOfStock++;
            } elseif ($cell->reorder_level > 0 && $available <= $cell->reorder_level) {
                $lowStock++;
            } else {
                $inStock++;
            }
        }

        return [
            'total_skus' => $cells->count(),
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function lowStockItems(array $arguments): array
    {
        $limit = $this->limit($arguments, 25);
        $search = $this->optionalString($arguments, 'search');

        $rows = ProductColorSize::query()
            ->with(['color.product', 'color.color', 'size.size'])
            ->whereHas('color.product', fn ($query) => $query->where('status', 'active'))
            ->where('reorder_level', '>', 0)
            ->whereRaw('(current_stock - reserved_quantity) > 0')
            ->whereRaw('(current_stock - reserved_quantity) <= reorder_level')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('color.color', fn ($colorQuery) => $colorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('color.product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->limit($limit)
            ->get()
            ->map(fn (ProductColorSize $cell) => [
                'product' => $cell->color?->product?->name,
                'color' => $cell->color?->color?->name,
                'size' => $cell->size?->size?->name,
                'available' => $cell->available_stock,
                'reorder_level' => $cell->reorder_level,
            ])
            ->values()
            ->all();

        return [
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function outOfStockItems(array $arguments): array
    {
        $limit = $this->limit($arguments, 25);
        $search = $this->optionalString($arguments, 'search');

        $rows = ProductColorSize::query()
            ->with(['color.product', 'color.color', 'size.size'])
            ->whereHas('color.product', fn ($query) => $query->where('status', 'active'))
            ->whereRaw('(current_stock - reserved_quantity) <= 0')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('color.color', fn ($colorQuery) => $colorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('color.product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->limit($limit)
            ->get()
            ->map(fn (ProductColorSize $cell) => [
                'product' => $cell->color?->product?->name,
                'color' => $cell->color?->color?->name,
                'size' => $cell->size?->size?->name,
                'available' => $cell->available_stock,
                'current_stock' => $cell->current_stock,
                'reserved' => $cell->reserved_quantity,
            ])
            ->values()
            ->all();

        return [
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function orderStats(array $arguments): array
    {
        [$from, $to] = $this->dateRange($arguments);

        $orders = CustomerOrder::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get(['status', 'production_stage', 'order_total']);

        $byStatus = [];

        foreach (CustomerOrderStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }

        $byStage = [];

        foreach (ProductionStage::cases() as $stage) {
            $byStage[$stage->value] = 0;
        }

        foreach ($orders as $order) {
            $statusKey = $order->status?->value ?? 'unknown';
            $stageKey = $order->production_stage?->value ?? 'unknown';
            $byStatus[$statusKey] = ($byStatus[$statusKey] ?? 0) + 1;
            $byStage[$stageKey] = ($byStage[$stageKey] ?? 0) + 1;
        }

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'total_orders' => $orders->count(),
            'total_order_value' => round((float) $orders->sum('order_total'), 2),
            'by_status' => $byStatus,
            'by_production_stage' => $byStage,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function recentOrders(array $arguments): array
    {
        $limit = $this->limit($arguments, 15);
        $status = $this->optionalString($arguments, 'status');

        $rows = CustomerOrder::query()
            ->with('customer:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerOrder $order) => [
                'order_number' => $order->order_number,
                'customer' => $order->customer?->name ?? $order->customer_name,
                'status' => $order->status?->value,
                'production_stage' => $order->production_stage?->value,
                'order_total' => (float) $order->order_total,
                'amount_paid' => (float) $order->amount_paid,
                'balance_due' => $order->balanceDue(),
                'created_at' => $order->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function financeSummary(array $arguments): array
    {
        [$from, $to] = $this->dateRange($arguments);

        $payments = OrderPayment::query()
            ->whereNull('reversed_at')
            ->whereDate('posted_at', '>=', $from)
            ->whereDate('posted_at', '<=', $to)
            ->get(['amount', 'method']);

        $openOrders = CustomerOrder::query()
            ->whereNotIn('status', [CustomerOrderStatus::Cancelled->value, CustomerOrderStatus::Fulfilled->value])
            ->get(['order_total', 'amount_paid']);

        $outstanding = $openOrders->sum(fn (CustomerOrder $order) => max(0, (float) $order->order_total - (float) $order->amount_paid));

        $byMethod = $payments
            ->groupBy(fn (OrderPayment $payment) => $payment->method ?: 'unspecified')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->all();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'payments_count' => $payments->count(),
            'payments_total' => round((float) $payments->sum('amount'), 2),
            'payments_by_method' => $byMethod,
            'open_orders_count' => $openOrders->count(),
            'outstanding_balance' => round((float) $outstanding, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function supplierOrderStats(array $arguments): array
    {
        [$from, $to] = $this->dateRange($arguments);

        $orders = SupplierOrder::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get(['status']);

        $byStatus = [];

        foreach (SupplierOrderStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }

        foreach ($orders as $order) {
            $key = $order->status?->value ?? (is_string($order->status) ? $order->status : 'unknown');
            $byStatus[$key] = ($byStatus[$key] ?? 0) + 1;
        }

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'total_purchase_orders' => $orders->count(),
            'by_status' => $byStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productionPipeline(): array
    {
        $orders = CustomerOrder::query()
            ->whereNotIn('status', [CustomerOrderStatus::Cancelled->value, CustomerOrderStatus::Fulfilled->value])
            ->get(['production_stage', 'production_blocked']);

        $byStage = [];

        foreach (ProductionStage::cases() as $stage) {
            $byStage[$stage->value] = 0;
        }

        $blocked = 0;

        foreach ($orders as $order) {
            $key = $order->production_stage?->value ?? 'unknown';
            $byStage[$key] = ($byStage[$key] ?? 0) + 1;

            if ($order->production_blocked) {
                $blocked++;
            }
        }

        return [
            'open_orders' => $orders->count(),
            'blocked_orders' => $blocked,
            'by_stage' => $byStage,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function searchProducts(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException('search_products requires a query.');
        }

        $limit = $this->limit($arguments, 20);

        $productIds = Product::query()
            ->where('status', 'active')
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%");
            })
            ->limit(20)
            ->pluck('id');

        $rows = ProductColorSize::query()
            ->with(['color.product', 'color.color', 'size.size'])
            ->whereHas('color', fn ($colorQuery) => $colorQuery->whereIn('product_id', $productIds))
            ->limit($limit)
            ->get()
            ->map(fn (ProductColorSize $cell) => [
                'product' => $cell->color?->product?->name,
                'code' => $cell->color?->product?->code,
                'color' => $cell->color?->color?->name,
                'size' => $cell->size?->size?->name,
                'current_stock' => $cell->current_stock,
                'reserved' => $cell->reserved_quantity,
                'available' => $cell->available_stock,
                'reorder_level' => $cell->reorder_level,
            ])
            ->values()
            ->all();

        return [
            'query' => $query,
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function searchCustomers(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException('search_customers requires a query.');
        }

        $limit = $this->limit($arguments, 20);

        $rows = Customer::query()
            ->withCount('orders')
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('handle', 'like', "%{$query}%")
                    ->orWhere('contact', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'handle' => $customer->handle,
                'contact' => $customer->contact,
                'source' => $customer->source?->value ?? $customer->source,
                'orders_count' => $customer->orders_count,
            ])
            ->values()
            ->all();

        return [
            'query' => $query,
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(array $arguments): array
    {
        $to = filled($arguments['date_to'] ?? null)
            ? Carbon::parse((string) $arguments['date_to'])->startOfDay()
            : now()->startOfDay();

        $from = filled($arguments['date_from'] ?? null)
            ? Carbon::parse((string) $arguments['date_from'])->startOfDay()
            : $to->copy()->subDays(30);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function limit(array $arguments, int $default): int
    {
        $limit = (int) ($arguments['limit'] ?? $default);

        return max(1, min(self::MAX_ROWS, $limit > 0 ? $limit : $default));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function optionalString(array $arguments, string $key): ?string
    {
        $value = $arguments[$key] ?? null;

        if (! is_string($value) || blank($value)) {
            return null;
        }

        return trim($value);
    }
}
