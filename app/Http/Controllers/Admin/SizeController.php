<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSizeRequest;
use App\Http\Requests\Admin\UpdateSizeRequest;
use App\Http\Requests\TableDataRequest;
use App\Models\Size;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
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

        $paginator = Size::query()
            ->withCount('productSizes as products_count')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $paginator->through(fn (Size $size) => [
                'id' => $size->id,
                'name' => $size->name,
                'products_count' => $size->products_count,
            ])
        );
    }

    public function store(StoreSizeRequest $request): JsonResponse
    {
        $this->authorize('create', Size::class);

        $size = Size::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Size created successfully.',
            'data' => $size,
        ]);
    }

    public function update(UpdateSizeRequest $request, Size $size): JsonResponse
    {
        $this->authorize('update', $size);

        $size->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Size updated successfully.',
            'data' => $size->fresh(),
        ]);
    }

    public function destroy(Size $size): JsonResponse
    {
        $this->authorize('delete', $size);

        if ($size->productSizes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a size that is attached to one or more products.',
            ], 422);
        }

        $size->delete();

        return response()->json([
            'success' => true,
            'message' => 'Size deleted successfully.',
        ]);
    }
}
