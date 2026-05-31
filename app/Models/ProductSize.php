<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSize extends Model
{
    protected $table = 'product_size';

    protected $fillable = [
        'product_id',
        'size_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ProductSize $productSize): void {
            $productSize->loadMissing('product.colors');

            foreach ($productSize->product->colors as $color) {
                ProductColorSize::query()->firstOrCreate(
                    [
                        'product_color_id' => $color->id,
                        'product_size_id' => $productSize->id,
                    ],
                    [
                        'current_stock' => 0,
                        'reserved_quantity' => 0,
                        'reorder_level' => 0,
                    ]
                );
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(ProductColorSize::class);
    }
}
