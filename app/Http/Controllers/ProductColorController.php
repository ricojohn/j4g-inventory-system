<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkStoreProductColorsRequest;
use App\Http\Requests\StoreProductColorRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateProductColorRequest;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductColor;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductColorController extends Controller
{
    public function suggestions(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('edit products'), 403);

        $query = Color::query()->orderBy('name');

        if ($request->filled('exclude_product_id')) {
            $productId = $request->integer('exclude_product_id');
            $attachedIds = ProductColor::query()
                ->where('product_id', $productId)
                ->pluck('color_id');

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

        $paginator = $product->colors()
            ->with('color')
            ->withCount('cells')
            ->when($request->filled('search'), fn ($query) => $query->whereHas(
                'color',
                fn ($colorQuery) => $colorQuery->where('name', 'like', '%'.$request->string('search').'%')
            ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $paginator->through(fn (ProductColor $productColor) => [
                'id' => $productColor->id,
                'item_code' => $productColor->item_code,
                'color_name' => $productColor->color->name,
                'color_code' => $productColor->color_code,
                'sort_order' => $productColor->sort_order,
                'cell_count' => $productColor->cells_count,
            ])
        );
    }

    public function store(StoreProductColorRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $colorName = trim($data['color_name']);
        $color = Color::query()->firstOrCreate(['name' => $colorName]);

        $existing = $product->colors()->where('color_id', $color->id)->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This color is already attached to the product.',
            ], 422);
        }

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (int) $product->colors()->max('sort_order') + 1;
        }

        $productColor = $product->colors()->create([
            'color_id' => $color->id,
            'color_code' => $data['color_code'] ?? null,
            'sort_order' => $data['sort_order'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Color added successfully.',
            'data' => $productColor->load('color'),
        ]);
    }

    public function storeBulk(BulkStoreProductColorsRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($request, $product, &$created, &$skipped): void {
            $existingColorIds = $product->colors()->pluck('color_id')->all();
            $sortOrder = (int) $product->colors()->max('sort_order');

            foreach ($request->validated('colors') as $entry) {
                $colorName = trim($entry['color_name']);

                if ($colorName === '') {
                    continue;
                }

                $color = Color::query()->firstOrCreate(['name' => $colorName]);

                if (in_array($color->id, $existingColorIds, true)) {
                    $skipped[] = $colorName;

                    continue;
                }

                $sortOrder++;
                $product->colors()->create([
                    'color_id' => $color->id,
                    'color_code' => $entry['color_code'] ?? null,
                    'sort_order' => $sortOrder,
                ]);
                $existingColorIds[] = $color->id;
                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$created} color(s) added.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function update(UpdateProductColorRequest $request, Product $product, ProductColor $color): JsonResponse
    {
        $this->authorize('update', $product);
        abort_unless($color->product_id === $product->id, 404);

        $data = $request->validated();

        if (isset($data['color_name'])) {
            $master = Color::query()->firstOrCreate(['name' => trim($data['color_name'])]);
            $data['color_id'] = $master->id;
            unset($data['color_name']);
        }

        $color->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Color updated successfully.',
            'data' => $color->fresh('color'),
        ]);
    }

    public function destroy(Product $product, ProductColor $color): JsonResponse
    {
        $this->authorize('update', $product);
        abort_unless($color->product_id === $product->id, 404);

        $hasStock = $color->cells()
            ->where(function ($query) {
                $query->where('current_stock', '>', 0)
                    ->orWhere('reserved_quantity', '>', 0);
            })
            ->exists();

        if ($hasStock) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a color that has stock on hand.',
            ], 422);
        }

        $color->delete();

        return response()->json([
            'success' => true,
            'message' => 'Color removed from product.',
        ]);
    }
}
