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
}
