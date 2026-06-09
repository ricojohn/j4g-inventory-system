<?php

namespace App\Services\Ai;

use App\Exceptions\IntegrationException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider extends AbstractAiProvider
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(?string $apiKey = null): array
    {
        try {
            $key = $apiKey ?? $this->integration->apiKey();

            if (blank($key)) {
                throw new IntegrationException('Google Gemini is not connected. Configure it in Integrations.');
            }

            $model = $this->getModel();
            $url = $this->endpoint($model, $key);

            $response = Http::acceptJson()
                ->timeout(config('services.gemini.request_timeout'))
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => 'Reply with OK only.'],
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'Google Gemini connection successful.',
                ];
            }

            return [
                'ok' => false,
                'message' => $this->mapErrorMessage($response->json(), $response->status()),
            ];
        } catch (IntegrationException $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function parseOrderMessage(string $message): array
    {
        $key = $this->integration->apiKey();

        if (blank($key)) {
            throw new IntegrationException('Google Gemini is not connected. Configure it in Integrations.');
        }

        $model = $this->getModel();
        $url = $this->endpoint($model, $key);

        $response = Http::acceptJson()
            ->timeout(config('services.gemini.request_timeout'))
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $this->systemPrompt()],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $message],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.2,
                ],
            ]);

        if (! $response->successful()) {
            $this->logProviderFailure('gemini', 'parseOrderMessage', $response->body());

            throw new RuntimeException($this->mapErrorMessage($response->json(), $response->status()));
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($content) || blank($content)) {
            throw new RuntimeException('Google Gemini returned an empty response.');
        }

        return $this->validateParsedPayload($this->extractJson($content));
    }

    private function endpoint(string $model, string $apiKey): string
    {
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');

        return "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mapErrorMessage(?array $payload, int $status): string
    {
        $message = $payload['error']['message'] ?? 'Google Gemini request failed.';
        $statusCode = $payload['error']['status'] ?? null;

        if ($status === 429 || str_contains(strtolower($message), 'quota')) {
            return 'Gemini quota exceeded. Check billing and usage limits in Google AI Studio.';
        }

        if ($status === 400 && ($statusCode === 'API_KEY_INVALID' || str_contains(strtolower($message), 'api key'))) {
            return 'Gemini invalid key. Verify your API key in Google AI Studio.';
        }

        return $message;
    }
}
