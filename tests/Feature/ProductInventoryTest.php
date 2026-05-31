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
        ->assertSee($product->name);

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
