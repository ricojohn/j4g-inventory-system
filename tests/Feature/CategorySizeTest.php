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

function categoryForSizeTests(string $code = 'TSHIRT'): ProductCategory
{
    return ProductCategory::query()->where('code', $code)->firstOrFail();
}

test('category sizes endpoint returns assigned and available sizes', function () {
    $category = categoryForSizeTests();
    $assignedCount = $category->sizes()->count();
    $availableCount = Size::query()->count() - $assignedCount;

    $this->getJson(route('categories.sizes', $category))
        ->assertOk()
        ->assertJsonPath('category.id', $category->id)
        ->assertJsonCount($assignedCount, 'assigned')
        ->assertJsonCount($availableCount, 'available');
});

test('seeded reversible kids category has two assigned sizes', function () {
    $category = categoryForSizeTests('REV-KIDS');

    expect($category->sizes()->pluck('sizes.name')->all())
        ->toBe(['Regular Kids', 'Upsize Kids']);
});

test('seeded reversible adult category has seven assigned sizes', function () {
    $category = categoryForSizeTests('REV-ADULT');

    expect($category->sizes()->count())->toBe(7)
        ->and($category->sizes()->pluck('sizes.name')->all())->toBe([
            'Regular',
            'Upsize',
            '3XL(2XL)',
            '4XL(3XL)',
            '5XL(4XL)',
            '6XL(5XL)',
            '7XL(6XL)',
        ]);
});

test('seeded apparel category has standard xs through 5xl sizes', function () {
    $category = categoryForSizeTests('TSHIRT');

    expect($category->sizes()->pluck('sizes.name')->all())
        ->toBe(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL']);
});

test('new category starts with zero assigned sizes', function () {
    $this->postJson(route('categories.store'), [
        'name' => 'Empty Sizes Category',
        'code' => 'EMPTY-SZ',
        'low_stock_threshold' => 5,
        'status' => 'active',
    ])
        ->assertOk();

    $category = ProductCategory::query()->where('code', 'EMPTY-SZ')->firstOrFail();

    expect($category->sizes()->count())->toBe(0);
});

test('sync sizes updates category pivot', function () {
    $category = categoryForSizeTests();
    $sizeIds = Size::query()->orderBy('sort_order')->limit(3)->pluck('id')->all();

    $this->putJson(route('categories.sizes.sync', $category), [
        'size_ids' => $sizeIds,
    ])
        ->assertOk()
        ->assertJsonPath('assigned_size_ids', $sizeIds);

    expect($category->fresh()->sizes()->pluck('sizes.id')->all())->toBe($sizeIds);
});

test('sync sizes allows empty assignment', function () {
    $category = categoryForSizeTests();

    $this->putJson(route('categories.sizes.sync', $category), [
        'size_ids' => [],
    ])
        ->assertOk()
        ->assertJsonPath('assigned_size_ids', []);

    expect($category->fresh()->sizes()->count())->toBe(0);
});

test('cannot remove size from category when product variant uses it', function () {
    $category = categoryForSizeTests();
    $assignedSize = $category->sizes()->orderBy('sort_order')->firstOrFail();
    $remainingSizeIds = $category->sizes()
        ->where('sizes.id', '!=', $assignedSize->id)
        ->pluck('sizes.id')
        ->all();

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'item_code' => 'TSHIRT-0097',
        'name' => 'Detach Guard Product',
        'color' => 'White',
        'description' => null,
        'status' => 'active',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $assignedSize->id,
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $this->putJson(route('categories.sizes.sync', $category), [
        'size_ids' => $remainingSizeIds,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot remove a size that is used by products in this category.');

    expect($category->fresh()->sizes()->where('sizes.id', $assignedSize->id)->exists())->toBeTrue();
});

test('variant options endpoint returns only assigned sizes', function () {
    $category = categoryForSizeTests();
    $sizeIds = Size::query()->orderBy('sort_order')->limit(2)->pluck('id')->all();
    $category->sizes()->sync($sizeIds);

    $response = $this->getJson(route('categories.variant-options', $category))
        ->assertOk();

    expect(collect($response->json('sizes'))->pluck('id')->all())->toBe($sizeIds);
});

test('product store rejects size not assigned to category', function () {
    $category = categoryForSizeTests();
    $assignedSize = Size::query()->orderBy('sort_order')->firstOrFail();
    $unassignedSize = Size::query()->orderByDesc('sort_order')->firstOrFail();

    $category->sizes()->sync([$assignedSize->id]);

    $this->postJson(route('products.store'), [
        'product_category_id' => $category->id,
        'name' => 'Invalid Size Product',
        'color' => 'Red',
        'description' => null,
        'status' => 'active',
        'size_ids' => [$unassignedSize->id],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size_ids.0']);
});

test('product store accepts valid category and size combination', function () {
    $category = categoryForSizeTests();
    $sizeIds = Size::query()->orderBy('sort_order')->limit(2)->pluck('id')->all();
    $category->sizes()->sync($sizeIds);

    $this->postJson(route('products.store'), [
        'product_category_id' => $category->id,
        'name' => 'Valid Size Product',
        'color' => 'Blue',
        'description' => null,
        'status' => 'active',
        'size_ids' => $sizeIds,
    ])
        ->assertOk()
        ->assertJsonPath('data.item_code', 'TSHIRT-0001');

    $product = Product::query()->where('name', 'Valid Size Product')->firstOrFail();

    expect($product->variants()->pluck('size_id')->sort()->values()->all())
        ->toBe(collect($sizeIds)->sort()->values()->all());
});

test('product update removes variants with sizes no longer valid for new category', function () {
    $tshirt = categoryForSizeTests('TSHIRT');
    $polo = categoryForSizeTests('POLO');

    $tshirtSize = Size::query()->orderBy('sort_order')->firstOrFail();
    $poloSize = Size::query()->orderBy('sort_order')->skip(1)->firstOrFail();

    $tshirt->sizes()->sync([$tshirtSize->id]);
    $polo->sizes()->sync([$poloSize->id]);

    $product = Product::query()->create([
        'product_category_id' => $tshirt->id,
        'item_code' => 'TSHIRT-0099',
        'name' => 'Switch Category Product',
        'color' => 'Green',
        'description' => null,
        'status' => 'active',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $tshirtSize->id,
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $this->putJson(route('products.update', $product), [
        'product_category_id' => $polo->id,
        'name' => 'Switch Category Product',
        'color' => 'Green',
        'description' => null,
        'status' => 'active',
        'size_ids' => [$poloSize->id],
    ])->assertOk();

    $product->refresh();

    expect($product->product_category_id)->toBe($polo->id)
        ->and($product->variants()->pluck('size_id')->all())->toBe([$poloSize->id]);
});
