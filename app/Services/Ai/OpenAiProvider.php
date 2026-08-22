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

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>  $tools
     * @return array{content: ?string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->getModel(),
            'messages' => $this->formatMessages($messages),
            'temperature' => 0.2,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = $this->getClient()->post('/chat/completions', $payload);

        if (! $response->successful()) {
            $this->logProviderFailure('openai', 'chat', $response->body());

            throw new RuntimeException($this->mapErrorMessage($response->json('error.message'), $response->status()));
        }

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw new RuntimeException('OpenAI returned an empty chat response.');
        }

        $content = $message['content'] ?? null;
        $content = is_string($content) && filled($content) ? $content : null;

        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $toolCall) {
            if (! is_array($toolCall)) {
                continue;
            }

            $id = (string) ($toolCall['id'] ?? '');
            $name = (string) ($toolCall['function']['name'] ?? '');
            $rawArguments = $toolCall['function']['arguments'] ?? '{}';

            if ($id === '' || $name === '') {
                continue;
            }

            $arguments = is_string($rawArguments)
                ? json_decode($rawArguments, true)
                : (is_array($rawArguments) ? $rawArguments : []);

            if (! is_array($arguments)) {
                $arguments = [];
            }

            $toolCalls[] = [
                'id' => $id,
                'name' => $name,
                'arguments' => $arguments,
            ];
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? '');

            if ($role === 'tool') {
                $formatted[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($message['tool_call_id'] ?? ''),
                    'content' => (string) ($message['content'] ?? ''),
                ];

                continue;
            }

            if ($role === 'assistant' && ! empty($message['tool_calls']) && is_array($message['tool_calls'])) {
                $formatted[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? null,
                    'tool_calls' => collect($message['tool_calls'])
                        ->map(fn (array $call) => [
                            'id' => (string) ($call['id'] ?? ''),
                            'type' => 'function',
                            'function' => [
                                'name' => (string) ($call['name'] ?? ''),
                                'arguments' => json_encode($call['arguments'] ?? []),
                            ],
                        ])
                        ->values()
                        ->all(),
                ];

                continue;
            }

            $formatted[] = [
                'role' => $role,
                'content' => (string) ($message['content'] ?? ''),
            ];
        }

        return $formatted;
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
