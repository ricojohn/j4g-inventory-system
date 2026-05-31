<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductColorSize extends Model
{
    protected $fillable = [
        'product_color_id',
        'product_size_id',
        'current_stock',
        'reserved_quantity',
        'reorder_level',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'integer',
            'reserved_quantity' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getProductAttribute(): Product
    {
        return $this->color->product;
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->current_stock - $this->reserved_quantity;
    }

    public function isLowStock(): bool
    {
        return $this->reorder_level > 0
            && $this->available_stock <= $this->reorder_level
            && $this->available_stock > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->available_stock <= 0;
    }
}
