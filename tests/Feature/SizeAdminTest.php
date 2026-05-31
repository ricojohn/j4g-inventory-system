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
    $this->actingAs(userWithRole('Manager'));
});

test('sizes data endpoint returns paginated json structure', function () {
    $this->getJson(route('admin.sizes.data'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'sort_order', 'category_count', 'variant_count'],
            ],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('sizes admin page is accessible to manager', function () {
    $this->get(route('admin.sizes.index'))
        ->assertOk()
        ->assertSee('Sizes');
});

test('staff cannot access sizes admin', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('admin.sizes.index'))
        ->assertForbidden();
});

test('store size creates catalog entry', function () {
    $this->postJson(route('admin.sizes.store'), [
        'name' => 'Custom Size',
        'sort_order' => 99,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Custom Size')
        ->assertJsonPath('data.sort_order', 99);

    expect(Size::query()->where('name', 'Custom Size')->exists())->toBeTrue();
});

test('store size validates unique name', function () {
    $existing = Size::query()->firstOrFail();

    $this->postJson(route('admin.sizes.store'), [
        'name' => $existing->name,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('update size changes name and sort order', function () {
    $size = Size::query()->where('name', 'XS')->firstOrFail();

    $this->putJson(route('admin.sizes.update', $size), [
        'name' => 'Extra Small',
        'sort_order' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Extra Small');

    expect($size->fresh()->name)->toBe('Extra Small');
});

test('show json returns single size for edit modal', function () {
    $size = Size::query()->firstOrFail();

    $this->getJson(route('admin.sizes.show-json', $size))
        ->assertOk()
        ->assertJsonPath('data.id', $size->id)
        ->assertJsonPath('data.name', $size->name);
});

test('cannot delete size used by product variants', function () {
    $category = ProductCategory::query()->where('code', 'TSHIRT')->firstOrFail();
    $size = $category->sizes()->firstOrFail();

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'item_code' => 'TSHIRT-0098',
        'name' => 'Variant Guard Product',
        'color' => 'Black',
        'description' => null,
        'status' => 'active',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $size->id,
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $this->deleteJson(route('admin.sizes.destroy', $size))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete a size that is used by product variants.');

    expect(Size::query()->whereKey($size->id)->exists())->toBeTrue();
});

test('delete unused size removes catalog entry and detaches categories', function () {
    $size = Size::query()->create([
        'name' => 'Temporary Size',
        'sort_order' => 500,
    ]);

    $category = ProductCategory::query()->where('code', 'TSHIRT')->firstOrFail();
    $category->sizes()->attach($size->id);

    $this->deleteJson(route('admin.sizes.destroy', $size))
        ->assertOk();

    expect(Size::query()->whereKey($size->id)->exists())->toBeFalse()
        ->and($category->fresh()->sizes()->where('sizes.id', $size->id)->exists())->toBeFalse();
});
