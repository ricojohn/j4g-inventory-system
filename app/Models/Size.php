<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    protected $fillable = [
        'name',
    ];

    public function productSizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_size')
            ->withPivot('id', 'sort_order')
            ->withTimestamps();
    }
}
