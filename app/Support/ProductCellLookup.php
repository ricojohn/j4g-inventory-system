<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductColorSize;
use App\Services\InventoryService;
use Illuminate\Support\Collection;

class ProductCellLookup
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cellsForProduct(Product $product): array
    {
        if ($product->status !== 'active') {
            return [];
        }

        return ProductColorSize::query()
            ->whereHas('color', fn ($query) => $query->where('product_id', $product->id))
            ->with(['color.color', 'color.product', 'size.size'])
            ->get()
            ->map(fn (ProductColorSize $cell) => $this->formatCell($cell))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatCell(ProductColorSize $cell): array
    {
        $cell->loadMissing(['color.color', 'color.product', 'size.size']);

        return [
            'cell_id' => $cell->id,
            'product_id' => $cell->color->product_id,
            'product_name' => $cell->color->product->name,
            'item_code' => $cell->color->item_code,
            'color_name' => $cell->color->color->name,
            'size_name' => $cell->size->size->name,
            'available_stock' => $this->inventoryService->getAvailableStock($cell),
            'current_stock' => $cell->current_stock,
        ];
    }

    /**
     * @param  Collection<int, ProductColorSize>|iterable<int, ProductColorSize>  $cells
     */
    public function ensureActiveProducts(iterable $cells): void
    {
        foreach ($cells as $cell) {
            $cell->loadMissing('color.product');

            if ($cell->color->product->status !== 'active') {
                throw new \RuntimeException('Cannot process orders for inactive products.');
            }
        }
    }
}
