<?php

use App\Enums\AiOrderDraftStatus;
use App\Models\AiOrderDraft;
use App\Models\CustomerOrder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can create order with optional reference image', function () {
    Storage::fake('public');

    $cell = createTestCell(100);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Image Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 5],
            ],
            'order_image' => UploadedFile::fake()->image('order-reference.jpg'),
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->first();

    expect($order->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($order->image_path);
});

test('order can be created without reference image', function () {
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

    expect($order->image_path)->toBeNull();
});

test('invalid order reference image is rejected', function () {
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

test('order show page displays reference image when present', function () {
    Storage::fake('public');

    $cell = createTestCell(100);
    $path = UploadedFile::fake()->image('order.jpg')->store('order-images', 'public');

    $order = CustomerOrder::factory()->create([
        'image_path' => $path,
        'created_by' => userWithRole('Staff')->id,
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
        ->assertSee('Order Reference Image', false);
});

test('staff can upload reference image to ai draft', function () {
    Storage::fake('public');

    $draft = AiOrderDraft::factory()->create([
        'status' => AiOrderDraftStatus::Draft,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('ai.order-assistant.drafts.image.upload', $draft), [
            'image' => UploadedFile::fake()->image('draft-reference.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['image_url']);

    $draft->refresh();

    expect($draft->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($draft->image_path);
});

test('staff can remove reference image from ai draft', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('draft.jpg')->store('order-images', 'public');

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

test('draft conversion copies reference image to customer order', function () {
    Storage::fake('public');

    $cell = createTestCell(20);
    $path = UploadedFile::fake()->image('draft.jpg')->store('order-images', 'public');

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

    expect($order->image_path)->toBe($path);
});

test('draft detail includes image url when image exists', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('draft.jpg')->store('order-images', 'public');

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
