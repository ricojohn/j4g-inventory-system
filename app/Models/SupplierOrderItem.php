<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_order_id',
        'product_color_size_id',
        'quantity_ordered',
        'quantity_received',
        'customer_order_item_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
        ];
    }

    public function po(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(ProductColorSize::class, 'product_color_size_id');
    }

    public function customerOrderItem(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderItem::class);
    }
}
