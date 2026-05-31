<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\SyncCategorySizesRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\ProductCodeService;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function __construct(
        private ProductCodeService $productCodeService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', ProductCategory::class);

        return view('categories.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::query()
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
            $categories->through(fn (ProductCategory $category) => $this->formatCategory($category))
        );
    }

    public function showJson(ProductCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        return response()->json([
            'success' => true,
            'data' => $this->formatCategory($category),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = ProductCategory::query()->create($request->validated());
        $category->sizes()->sync([]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $this->formatCategory($category),
        ]);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        DB::transaction(function () use ($request, $category) {
            $category->update($request->validated());

            if (! $category->wasChanged('code')) {
                return;
            }

            $category->products()
                ->select(['id', 'item_code'])
                ->orderBy('id')
                ->each(function (Product $product) use ($category) {
                    $product->update([
                        'item_code' => $this->productCodeService->rebuildForCategory($product->item_code, $category),
                    ]);
                });
        });

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $this->formatCategory($category->fresh()),
        ]);
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a category that has products.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }

    public function sizes(ProductCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $assigned = $category->sizes()
            ->orderBy('sort_order')
            ->get(['sizes.id', 'sizes.name', 'sizes.sort_order']);

        $assignedIds = $assigned->pluck('id');

        $available = Size::query()
            ->whereNotIn('id', $assignedIds)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        return response()->json([
            'category' => $category->only(['id', 'name', 'code']),
            'assigned' => $assigned,
            'available' => $available,
        ]);
    }

    public function syncSizes(SyncCategorySizesRequest $request, ProductCategory $category): JsonResponse
    {
        $newSizeIds = $request->validated('size_ids');
        $currentSizeIds = $category->sizes()->pluck('sizes.id')->all();
        $removedSizeIds = array_diff($currentSizeIds, $newSizeIds);

        if ($removedSizeIds !== []) {
            $inUse = ProductVariant::query()
                ->whereIn('size_id', $removedSizeIds)
                ->whereHas('product', fn ($query) => $query->where('product_category_id', $category->id))
                ->exists();

            if ($inUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove a size that is used by products in this category.',
                ], 422);
            }
        }

        $category->sizes()->sync($newSizeIds);

        return response()->json([
            'success' => true,
            'message' => 'Category sizes updated successfully.',
            'assigned_size_ids' => $category->sizes()->pluck('sizes.id'),
        ]);
    }

    public function variantOptions(ProductCategory $category): JsonResponse
    {
        $this->authorize('view', $category);

        $sizes = $category->sizes()
            ->orderBy('sort_order')
            ->get(['sizes.id', 'sizes.name', 'sizes.sort_order']);

        return response()->json([
            'sizes' => $sizes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCategory(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'code' => $category->code,
            'low_stock_threshold' => $category->low_stock_threshold,
            'status' => $category->status,
        ];
    }
}
