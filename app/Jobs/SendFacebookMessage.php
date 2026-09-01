<?php

namespace App\Jobs;

use App\Models\FacebookMessage;
use App\Services\Facebook\FacebookGraphClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    }

    public function failed(Throwable $exception): void
    {
        FacebookMessage::query()->whereKey($this->messageId)->update(['status' => 'failed', 'error_message' => str($exception->getMessage())->limit(2000)]);
    }
}
