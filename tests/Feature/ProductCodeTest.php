<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Size;
use Database\Seeders\CategorySizeSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new ProductCategorySeeder)->run();
    (new CategorySizeSeeder)->run();
    (new UserSeeder)->run();
    $this->actingAs(userWithRole('Staff'));
});

function categoryByCode(string $code): ProductCategory
{
    return ProductCategory::query()->where('code', $code)->firstOrFail();
}

function createProductInCategory(ProductCategory $category, array $overrides = []): Product
{
    return Product::query()->create(array_merge([
        'product_category_id' => $category->id,
        'item_code' => strtoupper($category->code).'-9999',
        'name' => 'Test Product',
        'color' => 'Black',
        'description' => null,
        'status' => 'active',
    ], $overrides));
}

test('preview endpoint returns first code for category with no products', function () {
    $category = categoryByCode('TSC');

    $this->getJson(route('products.preview-item-code', ['category_id' => $category->id]))
        ->assertOk()
        ->assertJson(['item_code' => 'TSC-001']);
});

test('preview endpoint increments within same category', function () {
    $category = categoryByCode('TSC');
    createProductInCategory($category, ['item_code' => 'TSC-001']);

    $this->getJson(route('products.preview-item-code', ['category_id' => $category->id]))
        ->assertOk()
        ->assertJson(['item_code' => 'TSC-002']);
});

test('preview endpoint uses independent sequence per category', function () {
    $tshirt = categoryByCode('TSC');
    $polo = categoryByCode('PSC');

    createProductInCategory($tshirt, ['item_code' => 'TSC-001']);
    createProductInCategory($tshirt, ['item_code' => 'TSC-002']);

    $this->getJson(route('products.preview-item-code', ['category_id' => $polo->id]))
        ->assertOk()
        ->assertJson(['item_code' => 'PSC-001']);
});

test('store generates item code on server and ignores client override', function () {
    $category = categoryByCode('TSC');
    $sizeId = Size::query()->firstOrFail()->id;

    $this->postJson(route('products.store'), [
        'product_category_id' => $category->id,
        'item_code' => 'HACKED-001',
        'name' => 'White T-Shirt',
        'color' => 'White',
        'description' => null,
        'status' => 'active',
        'size_ids' => [$sizeId],
    ])
        ->assertOk()
        ->assertJsonPath('data.item_code', 'TSC-001');

    expect(Product::query()->where('name', 'White T-Shirt')->value('item_code'))->toBe('TSC-001');
});

test('update does not change existing item code', function () {
    $category = categoryByCode('PSC');
    $sizeId = Size::query()->firstOrFail()->id;

    $product = createProductInCategory($category, [
        'item_code' => 'PSC-042',
        'name' => 'Blue Polo',
        'color' => 'Blue',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $sizeId,
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $this->putJson(route('products.update', $product), [
        'product_category_id' => $category->id,
        'item_code' => 'PSC-999',
        'name' => 'Updated Polo Name',
        'color' => 'Navy',
        'description' => 'Updated',
        'status' => 'active',
        'size_ids' => [$sizeId],
    ])->assertOk();

    expect($product->fresh()->item_code)->toBe('PSC-042')
        ->and($product->fresh()->name)->toBe('Updated Polo Name');
});

test('renaming a category code rewrites item codes for all its products while preserving sequence', function () {
    $category = ProductCategory::query()->create([
        'name' => 'Polo Shirt',
        'code' => 'POLO',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ]);

    $first = createProductInCategory($category, ['item_code' => 'POLO-001', 'name' => 'Polo One']);
    $second = createProductInCategory($category, ['item_code' => 'POLO-007', 'name' => 'Polo Two']);
    $third = createProductInCategory($category, ['item_code' => 'POLO-042', 'name' => 'Polo Three']);

    $this->putJson(route('categories.update', $category), [
        'name' => $category->name,
        'code' => 'POLO2',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ])->assertOk();

    expect($first->fresh()->item_code)->toBe('POLO2-001')
        ->and($second->fresh()->item_code)->toBe('POLO2-007')
        ->and($third->fresh()->item_code)->toBe('POLO2-042');
});

test('updating a category without changing code leaves item codes untouched', function () {
    $category = ProductCategory::query()->create([
        'name' => 'Polo Shirt',
        'code' => 'POLO',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ]);

    $product = createProductInCategory($category, ['item_code' => 'POLO-001']);

    $this->putJson(route('categories.update', $category), [
        'name' => 'Renamed Polo Shirt',
        'code' => 'POLO',
        'low_stock_threshold' => 15,
        'status' => 'active',
    ])->assertOk();

    expect($product->fresh()->item_code)->toBe('POLO-001');
});

test('renaming a category code does not affect products in other categories', function () {
    $polo = ProductCategory::query()->create([
        'name' => 'Polo Shirt',
        'code' => 'POLO',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ]);

    $tshirt = ProductCategory::query()->create([
        'name' => 'T-Shirt',
        'code' => 'TSHIRT',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ]);

    $poloProduct = createProductInCategory($polo, ['item_code' => 'POLO-001', 'name' => 'Polo Product']);
    $tshirtProduct = createProductInCategory($tshirt, ['item_code' => 'TSHIRT-001', 'name' => 'T-Shirt Product']);

    $this->putJson(route('categories.update', $polo), [
        'name' => $polo->name,
        'code' => 'POLO2',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ])->assertOk();

    expect($poloProduct->fresh()->item_code)->toBe('POLO2-001')
        ->and($tshirtProduct->fresh()->item_code)->toBe('TSHIRT-001');
});
