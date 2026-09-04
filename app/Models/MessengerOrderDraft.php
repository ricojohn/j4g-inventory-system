<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerOrderDraft extends Model
{
    protected $fillable = ['branch_id', 'facebook_conversation_id', 'customer_id', 'customer_name', 'psid', 'fulfillment_method', 'delivery_address', 'payment_method_preference', 'status', 'version', 'summary_data', 'summary_text', 'summary_hash', 'summarized_at', 'confirmed_at', 'confirmation_actor_type', 'confirmed_by_user_id', 'confirmation_message_id', 'confirmation_expires_at', 'customer_order_id'];

    protected function casts(): array
    {
        return ['summary_data' => 'array', 'summarized_at' => 'datetime', 'confirmed_at' => 'datetime', 'confirmation_expires_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FacebookConversation::class, 'facebook_conversation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MessengerOrderDraftItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
