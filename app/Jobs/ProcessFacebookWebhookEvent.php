<?php

namespace App\Jobs;

use App\Events\MessengerConversationUpdated;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\FacebookWebhookEvent;
use App\Services\Facebook\MessengerConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessFacebookWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120, 300];

    public function __construct(public int $eventId)
    {
        $this->afterCommit();
    }

    public function handle(MessengerConversationService $conversationService): void
    {
        $processed = DB::transaction(function (): ?array {
            $event = FacebookWebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);

            if ($event->status === 'processed') {
                return null;
            }

            $event->increment('attempts');

            if (! in_array($event->event_type, ['message', 'postback'], true) || blank($event->sender_psid)) {
                $event->update(['status' => 'processed', 'processed_at' => now()]);

                return null;
            }

            $conversation = FacebookConversation::query()->firstOrCreate(
                ['facebook_page_id' => $event->facebook_page_id, 'psid' => $event->sender_psid],
                ['branch_id' => $event->branch_id],
            );

            $payload = $event->payload;
            $messageId = data_get($payload, 'message.mid') ?? data_get($payload, 'postback.mid');
            $body = data_get($payload, 'message.text') ?? data_get($payload, 'postback.payload');

            $message = FacebookMessage::query()->firstOrCreate(
                ['facebook_webhook_event_id' => $event->id],
                [
                    'branch_id' => $event->branch_id,
                    'facebook_conversation_id' => $conversation->id,
                    'meta_message_id' => $messageId,
                    'direction' => 'inbound',
                    'sender_type' => 'customer',
                    'message_type' => $event->event_type,
                    'body' => $body,
                    'attachments' => data_get($payload, 'message.attachments'),
                    'status' => 'received',
                ],
            );

            $conversation->update(['last_inbound_at' => $event->meta_timestamp ?? now()]);
            $event->update(['status' => 'processed', 'processed_at' => now(), 'failed_at' => null, 'error_message' => null]);

            return [$conversation->id, $message->id];
        });

        if ($processed) {
            $conversation = FacebookConversation::query()->with('page.branch.automationUser')->findOrFail($processed[0]);
            $message = FacebookMessage::query()->findOrFail($processed[1]);
            $conversationService->handleInbound($conversation, $message);

            $conversation->loadMissing('page', 'assignedUser', 'draft');
            $conversation->loadCount(['messages as unread_message_count' => fn ($query) => $query->where('direction', 'inbound')]);
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

    public function failed(Throwable $exception): void
    {
        FacebookWebhookEvent::query()->whereKey($this->eventId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => str($exception->getMessage())->limit(2000),
        ]);
    }
}
