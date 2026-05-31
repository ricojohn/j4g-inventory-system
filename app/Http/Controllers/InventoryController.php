<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\BulkStockMovementRequest;
use App\Http\Requests\Inventory\StockMovementRequest;
use App\Models\ProductColorSize;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function stockIn(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('stock in'), 403);

        return $this->handleMovement($request, fn ($cell, $quantity, $remarks) => $this->inventoryService->stockIn($cell, $quantity, $remarks));
    }

    public function stockOut(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('stock out'), 403);

        return $this->handleMovement($request, fn ($cell, $quantity, $remarks) => $this->inventoryService->stockOut($cell, $quantity, $remarks));
    }

    public function reserve(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('reserve stock'), 403);

        return $this->handleMovement($request, fn ($cell, $quantity, $remarks) => $this->inventoryService->reserve($cell, $quantity, $remarks));
    }

    public function release(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('release stock'), 403);

        return $this->handleMovement($request, fn ($cell, $quantity, $remarks) => $this->inventoryService->release($cell, $quantity, $remarks));
    }

    public function damage(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('damage stock'), 403);

        return $this->handleMovement($request, fn ($cell, $quantity, $remarks) => $this->inventoryService->damage($cell, $quantity, $remarks));
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('adjust stock'), 403);

        try {
            $cell = $this->resolveCell($request->integer('cell_id'));
            $updatedCell = $this->inventoryService->adjust(
                $cell,
                $request->integer('new_quantity'),
                $request->string('remarks'),
                $request->filled('reorder_level') ? $request->integer('reorder_level') : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully.',
                'data' => $this->inventoryService->formatCellResponse($updatedCell),
            ]);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to adjust stock.',
            ], 500);
        }
    }

    public function bulk(BulkStockMovementRequest $request): JsonResponse
    {
        $action = $request->string('action')->toString();
        $permissionMap = [
            'stock-in' => 'stock in',
            'stock-out' => 'stock out',
            'reserve' => 'reserve stock',
            'release' => 'release stock',
            'damage' => 'damage stock',
            'adjust' => 'adjust stock',
        ];

        abort_unless($request->user()?->can($permissionMap[$action]), 403);

        $productId = $request->integer('product_id');
        $remarks = $request->input('remarks');
        $items = $request->input('items', []);
        $results = [];
        $successCount = 0;

        foreach ($items as $item) {
            $cellId = (int) $item['cell_id'];

            try {
                $cell = ProductColorSize::query()
                    ->whereKey($cellId)
                    ->whereHas('color', fn ($query) => $query->where('product_id', $productId))
                    ->firstOrFail();

                $this->ensureProductIsActive($cell);

                $updatedCell = $this->applyBulkAction($action, $cell, $item, $remarks);

                $results[] = [
                    'cell_id' => $cellId,
                    'success' => true,
                    'message' => null,
                    'data' => $this->inventoryService->formatCellResponse($updatedCell),
                ];
                $successCount++;
            } catch (ModelNotFoundException) {
                $results[] = [
                    'cell_id' => $cellId,
                    'success' => false,
                    'message' => 'Cell not found for this product.',
                    'data' => null,
                ];
            } catch (RuntimeException|InvalidArgumentException $exception) {
                $results[] = [
                    'cell_id' => $cellId,
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'data' => null,
                ];
            } catch (Throwable) {
                $results[] = [
                    'cell_id' => $cellId,
                    'success' => false,
                    'message' => 'Unable to update stock.',
                    'data' => null,
                ];
            }
        }

        $total = count($items);

        return response()->json([
            'success' => true,
            'action' => $action,
            'results' => $results,
            'message' => "{$successCount} of {$total} cells updated.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyBulkAction(string $action, ProductColorSize $cell, array $item, ?string $remarks): ProductColorSize
    {
        if ($action === 'adjust') {
            if (! array_key_exists('new_quantity', $item)) {
                throw new RuntimeException('New quantity is required for adjustment.');
            }

            return $this->inventoryService->adjust($cell, (int) $item['new_quantity'], $remarks ?? '');
        }

        if (! array_key_exists('quantity', $item) || (int) $item['quantity'] < 1) {
            throw new RuntimeException('Quantity must be at least 1.');
        }

        $quantity = (int) $item['quantity'];

        return match ($action) {
            'stock-in' => $this->inventoryService->stockIn($cell, $quantity, $remarks),
            'stock-out' => $this->inventoryService->stockOut($cell, $quantity, $remarks),
            'reserve' => $this->inventoryService->reserve($cell, $quantity, $remarks),
            'release' => $this->inventoryService->release($cell, $quantity, $remarks),
            'damage' => $this->inventoryService->damage($cell, $quantity, $remarks),
            default => throw new RuntimeException('Unsupported inventory action.'),
        };
    }

    /**
     * @param  callable(ProductColorSize, int, ?string): ProductColorSize  $callback
     */
    private function handleMovement(StockMovementRequest $request, callable $callback): JsonResponse
    {
        try {
            $cell = $this->resolveCell($request->integer('cell_id'));
            $updatedCell = $callback(
                $cell,
                $request->integer('quantity'),
                $request->input('remarks')
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully.',
                'data' => $this->inventoryService->formatCellResponse($updatedCell),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to update stock.',
            ], 500);
        }
    }

    private function resolveCell(int $cellId): ProductColorSize
    {
        $cell = ProductColorSize::query()
            ->with('color.product')
            ->findOrFail($cellId);

        $this->ensureProductIsActive($cell);

        return $cell;
    }

    private function ensureProductIsActive(ProductColorSize $cell): void
    {
        if ($cell->color->product->status === 'inactive') {
            throw new RuntimeException('Cannot update inventory for an inactive product.');
        }
    }
}
