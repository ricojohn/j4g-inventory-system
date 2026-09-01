<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = [
        'provider',
        'name',
        'status',
        'credentials',
        'settings',
        'connected_at',
        'created_by',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'connected_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isConnected(): bool
    {
        return $this->status === 'active'
            && filled($this->credentials['api_key'] ?? null);
    }

    public function defaultModel(): string
    {
        $settings = $this->settings ?? [];

        return $settings['default_model']
            ?? $settings['model']
            ?? config("services.{$this->provider}.default_model");
    }

    public function isDefault(): bool
    {
        return (bool) ($this->settings['is_default_provider'] ?? false);
    }

    public function apiKey(): ?string
    {
        return $this->credentials['api_key'] ?? null;
    }

    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public static function forProvider(string $provider): ?self
    {
        return self::query()->where('provider', $provider)->first();
    }

    public static function openAi(): ?self
    {
        return self::forProvider('openai');
    }

    public static function gemini(): ?self
    {
        return self::forProvider('gemini');
    }
}
