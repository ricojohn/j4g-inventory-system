<?php

namespace App\Models;

use App\Services\ProductCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
        'branch_id',
    ];

    protected static function booted(): void
    {
        static::updated(function (Product $product): void {
            if (! $product->wasChanged('code')) {
                return;
            }

            $service = app(ProductCodeService::class);

            DB::transaction(function () use ($product, $service): void {
                $product->colors()
                    ->select(['id', 'item_code'])
                    ->orderBy('id')
                    ->each(function (ProductColor $color) use ($product, $service): void {
                        $color->update([
                            'item_code' => $service->rebuildForProduct($color->item_code, $product),
                        ]);
                    });
            });
        });
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function sizeMasters(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'product_size')
            ->withPivot('id', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function colorMasters(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'product_color')
            ->withPivot('id', 'color_code', 'item_code', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function cells(): HasManyThrough
    {
        return $this->hasManyThrough(ProductColorSize::class, ProductColor::class);
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->cells()->sum('current_stock');
    }

    public function getTotalReservedAttribute(): int
    {
        return (int) $this->cells()->sum('reserved_quantity');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
