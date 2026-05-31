<?php

namespace App\Http\Controllers;

use App\Http\Requests\TableDataRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $stats = $this->computeStats();

        return view('dashboard.index', [
            'totalCategories' => $stats['total_categories'],
            'totalProducts' => $stats['total_products'],
            'totalStock' => $stats['total_stock'],
            'totalReserved' => $stats['total_reserved'],
            'totalAvailable' => $stats['total_available'],
            'lowStockCount' => $stats['low_stock_count'],
            'outOfStockCount' => $stats['out_of_stock_count'],
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

    public function recentMovementsData(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view dashboard'), 403);

        $movements = StockMovement::query()
            ->with(['variant.product', 'variant.size', 'user'])
            ->latest()
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
        $variants = ProductVariant::query()->with('product.category')->get();

        $lowStockCount = 0;
        $outOfStockCount = 0;
        $totalStock = 0;
        $totalReserved = 0;

        foreach ($variants as $variant) {
            $totalStock += $variant->stock_quantity;
            $totalReserved += $variant->reserved_quantity;

            $status = $this->inventoryService->getStockStatus($variant);

            if ($status->value === 'OUT_OF_STOCK') {
                $outOfStockCount++;
            } elseif ($status->value === 'LOW_STOCK') {
                $lowStockCount++;
            }
        }

        return [
            'total_categories' => ProductCategory::query()->count(),
            'total_products' => Product::query()->count(),
            'total_stock' => $totalStock,
            'total_reserved' => $totalReserved,
            'total_available' => $totalStock - $totalReserved,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecentMovement(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'created_at' => $movement->created_at->format('M d, Y H:i'),
            'product_name' => $movement->variant->product->name,
            'color' => $movement->variant->product->color,
            'size_name' => $movement->variant->size->name,
            'movement_type' => $movement->movement_type->value,
            'quantity' => $movement->quantity,
            'user_name' => $movement->user->name,
        ];
    }
}
