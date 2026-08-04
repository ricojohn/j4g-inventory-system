<?php

use App\Enums\AiOrderDraftStatus;
use App\Enums\OrderLayoutStatus;
use App\Models\AiOrderDraft;
use App\Models\CustomerOrder;
use App\Models\OrderLayout;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can create order with optional layout image', function () {
    Storage::fake('public');

    $cell = createTestCell(100);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Image Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 5],
            ],
            'order_image' => UploadedFile::fake()->image('order-layout.jpg'),
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->first();
    $layout = OrderLayout::query()->where('customer_order_id', $order->id)->first();

    expect($order->image_path)->toBeNull()
        ->and($layout)->not->toBeNull()
        ->and($layout->version)->toBe(1)
        ->and($layout->title)->toBe('Initial layout')
        ->and($layout->status)->toBe(OrderLayoutStatus::Draft);

    Storage::disk('public')->assertExists($layout->file_path);
});

test('order can be created without layout image', function () {
    $cell = createTestCell(100);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'No Image Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 5],
            ],
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->first();

    expect($order->image_path)->toBeNull()
        ->and($order->layouts()->count())->toBe(0);
});

test('invalid order layout image is rejected', function () {
    $cell = createTestCell(100);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Invalid Image Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 5],
            ],
            'order_image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('order_image');

    expect(CustomerOrder::query()->count())->toBe(0);
});

test('order show page displays layout image when present', function () {
    Storage::fake('public');

    $cell = createTestCell(100);
    $path = UploadedFile::fake()->image('layout.jpg')->store('order-layouts', 'public');

    $order = CustomerOrder::factory()->create([
        'created_by' => userWithRole('Staff')->id,
    ]);

    $order->layouts()->create([
        'version' => 1,
        'title' => 'Initial layout',
        'file_path' => $path,
        'status' => OrderLayoutStatus::Draft,
    ]);

    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 1,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Layout Image', false)
        ->assertSee('Initial layout', false);
});

test('staff can upload layout image to ai draft', function () {
    Storage::fake('public');

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('ai.order-assistant.drafts.image.upload', $draft), [
            'image' => UploadedFile::fake()->image('draft-layout.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['image_url']);

    $draft->refresh();

    expect($draft->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($draft->image_path);
});

test('staff can remove layout image from ai draft', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('draft.jpg')->store('order-layouts', 'public');

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'image_path' => $path,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->delete(route('ai.order-assistant.drafts.image.destroy', $draft))
        ->assertOk()
        ->assertJsonPath('success', true);

    $draft->refresh();

    expect($draft->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('draft conversion creates layout from draft image', function () {
    Storage::fake('public');

    $cell = createTestCell(20);
    $path = UploadedFile::fake()->image('draft.jpg')->store('order-layouts', 'public');

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'customer_name' => 'Juan Dela Cruz',
        'customer_source' => 'facebook',
        'image_path' => $path,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('ai.order-assistant.drafts.convert', $draft), [
            'customer_name' => 'Juan Dela Cruz',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $order = CustomerOrder::query()->first();
    $layout = OrderLayout::query()->where('customer_order_id', $order->id)->first();

    expect($order->image_path)->toBeNull()
        ->and($layout)->not->toBeNull()
        ->and($layout->file_path)->toBe($path)
        ->and($layout->version)->toBe(1)
        ->and($layout->status)->toBe(OrderLayoutStatus::Draft);
});

test('draft detail includes image url when image exists', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('draft.jpg')->store('order-layouts', 'public');

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'image_path' => $path,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('ai.order-assistant.drafts.show', $draft))
        ->assertOk()
        ->assertJsonPath('draft.image_path', $path)
        ->assertJsonStructure(['draft' => ['image_url']]);
});
