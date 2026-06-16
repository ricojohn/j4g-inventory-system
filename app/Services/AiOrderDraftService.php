<?php

namespace App\Services;

use App\Enums\AiOrderDraftStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use App\Models\AiOrderDraft;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductColorSize;
use App\Models\ProductSize;
use App\Models\User;
use App\Services\Ai\AiProviderManager;
use App\Support\ProductCellLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiOrderDraftService
{
    public function __construct(
        private AiProviderManager $aiProviderManager,
        private CustomerOrderService $customerOrderService,
        private ProductCellLookup $productCellLookup,
    ) {}

    public function createDraftFromMessage(string $message, User $user): AiOrderDraft
    {
        $parsed = $this->aiProviderManager->getDefaultProvider()->parseOrderMessage($message);
        $matched = $this->matchParsedItemsToInventory($parsed);

        $notes = collect([
            $parsed['notes'] ?? null,
            filled($parsed['deadline'] ?? null) ? 'Deadline: '.$parsed['deadline'] : null,
        ])->filter()->implode("\n");

        return AiOrderDraft::query()->create([
            'raw_message' => $message,
            'parsed_json' => $parsed,
            'matched_json' => $matched,
            'confidence_score' => $parsed['confidence'] ?? null,
            'status' => AiOrderDraftStatus::Draft,
            'customer_name' => $parsed['customer_name'],
            'customer_contact' => $parsed['customer_contact'],
            'customer_source' => $this->resolveCustomerSource($parsed['customer_source'] ?? 'facebook'),
            'customer_notes' => filled($notes) ? $notes : null,
            'created_by' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{items: array<int, array<string, mixed>>}
     */
    public function matchParsedItemsToInventory(array $parsed): array
    {
        $items = collect($parsed['items'] ?? [])
            ->map(fn (array $item) => $this->matchSingleItem($item))
            ->values()
            ->all();

        return ['items' => $items];
    }

    /**
     * @param  array<string, mixed>  $reviewedData
     * @return array{order: CustomerOrder, redirect_url: string}
     */
    public function convertDraftToCustomerOrder(AiOrderDraft $draft, array $reviewedData, User $user): array
    {
        if ($draft->status === AiOrderDraftStatus::Converted) {
            throw new InvalidArgumentException('This draft has already been converted.');
        }

        if ($draft->status === AiOrderDraftStatus::Rejected) {
            throw new InvalidArgumentException('Rejected drafts cannot be converted.');
        }

        return DB::transaction(function () use ($draft, $reviewedData, $user) {
            $cells = ProductColorSize::query()
                ->whereIn('id', collect($reviewedData['items'])->pluck('product_color_size_id'))
                ->with('color.product')
                ->get()
                ->keyBy('id');

            $this->productCellLookup->ensureActiveProducts($cells->values());

            $order = CustomerOrder::query()->create([
                'customer_name' => $reviewedData['customer_name'],
                'customer_contact' => $reviewedData['customer_contact'] ?? null,
                'customer_source' => $reviewedData['customer_source'] ?? CustomerSource::Facebook->value,
                'customer_notes' => $reviewedData['customer_notes'] ?? null,
                'image_path' => $draft->image_path,
                'status' => CustomerOrderStatus::Pending,
                'created_by' => $user->id,
            ]);

            foreach ($reviewedData['items'] as $item) {
                $cellId = (int) $item['product_color_size_id'];
                $cell = $cells->get($cellId);

                if (! $cell) {
                    throw new InvalidArgumentException('One or more items reference invalid inventory cells.');
                }

                $order->items()->create([
                    'product_color_size_id' => $cellId,
                    'quantity_ordered' => (int) $item['quantity'],
                    'quantity_reserved' => 0,
                    'status' => 'pending',
                ]);
            }

            $this->customerOrderService->reserveOrder($order);
            $order->refresh();

            $shortage = $this->customerOrderService->getShortageItems($order);

            $draft->update([
                'status' => AiOrderDraftStatus::Converted,
                'customer_order_id' => $order->id,
                'customer_name' => $reviewedData['customer_name'],
                'customer_contact' => $reviewedData['customer_contact'] ?? null,
                'customer_source' => $reviewedData['customer_source'] ?? CustomerSource::Facebook->value,
                'customer_notes' => $reviewedData['customer_notes'] ?? null,
            ]);

            $redirectUrl = route('orders.show', $order);

            if ($shortage->isNotEmpty() && $user->can('create supplier orders')) {
                $redirectUrl = route('supplier-orders.create', ['from_order_id' => $order->id]);
            }

            return [
                'order' => $order,
                'redirect_url' => $redirectUrl,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function matchSingleItem(array $item): array
    {
        $productName = (string) ($item['product_name'] ?? '');
        $colorName = (string) ($item['color_name'] ?? '');
        $sizeName = (string) ($item['size_name'] ?? '');
        $quantity = max(0, (int) ($item['quantity'] ?? 0));

        $products = Product::query()
            ->where('status', 'active')
            ->with(['colors.color', 'sizes.size'])
            ->get();

        $productMatch = $this->bestProductMatch($products, $productName);
        $product = $productMatch['model'];

        $colorMatch = null;
        $sizeMatch = null;
        $cell = null;

        if ($product instanceof Product) {
            $colorMatch = $this->bestColorMatch($product->colors, $colorName);
            $sizeMatch = $this->bestSizeMatch($product->sizes, $sizeName);

            if ($colorMatch['model'] instanceof ProductColor && $sizeMatch['model'] instanceof ProductSize) {
                $cell = ProductColorSize::query()
                    ->where('product_color_id', $colorMatch['model']->id)
                    ->where('product_size_id', $sizeMatch['model']->id)
                    ->with(['color.color', 'color.product', 'size.size'])
                    ->first();
            }
        }

        $matched = $cell instanceof ProductColorSize
            && $productMatch['score'] >= 0.85
            && $colorMatch['score'] >= 0.85
            && $sizeMatch['score'] >= 0.85;

        $availableStock = $cell ? $this->productCellLookup->formatCell($cell)['available_stock'] : 0;

        $suggestions = $this->buildSuggestions($products, $productName, $colorName);

        return [
            'parsed' => [
                'product_name' => $productName,
                'color_name' => $colorName,
                'size_name' => $sizeName,
                'quantity' => $quantity,
                'notes' => $item['notes'] ?? null,
            ],
            'matched' => $matched,
            'status' => $matched ? 'matched' : 'needs_review',
            'product_id' => $product?->id,
            'product_name' => $product?->name,
            'color_name' => $colorMatch['model']?->color?->name ?? $colorName,
            'size_name' => $sizeMatch['model']?->size?->name ?? $sizeName,
            'cell_id' => $cell?->id,
            'item_code' => $cell?->color?->item_code,
            'image_url' => $cell?->color?->imageUrl(),
            'available_stock' => $availableStock,
            'stock_status' => $cell ? app(InventoryService::class)->getStockStatus($cell)->value : null,
            'match_confidence' => round(min(
                $productMatch['score'],
                $colorMatch['score'] ?? 0,
                $sizeMatch['score'] ?? 0
            ), 2),
            'suggestions' => $suggestions,
            'shortage' => $matched && $quantity > $availableStock,
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{model: Product|null, score: float}
     */
    private function bestProductMatch(Collection $products, string $needle): array
    {
        $best = ['model' => null, 'score' => 0.0];

        foreach ($products as $product) {
            $score = $this->similarityScore($needle, $product->name);

            if ($score > $best['score']) {
                $best = ['model' => $product, 'score' => $score];
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, ProductColor>  $colors
     * @return array{model: ProductColor|null, score: float}
     */
    private function bestColorMatch(Collection $colors, string $needle): array
    {
        $best = ['model' => null, 'score' => 0.0];

        foreach ($colors as $color) {
            $score = $this->similarityScore($needle, $color->color->name);

            if ($score > $best['score']) {
                $best = ['model' => $color, 'score' => $score];
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, ProductSize>  $sizes
     * @return array{model: ProductSize|null, score: float}
     */
    private function bestSizeMatch(Collection $sizes, string $needle): array
    {
        $best = ['model' => null, 'score' => 0.0];

        foreach ($sizes as $size) {
            $score = $this->similarityScore($needle, $size->size->name);

            if ($score > $best['score']) {
                $best = ['model' => $size, 'score' => $score];
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function buildSuggestions(Collection $products, string $productName, string $colorName): array
    {
        $productMatch = $this->bestProductMatch($products, $productName);

        if (! $productMatch['model'] instanceof Product) {
            return $products
                ->take(5)
                ->map(fn (Product $product) => [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'color_name' => null,
                    'size_name' => null,
                ])
                ->values()
                ->all();
        }

        return $productMatch['model']->colors
            ->map(function (ProductColor $color) use ($productMatch, $colorName) {
                return [
                    'product_id' => $productMatch['model']->id,
                    'product_name' => $productMatch['model']->name,
                    'color_name' => $color->color->name,
                    'score' => $this->similarityScore($colorName, $color->color->name),
                ];
            })
            ->sortByDesc('score')
            ->take(5)
            ->values()
            ->all();
    }

    private function similarityScore(string $needle, string $haystack): float
    {
        $needle = $this->normalize($needle);
        $haystack = $this->normalize($haystack);

        if ($needle === '' || $haystack === '') {
            return 0.0;
        }

        if ($needle === $haystack) {
            return 1.0;
        }

        if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
            return 0.9;
        }

        similar_text($needle, $haystack, $percent);

        return round($percent / 100, 2);
    }

    private function normalize(string $value): string
    {
        return str($value)
            ->lower()
            ->replace(['/', '-', '_'], ' ')
            ->squish()
            ->toString();
    }

    private function resolveCustomerSource(string $source): CustomerSource
    {
        return CustomerSource::tryFrom($source) ?? CustomerSource::Facebook;
    }
}
