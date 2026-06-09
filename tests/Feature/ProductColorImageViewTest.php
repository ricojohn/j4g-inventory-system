<?php

use App\Services\AiOrderDraftService;
use App\Support\ProductCellLookup;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('product cell lookup includes image url and product color id', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $productColor = $cell->color;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$productColor->product, $productColor]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk();

    $formatted = app(ProductCellLookup::class)->formatCell($cell->fresh(['color.color', 'color.product', 'size.size']));

    expect($formatted)->toHaveKeys(['image_url', 'product_color_id'])
        ->and($formatted['product_color_id'])->toBe($productColor->id)
        ->and($formatted['image_url'])->toContain('/storage/');
});

test('low stock report data includes image url', function () {
    Storage::fake('public');

    $cell = createTestCell(stock: 1, reserved: 0);
    $cell->update(['reorder_level' => 5]);
    $productColor = $cell->color;

    $this->actingAs(userWithRole('Admin'))
        ->post(route('products.colors.image.upload', [$productColor->product, $productColor]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk();

    $response = $this->actingAs(userWithRole('Staff'))
        ->getJson(route('reports.low-stock.data'))
        ->assertOk()
        ->json();

    $row = collect($response['data'])->firstWhere('id', $cell->id);

    expect($row)->not->toBeNull()
        ->and($row['image_url'])->toContain('/storage/')
        ->and($row['item_code'])->toBe($productColor->item_code);
});

test('dashboard recent movements data includes image url', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $productColor = $cell->color;

    $this->actingAs(userWithRole('Admin'))
        ->post(route('products.colors.image.upload', [$productColor->product, $productColor]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk();

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => $cell->id,
            'quantity' => 5,
            'remarks' => 'Test stock in',
        ])
        ->assertOk();

    $response = $this->actingAs(userWithRole('Staff'))
        ->getJson(route('dashboard.recent-movements.data'))
        ->assertOk()
        ->json();

    expect($response['data'][0]['image_url'] ?? null)->toContain('/storage/')
        ->and($response['data'][0]['item_code'] ?? null)->toBe($productColor->item_code);
});

test('ai draft matched item includes image url when cell is matched', function () {
    Storage::fake('public');

    $cell = createTestCell(20);
    $productColor = $cell->color;

    $this->actingAs(userWithRole('Admin'))
        ->post(route('products.colors.image.upload', [$productColor->product, $productColor]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk();

    $matched = app(AiOrderDraftService::class)->matchParsedItemsToInventory([
        'items' => [
            [
                'product_name' => $productColor->product->name,
                'color_name' => $productColor->color->name,
                'size_name' => $cell->size->size->name,
                'quantity' => 5,
            ],
        ],
    ]);

    expect($matched['items'][0]['image_url'])->toContain('/storage/');
});
