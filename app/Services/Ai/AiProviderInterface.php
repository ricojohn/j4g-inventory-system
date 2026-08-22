<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function getProviderName(): string;

    public function getModel(): string;

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(?string $apiKey = null): array;

    /**
     * @return array<string, mixed>
     */
    public function parseOrderMessage(string $message): array;

    /**
     * Multi-turn chat with optional tool calling.
     *
     * Neutral message roles: system, user, assistant, tool.
     * Assistant messages may include tool_calls; tool messages need tool_call_id and name.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>  $tools
     * @return array{content: ?string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    public function chat(array $messages, array $tools = []): array;
}
