<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FacebookConversation extends Model
{
    protected $fillable = ['branch_id', 'facebook_page_id', 'psid', 'customer_name', 'customer_id', 'state', 'control_mode', 'assigned_user_id', 'taken_over_at', 'returned_to_ai_at', 'last_inbound_at', 'last_outbound_at', 'last_read_at', 'version'];

    protected function casts(): array
    {
        return ['taken_over_at' => 'datetime', 'returned_to_ai_at' => 'datetime', 'last_inbound_at' => 'datetime', 'last_outbound_at' => 'datetime', 'last_read_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class, 'facebook_page_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class);
    }

    public function draft(): HasOne
    {
        return $this->hasOne(MessengerOrderDraft::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(FacebookTag::class, 'facebook_conversation_tag')->withTimestamps();
    }
}
