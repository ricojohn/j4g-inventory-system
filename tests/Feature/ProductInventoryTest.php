<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('product inventory page is accessible to staff with view inventory permission', function () {
    $cell = createTestCell();
    $product = $cell->color->product;

    $this->actingAs(userWithRole('Staff'))
        ->get(route('products.inventory', $product))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Recent Stock History')
        ->assertSee('Total Colors');

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', $product))
        ->assertOk()
        ->assertJsonPath('sizes.0.size_name', $cell->size->size->name);
});

test('user without view inventory permission cannot access product inventory page', function () {
    $cell = createTestCell();
    $product = $cell->color->product;

    $role = Role::findOrCreate('Products Only');
    $role->syncPermissions(['view products']);

    $user = User::factory()->create([
        'email' => 'products-only@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->get(route('products.inventory', $product))
        ->assertForbidden();
});

test('inventory data returns paginated colors with search', function () {
    $product = createTestProduct(['code' => 'PAG', 'name' => 'Pagination Test']);

    attachTestSize($product, 'M', 1);

    foreach (range(1, 21) as $index) {
        attachTestColor($product, "Color {$index}", $index);
    }

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', ['product' => $product, 'per_page' => 20]))
        ->assertOk()
        ->assertJsonStructure([
            'sizes',
            'colors',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('pagination.per_page', 20)
        ->assertJsonPath('pagination.total', 21)
        ->assertJsonCount(20, 'colors');

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', ['product' => $product, 'per_page' => 20, 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('pagination.current_page', 2)
        ->assertJsonPath('colors.0.color_name', 'Color 21');

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', ['product' => $product, 'search' => 'Color 5']))
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('colors.0.color_name', 'Color 5');
});

test('inventory data returns sizes ordered by sort order', function () {
    $product = createTestProduct(['code' => 'ORD', 'name' => 'Order Test']);

    attachTestSize($product, 'Large Test', 2);
    attachTestSize($product, 'Small Test', 1);
    attachTestColor($product, 'Blue', 1);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', $product))
        ->assertOk()
        ->assertJsonPath('sizes.0.size_name', 'Small Test')
        ->assertJsonPath('sizes.1.size_name', 'Large Test')
        ->assertJsonPath('colors.0.color_name', 'Blue');
});

test('products data includes inventory url for users with view inventory permission', function () {
    createTestCell();

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.data'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['*' => ['inventory_url']]])
        ->assertJsonPath('data.0.inventory_url', fn ($url) => str_contains($url, '/products/'));
});

test('stock in from product inventory updates cell stock', function () {
    $cell = createTestCell(stock: 10);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => $cell->id,
            'quantity' => 5,
            'remarks' => 'Restock',
        ])
        ->assertOk()
        ->assertJsonPath('data.current_stock', 15);

    expect($cell->fresh()->current_stock)->toBe(15);
});

test('global inventory list routes are removed', function () {
    expect(Route::has('inventory.index'))->toBeFalse();
    expect(Route::has('inventory.data'))->toBeFalse();
});

test('inactive product inventory actions return 422', function () {
    $cell = createTestCell(stock: 10);
    $cell->color->product->update(['status' => 'inactive']);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => $cell->id,
            'quantity' => 1,
        ])
        ->assertUnprocessable();
});

test('inventory data includes summary totals for the product', function () {
    $product = createTestProduct(['code' => 'SUM', 'name' => 'Summary Test']);
    $size = attachTestSize($product, 'M', 1);
    $red = attachTestColor($product, 'Red', 1);
    $blue = attachTestColor($product, 'Blue', 2);

    foreach ([$red, $blue] as $productColor) {
        $productColor->cells()->where('product_size_id', $size->id)->update([
            'current_stock' => 20,
            'reserved_quantity' => 5,
            'reorder_level' => 20,
        ]);
    }

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', $product))
        ->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'total_colors',
                'total_skus',
                'total_stock',
                'total_reserved',
                'total_available',
                'low_stock_count',
                'out_of_stock_count',
            ],
        ])
        ->assertJsonPath('summary.total_colors', 2)
        ->assertJsonPath('summary.total_skus', 2)
        ->assertJsonPath('summary.total_stock', 40)
        ->assertJsonPath('summary.total_reserved', 10)
        ->assertJsonPath('summary.total_available', 30)
        ->assertJsonPath('summary.low_stock_count', 2)
        ->assertJsonPath('summary.out_of_stock_count', 0);
});

test('cell history endpoint returns latest movements for a cell', function () {
    $cell = createTestCell(stock: 10);
    $staff = userWithRole('Staff');

    $this->actingAs($staff)
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => $cell->id,
            'quantity' => 3,
            'remarks' => 'First restock',
        ])
        ->assertOk();

    $this->travel(1)->second();

    $this->actingAs($staff)
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => $cell->id,
            'quantity' => 2,
            'remarks' => 'Second restock',
        ])
        ->assertOk();

    $this->actingAs($staff)
        ->getJson(route('inventory.cell-history', $cell))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'movements')
        ->assertJsonPath('movements.0.movement_type', 'IN')
        ->assertJsonPath('movements.0.quantity', 2)
        ->assertJsonPath('movements.0.before_stock', 13)
        ->assertJsonPath('movements.0.after_stock', 15)
        ->assertJsonPath('movements.0.remarks', 'Second restock')
        ->assertJsonPath('movements.0.user_name', $staff->name)
        ->assertJsonPath('movements.1.movement_type', 'IN')
        ->assertJsonPath('movements.1.quantity', 3)
        ->assertJsonPath('movements.1.remarks', 'First restock');
});

test('cell history endpoint returns at most five movements', function () {
    $cell = createTestCell(stock: 0);
    $staff = userWithRole('Staff');

    foreach (range(1, 6) as $index) {
        $this->actingAs($staff)
            ->postJson(route('inventory.stock-in'), [
                'cell_id' => $cell->id,
                'quantity' => 1,
                'remarks' => "Restock {$index}",
            ])
            ->assertOk();

        $this->travel(1)->second();
    }

    $this->actingAs($staff)
        ->getJson(route('inventory.cell-history', $cell))
        ->assertOk()
        ->assertJsonCount(5, 'movements')
        ->assertJsonPath('movements.0.remarks', 'Restock 6')
        ->assertJsonPath('movements.4.remarks', 'Restock 2');
});

test('user without view inventory permission cannot access cell history endpoint', function () {
    $cell = createTestCell();

    $role = Role::findOrCreate('Products Only');
    $role->syncPermissions(['view products']);

    $user = User::factory()->create([
        'email' => 'products-only-history@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->getJson(route('inventory.cell-history', $cell))
        ->assertForbidden();
});
