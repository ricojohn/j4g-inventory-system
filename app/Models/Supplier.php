<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    // TODO: supplier_categories pivot (supplier_id, category_id) — future feature

    protected $fillable = [
        'name',
        'contact',
        'address',
        'notes',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }
}
