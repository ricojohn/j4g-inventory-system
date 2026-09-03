<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookMessage extends Model
{
    protected $fillable = ['branch_id', 'facebook_conversation_id', 'facebook_webhook_event_id', 'meta_message_id', 'idempotency_key', 'direction', 'sender_type', 'message_type', 'body', 'attachments', 'ai_generated', 'status', 'error_message', 'sent_at'];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'ai_generated' => 'boolean', 'sent_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FacebookConversation::class, 'facebook_conversation_id');
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(FacebookWebhookEvent::class, 'facebook_webhook_event_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}
