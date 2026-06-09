<?php

namespace App\Services\Ai;

use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractAiProvider implements AiProviderInterface
{
    public function __construct(protected Integration $integration) {}

    public function getProviderName(): string
    {
        return $this->integration->name;
    }

    public function getModel(): string
    {
        return $this->integration->defaultModel();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateParsedPayload(array $parsed): array
    {
        $intent = $parsed['intent'] ?? 'unclear';

        if (! in_array($intent, ['create_order', 'inquiry', 'unclear'], true)) {
            throw new InvalidArgumentException('Invalid intent from AI provider response.');
        }

        $items = $parsed['items'] ?? [];

        if (! is_array($items)) {
            throw new InvalidArgumentException('Invalid items from AI provider response.');
        }

        $normalizedItems = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalizedItems[] = [
                'product_name' => isset($item['product_name']) ? (string) $item['product_name'] : '',
                'color_name' => isset($item['color_name']) ? (string) $item['color_name'] : '',
                'size_name' => isset($item['size_name']) ? (string) $item['size_name'] : '',
                'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
                'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
            ];

            if (blank($normalizedItems[$index]['product_name'])) {
                $parsed['missing_fields'][] = "items[{$index}].product_name";
            }

            if (blank($normalizedItems[$index]['color_name'])) {
                $parsed['missing_fields'][] = "items[{$index}].color_name";
            }

            if (blank($normalizedItems[$index]['size_name'])) {
                $parsed['missing_fields'][] = "items[{$index}].size_name";
            }

            if ($normalizedItems[$index]['quantity'] <= 0) {
                $parsed['missing_fields'][] = "items[{$index}].quantity";
            }
        }

        $missingFields = collect($parsed['missing_fields'] ?? [])
            ->filter(fn ($field) => is_string($field) && filled($field))
            ->unique()
            ->values()
            ->all();

        $confidence = (float) ($parsed['confidence'] ?? 0);
        $confidence = max(0, min(1, $confidence));

        return [
            'intent' => $intent,
            'customer_name' => filled($parsed['customer_name'] ?? null) ? (string) $parsed['customer_name'] : null,
            'customer_contact' => filled($parsed['customer_contact'] ?? null) ? (string) $parsed['customer_contact'] : null,
            'customer_source' => filled($parsed['customer_source'] ?? null) ? (string) $parsed['customer_source'] : 'facebook',
            'items' => $normalizedItems,
            'deadline' => filled($parsed['deadline'] ?? null) ? (string) $parsed['deadline'] : null,
            'notes' => filled($parsed['notes'] ?? null) ? (string) $parsed['notes'] : null,
            'missing_fields' => $missingFields,
            'confidence' => $confidence,
        ];
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract structured order data from customer messages for a print shop inventory system in the Philippines.
Return ONLY valid JSON matching this exact schema:
{
  "intent": "create_order|inquiry|unclear",
  "customer_name": null,
  "customer_contact": null,
  "customer_source": "facebook",
  "items": [
    {
      "product_name": "string",
      "color_name": "string",
      "size_name": "string",
      "quantity": 0,
      "notes": null
    }
  ],
  "deadline": null,
  "notes": null,
  "missing_fields": [],
  "confidence": 0.0
}

Rules:
- intent is create_order when the customer wants to order products.
- customer_source should be facebook unless clearly another channel (instagram, viber, whatsapp, walk_in, referral, other).
- items must list every distinct product/color/size/qty mentioned.
- quantity must be a positive integer when known; use missing_fields if unknown.
- If product_name, color_name, size_name, or quantity is missing or ambiguous, add to missing_fields (e.g. "items[0].size_name").
- confidence is 0.0 to 1.0 reflecting extraction certainty.
- Normalize Filipino/Tagalog order phrases (e.g. "pa order po", "need sa Friday").
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractJson(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $parsed = json_decode($content, true);

        if (! is_array($parsed)) {
            throw new RuntimeException('AI provider returned invalid JSON.');
        }

        return $parsed;
    }

    protected function logProviderFailure(string $provider, string $context, mixed $responseBody): void
    {
        $safeBody = is_string($responseBody)
            ? preg_replace('/sk-[A-Za-z0-9_-]+/', '[REDACTED]', $responseBody)
            : json_encode($responseBody);

        Log::warning("AI provider {$provider} {$context} failed.", [
            'provider' => $provider,
            'response' => $safeBody,
        ]);
    }
}
