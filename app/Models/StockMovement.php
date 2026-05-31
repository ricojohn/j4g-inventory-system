<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_variant_id',
        'movement_type',
        'quantity',
        'before_stock',
        'after_stock',
        'before_reserved',
        'after_reserved',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity' => 'integer',
            'before_stock' => 'integer',
            'after_stock' => 'integer',
            'before_reserved' => 'integer',
            'after_reserved' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
