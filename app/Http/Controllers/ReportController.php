<?php

namespace App\Http\Controllers;

use App\Http\Requests\TableDataRequest;
use App\Models\Product;
use App\Models\ProductVariant;
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
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
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
            ->with(['variant.product', 'variant.size', 'user'])
            ->when($request->filled('movement_type'), fn ($query) => $query->where('movement_type', $request->string('movement_type')))
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->whereHas('variant', fn ($variantQuery) => $variantQuery->where('product_id', $request->integer('product_id')));
            })
            ->when($request->filled('user_id'), fn ($query) => $query->where('created_by', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')))
            ->latest()
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

        $variants = $this->lowStockQuery($request)
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $variants->through(fn (ProductVariant $variant) => $this->formatLowStockVariant($variant))
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

        $variants = $this->outOfStockQuery($request)
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $variants->through(fn (ProductVariant $variant) => $this->formatOutOfStockVariant($variant))
        );
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function lowStockQuery(TableDataRequest $request): Builder
    {
        return ProductVariant::query()
            ->with(['product.category', 'size'])
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('product_categories', 'products.product_category_id', '=', 'product_categories.id')
            ->whereRaw('(product_variants.stock_quantity - product_variants.reserved_quantity) > 0')
            ->whereRaw('(product_variants.stock_quantity - product_variants.reserved_quantity) <= COALESCE(product_categories.low_stock_threshold, 0)')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.color', 'like', "%{$search}%");
                });
            })
            ->select('product_variants.*')
            ->orderBy('products.name');
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function outOfStockQuery(TableDataRequest $request): Builder
    {
        return ProductVariant::query()
            ->with(['product.category', 'size'])
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereRaw('(product_variants.stock_quantity - product_variants.reserved_quantity) <= 0')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.color', 'like', "%{$search}%");
                });
            })
            ->select('product_variants.*')
            ->orderBy('products.name');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMovement(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'created_at' => $movement->created_at->format('M d, Y H:i'),
            'product_name' => $movement->variant->product->name,
            'size_name' => $movement->variant->size->name,
            'movement_type' => $movement->movement_type->value,
            'quantity' => $movement->quantity,
            'before_stock' => $movement->before_stock,
            'after_stock' => $movement->after_stock,
            'before_reserved' => $movement->before_reserved,
            'after_reserved' => $movement->after_reserved,
            'user_name' => $movement->user->name,
            'remarks' => $movement->remarks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLowStockVariant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'product_name' => $variant->product->name,
            'color' => $variant->product->color,
            'size_name' => $variant->size->name,
            'available_stock' => $this->inventoryService->getAvailableStock($variant),
            'low_stock_threshold' => $variant->product->category->low_stock_threshold,
            'status' => 'LOW_STOCK',
            'status_label' => 'LOW STOCK',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOutOfStockVariant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'product_name' => $variant->product->name,
            'color' => $variant->product->color,
            'size_name' => $variant->size->name,
            'stock_quantity' => $variant->stock_quantity,
            'reserved_quantity' => $variant->reserved_quantity,
            'status' => 'OUT_OF_STOCK',
            'status_label' => 'OUT OF STOCK',
        ];
    }
}
