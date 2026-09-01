<?php

use App\Models\Branch;
use App\Models\FacebookConversation;
use App\Models\FacebookPage;
use App\Models\MessengerOrderDraft;
use App\Models\Product;
use App\Services\Facebook\MessengerOrderDraftService;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('product queries are isolated to the authenticated staff branch', function () {
    $main = Branch::query()->where('code', 'MAIN')->firstOrFail();
    $other = Branch::query()->create(['code' => 'OTHER', 'name' => 'Other']);
    Product::query()->create(['branch_id' => $main->id, 'name' => 'Main Product', 'code' => 'MAIN-1']);
    Product::query()->create(['branch_id' => $other->id, 'name' => 'Other Product', 'code' => 'OTHER-1']);

    $this->actingAs(userWithRole('Staff'));

    expect(Product::query()->pluck('name')->all())->toBe(['Main Product']);
});

test('admin can configure a page without exposing the saved token', function () {
    $admin = userWithRole('Admin');
    $this->actingAs($admin)->post(route('facebook-pages.store'), [
        'page_id' => 'page-admin',
        'name' => 'Admin Page',
        'access_token' => 'page-secret-token',
        'graph_api_version' => 'v23.0',
        'status' => 'active',
        'ai_enabled' => '1',
    ])->assertRedirect();

    $page = FacebookPage::query()->where('page_id', 'page-admin')->firstOrFail();
    expect($page->access_token)->toBe('page-secret-token')
        ->and((string) $page->getRawOriginal('access_token'))->not->toContain('page-secret-token');

    $this->actingAs($admin)->get(route('facebook-pages.index'))
        ->assertOk()->assertDontSee('page-secret-token');
});

test('saving a draft invalidates an earlier confirmation summary', function () {
    [$draft, $staff, $cell] = operationsDraftFixture();
    $draft = app(MessengerOrderDraftService::class)->prepareSummary($draft);
    $draft->update(['status' => 'confirmed', 'confirmed_at' => now()]);

    $this->actingAs($staff)->put(route('messenger.draft.update', $draft->conversation), [
        'customer_name' => 'Updated Name',
        'fulfillment_method' => 'pickup',
        'delivery_address' => null,
        'payment_method_preference' => 'cash',
        'items' => [['product_color_size_id' => $cell->id, 'quantity' => 1, 'unit_price' => 50]],
    ])->assertRedirect();

    expect($draft->fresh()->status)->toBe('collecting')
        ->and($draft->fresh()->summary_hash)->toBeNull()
        ->and($draft->fresh()->confirmed_at)->toBeNull()
        ->and($draft->fresh()->version)->toBe(2);
});

function operationsDraftFixture(): array
{
    $staff = userWithRole('Staff');
    $branch = $staff->branch;
    $cell = createTestCell(5, 0, createTestProduct(['branch_id' => $branch->id]));
    $page = FacebookPage::query()->create(['branch_id' => $branch->id, 'page_id' => 'operations-page', 'name' => 'J4G', 'status' => 'active']);
    $conversation = FacebookConversation::query()->create(['branch_id' => $branch->id, 'facebook_page_id' => $page->id, 'psid' => 'operations-psid']);
    $draft = MessengerOrderDraft::query()->create(['branch_id' => $branch->id, 'facebook_conversation_id' => $conversation->id, 'customer_name' => 'Customer', 'psid' => $conversation->psid, 'fulfillment_method' => 'pickup', 'payment_method_preference' => 'cash']);
    $draft->items()->create(['product_color_size_id' => $cell->id, 'quantity' => 2]);

    return [$draft->fresh('conversation'), $staff, $cell];
}
