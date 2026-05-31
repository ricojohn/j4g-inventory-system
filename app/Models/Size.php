<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'category_size')
            ->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
