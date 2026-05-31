<?php

use App\Models\Product;
use App\Models\ProductColorSize;
use App\Models\Size;
use App\Services\ProductCodeService;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('product seeder creates seven products', function () {
    (new ProductSeeder)->run();

    expect(Product::query()->count())->toBe(7);
});

test('adding color auto creates cells for existing sizes', function () {
    $product = createTestProduct();
    attachTestSize($product, 'M', 1);
    attachTestSize($product, 'L', 2);

    attachTestColor($product, 'Red', 1);

    expect(ProductColorSize::query()->whereHas('color', fn ($q) => $q->where('product_id', $product->id))->count())->toBe(2);
});

test('item code generation produces padded sequence', function () {
    $product = createTestProduct(['code' => 'ABC']);
    $service = app(ProductCodeService::class);

    expect($service->generate($product))->toBe('ABC-001');

    attachTestColor($product, 'One', 1);
    expect($service->generate($product))->toBe('ABC-002');
});

test('renaming product code cascades color item codes', function () {
    $product = createTestProduct(['code' => 'OLD']);
    $color = attachTestColor($product, 'Black', 1);

    expect($color->item_code)->toBe('OLD-001');

    $product->update(['code' => 'NEW']);

    expect($color->fresh()->item_code)->toBe('NEW-001');
});

test('products data hides inactive by default', function () {
    createTestProduct(['status' => 'inactive', 'code' => 'INA', 'name' => 'Inactive Product']);
    createTestProduct(['status' => 'active', 'code' => 'ACT', 'name' => 'Active Product']);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('products.data'))
        ->assertOk()
        ->assertJsonMissing(['code' => 'INA']);
});

test('size suggestions returns master sizes', function () {
    $product = createTestProduct();
    attachTestSize($product, 'XL', 1);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('products.sizes.suggestions'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['name' => 'XL']);
});

test('size suggestions excludes sizes already on product', function () {
    $product = createTestProduct();
    attachTestSize($product, 'XL', 1);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('products.sizes.suggestions', ['exclude_product_id' => $product->id]))
        ->assertOk()
        ->assertJsonMissing(['name' => 'XL']);
});

test('bulk attach creates master and pivot rows', function () {
    $product = createTestProduct();

    $this->actingAs(userWithRole('Manager'))
        ->postJson(route('products.sizes.bulk', $product), [
            'size_names' => ['New Size A', 'New Size B'],
        ])
        ->assertOk()
        ->assertJsonPath('created', 2);

    expect(Size::query()->where('name', 'New Size A')->exists())->toBeTrue();
    expect($product->sizes()->count())->toBe(2);

    $this->actingAs(userWithRole('Manager'))
        ->postJson(route('products.sizes.bulk', $product), [
            'size_names' => ['New Size A'],
        ])
        ->assertOk()
        ->assertJsonPath('created', 0);
});

test('backup command writes json file', function () {
    createTestCell();

    $this->artisan('inventory:backup', ['--path' => 'backups/test-backup.json'])
        ->assertSuccessful();

    $path = storage_path('app/backups/test-backup.json');
    expect(file_exists($path))->toBeTrue();

    $payload = json_decode(file_get_contents($path), true);
    expect($payload)->toHaveKeys(['exported_at', 'tables']);
});
