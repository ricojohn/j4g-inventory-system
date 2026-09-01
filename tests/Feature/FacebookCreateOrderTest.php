<?php

use App\Models\Branch;
use App\Models\CustomerOrder;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use App\Models\MessengerOrderDraft;
use App\Models\ProductColorSize;
use App\Models\User;
use App\Services\Facebook\CreateMessengerOrderService;
use App\Services\Facebook\MessengerOrderDraftService;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    Queue::fake();
});

test('create order is rejected without an explicitly confirmed final summary', function () {
    [$draft, $staff] = messengerDraftFixture();

    expect(fn () => app(CreateMessengerOrderService::class)->execute($draft, $staff))
        ->toThrow(RuntimeException::class, 'requires explicit confirmation');

    expect(CustomerOrder::query()->count())->toBe(0);
});

test('confirmed create order reserves stock once and is idempotent', function () {
    [$draft, $staff, $cell] = messengerDraftFixture();
    $draft = app(MessengerOrderDraftService::class)->prepareSummary($draft);
    $draft->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_actor_type' => 'staff', 'confirmed_by_user_id' => $staff->id]);

    $first = app(CreateMessengerOrderService::class)->execute($draft->fresh(), $staff);
    $second = app(CreateMessengerOrderService::class)->execute($draft->fresh(), $staff);

    expect($second->id)->toBe($first->id)
        ->and(CustomerOrder::query()->count())->toBe(1)
        ->and($first->items->first()->quantity_reserved)->toBe(2)
        ->and($cell->fresh()->reserved_quantity)->toBe(2)
        ->and($cell->fresh()->current_stock)->toBe(5);
});

test('stock change after summary prevents order creation atomically', function () {
    [$draft, $staff, $cell] = messengerDraftFixture();
    $draft = app(MessengerOrderDraftService::class)->prepareSummary($draft);
    $draft->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_actor_type' => 'staff', 'confirmed_by_user_id' => $staff->id]);
    $cell->update(['current_stock' => 1]);

    expect(fn () => app(CreateMessengerOrderService::class)->execute($draft->fresh(), $staff))
        ->toThrow(RuntimeException::class, 'Stock changed');

    expect(CustomerOrder::query()->count())->toBe(0)
        ->and($cell->fresh()->reserved_quantity)->toBe(0);
});

test('human takeover suppresses pending ai messages and cross branch access is hidden', function () {
    [$draft, $staff] = messengerDraftFixture();
    $conversation = $draft->conversation;
    FacebookMessage::query()->create([
        'branch_id' => $draft->branch_id,
        'facebook_conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'sender_type' => 'ai',
        'body' => 'Pending AI response',
        'ai_generated' => true,
        'status' => 'pending',
    ]);

    $this->actingAs($staff)->post(route('messenger.take-over', $conversation))->assertRedirect();
    expect($conversation->fresh()->control_mode)->toBe('human')
        ->and($conversation->messages()->first()->status)->toBe('suppressed');

    $otherBranch = Branch::query()->create(['code' => 'OTHER', 'name' => 'Other']);
    $staff->update(['branch_id' => $otherBranch->id]);
    $this->actingAs($staff->fresh())->get(route('messenger.show', $conversation))->assertNotFound();
});

/** @return array{MessengerOrderDraft, User, ProductColorSize} */
function messengerDraftFixture(): array
{
    $staff = userWithRole('Staff');
    $branch = $staff->branch;
    $product = createTestProduct(['branch_id' => $branch->id]);
    $cell = createTestCell(5, 0, $product);
    $page = FacebookPage::query()->create(['branch_id' => $branch->id, 'page_id' => fake()->unique()->numerify('page-####'), 'name' => 'J4G', 'status' => 'active']);
    $conversation = FacebookConversation::query()->create(['branch_id' => $branch->id, 'facebook_page_id' => $page->id, 'psid' => fake()->unique()->numerify('psid-####')]);
    $draft = MessengerOrderDraft::query()->create([
        'branch_id' => $branch->id,
        'facebook_conversation_id' => $conversation->id,
        'customer_name' => 'Messenger Customer',
        'psid' => $conversation->psid,
        'fulfillment_method' => 'delivery',
        'delivery_address' => '123 Test Street',
        'payment_method_preference' => 'gcash',
    ]);
    $draft->items()->create(['product_color_size_id' => $cell->id, 'quantity' => 2, 'unit_price' => 150]);

    return [$draft->fresh('conversation'), $staff, $cell];
}
