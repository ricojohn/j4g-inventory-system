<?php

namespace App\Services;

use App\Models\Product;

class ProductCodeService
{
    public function preview(Product $product): string
    {
        return $this->buildCode($product, $this->nextSequence($product));
    }

    public function generate(Product $product): string
    {
        return $this->buildCode($product, $this->nextSequence($product));
    }

    public function rebuildForProduct(string $existingCode, Product $product): string
    {
        $prefix = strtoupper($product->code);
        $suffix = str_contains($existingCode, '-')
            ? substr(strrchr($existingCode, '-'), 1)
            : $existingCode;

        return "{$prefix}-{$suffix}";
    }

    public function suggestPrefixFromName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        $prefix = preg_replace('/[^A-Z0-9]/', '', $initials) ?: 'PRD';

        return substr($prefix, 0, 16);
    }

    private function nextSequence(Product $product): int
    {
        return $product->colors()->count() + 1;
    }

    private function buildCode(Product $product, int $sequence): string
    {
        $prefix = strtoupper($product->code);
        $number = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$number}";
    }
}
