<?php

use App\Models\ProductCategory;
use App\Models\StockMovement;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new ProductCategorySeeder)->run();
    (new UserSeeder)->run();
});

test('categories data endpoint returns paginated json structure', function () {
    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('categories.data'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'code', 'low_stock_threshold', 'status'],
            ],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('categories data endpoint respects search and per page', function () {
    ProductCategory::query()->create([
        'name' => 'Unique Widget Category',
        'code' => 'WIDGET-UNIQUE',
        'low_stock_threshold' => 5,
        'status' => 'active',
    ]);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('categories.data', [
            'search' => 'Unique Widget',
            'per_page' => 50,
        ]))
        ->assertOk()
        ->assertJsonPath('pagination.per_page', 50)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'WIDGET-UNIQUE');
});

test('categories show json returns single record for edit', function () {
    $category = ProductCategory::query()->firstOrFail();

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('categories.show-json', $category))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.name', $category->name);
});

test('viewer cannot access admin roles data endpoint', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->getJson(route('admin.roles.data'))
        ->assertForbidden();
});

test('low stock data endpoint paginates without loading all rows', function () {
    $variant = createTestVariant(stock: 5, reserved: 0);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('reports.low-stock.data', ['per_page' => 25]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('data.0.id', $variant->id);
});

test('out of stock data endpoint returns paginated variants', function () {
    $variant = createTestVariant(stock: 0, reserved: 0);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('reports.out-of-stock.data'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $variant->id);
});

test('dashboard recent movements data endpoint returns paginated json', function () {
    $variant = createTestVariant();
    $user = userWithRole('Staff');

    StockMovement::query()->create([
        'product_variant_id' => $variant->id,
        'movement_type' => 'IN',
        'quantity' => 5,
        'before_stock' => 0,
        'after_stock' => 5,
        'before_reserved' => 0,
        'after_reserved' => 0,
        'remarks' => 'Initial stock',
        'created_by' => $user->id,
    ]);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('dashboard.recent-movements.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'created_at', 'product_name', 'size_name', 'movement_type', 'quantity', 'user_name'],
            ],
            'pagination',
        ]);
});

test('products data endpoint respects category filter', function () {
    $variant = createTestVariant();
    $variant->load('product');

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('products.data', [
            'category_id' => $variant->product->product_category_id,
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $variant->product_id);
});

test('viewer cannot access admin users data endpoint', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->getJson(route('admin.users.data'))
        ->assertForbidden();
});
