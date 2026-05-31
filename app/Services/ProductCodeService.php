<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;

class ProductCodeService
{
    public function preview(ProductCategory $category): string
    {
        return $this->buildCode($category, $this->nextSequence($category));
    }

    public function generate(ProductCategory $category): string
    {
        return $this->buildCode($category, $this->nextSequence($category));
    }

    public function rebuildForCategory(string $existingCode, ProductCategory $category): string
    {
        $prefix = strtoupper($category->code);
        $suffix = str_contains($existingCode, '-')
            ? substr(strrchr($existingCode, '-'), 1)
            : $existingCode;

        return "{$prefix}-{$suffix}";
    }

    private function nextSequence(ProductCategory $category): int
    {
        return Product::query()
            ->where('product_category_id', $category->id)
            ->count() + 1;
    }

    private function buildCode(ProductCategory $category, int $sequence): string
    {
        $prefix = strtoupper($category->code);
        $number = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$number}";
    }
}
