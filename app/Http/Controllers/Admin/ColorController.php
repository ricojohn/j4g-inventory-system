<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreColorRequest;
use App\Http\Requests\Admin\UpdateColorRequest;
use App\Http\Requests\TableDataRequest;
use App\Models\Color;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ColorController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Color::class);

        return view('admin.colors.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Color::class);

        $paginator = Color::query()
            ->withCount('productColors as products_count')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $paginator->through(fn (Color $color) => [
                'id' => $color->id,
                'name' => $color->name,
                'products_count' => $color->products_count,
            ])
        );
    }

    public function store(StoreColorRequest $request): JsonResponse
    {
        $this->authorize('create', Color::class);

        $color = Color::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Color created successfully.',
            'data' => $color,
        ]);
    }

    public function update(UpdateColorRequest $request, Color $color): JsonResponse
    {
        $this->authorize('update', $color);

        $color->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Color updated successfully.',
            'data' => $color->fresh(),
        ]);
    }

    public function destroy(Color $color): JsonResponse
    {
        $this->authorize('delete', $color);

        if ($color->productColors()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a color that is attached to one or more products.',
            ], 422);
        }

        $color->delete();

        return response()->json([
            'success' => true,
            'message' => 'Color deleted successfully.',
        ]);
    }
}
