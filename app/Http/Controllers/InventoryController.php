<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\BulkStockMovementRequest;
use App\Http\Requests\Inventory\StockMovementRequest;
use App\Models\ProductVariant;
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

        return $this->handleMovement($request, fn ($variant, $quantity, $remarks) => $this->inventoryService->stockIn($variant, $quantity, $remarks));
    }

    public function stockOut(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('stock out'), 403);

        return $this->handleMovement($request, fn ($variant, $quantity, $remarks) => $this->inventoryService->stockOut($variant, $quantity, $remarks));
    }

    public function reserve(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('reserve stock'), 403);

        return $this->handleMovement($request, fn ($variant, $quantity, $remarks) => $this->inventoryService->reserve($variant, $quantity, $remarks));
    }

    public function release(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('release stock'), 403);

        return $this->handleMovement($request, fn ($variant, $quantity, $remarks) => $this->inventoryService->release($variant, $quantity, $remarks));
    }

    public function damage(StockMovementRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('damage stock'), 403);

        return $this->handleMovement($request, fn ($variant, $quantity, $remarks) => $this->inventoryService->damage($variant, $quantity, $remarks));
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('adjust stock'), 403);

        try {
            $variant = ProductVariant::query()->findOrFail($request->integer('product_variant_id'));
            $updatedVariant = $this->inventoryService->adjust(
                $variant,
                $request->integer('new_quantity'),
                $request->string('remarks')
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully.',
                'data' => $this->inventoryService->formatVariantResponse($updatedVariant),
            ]);
        } catch (RuntimeException $exception) {
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
            $variantId = (int) $item['product_variant_id'];

            try {
                $variant = ProductVariant::query()
                    ->whereKey($variantId)
                    ->where('product_id', $productId)
                    ->firstOrFail();

                $updatedVariant = $this->applyBulkAction($action, $variant, $item, $remarks);

                $results[] = [
                    'variant_id' => $variantId,
                    'success' => true,
                    'message' => null,
                    'data' => $this->inventoryService->formatVariantResponse($updatedVariant),
                ];
                $successCount++;
            } catch (ModelNotFoundException) {
                $results[] = [
                    'variant_id' => $variantId,
                    'success' => false,
                    'message' => 'Variant not found for this product.',
                    'data' => null,
                ];
            } catch (RuntimeException|InvalidArgumentException $exception) {
                $results[] = [
                    'variant_id' => $variantId,
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'data' => null,
                ];
            } catch (Throwable) {
                $results[] = [
                    'variant_id' => $variantId,
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
            'message' => "{$successCount} of {$total} variants updated.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyBulkAction(string $action, ProductVariant $variant, array $item, ?string $remarks): ProductVariant
    {
        if ($action === 'adjust') {
            if (! array_key_exists('new_quantity', $item)) {
                throw new RuntimeException('New quantity is required for adjustment.');
            }

            return $this->inventoryService->adjust($variant, (int) $item['new_quantity'], $remarks ?? '');
        }

        if (! array_key_exists('quantity', $item) || (int) $item['quantity'] < 1) {
            throw new RuntimeException('Quantity must be at least 1.');
        }

        $quantity = (int) $item['quantity'];

        return match ($action) {
            'stock-in' => $this->inventoryService->stockIn($variant, $quantity, $remarks),
            'stock-out' => $this->inventoryService->stockOut($variant, $quantity, $remarks),
            'reserve' => $this->inventoryService->reserve($variant, $quantity, $remarks),
            'release' => $this->inventoryService->release($variant, $quantity, $remarks),
            'damage' => $this->inventoryService->damage($variant, $quantity, $remarks),
            default => throw new RuntimeException('Unsupported inventory action.'),
        };
    }

    /**
     * @param  callable(ProductVariant, int, ?string): ProductVariant  $callback
     */
    private function handleMovement(StockMovementRequest $request, callable $callback): JsonResponse
    {
        try {
            $variant = ProductVariant::query()->findOrFail($request->integer('product_variant_id'));
            $updatedVariant = $callback(
                $variant,
                $request->integer('quantity'),
                $request->input('remarks')
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully.',
                'data' => $this->inventoryService->formatVariantResponse($updatedVariant),
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
}
