<?php

use App\Enums\MovementType;
use App\Models\StockMovement;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('products data endpoint returns paginated json structure', function () {
    createTestCell();

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('products.data'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'code', 'name', 'status', 'size_count', 'color_count'],
            ],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('products data endpoint respects search and status filter', function () {
    createTestProduct(['name' => 'Unique Widget Product', 'code' => 'UWP', 'status' => 'inactive']);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('products.data', [
            'search' => 'Unique Widget',
            'status' => 'all',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.code', 'UWP');
});

test('viewer cannot access admin roles data endpoint', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->getJson(route('admin.roles.data'))
        ->assertForbidden();
});

test('low stock data endpoint paginates cells', function () {
    $cell = createTestCell(stock: 3, reserved: 0);
    $cell->update(['reorder_level' => 5]);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('reports.low-stock.data', ['per_page' => 25]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $cell->id);
});

test('out of stock data endpoint returns paginated cells', function () {
    $cell = createTestCell(stock: 0, reserved: 0);

    $this->actingAs(userWithRole('Manager'))
        ->getJson(route('reports.out-of-stock.data'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $cell->id);
});

test('dashboard recent movements data endpoint returns paginated json', function () {
    $cell = createTestCell();
    $user = userWithRole('Staff');

    StockMovement::query()->create([
        'product_color_size_id' => $cell->id,
        'type' => MovementType::In,
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
                '*' => ['id', 'created_at', 'product_name', 'color_name', 'size_name', 'movement_type', 'quantity', 'user_name'],
            ],
            'pagination',
        ]);
});

test('viewer cannot access admin users data endpoint', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->getJson(route('admin.users.data'))
        ->assertForbidden();
});
