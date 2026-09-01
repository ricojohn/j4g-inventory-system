<?php

use App\Jobs\ProcessFacebookWebhookEvent;
use App\Models\Branch;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use App\Models\FacebookWebhookEvent;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.facebook.app_secret', 'test-app-secret');
    config()->set('services.facebook.verify_token', 'test-verify-token');
});

test('meta can verify the webhook challenge', function () {
    $this->get('/api/webhooks/facebook?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=challenge-123')
        ->assertOk()
        ->assertSeeText('challenge-123');

    $this->get('/api/webhooks/facebook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=nope')
        ->assertForbidden();
});

test('webhook post requires a valid sha256 signature', function () {
    $body = json_encode(['object' => 'page', 'entry' => []], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/webhooks/facebook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=wrong',
    ], $body)->assertUnauthorized();
});

test('valid webhook is stored once and dispatches one job', function () {
    Queue::fake();
    $page = createFacebookPage();
    $body = webhookBody($page->page_id);

    signedFacebookPost($this, $body)->assertOk()->assertJson(['accepted' => 1]);
    signedFacebookPost($this, $body)->assertOk()->assertJson(['accepted' => 0]);

    expect(FacebookWebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessFacebookWebhookEvent::class, 1);
});

test('queued processing creates one conversation and inbound message idempotently', function () {
    $page = createFacebookPage();
    $body = webhookBody($page->page_id);
    signedFacebookPost($this, $body)->assertOk();

    $event = FacebookWebhookEvent::query()->firstOrFail();
    app()->call([new ProcessFacebookWebhookEvent($event->id), 'handle']);

    expect(FacebookConversation::query()->count())->toBe(1)
        ->and(FacebookMessage::query()->count())->toBe(1)
        ->and($event->fresh()->status)->toBe('processed')
        ->and(FacebookMessage::query()->first()->body)->toBe('Hello');
});

function createFacebookPage(): FacebookPage
{
    $branch = Branch::query()->firstOrFail();

    return FacebookPage::query()->create([
        'branch_id' => $branch->id,
        'page_id' => '123456789',
        'name' => 'J4G Main',
        'status' => 'active',
    ]);
}

function webhookBody(string $pageId): string
{
    return json_encode([
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => 1788235200000,
            'messaging' => [[
                'sender' => ['id' => 'psid-100'],
                'recipient' => ['id' => $pageId],
                'timestamp' => 1788235200000,
                'message' => ['mid' => 'mid.100', 'text' => 'Hello'],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR);
}

function signedFacebookPost($testCase, string $body)
{
    return $testCase->call('POST', '/api/webhooks/facebook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-app-secret'),
    ], $body);
}
