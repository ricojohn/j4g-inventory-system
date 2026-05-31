<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkStoreProductSizesRequest;
use App\Http\Requests\StoreProductSizeRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateProductSizeRequest;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Size;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSizeController extends Controller
{
    public function suggestions(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('edit products'), 403);

        $query = Size::query()->orderBy('name');

        if ($request->filled('exclude_product_id')) {
            $productId = $request->integer('exclude_product_id');
            $attachedIds = ProductSize::query()
                ->where('product_id', $productId)
                ->pluck('size_id');

            $query->whereNotIn('id', $attachedIds);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(['id', 'name']),
        ]);
    }

    public function data(TableDataRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $paginator = $product->sizes()
            ->with('size')
            ->withCount('cells')
            ->when($request->filled('search'), fn ($query) => $query->whereHas(
                'size',
                fn ($sizeQuery) => $sizeQuery->where('name', 'like', '%'.$request->string('search').'%')
            ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $paginator->through(fn (ProductSize $productSize) => [
                'id' => $productSize->id,
                'size_name' => $productSize->size->name,
                'sort_order' => $productSize->sort_order,
                'cell_count' => $productSize->cells_count,
            ])
        );
    }

    public function store(StoreProductSizeRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $sizeName = trim($request->validated('size_name'));
        $size = Size::query()->firstOrCreate(['name' => $sizeName]);

        $existing = $product->sizes()->where('size_id', $size->id)->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This size is already attached to the product.',
            ], 422);
        }

        $sortOrder = $request->validated('sort_order')
            ?? ((int) $product->sizes()->max('sort_order') + 1);

        $productSize = $product->sizes()->create([
            'size_id' => $size->id,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Size added successfully.',
            'data' => $productSize->load('size'),
        ]);
    }

    public function storeBulk(BulkStoreProductSizesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($request, $product, &$created, &$skipped): void {
            $existingSizeIds = $product->sizes()->pluck('size_id')->all();
            $sortOrder = (int) $product->sizes()->max('sort_order');

            foreach ($request->validated('size_names') as $sizeName) {
                $trimmed = trim($sizeName);

                if ($trimmed === '') {
                    continue;
                }

                $size = Size::query()->firstOrCreate(['name' => $trimmed]);

                if (in_array($size->id, $existingSizeIds, true)) {
                    $skipped[] = $trimmed;

                    continue;
                }

                $sortOrder++;
                $product->sizes()->create([
                    'size_id' => $size->id,
                    'sort_order' => $sortOrder,
                ]);
                $existingSizeIds[] = $size->id;
                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$created} size(s) added.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function update(UpdateProductSizeRequest $request, Product $product, ProductSize $size): JsonResponse
    {
        $this->authorize('update', $product);
        abort_unless($size->product_id === $product->id, 404);

        $data = $request->validated();

        if (isset($data['size_name'])) {
            $master = Size::query()->firstOrCreate(['name' => trim($data['size_name'])]);
            $data['size_id'] = $master->id;
            unset($data['size_name']);
        }

        $size->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Size updated successfully.',
            'data' => $size->fresh('size'),
        ]);
    }

    public function destroy(Product $product, ProductSize $size): JsonResponse
    {
        $this->authorize('update', $product);
        abort_unless($size->product_id === $product->id, 404);

        $hasStock = $size->cells()
            ->where(function ($query) {
                $query->where('current_stock', '>', 0)
                    ->orWhere('reserved_quantity', '>', 0);
            })
            ->exists();

        if ($hasStock) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a size that has stock on hand.',
            ], 422);
        }

        $size->delete();

        return response()->json([
            'success' => true,
            'message' => 'Size removed from product.',
        ]);
    }
}
