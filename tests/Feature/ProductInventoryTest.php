<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Size;
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
    $variant = createTestVariant();

    $this->actingAs(userWithRole('Staff'))
        ->get(route('products.inventory', $variant->product))
        ->assertOk()
        ->assertSee($variant->product->name);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', $variant->product))
        ->assertOk()
        ->assertJsonPath('data.0.size_name', $variant->size->name);
});

test('user without view inventory permission cannot access product inventory page', function () {
    $variant = createTestVariant();

    $role = Role::findOrCreate('Products Only');
    $role->syncPermissions(['view products']);

    $user = User::factory()->create([
        'email' => 'products-only@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->get(route('products.inventory', $variant->product))
        ->assertForbidden();
});

test('product inventory page lists variants ordered by size sort order', function () {
    $category = ProductCategory::query()->create([
        'name' => 'Order Test Category',
        'code' => 'ORDER-TEST',
        'low_stock_threshold' => 5,
        'status' => 'active',
    ]);

    $small = Size::query()->create(['name' => 'Small Test', 'sort_order' => 1]);
    $large = Size::query()->create(['name' => 'Large Test', 'sort_order' => 2]);

    $category->sizes()->sync([$small->id, $large->id]);

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'item_code' => 'ORDER-0001',
        'name' => 'Order Test Product',
        'color' => 'Blue',
        'description' => null,
        'status' => 'active',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $large->id,
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $small->id,
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', $product))
        ->assertOk()
        ->assertJsonPath('data.0.size_name', 'Small Test')
        ->assertJsonPath('data.1.size_name', 'Large Test');
});

test('products data includes inventory url for users with view inventory permission', function () {
    createTestVariant();

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['inventory_url'],
            ],
        ])
        ->assertJsonPath('data.0.inventory_url', fn ($url) => str_contains($url, '/products/'));
});

test('stock in from product inventory page updates variant stock', function () {
    $variant = createTestVariant(stock: 10);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('inventory.stock-in'), [
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'remarks' => 'Restock',
        ])
        ->assertOk()
        ->assertJsonPath('data.stock_quantity', 15);

    expect($variant->fresh()->stock_quantity)->toBe(15);
});

test('global inventory list routes are removed', function () {
    expect(Route::has('inventory.index'))->toBeFalse();
    expect(Route::has('inventory.data'))->toBeFalse();
});
