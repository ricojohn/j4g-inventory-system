<?php

namespace App\Services\Facebook;

use App\Models\BusinessKnowledgeBaseEntry;
use App\Models\FacebookPage;
use App\Models\Product;
use App\Models\ProductColorSize;

class BusinessKnowledgeBaseService
{
    public function buildAnswer(FacebookPage $page, string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $kb = BusinessKnowledgeBaseEntry::query()
            ->where('branch_id', $page->branch_id)
            ->where('is_active', true)
            ->orderByDesc('sort_order')
            ->orderBy('title')
            ->get();

        foreach ($kb as $entry) {
            if ($this->matches($entry->title.' '.$entry->content, $message)) {
                return $entry->content;
            }
        }

        $productRows = $this->productRows($page, $message);

        if ($productRows === []) {
            return null;
        }

        $lines = ["Here are the product details I found:"];
        foreach ($productRows as $row) {
            $lines[] = sprintf(
                '- %s (%s) | %s / %s | Available: %s',
                $row['product'],
                $row['code'] ?? 'n/a',
                $row['color'],
                $row['size'],
                $row['available']
            );
        }

        return implode("\n", $lines);
    }

    private function matches(string $haystack, string $message): bool
    {
        $haystack = mb_strtolower($haystack);
        $message = mb_strtolower($message);

        foreach (preg_split('/\s+/', $message) ?: [] as $word) {
            if (mb_strlen($word) < 4) {
                continue;
            }

            if (str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    private function productRows(FacebookPage $page, string $message): array
    {
        $products = Product::query()
            ->where('branch_id', $page->branch_id)
            ->where('status', 'active')
            ->where(function ($query) use ($message): void {
                foreach (preg_split('/\s+/', $message) ?: [] as $word) {
                    if (mb_strlen($word) < 3) {
                        continue;
                    }
                    $query->orWhere('name', 'like', "%{$word}%")
                        ->orWhere('code', 'like', "%{$word}%");
                }
            })
            ->limit(10)
            ->pluck('id');

        if ($products->isEmpty()) {
            return [];
        }

        return ProductColorSize::query()
            ->with('color.product', 'color.color', 'size.size')
            ->whereHas('color', fn ($query) => $query->whereIn('product_id', $products))
            ->limit(10)
            ->get()
            ->map(fn (ProductColorSize $cell) => [
                'product' => $cell->color?->product?->name,
                'code' => $cell->color?->product?->code,
                'color' => $cell->color?->color?->name,
                'size' => $cell->size?->size?->name,
                'available' => $cell->available_stock,
            ])
            ->all();
    }
}
