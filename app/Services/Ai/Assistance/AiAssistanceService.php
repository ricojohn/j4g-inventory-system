<?php

namespace App\Services\Ai\Assistance;

use App\Exceptions\IntegrationException;
use App\Services\Ai\AiProviderManager;
use RuntimeException;

class AiAssistanceService
{
    public function __construct(
        private AiProviderManager $aiProviderManager,
        private AiAssistanceToolRegistry $toolRegistry,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{answer: string, tool_trace: list<array{name: string, arguments: array<string, mixed>}>, rows: list<array<string, mixed>>}
     */
    public function ask(string $message, array $history = []): array
    {
        $provider = $this->aiProviderManager->getDefaultProvider();

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
        ];

        foreach (array_slice($history, -10) as $turn) {
            $role = (string) ($turn['role'] ?? '');
            $content = (string) ($turn['content'] ?? '');

            if (! in_array($role, ['user', 'assistant'], true) || blank($content)) {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $tools = $this->toolRegistry->schemas();
        $toolTrace = [];
        $exportRows = [];

        for ($round = 0; $round < AiAssistanceToolRegistry::MAX_ROUNDS; $round++) {
            $response = $provider->chat($messages, $tools);
            $toolCalls = $response['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $answer = trim((string) ($response['content'] ?? ''));

                if ($answer === '') {
                    throw new RuntimeException('AI returned an empty response.');
                }

                return [
                    'answer' => $answer,
                    'tool_trace' => $toolTrace,
                    'rows' => $exportRows,
                ];
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $response['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name = (string) ($call['name'] ?? '');
                $arguments = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

                try {
                    $result = $this->toolRegistry->execute($name, $arguments);
                } catch (\Throwable $exception) {
                    $result = [
                        'error' => $exception->getMessage(),
                    ];
                }

                $toolTrace[] = [
                    'name' => $name,
                    'arguments' => $arguments,
                ];

                if (isset($result['rows']) && is_array($result['rows']) && $result['rows'] !== []) {
                    $exportRows = array_values(array_filter(
                        $result['rows'],
                        fn ($row) => is_array($row)
                    ));
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($call['id'] ?? ''),
                    'name' => $name,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        throw new RuntimeException('AI assistance exceeded the maximum tool-call rounds. Try a narrower question.');
    }

    public function hasConnectedProvider(): bool
    {
        try {
            $this->aiProviderManager->getDefaultProvider();

            return true;
        } catch (IntegrationException) {
            return false;
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the AI Assistance module for the J4G Printing inventory and operations system.

Rules:
- Answer ONLY questions about this system's business data (inventory, orders, customers, finance, production, supplier POs).
- Use the provided tools to fetch live data before stating numbers or lists.
- Ground every factual claim in tool results. Never invent stock counts, amounts, customers, or orders.
- If the user asks something unrelated to this system, politely refuse and explain you can only help with J4G ops data.
- Prefer clear Markdown: short summary first, then bullet lists or tables when useful.
- Keep answers concise and operational. Do not reveal API keys, credentials, or internal implementation details.
- Do not claim you can create, update, delete, or fulfill records — you are read-only.
PROMPT;
    }
}
