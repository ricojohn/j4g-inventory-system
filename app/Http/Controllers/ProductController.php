<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\ProductCodeService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('products.index', [
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $request->validate([
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
        ]);

        $products = Product::query()
            ->with(['category'])
            ->withCount('variants')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('product_category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $products->through(fn (Product $product) => $this->formatProduct($product))
        );
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', [
            'categories' => ProductCategory::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function previewItemCode(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
        ]);

        $category = ProductCategory::query()->findOrFail($data['category_id']);

        return response()->json([
            'item_code' => $this->productCodeService->preview($category),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $sizeIds = $data['size_ids'];
        unset($data['size_ids']);

        $category = ProductCategory::query()->findOrFail($data['product_category_id']);

        $product = DB::transaction(function () use ($data, $sizeIds, $category) {
            $data['item_code'] = $this->productCodeService->generate($category);

            try {
                $product = Product::query()->create($data);
            } catch (QueryException $exception) {
                if ($this->isDuplicateItemCodeException($exception)) {
                    $data['item_code'] = $this->productCodeService->generate($category->fresh());
                    $product = Product::query()->create($data);
                } else {
                    throw $exception;
                }
            }

            $this->syncVariants($product, $sizeIds);

            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'redirect' => route('products.index'),
            'data' => ['item_code' => $product->item_code],
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['variants.size']);

        return view('products.edit', [
            'product' => $product,
            'categories' => ProductCategory::query()->where('status', 'active')->orderBy('name')->get(),
            'selectedSizeIds' => $product->variants->pluck('size_id')->all(),
        ]);
    }

    public function manageInventory(Product $product): View
    {
        $this->authorize('view', $product);
        abort_unless(auth()->user()?->can('view inventory'), 403);

        $product->load('category');

        return view('products.inventory', [
            'product' => $product,
        ]);
    }

    public function inventoryData(TableDataRequest $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);
        abort_unless(auth()->user()?->can('view inventory'), 403);

        $paginator = $product->variants()
            ->with('size')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('size', fn ($inner) => $inner->where('name', 'like', "%{$search}%"));
            })
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->orderBy('sizes.sort_order')
            ->select('product_variants.*')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $paginator->through(fn (ProductVariant $variant) => $this->inventoryService->formatVariantForDisplay($variant))
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $sizeIds = $data['size_ids'];
        unset($data['size_ids']);

        $product->update($data);
        $this->syncVariants($product, $sizeIds);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'redirect' => route('products.index'),
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
     * @param  list<int>  $sizeIds
     */
    private function syncVariants(Product $product, array $sizeIds): void
    {
        $existingSizeIds = $product->variants()->pluck('size_id')->all();

        foreach ($sizeIds as $sizeId) {
            if (! in_array($sizeId, $existingSizeIds, true)) {
                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'size_id' => $sizeId,
                    'stock_quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }
        }

        $product->variants()
            ->whereNotIn('size_id', $sizeIds)
            ->where('stock_quantity', 0)
            ->where('reserved_quantity', 0)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'item_code' => $product->item_code,
            'name' => $product->name,
            'color' => $product->color,
            'category_name' => $product->category->name,
            'status' => $product->status,
            'variant_count' => $product->variants_count,
            'edit_url' => route('products.edit', $product),
            'inventory_url' => auth()->user()?->can('view inventory')
                ? route('products.inventory', $product)
                : null,
        ];
    }

    private function isDuplicateItemCodeException(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'products_item_code_unique')
            || str_contains($exception->getMessage(), 'Duplicate entry');
    }
}
