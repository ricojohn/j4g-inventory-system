<?php

use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

function userWithoutDashboardAccess(): User
{
    $role = Role::findOrCreate('No Dashboard Charts');
    $role->syncPermissions(['view products']);

    $user = User::factory()->create([
        'email' => 'no-dashboard-charts@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    return $user;
}

test('dashboard page loads for authorized user', function () {
    createTestCell();

    $this->actingAs(userWithRole('Staff'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Stock Health')
        ->assertSee('Recent Stock Movements');
});

test('stock health endpoint returns labels and series counts', function () {
    $product = createTestProduct(['code' => 'HLT', 'name' => 'Health Test']);
    $size = attachTestSize($product, 'M', 1);
    $okColor = attachTestColor($product, 'OK Color', 1);
    $lowColor = attachTestColor($product, 'Low Color', 2);
    $outColor = attachTestColor($product, 'Out Color', 3);

    $okColor->cells()->where('product_size_id', $size->id)->update([
        'current_stock' => 100,
        'reserved_quantity' => 0,
        'reorder_level' => 5,
    ]);

    $lowColor->cells()->where('product_size_id', $size->id)->update([
        'current_stock' => 20,
        'reserved_quantity' => 5,
        'reorder_level' => 20,
    ]);

    $outColor->cells()->where('product_size_id', $size->id)->update([
        'current_stock' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 5,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('dashboard.stock-health'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'labels')
        ->assertJsonCount(3, 'series')
        ->assertJsonPath('labels.0', 'OK')
        ->assertJsonPath('labels.1', 'Low Stock')
        ->assertJsonPath('labels.2', 'Out of Stock')
        ->assertJsonPath('series.0', 1)
        ->assertJsonPath('series.1', 1)
        ->assertJsonPath('series.2', 1);
});

test('stock movement trend endpoint returns categories and series', function () {
    $cell = createTestCell(stock: 10);
    $staff = userWithRole('Staff');

    $this->actingAs($staff);

    app(InventoryService::class)->stockIn($cell, 25, 'Trend test');

    $response = $this->getJson(route('dashboard.stock-movement-trend', ['days' => 14]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'categories',
            'series' => [
                ['name', 'data'],
                ['name', 'data'],
                ['name', 'data'],
            ],
        ])
        ->assertJsonPath('series.0.name', 'Stock In')
        ->assertJsonPath('series.1.name', 'Stock Out')
        ->assertJsonPath('series.2.name', 'Damaged');

    $stockInTotal = array_sum($response->json('series.0.data'));
    expect($stockInTotal)->toBeGreaterThanOrEqual(25);
});

test('low stock by product endpoint returns grouped categories and series', function () {
    $product = createTestProduct(['code' => 'LSP', 'name' => 'Low Stock Product']);
    $size = attachTestSize($product, 'M', 1);
    $lowColor = attachTestColor($product, 'Low', 1);
    $outColor = attachTestColor($product, 'Out', 2);

    $lowColor->cells()->where('product_size_id', $size->id)->update([
        'current_stock' => 10,
        'reserved_quantity' => 0,
        'reorder_level' => 10,
    ]);

    $outColor->cells()->where('product_size_id', $size->id)->update([
        'current_stock' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 5,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('dashboard.low-stock-by-product'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('categories.0', 'Low Stock Product')
        ->assertJsonPath('series.0.name', 'Low Stock')
        ->assertJsonPath('series.1.name', 'Out of Stock')
        ->assertJsonPath('series.0.data.0', 1)
        ->assertJsonPath('series.1.data.0', 1);
});

test('active products endpoint returns grouped movement counts', function () {
    $cell = createTestCell(stock: 10);
    $productName = $cell->color->product->name;
    $staff = userWithRole('Staff');

    $this->actingAs($staff);

    app(InventoryService::class)->stockIn($cell, 3, 'Active 1');
    app(InventoryService::class)->stockOut($cell, 1, 'Active 2');

    $this->getJson(route('dashboard.active-products', ['days' => 30]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('categories.0', $productName)
        ->assertJsonPath('series.0.name', 'Movements')
        ->assertJsonPath('series.0.data.0', 2);
});

test('dashboard chart endpoints require view dashboard permission', function () {
    createTestCell();
    $user = userWithoutDashboardAccess();

    $routes = [
        route('dashboard.stock-health'),
        route('dashboard.stock-movement-trend'),
        route('dashboard.low-stock-by-product'),
        route('dashboard.active-products'),
    ];

    foreach ($routes as $url) {
        $this->actingAs($user)
            ->getJson($url)
            ->assertForbidden();
    }
});
