<?php

namespace App\Http\Controllers;

use App\Http\Requests\TableDataRequest;
use App\Models\Product;
use App\Models\ProductColorSize;
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
