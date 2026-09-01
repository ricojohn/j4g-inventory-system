<?php

use App\Jobs\SendFacebookMessage;
use App\Models\Branch;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use App\Models\MessengerOrderDraft;
use App\Models\ProductColorSize;
use App\Models\User;
use App\Services\Facebook\FacebookGraphClient;
use App\Services\Facebook\MessengerConversationService;
use App\Services\Facebook\MessengerOrderDraftService;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    Queue::fake();
});

test('customer confirm explicitly confirms only the current final summary', function () {
    [$draft] = messagingServiceDraftFixture();
    $draft->conversation->page->update(['ai_enabled' => true]);
    $draft->conversation->page->branch->update(['automation_user_id' => userWithRole('Staff')->id]);
    $draft = app(MessengerOrderDraftService::class)->prepareSummary($draft);
    $inbound = $draft->conversation->messages()->create([
        'branch_id' => $draft->branch_id,
        'direction' => 'inbound',
        'sender_type' => 'customer',
        'body' => 'CONFIRM',
        'status' => 'received',
    ]);

    app(MessengerConversationService::class)->handleInbound($draft->conversation->fresh('page'), $inbound);

    expect($draft->fresh()->status)->toBe('converted')
        ->and($draft->fresh()->confirmation_actor_type)->toBe('customer')
        ->and($draft->fresh()->confirmation_message_id)->toBe($inbound->id)
        ->and($draft->fresh()->customer_order_id)->not->toBeNull()
        ->and($draft->conversation->messages()->where('direction', 'outbound')->first()->body)->toContain('Create Order completed');
});

test('send job suppresses an ai reply after human takeover', function () {
    [$draft] = messagingServiceDraftFixture();
    $conversation = $draft->conversation;
    $message = FacebookMessage::query()->create([
        'branch_id' => $draft->branch_id,
        'facebook_conversation_id' => $conversation->id,
        'idempotency_key' => (string) str()->uuid(),
        'direction' => 'outbound',
        'sender_type' => 'ai',
        'body' => 'AI reply',
        'ai_generated' => true,
        'status' => 'pending',
    ]);
    $conversation->update(['control_mode' => 'human']);
    Http::fake();

    app()->call([new SendFacebookMessage($message->id), 'handle']);

    expect($message->fresh()->status)->toBe('suppressed');
    Http::assertNothingSent();
});

/** @return array{MessengerOrderDraft, User, ProductColorSize} */
function messagingServiceDraftFixture(): array
{
    $staff = userWithRole('Staff');
    $branch = $staff->branch;
    $product = createTestProduct(['branch_id' => $branch->id]);
    $cell = createTestCell(5, 0, $product);
    $page = FacebookPage::query()->create(['branch_id' => $branch->id, 'page_id' => 'messaging-page', 'name' => 'J4G', 'status' => 'active']);
    $conversation = FacebookConversation::query()->create(['branch_id' => $branch->id, 'facebook_page_id' => $page->id, 'psid' => 'messaging-psid']);
    $draft = MessengerOrderDraft::query()->create([
        'branch_id' => $branch->id,
        'facebook_conversation_id' => $conversation->id,
        'customer_name' => 'Messenger Customer',
        'psid' => $conversation->psid,
        'fulfillment_method' => 'pickup',
        'payment_method_preference' => 'cash',
    ]);
    $draft->items()->create(['product_color_size_id' => $cell->id, 'quantity' => 2, 'unit_price' => 100]);

    return [$draft->fresh('conversation.page'), $staff, $cell];
}

test('graph client sends a bounded text response using the encrypted page token', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['recipient_id' => 'psid-1', 'message_id' => 'mid.sent'], 200)]);
    $branch = Branch::query()->firstOrFail();
    $page = FacebookPage::query()->create([
        'branch_id' => $branch->id,
        'page_id' => 'page-graph',
        'name' => 'Graph Page',
        'status' => 'active',
        'access_token' => 'secret-page-token',
        'graph_api_version' => 'v23.0',
    ]);

    $result = app(FacebookGraphClient::class)->sendText($page, 'psid-1', 'Hello');

    expect($result['message_id'])->toBe('mid.sent');
    Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v23.0/me/messages'
        && $request['recipient']['id'] === 'psid-1'
        && $request['message']['text'] === 'Hello'
        && $request->hasHeader('Authorization', 'Bearer secret-page-token'));
});
