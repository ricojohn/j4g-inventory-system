<?php

namespace App\Services\Facebook;

use App\Jobs\ProcessFacebookWebhookEvent;
use App\Models\FacebookPage;
use App\Models\FacebookWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FacebookWebhookIngestService
{
    public function __construct(private FacebookWebhookEventKey $eventKey) {}

    /** @param array<string, mixed> $payload */
    public function ingest(array $payload): int
    {
        $acceptedIds = [];

        DB::transaction(function () use ($payload, &$acceptedIds): void {
            foreach ($payload['entry'] as $entry) {
                $page = FacebookPage::query()->where('page_id', (string) $entry['id'])->where('status', 'active')->first();

                if (! $page) {
                    continue;
                }

                foreach ($entry['messaging'] ?? [] as $event) {
                    if (! is_array($event)) {
                        continue;
                    }

                    $key = $this->eventKey->for($event);
                    $webhookEvent = FacebookWebhookEvent::query()->firstOrCreate(
                        ['facebook_page_id' => $page->id, 'event_key' => $key],
                        [
                            'branch_id' => $page->branch_id,
                            'event_type' => $this->eventType($event),
                            'sender_psid' => data_get($event, 'message.is_echo')
                                ? (data_get($event, 'recipient.id') ?? data_get($event, 'sender.id'))
                                : data_get($event, 'sender.id'),
                            'meta_timestamp' => isset($event['timestamp'])
                                ? CarbonImmutable::createFromTimestampMs((int) $event['timestamp'])
                                : null,
                            'payload' => $event,
                        ],
                    );

                    if ($webhookEvent->wasRecentlyCreated) {
                        $acceptedIds[] = $webhookEvent->id;
                    }
                }
            }

            DB::afterCommit(function () use (&$acceptedIds): void {
                foreach ($acceptedIds as $eventId) {
                    ProcessFacebookWebhookEvent::dispatch($eventId);
                }
            });
        });

        return count($acceptedIds);
    }

    /** @param array<string, mixed> $event */
    private function eventType(array $event): string
    {
        return match (true) {
            isset($event['message']) => data_get($event, 'message.is_echo') ? 'message_echo' : 'message',
            isset($event['postback']) => 'postback',
            isset($event['delivery']) => 'delivery',
            isset($event['read']) => 'read',
            default => 'unsupported',
        };
    }
}
