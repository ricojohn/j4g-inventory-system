<?php

namespace App\Services\Facebook;

use App\Events\MessengerConversationUpdated;
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
        private CreateMessengerOrderService $createOrderService,
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
                $automationUser = $conversation->page->branch->automationUser;
                if (! $automationUser) {
                    $this->queueReply($conversation, 'Thank you. Your final summary is confirmed. A staff member will complete Create Order.');
                    $this->broadcastConversationUpdated($conversation->fresh());

                    return;
                }

                try {
                    $order = $this->createOrderService->execute($draft->fresh(), $automationUser);
                    $this->queueReply($conversation, "Create Order completed. Your order number is {$order->order_number}.");
                } catch (Throwable $exception) {
                    $this->queueReply($conversation, 'Your confirmation was received, but Create Order could not complete: '.$exception->getMessage());
                }
                $this->broadcastConversationUpdated($conversation->fresh());
            } else {
                $this->queueReply($conversation, 'That summary expired. We need to review availability and send a new final summary.');
                $this->broadcastConversationUpdated($conversation->fresh());
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
            $this->broadcastConversationUpdated($conversation->fresh());
        } catch (Throwable) {
            $this->queueReply($conversation, 'I could not safely match those order details. A staff member will review the conversation.');
            $this->broadcastConversationUpdated($conversation->fresh());
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
        DB::afterCommit(fn () => $this->broadcastConversationUpdated($conversation->fresh()));

        return $message;
    }

    private function broadcastConversationUpdated(FacebookConversation $conversation): void
    {
        $conversation->loadMissing('page', 'assignedUser', 'draft');
        $conversation->loadCount(['messages as unread_message_count' => fn ($query) => $query->where('direction', 'inbound')->whereNull('read_at')]);

        broadcast(new MessengerConversationUpdated([
            'conversation' => [
                'id' => $conversation->id,
                'branch_id' => $conversation->branch_id,
                'psid' => $conversation->psid,
                'page_name' => $conversation->page->name,
                'control_mode' => $conversation->control_mode,
                'state' => $conversation->state,
                'assigned_user_name' => $conversation->assignedUser?->name,
                'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
                'last_outbound_at' => $conversation->last_outbound_at?->toIso8601String(),
                'unread_message_count' => (int) ($conversation->unread_message_count ?? 0),
                'draft_status' => $conversation->draft?->status,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ],
        ]));
    }
}
