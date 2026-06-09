<?php

namespace App\Http\Controllers;

use App\Exceptions\IntegrationException;
use App\Http\Requests\UpdateIntegrationRequest;
use App\Models\Integration;
use App\Services\Ai\AiProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function __construct(private AiProviderManager $aiProviderManager) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        $this->aiProviderManager->ensureProviderRows($request->user()->id);

        $providerModels = collect($this->aiProviderManager->providerKeys())
            ->mapWithKeys(fn (string $provider) => [
                $provider => config("services.{$provider}.models", []),
            ])
            ->all();

        return view('integrations.index', [
            'providers' => $this->aiProviderManager->listProviders(),
            'providerModels' => $providerModels,
        ]);
    }

    public function update(UpdateIntegrationRequest $request, string $provider): JsonResponse
    {
        $this->aiProviderManager->ensureProviderRows($request->user()->id);

        $integration = Integration::forProvider($provider);

        if (! $integration) {
            return response()->json([
                'success' => false,
                'message' => 'Integration not found.',
            ], 404);
        }

        $credentials = $integration->credentials ?? [];

        if ($request->filled('api_key')) {
            $credentials['api_key'] = $request->string('api_key')->toString();
        } elseif (! filled($credentials['api_key'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required for a new connection.',
            ], 422);
        }

        $settings = $integration->settings ?? [];
        $settings['default_model'] = $request->string('model')->toString();

        $integration->update([
            'credentials' => $credentials,
            'settings' => $settings,
            'status' => 'active',
            'connected_at' => now(),
            'created_by' => $integration->created_by ?? $request->user()->id,
        ]);

        $label = config("services.ai.providers.{$provider}.label", ucfirst($provider));

        return response()->json([
            'success' => true,
            'message' => "{$label} integration saved.",
            'provider' => $provider,
            'status' => $integration->status,
            'model' => $integration->defaultModel(),
            'connected_at' => $integration->connected_at?->format('M d, Y H:i'),
            'has_api_key' => true,
        ]);
    }

    public function test(Request $request, string $provider): JsonResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $apiKey = $request->filled('api_key')
            ? $request->string('api_key')->toString()
            : Integration::forProvider($provider)?->apiKey();

        try {
            $aiProvider = $this->aiProviderManager->getProvider($provider);
            $result = $aiProvider->testConnection($apiKey);
        } catch (IntegrationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    public function disconnect(Request $request, string $provider): JsonResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        $integration = Integration::forProvider($provider);

        if ($integration) {
            $integration->update([
                'credentials' => null,
                'settings' => null,
                'status' => 'inactive',
                'connected_at' => null,
            ]);
        }

        $label = config("services.ai.providers.{$provider}.label", ucfirst($provider));

        return response()->json([
            'success' => true,
            'message' => "{$label} integration disconnected.",
        ]);
    }

    public function setDefault(Request $request, string $provider): JsonResponse
    {
        abort_unless($request->user()?->can('manage integrations'), 403);

        try {
            $this->aiProviderManager->setDefaultProvider($provider);
        } catch (IntegrationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $label = config("services.ai.providers.{$provider}.label", ucfirst($provider));

        return response()->json([
            'success' => true,
            'message' => "{$label} is now the default AI provider.",
            'provider' => $provider,
        ]);
    }
}
