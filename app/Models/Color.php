<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    protected $fillable = [
        'name',
    ];

    public function productColors(): HasMany
    {
        return $this->hasMany(ProductColor::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_color')
            ->withPivot('id', 'color_code', 'item_code', 'sort_order')
            ->withTimestamps();
    }
}
