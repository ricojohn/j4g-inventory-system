<?php

namespace App\Jobs;

use App\Events\MessengerConversationUpdated;
use App\Models\FacebookMessage;
use App\Services\Facebook\FacebookGraphClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendFacebookMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $messageId)
    {
        $this->afterCommit();
    }

    public function handle(FacebookGraphClient $client): void
    {
        $message = FacebookMessage::query()->with('conversation.page')->findOrFail($this->messageId);

        if ($message->status !== 'pending') {
            return;
        }

        if ($message->ai_generated && $message->conversation->control_mode !== 'ai') {
            $message->update(['status' => 'suppressed', 'error_message' => 'Human takeover']);

            return;
        }

        $result = $client->sendText($message->conversation->page, $message->conversation->psid, (string) $message->body);
        $message->update(['status' => 'sent', 'meta_message_id' => $result['message_id'] ?? null, 'sent_at' => now(), 'error_message' => null]);
        $message->conversation->update(['last_outbound_at' => now()]);
        DB::afterCommit(function () use ($message): void {
            $conversation = $message->conversation()->with('page', 'assignedUser', 'draft')->first();
            if (! $conversation) {
                return;
            }
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
        });
    }

    public function failed(Throwable $exception): void
    {
        FacebookMessage::query()->whereKey($this->messageId)->update(['status' => 'failed', 'error_message' => str($exception->getMessage())->limit(2000)]);
    }
}
