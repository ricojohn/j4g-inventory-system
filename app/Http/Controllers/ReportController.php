<?php

namespace App\Http\Controllers;

use App\Http\Requests\TableDataRequest;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function stockHistory(): View
    {
        abort_unless(auth()->user()?->can('view stock history'), 403);

        return view('reports.stock-history');
    }

    public function stockHistoryFilterOptions(): JsonResponse
    {
        abort_unless(auth()->user()?->can('view stock history'), 403);

        return response()->json([
            'success' => true,
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function stockHistoryData(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view stock history'), 403);

        $request->validate([
            'movement_type' => ['sometimes', 'nullable', 'string', 'in:IN,OUT,RESERVE,RELEASE,DAMAGED,ADJUSTMENT'],
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $movements = StockMovement::query()
            ->with(['cell.color.product', 'cell.color.color', 'cell.size.size', 'user'])
            ->whereHas('cell.color.product', fn ($query) => $query->where('status', 'active'))
            ->when($request->filled('movement_type'), fn ($query) => $query->where('type', $request->string('movement_type')))
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->whereHas('cell.color', fn ($colorQuery) => $colorQuery->where('product_id', $request->integer('product_id')));
            })
            ->when($request->filled('user_id'), fn ($query) => $query->where('created_by', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')))
            ->latest('created_at')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $movements->through(fn (StockMovement $movement) => $this->formatMovement($movement))
        );
    }

    public function lowStock(): View
    {
        abort_unless(auth()->user()?->can('view low stock report'), 403);

        return view('reports.low-stock');
    }

    public function lowStockData(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view low stock report'), 403);

        $cells = $this->lowStockQuery($request)
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $cells->through(fn (ProductColorSize $cell) => $this->formatLowStockCell($cell))
        );
    }

    public function outOfStock(): View
    {
        abort_unless(auth()->user()?->can('view out of stock report'), 403);

        return view('reports.out-of-stock');
    }

    public function outOfStockData(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('view out of stock report'), 403);

        $cells = $this->outOfStockQuery($request)
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $cells->through(fn (ProductColorSize $cell) => $this->formatOutOfStockCell($cell))
        );
    }

    /**
     * @return Builder<ProductColorSize>
     */
    private function lowStockQuery(TableDataRequest $request): Builder
    {
        return ProductColorSize::query()
            ->with(['color.product', 'color.color', 'size.size'])
            ->whereHas('color.product', fn ($query) => $query->where('status', 'active'))
            ->where('reorder_level', '>', 0)
            ->whereRaw('(current_stock - reserved_quantity) > 0')
            ->whereRaw('(current_stock - reserved_quantity) <= reorder_level')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('color.color', fn ($colorQuery) => $colorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('color.product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->join('product_color', 'product_color.id', '=', 'product_color_sizes.product_color_id')
            ->join('products', 'products.id', '=', 'product_color.product_id')
            ->orderBy('products.name')
            ->select('product_color_sizes.*');
    }

    /**
     * @return Builder<ProductColorSize>
     */
    private function outOfStockQuery(TableDataRequest $request): Builder
    {
        return ProductColorSize::query()
            ->with(['color.product', 'color.color', 'size.size'])
            ->whereHas('color.product', fn ($query) => $query->where('status', 'active'))
            ->whereRaw('(current_stock - reserved_quantity) <= 0')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('color.color', fn ($colorQuery) => $colorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('color.product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMovement(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'created_at' => $movement->created_at->format('M d, Y H:i'),
            'product_name' => $movement->cell->color->product->name,
            'color_name' => $movement->cell->color->color->name,
            'size_name' => $movement->cell->size->size->name,
            'movement_type' => $movement->type->value,
            'quantity' => $movement->quantity,
            'before_stock' => $movement->before_stock,
            'after_stock' => $movement->after_stock,
            'before_reserved' => $movement->before_reserved,
            'after_reserved' => $movement->after_reserved,
            'user_name' => $movement->user?->name ?? 'System',
            'remarks' => $movement->remarks,
            'item_code' => $movement->cell->color->item_code,
            'image_url' => $movement->cell->color->imageUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLowStockCell(ProductColorSize $cell): array
    {
        $status = $this->inventoryService->getStockStatus($cell);

        return [
            'id' => $cell->id,
            'product_name' => $cell->color->product->name,
            'color_name' => $cell->color->color->name,
            'color' => $cell->color->color->name,
            'size_name' => $cell->size->size->name,
            'current_stock' => $cell->current_stock,
            'available_stock' => $this->inventoryService->getAvailableStock($cell),
            'reorder_level' => $cell->reorder_level,
            'status' => $status->value,
            'status_label' => $status->label(),
            'item_code' => $cell->color->item_code,
            'image_url' => $cell->color->imageUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOutOfStockCell(ProductColorSize $cell): array
    {
        $status = $this->inventoryService->getStockStatus($cell);

        return [
            'id' => $cell->id,
            'product_name' => $cell->color->product->name,
            'color_name' => $cell->color->color->name,
            'color' => $cell->color->color->name,
            'size_name' => $cell->size->size->name,
            'current_stock' => $cell->current_stock,
            'reserved_quantity' => $cell->reserved_quantity,
            'status' => $status->value,
            'status_label' => $status->label(),
            'item_code' => $cell->color->item_code,
            'image_url' => $cell->color->imageUrl(),
        ];
    }
}
