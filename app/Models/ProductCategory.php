<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = [
        'name',
        'code',
        'low_stock_threshold',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'category_size')
            ->withTimestamps()
            ->orderBy('sort_order');
    }
}
