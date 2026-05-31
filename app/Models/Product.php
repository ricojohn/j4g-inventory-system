<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'product_category_id',
        'item_code',
        'name',
        'color',
        'description',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->sum('stock_quantity');
    }

    public function getTotalReservedAttribute(): int
    {
        return (int) $this->variants->sum('reserved_quantity');
    }

    public function getTotalAvailableAttribute(): int
    {
        return $this->total_stock - $this->total_reserved;
    }
}
