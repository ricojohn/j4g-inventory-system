<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Events\StockUpdated;
use App\Models\ProductColorSize;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    public function stockIn(ProductColorSize $cell, int $quantity, ?string $remarks = null): ProductColorSize
    {
        return $this->applyMovement($cell, function (ProductColorSize $lockedCell) use ($quantity): array {
            $beforeStock = $lockedCell->current_stock;
            $lockedCell->current_stock = $beforeStock + $quantity;

            return [
                'type' => MovementType::In,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $lockedCell->current_stock,
                'before_reserved' => $lockedCell->reserved_quantity,
                'after_reserved' => $lockedCell->reserved_quantity,
            ];
        }, $remarks);
    }

    public function stockOut(ProductColorSize $cell, int $quantity, ?string $remarks = null): ProductColorSize
    {
        return $this->applyMovement($cell, function (ProductColorSize $lockedCell) use ($quantity): array {
            $available = $this->getAvailableStock($lockedCell);

            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock.');
            }

            $beforeStock = $lockedCell->current_stock;
            $lockedCell->current_stock = $beforeStock - $quantity;

            return [
                'type' => MovementType::Out,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $lockedCell->current_stock,
                'before_reserved' => $lockedCell->reserved_quantity,
                'after_reserved' => $lockedCell->reserved_quantity,
            ];
        }, $remarks);
    }

    public function reserve(ProductColorSize $cell, int $quantity, ?string $remarks = null): ProductColorSize
    {
        return $this->applyMovement($cell, function (ProductColorSize $lockedCell) use ($quantity): array {
            $available = $this->getAvailableStock($lockedCell);

            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock to reserve.');
            }

            $beforeReserved = $lockedCell->reserved_quantity;
            $lockedCell->reserved_quantity = $beforeReserved + $quantity;

            return [
                'type' => MovementType::Reserve,
                'quantity' => $quantity,
                'before_stock' => $lockedCell->current_stock,
                'after_stock' => $lockedCell->current_stock,
                'before_reserved' => $beforeReserved,
                'after_reserved' => $lockedCell->reserved_quantity,
            ];
        }, $remarks);
    }

    public function release(ProductColorSize $cell, int $quantity, ?string $remarks = null): ProductColorSize
    {
        return $this->applyMovement($cell, function (ProductColorSize $lockedCell) use ($quantity): array {
            if ($lockedCell->reserved_quantity < $quantity) {
                throw new RuntimeException('Not enough reserved stock to release.');
            }

            $beforeReserved = $lockedCell->reserved_quantity;
            $lockedCell->reserved_quantity = $beforeReserved - $quantity;

            return [
                'type' => MovementType::Release,
                'quantity' => $quantity,
                'before_stock' => $lockedCell->current_stock,
                'after_stock' => $lockedCell->current_stock,
                'before_reserved' => $beforeReserved,
                'after_reserved' => $lockedCell->reserved_quantity,
            ];
        }, $remarks);
    }

    public function damage(ProductColorSize $cell, int $quantity, ?string $remarks = null): ProductColorSize
    {
        return $this->applyMovement($cell, function (ProductColorSize $lockedCell) use ($quantity): array {
            $available = $this->getAvailableStock($lockedCell);

            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock to mark as damaged.');
            }

            $beforeStock = $lockedCell->current_stock;
            $lockedCell->current_stock = $beforeStock - $quantity;

            return [
                'type' => MovementType::Damaged,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $lockedCell->current_stock,
                'before_reserved' => $lockedCell->reserved_quantity,
                'after_reserved' => $lockedCell->reserved_quantity,
            ];
        }, $remarks);
    }

    public function adjust(ProductColorSize $cell, int $newQuantity, string $remarks, ?int $reorderLevel = null): ProductColorSize
    {
        if ($remarks === '') {
            throw new InvalidArgumentException('Remarks are required for stock adjustment.');
        }

        return $this->applyMovement($cell, function (ProductColorSize $lockedCell) use ($newQuantity, $reorderLevel): array {
            if ($newQuantity < $lockedCell->reserved_quantity) {
                throw new RuntimeException('Adjusted stock cannot be less than reserved quantity.');
            }

            if ($reorderLevel !== null) {
                $lockedCell->reorder_level = $reorderLevel;
            }

            $beforeStock = $lockedCell->current_stock;
            $lockedCell->current_stock = $newQuantity;

            return [
                'type' => MovementType::Adjustment,
                'quantity' => abs($newQuantity - $beforeStock),
                'before_stock' => $beforeStock,
                'after_stock' => $lockedCell->current_stock,
                'before_reserved' => $lockedCell->reserved_quantity,
                'after_reserved' => $lockedCell->reserved_quantity,
            ];
        }, $remarks);
    }

    public function getAvailableStock(ProductColorSize $cell): int
    {
        return $cell->current_stock - $cell->reserved_quantity;
    }

    public function getStockStatus(ProductColorSize $cell): StockStatus
    {
        $available = $this->getAvailableStock($cell);

        if ($available <= 0) {
            return StockStatus::OutOfStock;
        }

        if ($cell->reorder_level > 0 && $available <= $cell->reorder_level) {
            return StockStatus::LowStock;
        }

        return StockStatus::Ok;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatCellForDisplay(ProductColorSize $cell): array
    {
        $cell->loadMissing(['color.color', 'size.size']);
        $status = $this->getStockStatus($cell);

        return [
            'id' => $cell->id,
            'color_id' => $cell->product_color_id,
            'size_id' => $cell->product_size_id,
            'color_name' => $cell->color->color->name,
            'color_item_code' => $cell->color->item_code,
            'size_name' => $cell->size->size->name,
            'current_stock' => $cell->current_stock,
            'reserved_quantity' => $cell->reserved_quantity,
            'available_stock' => $this->getAvailableStock($cell),
            'reorder_level' => $cell->reorder_level,
            'status' => $status->value,
            'status_label' => $status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatCellResponse(ProductColorSize $cell): array
    {
        return [
            'cell_id' => $cell->id,
            'product_id' => $cell->color->product_id,
            'current_stock' => $cell->current_stock,
            'reserved_quantity' => $cell->reserved_quantity,
            'available_stock' => $this->getAvailableStock($cell),
            'reorder_level' => $cell->reorder_level,
            'status' => $this->getStockStatus($cell)->value,
        ];
    }

    /**
     * @param  callable(ProductColorSize): array<string, mixed>  $operation
     */
    private function applyMovement(ProductColorSize $cell, callable $operation, ?string $remarks = null): ProductColorSize
    {
        return DB::transaction(function () use ($cell, $operation, $remarks) {
            $lockedCell = ProductColorSize::query()
                ->whereKey($cell->id)
                ->lockForUpdate()
                ->firstOrFail();

            $movementData = $operation($lockedCell);
            $lockedCell->save();

            $lockedCell->load(['color.product', 'color.color', 'size.size']);

            $movement = StockMovement::query()->create([
                ...$movementData,
                'product_color_size_id' => $lockedCell->id,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
            ]);

            $payload = [
                'cell_id' => $lockedCell->id,
                'variant_id' => $lockedCell->id,
                'product_id' => $lockedCell->color->product_id,
                'product_code' => $lockedCell->color->product->code,
                'product_name' => $lockedCell->color->product->name,
                'color_name' => $lockedCell->color->color->name,
                'color' => $lockedCell->color->color->name,
                'color_item_code' => $lockedCell->color->item_code,
                'size_name' => $lockedCell->size->size->name,
                'current_stock' => $lockedCell->current_stock,
                'stock_quantity' => $lockedCell->current_stock,
                'reserved_quantity' => $lockedCell->reserved_quantity,
                'available_stock' => $this->getAvailableStock($lockedCell),
                'reorder_level' => $lockedCell->reorder_level,
                'status' => $this->getStockStatus($lockedCell)->value,
                'movement_id' => $movement->id,
                'movement_type' => $movementData['type']->value,
                'quantity' => $movementData['quantity'],
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name ?? 'System',
                'created_at_human' => now()->format('M d, Y H:i'),
                'updated_by' => Auth::id(),
                'updated_at' => now()->toIso8601String(),
            ];

            DB::afterCommit(function () use ($payload) {
                broadcast(new StockUpdated($payload));
            });

            return $lockedCell->fresh(['color.product', 'color.color', 'size.size']);
        });
    }
}
