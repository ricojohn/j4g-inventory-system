<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Events\StockUpdated;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    public function stockIn(ProductVariant $variant, int $quantity, ?string $remarks = null): ProductVariant
    {
        return $this->applyMovement($variant, function (ProductVariant $lockedVariant) use ($quantity): array {
            $beforeStock = $lockedVariant->stock_quantity;
            $lockedVariant->stock_quantity = $beforeStock + $quantity;

            return [
                'movement_type' => MovementType::In,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $lockedVariant->stock_quantity,
                'before_reserved' => $lockedVariant->reserved_quantity,
                'after_reserved' => $lockedVariant->reserved_quantity,
            ];
        }, $remarks);
    }

    public function stockOut(ProductVariant $variant, int $quantity, ?string $remarks = null): ProductVariant
    {
        return $this->applyMovement($variant, function (ProductVariant $lockedVariant) use ($quantity): array {
            $available = $this->getAvailableStock($lockedVariant);

            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock.');
            }

            $beforeStock = $lockedVariant->stock_quantity;
            $lockedVariant->stock_quantity = $beforeStock - $quantity;

            return [
                'movement_type' => MovementType::Out,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $lockedVariant->stock_quantity,
                'before_reserved' => $lockedVariant->reserved_quantity,
                'after_reserved' => $lockedVariant->reserved_quantity,
            ];
        }, $remarks);
    }

    public function reserve(ProductVariant $variant, int $quantity, ?string $remarks = null): ProductVariant
    {
        return $this->applyMovement($variant, function (ProductVariant $lockedVariant) use ($quantity): array {
            $available = $this->getAvailableStock($lockedVariant);

            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock to reserve.');
            }

            $beforeReserved = $lockedVariant->reserved_quantity;
            $lockedVariant->reserved_quantity = $beforeReserved + $quantity;

            return [
                'movement_type' => MovementType::Reserve,
                'quantity' => $quantity,
                'before_stock' => $lockedVariant->stock_quantity,
                'after_stock' => $lockedVariant->stock_quantity,
                'before_reserved' => $beforeReserved,
                'after_reserved' => $lockedVariant->reserved_quantity,
            ];
        }, $remarks);
    }

    public function release(ProductVariant $variant, int $quantity, ?string $remarks = null): ProductVariant
    {
        return $this->applyMovement($variant, function (ProductVariant $lockedVariant) use ($quantity): array {
            if ($lockedVariant->reserved_quantity < $quantity) {
                throw new RuntimeException('Not enough reserved stock to release.');
            }

            $beforeReserved = $lockedVariant->reserved_quantity;
            $lockedVariant->reserved_quantity = $beforeReserved - $quantity;

            return [
                'movement_type' => MovementType::Release,
                'quantity' => $quantity,
                'before_stock' => $lockedVariant->stock_quantity,
                'after_stock' => $lockedVariant->stock_quantity,
                'before_reserved' => $beforeReserved,
                'after_reserved' => $lockedVariant->reserved_quantity,
            ];
        }, $remarks);
    }

    public function damage(ProductVariant $variant, int $quantity, ?string $remarks = null): ProductVariant
    {
        return $this->applyMovement($variant, function (ProductVariant $lockedVariant) use ($quantity): array {
            $available = $this->getAvailableStock($lockedVariant);

            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock to mark as damaged.');
            }

            $beforeStock = $lockedVariant->stock_quantity;
            $lockedVariant->stock_quantity = $beforeStock - $quantity;

            return [
                'movement_type' => MovementType::Damaged,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $lockedVariant->stock_quantity,
                'before_reserved' => $lockedVariant->reserved_quantity,
                'after_reserved' => $lockedVariant->reserved_quantity,
            ];
        }, $remarks);
    }

    public function adjust(ProductVariant $variant, int $newQuantity, string $remarks): ProductVariant
    {
        if ($remarks === '') {
            throw new InvalidArgumentException('Remarks are required for stock adjustment.');
        }

        return $this->applyMovement($variant, function (ProductVariant $lockedVariant) use ($newQuantity): array {
            if ($newQuantity < $lockedVariant->reserved_quantity) {
                throw new RuntimeException('Adjusted stock cannot be less than reserved quantity.');
            }

            $beforeStock = $lockedVariant->stock_quantity;
            $lockedVariant->stock_quantity = $newQuantity;

            return [
                'movement_type' => MovementType::Adjustment,
                'quantity' => abs($newQuantity - $beforeStock),
                'before_stock' => $beforeStock,
                'after_stock' => $lockedVariant->stock_quantity,
                'before_reserved' => $lockedVariant->reserved_quantity,
                'after_reserved' => $lockedVariant->reserved_quantity,
            ];
        }, $remarks);
    }

    public function getAvailableStock(ProductVariant $variant): int
    {
        return $variant->stock_quantity - $variant->reserved_quantity;
    }

    public function getStockStatus(ProductVariant $variant): StockStatus
    {
        $available = $this->getAvailableStock($variant);

        if ($available <= 0) {
            return StockStatus::OutOfStock;
        }

        $variant->loadMissing('product.category');
        $threshold = $variant->product->category->low_stock_threshold ?? 0;

        if ($available <= $threshold) {
            return StockStatus::LowStock;
        }

        return StockStatus::Ok;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatVariantForDisplay(ProductVariant $variant): array
    {
        $variant->loadMissing(['product', 'size']);
        $status = $this->getStockStatus($variant);

        return [
            'id' => $variant->id,
            'size_name' => $variant->size->name,
            'stock_quantity' => $variant->stock_quantity,
            'reserved_quantity' => $variant->reserved_quantity,
            'available_stock' => $this->getAvailableStock($variant),
            'status' => $status->value,
            'status_label' => $status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatVariantResponse(ProductVariant $variant): array
    {
        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'stock_quantity' => $variant->stock_quantity,
            'reserved_quantity' => $variant->reserved_quantity,
            'available_stock' => $this->getAvailableStock($variant),
            'status' => $this->getStockStatus($variant)->value,
        ];
    }

    /**
     * @param  callable(ProductVariant): array<string, mixed>  $operation
     */
    private function applyMovement(ProductVariant $variant, callable $operation, ?string $remarks = null): ProductVariant
    {
        return DB::transaction(function () use ($variant, $operation, $remarks) {
            $lockedVariant = ProductVariant::query()
                ->whereKey($variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $movementData = $operation($lockedVariant);
            $lockedVariant->save();

            $lockedVariant->load(['product', 'size']);

            $movement = StockMovement::query()->create([
                ...$movementData,
                'product_variant_id' => $lockedVariant->id,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
            ]);

            $payload = [
                'variant_id' => $lockedVariant->id,
                'product_id' => $lockedVariant->product_id,
                'stock_quantity' => $lockedVariant->stock_quantity,
                'reserved_quantity' => $lockedVariant->reserved_quantity,
                'available_stock' => $this->getAvailableStock($lockedVariant),
                'status' => $this->getStockStatus($lockedVariant)->value,
                'movement_id' => $movement->id,
                'movement_type' => $movementData['movement_type']->value,
                'quantity' => $movementData['quantity'],
                'product_name' => $lockedVariant->product->name,
                'color' => $lockedVariant->product->color,
                'size_name' => $lockedVariant->size->name,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name ?? 'System',
                'created_at_human' => now()->format('M d, Y H:i'),
                'updated_by' => Auth::id(),
                'updated_at' => now()->toIso8601String(),
            ];

            DB::afterCommit(function () use ($payload) {
                broadcast(new StockUpdated($payload));
            });

            return $lockedVariant->fresh(['product.category', 'size']);
        });
    }
}
