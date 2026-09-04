<?php

namespace App\Services\Facebook;

use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use Carbon\CarbonImmutable;

class MessengerSyncService
{
    public function __construct(private FacebookGraphClient $client) {}

    public function syncPage(FacebookPage $page): array
    {
        $page = $page->fresh();
        if (! $page || $page->status !== 'active') {
            return ['pages' => 0, 'conversations' => 0, 'messages' => 0];
        }

        $conversationCount = 0;
        $messageCount = 0;

        $conversationCursor = null;

        do {
            $conversationPayload = $this->client->listConversations($page, 25, $conversationCursor);
            $collection = collect($conversationPayload['data'] ?? []);

            foreach ($collection as $remoteConversation) {
                if (! is_array($remoteConversation) || blank(data_get($remoteConversation, 'id'))) {
                    continue;
                }

                $conversationCount++;
                $messageCount += $this->syncConversation($page, (string) data_get($remoteConversation, 'id'));
            }

            $conversationCursor = data_get($conversationPayload, 'paging.cursors.after');
        } while (filled($conversationCursor));

        return ['pages' => 1, 'conversations' => $conversationCount, 'messages' => $messageCount];
    }

    public function syncConversation(FacebookPage $page, string $conversationId): int
    {
        $messagesSynced = 0;
        $messageCursor = null;
        $conversation = null;

        do {
            $payload = $this->client->listConversationMessages($page, $conversationId, 50, $messageCursor);
            $rows = collect(data_get($payload, 'messages.data', data_get($payload, 'data', [])))->reverse();

            foreach ($rows as $remoteMessage) {
                if (! is_array($remoteMessage)) {
                    continue;
                }

                $messageId = (string) data_get($remoteMessage, 'id');
                if (blank($messageId)) {
                    continue;
                }

                $messagePayload = $this->client->getMessage($page, $messageId);
                $messageCounted = $this->syncMessage($page, $conversation, $messagePayload, $conversationId);
                $messagesSynced += $messageCounted['messages'];
                $conversation ??= $messageCounted['conversation'];
            }

            $messageCursor = data_get($payload, 'paging.cursors.after');
        } while (filled($messageCursor));

        if ($conversation) {
            $conversation->update([
                'last_inbound_at' => $conversation->messages()->where('direction', 'inbound')->latest('created_at')->value('created_at') ?? $conversation->last_inbound_at,
                'last_outbound_at' => $conversation->messages()->where('direction', 'outbound')->latest('created_at')->value('created_at') ?? $conversation->last_outbound_at,
            ]);
        }

        return $messagesSynced;
    }

    /** @return array{conversation: ?FacebookConversation, messages: int} */
    private function syncMessage(FacebookPage $page, ?FacebookConversation $conversation, array $remoteMessage, string $conversationId): array
    {
        $metaMessageId = (string) data_get($remoteMessage, 'id');
        if (blank($metaMessageId)) {
            return ['conversation' => $conversation, 'messages' => 0];
        }

        $fromId = (string) data_get($remoteMessage, 'from.id');
        $toIds = collect(data_get($remoteMessage, 'to.data', []))->pluck('id')->filter()->values();
        $direction = $fromId === $page->page_id ? 'outbound' : 'inbound';
        $psid = $direction === 'inbound'
            ? $fromId
            : (string) ($toIds->first() ?: data_get($remoteMessage, 'to.id') ?: data_get($remoteMessage, 'recipient.id'));

        if (blank($psid)) {
            return ['conversation' => $conversation, 'messages' => 0];
        }

        $conversation ??= FacebookConversation::query()->firstOrCreate(
            ['facebook_page_id' => $page->id, 'psid' => $psid],
            ['branch_id' => $page->branch_id],
        );

        $remoteName = data_get($remoteMessage, 'from.name') ?? data_get($remoteMessage, 'sender.name');
        if ($direction === 'inbound' && filled($remoteName) && blank($conversation->customer_name)) {
            $conversation->update(['customer_name' => str($remoteName)->limit(255)->toString()]);
        }

        $createdAt = filled(data_get($remoteMessage, 'created_time'))
            ? CarbonImmutable::parse((string) data_get($remoteMessage, 'created_time'))
            : now();
        $body = data_get($remoteMessage, 'message')
            ?? data_get($remoteMessage, 'story')
            ?? data_get($remoteMessage, 'postback.payload')
            ?? null;
        $attachments = data_get($remoteMessage, 'attachments');

        $message = FacebookMessage::query()->firstOrCreate(
            ['facebook_conversation_id' => $conversation->id, 'meta_message_id' => $metaMessageId],
            [
                'branch_id' => $page->branch_id,
                'direction' => $direction,
                'sender_type' => $direction === 'inbound' ? 'customer' : 'staff',
                'message_type' => filled($body) ? 'text' : ($attachments ? 'attachment' : 'message'),
                'body' => is_string($body) ? $body : (is_null($body) ? null : json_encode($body, JSON_UNESCAPED_UNICODE)),
                'attachments' => $attachments,
                'status' => 'received',
                'sent_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        );

        // Re-syncing corrects timestamps on rows imported before timestamp support was added.
        if (! $message->wasRecentlyCreated) {
            $message->update([
                'sent_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => now(),
            ]);
        }

        if ($message->wasRecentlyCreated) {
            $conversation->forceFill([
                'last_inbound_at' => $direction === 'inbound' ? $createdAt : $conversation->last_inbound_at,
                'last_outbound_at' => $direction === 'outbound' ? $createdAt : $conversation->last_outbound_at,
            ])->save();

            return ['conversation' => $conversation, 'messages' => 1];
        }

        return ['conversation' => $conversation, 'messages' => 0];
    }
}
