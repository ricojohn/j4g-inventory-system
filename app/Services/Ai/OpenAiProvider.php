<?php

namespace App\Services\Ai;

use App\Exceptions\IntegrationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider extends AbstractAiProvider
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(?string $apiKey = null): array
    {
        try {
            $response = $this->getClient($apiKey)->get('/models');

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'message' => 'OpenAI connection successful.',
                ];
            }

            $message = $this->mapErrorMessage($response->json('error.message'), $response->status());

            return [
                'ok' => false,
                'message' => $message,
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
        $model = $this->getModel();

        $response = $this->getClient()->post('/chat/completions', [
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'temperature' => 0.2,
        ]);

        if (! $response->successful()) {
            $this->logProviderFailure('openai', 'parseOrderMessage', $response->body());

            throw new RuntimeException($this->mapErrorMessage($response->json('error.message'), $response->status()));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || blank($content)) {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        return $this->validateParsedPayload($this->extractJson($content));
    }

    private function getClient(?string $apiKey = null): PendingRequest
    {
        $key = $apiKey ?? $this->integration->apiKey();

        if (blank($key)) {
            throw new IntegrationException('OpenAI is not connected. Configure it in Integrations.');
        }

        return Http::baseUrl(config('services.openai.base_url'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(config('services.openai.request_timeout'));
    }

    private function mapErrorMessage(?string $message, int $status): string
    {
        $message ??= 'OpenAI request failed.';

        if ($status === 429 || str_contains(strtolower($message), 'quota')) {
            return 'OpenAI quota exceeded. Check billing and credits at platform.openai.com.';
        }

        return $message;
    }
}
