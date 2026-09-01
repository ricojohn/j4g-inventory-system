<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookPage extends Model
{
    protected $fillable = ['branch_id', 'page_id', 'name', 'status', 'access_token', 'graph_api_version', 'ai_enabled'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'ai_enabled' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(FacebookConversation::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(FacebookWebhookEvent::class);
    }
}
