<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_color_size_id',
        'type',
        'quantity',
        'before_stock',
        'after_stock',
        'before_reserved',
        'after_reserved',
        'remarks',
        'created_by',
        'created_at',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'before_stock' => 'integer',
            'after_stock' => 'integer',
            'before_reserved' => 'integer',
            'after_reserved' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            $movement->created_at = $movement->created_at ?? now();
        });
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(ProductColorSize::class, 'product_color_size_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
