<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_order_id',
        'product_color_size_id',
        'quantity_ordered',
        'quantity_reserved',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_reserved' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(ProductColorSize::class, 'product_color_size_id');
    }

    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierOrderItem::class);
    }
}
