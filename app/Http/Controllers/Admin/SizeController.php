<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSizeRequest;
use App\Http\Requests\TableDataRequest;
use App\Http\Requests\UpdateSizeRequest;
use App\Models\Size;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SizeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Size::class);

        return view('admin.sizes.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Size::class);

        $sizes = Size::query()
            ->withCount(['categories', 'variants'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $sizes->through(fn (Size $size) => $this->formatSize($size))
        );
    }

    public function showJson(Size $size): JsonResponse
    {
        $this->authorize('view', $size);

        return response()->json([
            'success' => true,
            'data' => $this->formatSize($size->loadCount(['categories', 'variants'])),
        ]);
    }

    public function store(StoreSizeRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            $data['sort_order'] = (int) Size::query()->max('sort_order') + 1;
        }

        $size = Size::query()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Size created successfully.',
            'data' => $this->formatSize($size->loadCount(['categories', 'variants'])),
        ]);
    }

    public function update(UpdateSizeRequest $request, Size $size): JsonResponse
    {
        $size->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Size updated successfully.',
            'data' => $this->formatSize($size->fresh()->loadCount(['categories', 'variants'])),
        ]);
    }

    public function destroy(Size $size): JsonResponse
    {
        $this->authorize('delete', $size);

        if ($size->variants()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a size that is used by product variants.',
            ], 422);
        }

        DB::transaction(function () use ($size): void {
            $size->categories()->detach();
            $size->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Size deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSize(Size $size): array
    {
        return [
            'id' => $size->id,
            'name' => $size->name,
            'sort_order' => $size->sort_order,
            'categories' => $size->categories()->get(['product_categories.name']),
            'variant_count' => $size->variants_count ?? $size->variants()->count(),
        ];
    }
}
