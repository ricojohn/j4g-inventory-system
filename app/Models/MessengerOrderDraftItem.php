<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerOrderDraftItem extends Model
{
    protected $fillable = ['messenger_order_draft_id', 'product_color_size_id', 'quantity', 'unit_price', 'product_snapshot', 'available_stock_snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'product_snapshot' => 'array', 'available_stock_snapshot' => 'integer'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MessengerOrderDraft::class, 'messenger_order_draft_id');
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(ProductColorSize::class, 'product_color_size_id');
    }
}
