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

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>  $tools
     * @return array{content: ?string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    public function chat(array $messages, array $tools = []): array
    {
        $key = $this->integration->apiKey();

        if (blank($key)) {
            throw new IntegrationException('Google Gemini is not connected. Configure it in Integrations.');
        }

        $model = $this->getModel();
        $url = $this->endpoint($model, $key);

        [$systemInstruction, $contents] = $this->formatMessages($messages);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        if ($tools !== []) {
            $payload['tools'] = [
                [
                    'functionDeclarations' => collect($tools)
                        ->map(fn (array $tool) => [
                            'name' => $tool['function']['name'],
                            'description' => $tool['function']['description'],
                            'parameters' => $tool['function']['parameters'],
                        ])
                        ->values()
                        ->all(),
                ],
            ];
        }

        $response = Http::acceptJson()
            ->timeout(config('services.gemini.request_timeout'))
            ->post($url, $payload);

        if (! $response->successful()) {
            $this->logProviderFailure('gemini', 'chat', $response->body());

            throw new RuntimeException($this->mapErrorMessage($response->json(), $response->status()));
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts) || $parts === []) {
            throw new RuntimeException('Google Gemini returned an empty chat response.');
        }

        $contentParts = [];
        $toolCalls = [];

        foreach ($parts as $index => $part) {
            if (! is_array($part)) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text']) && filled($part['text'])) {
                $contentParts[] = $part['text'];
            }

            $functionCall = $part['functionCall'] ?? null;

            if (! is_array($functionCall)) {
                continue;
            }

            $name = (string) ($functionCall['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $arguments = $functionCall['args'] ?? [];

            if (! is_array($arguments)) {
                $arguments = [];
            }

            $toolCalls[] = [
                'id' => 'gemini_'.$index.'_'.$name,
                'name' => $name,
                'arguments' => $arguments,
            ];
        }

        $content = $contentParts === [] ? null : implode("\n", $contentParts);

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{0: ?string, 1: list<array<string, mixed>>}
     */
    private function formatMessages(array $messages): array
    {
        $systemInstruction = null;
        $contents = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? '');

            if ($role === 'system') {
                $systemInstruction = (string) ($message['content'] ?? '');

                continue;
            }

            if ($role === 'user') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        ['text' => (string) ($message['content'] ?? '')],
                    ],
                ];

                continue;
            }

            if ($role === 'assistant') {
                $parts = [];

                if (filled($message['content'] ?? null)) {
                    $parts[] = ['text' => (string) $message['content']];
                }

                foreach ($message['tool_calls'] ?? [] as $call) {
                    if (! is_array($call)) {
                        continue;
                    }

                    $arguments = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

                    $parts[] = [
                        'functionCall' => [
                            'name' => (string) ($call['name'] ?? ''),
                            // Gemini Struct fields must be JSON objects; PHP [] encodes as a list.
                            'args' => $this->asGeminiObject($arguments),
                        ],
                    ];
                }

                if ($parts !== []) {
                    $contents[] = [
                        'role' => 'model',
                        'parts' => $parts,
                    ];
                }

                continue;
            }

            if ($role === 'tool') {
                $responsePayload = json_decode((string) ($message['content'] ?? '{}'), true);

                if (! is_array($responsePayload)) {
                    $responsePayload = ['result' => (string) ($message['content'] ?? '')];
                }

                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => (string) ($message['name'] ?? ''),
                                'response' => $this->asGeminiObject($responsePayload),
                            ],
                        ],
                    ],
                ];
            }
        }

        return [$systemInstruction, $contents];
    }

    /**
     * Gemini protobuf Struct fields must serialize as JSON objects, never arrays.
     *
     * @param  array<string, mixed>  $data
     */
    private function asGeminiObject(array $data): object
    {
        return (object) $data;
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
