<?php

namespace App\Services\Ai;

use App\Exceptions\IntegrationException;
use App\Models\Integration;
use Illuminate\Support\Facades\DB;

class AiProviderManager
{
    /**
     * @return array<int, string>
     */
    public function providerKeys(): array
    {
        return array_keys(config('services.ai.providers', []));
    }

    public function getProvider(string $provider): AiProviderInterface
    {
        $registry = config("services.ai.providers.{$provider}");

        if (! is_array($registry) || blank($registry['class'] ?? null)) {
            throw new IntegrationException("Unknown AI provider: {$provider}.");
        }

        $integration = Integration::forProvider($provider);

        if (! $integration) {
            throw new IntegrationException("{$provider} integration is not configured.");
        }

        $class = $registry['class'];

        return app($class, ['integration' => $integration]);
    }

    public function getDefaultProvider(): AiProviderInterface
    {
        $default = Integration::query()
            ->whereIn('provider', $this->providerKeys())
            ->get()
            ->first(fn (Integration $integration) => $integration->isDefault() && $integration->isConnected());

        if ($default) {
            return $this->getProvider($default->provider);
        }

        $connected = Integration::query()
            ->whereIn('provider', $this->providerKeys())
            ->get()
            ->first(fn (Integration $integration) => $integration->isConnected());

        if ($connected) {
            return $this->getProvider($connected->provider);
        }

        throw new IntegrationException('No AI provider is connected. Configure one in Integrations.');
    }

    public function getDefaultProviderKey(): ?string
    {
        try {
            return $this->resolveDefaultIntegration()?->provider;
        } catch (IntegrationException) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProviders(): array
    {
        return collect($this->providerKeys())
            ->map(function (string $provider) {
                $integration = Integration::forProvider($provider);
                $label = config("services.ai.providers.{$provider}.label", ucfirst($provider));

                return [
                    'provider' => $provider,
                    'label' => $label,
                    'connected' => $integration?->isConnected() ?? false,
                    'is_default' => $integration?->isDefault() ?? false,
                    'model' => $integration?->defaultModel() ?? config("services.{$provider}.default_model"),
                    'connected_at' => $integration?->connected_at?->format('M d, Y H:i'),
                    'status' => $integration?->status ?? 'inactive',
                ];
            })
            ->values()
            ->all();
    }

    public function setDefaultProvider(string $provider): Integration
    {
        if (! in_array($provider, $this->providerKeys(), true)) {
            throw new IntegrationException("Unknown AI provider: {$provider}.");
        }

        $integration = Integration::forProvider($provider);

        if (! $integration?->isConnected()) {
            throw new IntegrationException('Only a connected provider can be set as default.');
        }

        return DB::transaction(function () use ($integration, $provider) {
            Integration::query()
                ->whereIn('provider', $this->providerKeys())
                ->get()
                ->each(function (Integration $row) use ($provider) {
                    $settings = $row->settings ?? [];
                    $settings['is_default_provider'] = $row->provider === $provider;
                    $row->update(['settings' => $settings]);
                });

            return $integration->fresh();
        });
    }

    public function ensureProviderRows(?int $createdBy = null): void
    {
        foreach ($this->providerKeys() as $provider) {
            Integration::query()->firstOrCreate(
                ['provider' => $provider],
                [
                    'name' => config("services.ai.providers.{$provider}.label", ucfirst($provider)),
                    'status' => 'inactive',
                    'created_by' => $createdBy,
                ]
            );
        }
    }

    private function resolveDefaultIntegration(): ?Integration
    {
        $default = Integration::query()
            ->whereIn('provider', $this->providerKeys())
            ->get()
            ->first(fn (Integration $integration) => $integration->isDefault() && $integration->isConnected());

        if ($default) {
            return $default;
        }

        return Integration::query()
            ->whereIn('provider', $this->providerKeys())
            ->get()
            ->first(fn (Integration $integration) => $integration->isConnected());
    }
}
