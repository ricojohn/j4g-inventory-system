<?php

namespace App\Http\Controllers;

use App\Enums\CustomerOrderStatus;
use App\Enums\MovementType;
use App\Enums\SupplierOrderStatus;
use App\Http\Requests\TableDataRequest;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Models\StockMovement;
use App\Models\SupplierOrder;
use App\Services\InventoryService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const AVAILABLE_STOCK_SQL = '(product_color_sizes.current_stock - product_color_sizes.reserved_quantity)';

    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $stats = $this->computeStats();

        return view('dashboard.index', [
            'totalProducts' => $stats['total_products'],
            'totalStock' => $stats['total_stock'],
            'totalReserved' => $stats['total_reserved'],
            'totalAvailable' => $stats['total_available'],
            'lowStockCount' => $stats['low_stock_count'],
            'outOfStockCount' => $stats['out_of_stock_count'],
            'openOrders' => $stats['open_orders'],
            'openPos' => $stats['open_pos'],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        return response()->json([
            'success' => true,
            'data' => $this->computeStats(),
        ]);
    }

    public function stockHealth(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $available = self::AVAILABLE_STOCK_SQL;

        $counts = ProductColorSize::query()
            ->join('product_color', 'product_color.id', '=', 'product_color_sizes.product_color_id')
            ->join('products', 'products.id', '=', 'product_color.product_id')
            ->where('products.status', 'active')
            ->selectRaw("SUM(CASE WHEN {$available} <= 0 THEN 1 ELSE 0 END) as out_of_stock")
            ->selectRaw("SUM(CASE WHEN {$available} > 0 AND product_color_sizes.reorder_level > 0 AND {$available} <= product_color_sizes.reorder_level THEN 1 ELSE 0 END) as low_stock")
            ->selectRaw("SUM(CASE WHEN {$available} > 0 AND NOT (product_color_sizes.reorder_level > 0 AND {$available} <= product_color_sizes.reorder_level) THEN 1 ELSE 0 END) as ok_stock")
            ->first();

        return response()->json([
            'success' => true,
            'labels' => ['OK', 'Low Stock', 'Out of Stock'],
            'series' => [
                (int) ($counts->ok_stock ?? 0),
                (int) ($counts->low_stock ?? 0),
                (int) ($counts->out_of_stock ?? 0),
            ],
        ]);
    }

    public function stockMovementTrend(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $days = (int) ($validated['days'] ?? 14);
        $startDate = now()->subDays($days - 1)->startOfDay();

        $rows = StockMovement::query()
            ->selectRaw('DATE(created_at) as movement_date')
            ->selectRaw('type')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->where('created_at', '>=', $startDate)
            ->whereIn('type', [
                MovementType::In,
                MovementType::Out,
                MovementType::Damaged,
            ])
            ->whereHas('cell.color.product', fn ($query) => $query->where('status', 'active'))
            ->groupBy('movement_date', 'type')
            ->orderBy('movement_date')
            ->get();

        $dateRange = collect();
        for ($offset = 0; $offset < $days; $offset++) {
            $dateRange->push($startDate->copy()->addDays($offset)->toDateString());
        }

        $grouped = $rows->groupBy(fn ($row) => Carbon::parse($row->movement_date)->toDateString());

        $stockIn = [];
        $stockOut = [];
        $damaged = [];

        foreach ($dateRange as $date) {
            $dayRows = $grouped->get($date, collect());

            $stockIn[] = (int) ($this->movementQuantityForType($dayRows, MovementType::In));
            $stockOut[] = (int) ($this->movementQuantityForType($dayRows, MovementType::Out));
            $damaged[] = (int) ($this->movementQuantityForType($dayRows, MovementType::Damaged));
        }

        return response()->json([
            'success' => true,
            'categories' => $dateRange->map(fn (string $date) => Carbon::parse($date)->format('M d'))->values()->all(),
            'series' => [
                ['name' => 'Stock In', 'data' => $stockIn],
                ['name' => 'Stock Out', 'data' => $stockOut],
                ['name' => 'Damaged', 'data' => $damaged],
            ],
        ]);
    }

    public function lowStockByProduct(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $available = self::AVAILABLE_STOCK_SQL;

        $rows = ProductColorSize::query()
            ->join('product_color', 'product_color.id', '=', 'product_color_sizes.product_color_id')
            ->join('products', 'products.id', '=', 'product_color.product_id')
            ->where('products.status', 'active')
            ->select('products.name as product_name')
            ->selectRaw("SUM(CASE WHEN {$available} > 0 AND product_color_sizes.reorder_level > 0 AND {$available} <= product_color_sizes.reorder_level THEN 1 ELSE 0 END) as low_stock")
            ->selectRaw("SUM(CASE WHEN {$available} <= 0 THEN 1 ELSE 0 END) as out_of_stock")
            ->groupBy('products.id', 'products.name')
            ->havingRaw("SUM(CASE WHEN {$available} > 0 AND product_color_sizes.reorder_level > 0 AND {$available} <= product_color_sizes.reorder_level THEN 1 ELSE 0 END) + SUM(CASE WHEN {$available} <= 0 THEN 1 ELSE 0 END) > 0")
            ->orderByRaw("SUM(CASE WHEN {$available} > 0 AND product_color_sizes.reorder_level > 0 AND {$available} <= product_color_sizes.reorder_level THEN 1 ELSE 0 END) + SUM(CASE WHEN {$available} <= 0 THEN 1 ELSE 0 END) DESC")
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $rows->pluck('product_name')->values()->all(),
            'series' => [
                ['name' => 'Low Stock', 'data' => $rows->pluck('low_stock')->map(fn ($value) => (int) $value)->values()->all()],
                ['name' => 'Out of Stock', 'data' => $rows->pluck('out_of_stock')->map(fn ($value) => (int) $value)->values()->all()],
            ],
        ]);
    }

    public function activeProducts(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $startDate = now()->subDays($days - 1)->startOfDay();

        $rows = StockMovement::query()
            ->join('product_color_sizes', 'product_color_sizes.id', '=', 'stock_movements.product_color_size_id')
            ->join('product_color', 'product_color.id', '=', 'product_color_sizes.product_color_id')
            ->join('products', 'products.id', '=', 'product_color.product_id')
            ->where('products.status', 'active')
            ->where('stock_movements.created_at', '>=', $startDate)
            ->select('products.name as product_name')
            ->selectRaw('COUNT(*) as movement_count')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('movement_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $rows->pluck('product_name')->values()->all(),
            'series' => [
                ['name' => 'Movements', 'data' => $rows->pluck('movement_count')->map(fn ($value) => (int) $value)->values()->all()],
            ],
        ]);
    }

    public function recentMovementsData(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $movements = StockMovement::query()
            ->with(['cell.color.product', 'cell.color.color', 'cell.size.size', 'user'])
            ->whereHas('cell.color.product', fn ($query) => $query->where('status', 'active'))
            ->latest('created_at')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $movements->through(fn (StockMovement $movement) => $this->formatRecentMovement($movement))
        );
    }

    /**
     * @return array<string, int>
     */
    private function computeStats(): array
    {
        $cells = ProductColorSize::query()
            ->whereHas('color.product', fn ($query) => $query->where('status', 'active'))
            ->get();

        $lowStockCount = 0;
        $outOfStockCount = 0;
        $totalStock = 0;
        $totalReserved = 0;

        foreach ($cells as $cell) {
            $totalStock += $cell->current_stock;
            $totalReserved += $cell->reserved_quantity;

            $status = $this->inventoryService->getStockStatus($cell);

            if ($status->value === 'OUT_OF_STOCK') {
                $outOfStockCount++;
            } elseif ($status->value === 'LOW_STOCK') {
                $lowStockCount++;
            }
        }

        return [
            'total_products' => Product::query()->where('status', 'active')->count(),
            'total_stock' => $totalStock,
            'total_reserved' => $totalReserved,
            'total_available' => $totalStock - $totalReserved,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'open_orders' => CustomerOrder::query()
                ->whereNotIn('status', [CustomerOrderStatus::Fulfilled, CustomerOrderStatus::Cancelled])
                ->count(),
            'open_pos' => SupplierOrder::query()
                ->whereNotIn('status', [SupplierOrderStatus::Received, SupplierOrderStatus::Cancelled])
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $dayRows
     */
    private function movementQuantityForType($dayRows, MovementType $type): int
    {
        $row = $dayRows->first(function ($movementRow) use ($type) {
            $movementType = $movementRow->type instanceof MovementType
                ? $movementRow->type->value
                : (string) $movementRow->type;

            return $movementType === $type->value;
        });

        return (int) ($row->total_quantity ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecentMovement(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'created_at' => $movement->created_at->format('M d, Y H:i'),
            'product_name' => $movement->cell->color->product->name,
            'color_name' => $movement->cell->color->color->name,
            'color' => $movement->cell->color->color->name,
            'size_name' => $movement->cell->size->size->name,
            'movement_type' => $movement->type->value,
            'quantity' => $movement->quantity,
            'user_name' => $movement->user?->name ?? 'System',
        ];
    }
}
