<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'size_id',
        'stock_quantity',
        'reserved_quantity',
    ];

    protected function casts(): array
    {
        return [
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stock_quantity - $this->reserved_quantity;
    }
}
