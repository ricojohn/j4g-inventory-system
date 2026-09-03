<?php

use App\Models\Branch;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use App\Models\MessengerOrderDraft;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('messenger inbox renders a three-column layout for staff', function () {
    $staff = userWithRole('Staff');
    $branch = $staff->branch;
    $page = FacebookPage::query()->create([
        'branch_id' => $branch->id,
        'page_id' => 'page-1',
        'name' => 'J4G Messenger',
        'status' => 'active',
        'ai_enabled' => true,
    ]);
    $conversation = FacebookConversation::query()->create([
        'branch_id' => $branch->id,
        'facebook_page_id' => $page->id,
        'psid' => 'psid-1',
        'state' => 'collecting',
        'control_mode' => 'ai',
    ]);
    $conversation->messages()->create([
        'branch_id' => $branch->id,
        'idempotency_key' => (string) str()->uuid(),
        'direction' => 'inbound',
        'sender_type' => 'customer',
        'message_type' => 'message',
        'body' => 'Hello',
        'status' => 'received',
    ]);
    $conversation->draft()->create([
        'branch_id' => $branch->id,
        'psid' => 'psid-1',
        'customer_name' => 'Mary',
        'fulfillment_method' => 'pickup',
        'payment_method_preference' => 'cash',
    ]);

    $this->actingAs($staff)
        ->get(route('messenger.show', $conversation))
        ->assertOk()
        ->assertSee('Facebook Messenger')
        ->assertSee('data-messenger-inbox', false)
        ->assertSee('Take Over')
        ->assertSee('Order status')
        ->assertSee('AI order summary');
});

test('messenger snapshot returns the selected conversation state for live refreshes', function () {
    $staff = userWithRole('Staff');
    $branch = $staff->branch;
    $page = FacebookPage::query()->create([
        'branch_id' => $branch->id,
        'page_id' => 'page-2',
        'name' => 'J4G Messenger',
        'status' => 'active',
        'ai_enabled' => true,
    ]);
    $conversation = FacebookConversation::query()->create([
        'branch_id' => $branch->id,
        'facebook_page_id' => $page->id,
        'psid' => 'psid-2',
        'state' => 'collecting',
        'control_mode' => 'human',
    ]);
    $conversation->messages()->create([
        'branch_id' => $branch->id,
        'idempotency_key' => (string) str()->uuid(),
        'direction' => 'outbound',
        'sender_type' => 'staff',
        'message_type' => 'message',
        'body' => 'Reply',
        'status' => 'sent',
    ]);

    $this->actingAs($staff)
        ->getJson(route('messenger.snapshot', $conversation))
        ->assertOk()
        ->assertJsonPath('selectedConversation.id', $conversation->id)
        ->assertJsonPath('selectedConversation.control_mode', 'human')
        ->assertJsonPath('selectedConversation.messages.0.body', 'Reply');
});
