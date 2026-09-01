<?php

namespace App\Services\Facebook;

use App\Jobs\SendFacebookMessage;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\MessengerOrderDraft;
use App\Models\ProductColorSize;
use App\Services\Ai\AiProviderManager;
use App\Services\AiOrderDraftService;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessengerConversationService
{
    public function __construct(
        private AiProviderManager $aiProviderManager,
        private AiOrderDraftService $aiOrderDraftService,
        private MessengerOrderDraftService $draftService,
    ) {}

    public function handleInbound(FacebookConversation $conversation, FacebookMessage $message): void
    {
        $conversation->refresh();
        if ($conversation->control_mode !== 'ai' || ! $conversation->page->ai_enabled || blank($message->body)) {
            return;
        }

        $draft = MessengerOrderDraft::query()->firstOrCreate(
            ['facebook_conversation_id' => $conversation->id],
            ['branch_id' => $conversation->branch_id, 'psid' => $conversation->psid],
        );

        if ($this->isExplicitConfirmation((string) $message->body) && $draft->status === 'awaiting_confirmation') {
            if ($draft->confirmation_expires_at?->isFuture()) {
                $draft->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_actor_type' => 'customer', 'confirmation_message_id' => $message->id]);
                $this->queueReply($conversation, 'Thank you. Your final summary is confirmed. A staff member can now use Create Order.');
            } else {
                $this->queueReply($conversation, 'That summary expired. We need to review availability and send a new final summary.');
            }

            return;
        }

        try {
            $parsed = $this->aiProviderManager->getDefaultProvider()->parseOrderMessage((string) $message->body);
            $this->applyParsedData($draft, $parsed);
            $draft = $draft->fresh('items.cell');

            if ($this->isComplete($draft)) {
                $draft = $this->draftService->prepareSummary($draft);
                $this->queueReply($conversation, $draft->summary_text."\n\nReply CONFIRM only if this exact summary is correct.");
            } else {
                $this->queueReply($conversation, $this->nextQuestion($draft));
            }
        } catch (Throwable) {
            $this->queueReply($conversation, 'I could not safely match those order details. A staff member will review the conversation.');
        }
    }

    /** @param array<string, mixed> $parsed */
    private function applyParsedData(MessengerOrderDraft $draft, array $parsed): void
    {
        DB::transaction(function () use ($draft, $parsed): void {
            $updates = [];
            foreach (['customer_name', 'fulfillment_method', 'delivery_address', 'payment_method_preference'] as $field) {
                if (filled($parsed[$field] ?? null)) {
                    $updates[$field] = $parsed[$field];
                }
            }
            if ($updates !== [] || ($parsed['items'] ?? []) !== []) {
                $this->draftService->invalidateSummary($draft);
                $draft->refresh()->update($updates);
            }

            if (($parsed['items'] ?? []) !== []) {
                $matched = $this->aiOrderDraftService->matchParsedItemsToInventory($parsed);
                foreach ($matched['items'] as $item) {
                    if (! $item['matched'] || ! $item['cell_id'] || ($item['parsed']['quantity'] ?? 0) < 1) {
                        continue;
                    }
                    $cell = ProductColorSize::query()->with('color.product')->find($item['cell_id']);
                    if (! $cell || $cell->color->product->branch_id !== $draft->branch_id) {
                        continue;
                    }
                    $draft->items()->updateOrCreate(
                        ['product_color_size_id' => $cell->id],
                        ['quantity' => $item['parsed']['quantity']],
                    );
                }
            }
        });
    }

    private function isComplete(MessengerOrderDraft $draft): bool
    {
        return filled($draft->customer_name)
            && in_array($draft->fulfillment_method, ['delivery', 'pickup'], true)
            && ($draft->fulfillment_method === 'pickup' || filled($draft->delivery_address))
            && filled($draft->payment_method_preference)
            && $draft->items()->exists();
    }

    private function nextQuestion(MessengerOrderDraft $draft): string
    {
        return match (true) {
            blank($draft->customer_name) => 'May I have your full name for the order?',
            ! $draft->items()->exists() => 'Which product, color, size, and quantity would you like?',
            blank($draft->fulfillment_method) => 'Would you like delivery or pickup?',
            $draft->fulfillment_method === 'delivery' && blank($draft->delivery_address) => 'What is the complete delivery address?',
            blank($draft->payment_method_preference) => 'What payment method do you prefer?',
            default => 'A staff member needs to review one or more product details before we continue.',
        };
    }

    private function isExplicitConfirmation(string $body): bool
    {
        return in_array(str($body)->trim()->upper()->toString(), ['CONFIRM', 'I CONFIRM'], true);
    }

    public function queueReply(FacebookConversation $conversation, string $body, bool $aiGenerated = true, ?int $userId = null): FacebookMessage
    {
        $message = $conversation->messages()->create([
            'branch_id' => $conversation->branch_id,
            'idempotency_key' => (string) str()->uuid(),
            'direction' => 'outbound',
            'sender_type' => $aiGenerated ? 'ai' : 'staff',
            'body' => $body,
            'ai_generated' => $aiGenerated,
            'status' => 'pending',
        ]);
        DB::afterCommit(fn () => SendFacebookMessage::dispatch($message->id));

        return $message;
    }
}
