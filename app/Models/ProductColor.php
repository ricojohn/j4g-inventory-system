<?php

namespace App\Models;

use App\Services\ProductCodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductColor extends Model
{
    protected $table = 'product_color';

    protected $fillable = [
        'product_id',
        'color_id',
        'color_code',
        'item_code',
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
        static::creating(function (ProductColor $productColor): void {
            if (blank($productColor->item_code)) {
                $productColor->loadMissing('product');
                $productColor->item_code = app(ProductCodeService::class)->generate($productColor->product);
            }
        });

        static::created(function (ProductColor $productColor): void {
            $productColor->loadMissing('product.sizes');

            foreach ($productColor->product->sizes as $size) {
                ProductColorSize::query()->firstOrCreate(
                    [
                        'product_color_id' => $productColor->id,
                        'product_size_id' => $size->id,
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

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(ProductColorSize::class);
    }
}
