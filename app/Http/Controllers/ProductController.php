<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\ProductCodeService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private ProductCodeService $productCodeService,
        private InventoryService $inventoryService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        return view('products.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:active,inactive,all'],
        ]);

        $statusFilter = $request->string('status')->toString() ?: 'active';

        $products = Product::query()
            ->withCount(['sizes', 'colors'])
            ->when($statusFilter !== 'all', fn ($query) => $query->where('status', $statusFilter))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $products->through(fn (Product $product) => $this->formatProduct($product))
        );
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create');
    }

    public function previewCode(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $suggested = $this->productCodeService->suggestPrefixFromName($data['name']);

        return response()->json([
            'code' => $suggested,
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'redirect' => route('products.edit', $product),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.edit', [
            'product' => $product,
        ]);
    }

    public function manageInventory(Product $product): View
    {
        $this->authorize('view', $product);
        abort_unless(auth()->user()?->can('view inventory'), 403);

        return view('products.inventory', [
            'product' => $product,
            'readOnly' => $product->status === 'inactive',
        ]);
    }

    public function inventoryData(Product $product): JsonResponse
    {
        $this->authorize('view', $product);
        abort_unless(auth()->user()?->can('view inventory'), 403);

        $product->load([
            'sizes' => fn ($query) => $query->with('size')->orderBy('sort_order')->orderBy('id'),
            'colors.color',
            'colors.cells' => fn ($query) => $query->with(['size.size']),
        ]);

        $sizes = $product->sizes->map(fn ($productSize) => [
            'id' => $productSize->id,
            'size_name' => $productSize->size->name,
            'sort_order' => $productSize->sort_order,
        ])->values();

        $colors = $product->colors->map(function ($productColor) {
            $cells = [];

            foreach ($productColor->cells as $cell) {
                $cells[$cell->product_size_id] = $this->inventoryService->formatCellForDisplay($cell);
            }

            return [
                'id' => $productColor->id,
                'color_name' => $productColor->color->name,
                'color_code' => $productColor->color_code,
                'item_code' => $productColor->item_code,
                'sort_order' => $productColor->sort_order,
                'cells' => $cells,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'status' => $product->status,
            ],
            'sizes' => $sizes,
            'colors' => $colors,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'redirect' => route('products.edit', $product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProduct(Product $product): array
    {
        $cells = $product->cells()->get(['current_stock', 'reserved_quantity', 'reorder_level']);
        $lowStockCount = 0;

        foreach ($cells as $cell) {
            if ($this->inventoryService->getStockStatus($cell)->value === 'LOW_STOCK') {
                $lowStockCount++;
            }
        }

        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'status' => $product->status,
            'size_count' => $product->sizes_count,
            'color_count' => $product->colors_count,
            'total_stock' => (int) $cells->sum('current_stock'),
            'total_reserved' => (int) $cells->sum('reserved_quantity'),
            'low_stock_count' => $lowStockCount,
            'edit_url' => route('products.edit', $product),
            'inventory_url' => auth()->user()?->can('view inventory')
                ? route('products.inventory', $product)
                : null,
        ];
    }
}
