<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookWebhookEvent extends Model
{
    protected $fillable = ['branch_id', 'facebook_page_id', 'event_key', 'event_type', 'sender_psid', 'meta_timestamp', 'payload', 'status', 'attempts', 'processed_at', 'failed_at', 'error_message'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'meta_timestamp' => 'datetime', 'processed_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class, 'facebook_page_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
