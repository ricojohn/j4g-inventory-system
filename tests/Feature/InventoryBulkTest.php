<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    $this->actingAs(userWithRole('Staff'));
});

/**
 * @return array{product: Product, first: ProductVariant, second: ProductVariant}
 */
function createBulkTestProduct(int $firstStock = 10, int $secondStock = 20): array
{
    $category = ProductCategory::query()->create([
        'name' => 'Bulk Test Category',
        'code' => 'BULK-TEST',
        'low_stock_threshold' => 5,
        'status' => 'active',
    ]);

    $medium = Size::query()->create(['name' => 'Bulk Medium', 'sort_order' => 1]);
    $large = Size::query()->create(['name' => 'Bulk Large', 'sort_order' => 2]);

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'item_code' => 'BULK-001',
        'name' => 'Bulk Test Product',
        'color' => 'Black',
        'description' => null,
        'status' => 'active',
    ]);

    $first = ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $medium->id,
        'stock_quantity' => $firstStock,
        'reserved_quantity' => 0,
    ]);

    $second = ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $large->id,
        'stock_quantity' => $secondStock,
        'reserved_quantity' => 0,
    ]);

    return [
        'product' => $product,
        'first' => $first,
        'second' => $second,
    ];
}

test('bulk endpoint applies same action across variants of the same product', function () {
    ['product' => $product, 'first' => $first, 'second' => $second] = createBulkTestProduct(10, 5);

    $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'stock-in',
        'remarks' => 'Bulk delivery',
        'items' => [
            ['product_variant_id' => $first->id, 'quantity' => 10],
            ['product_variant_id' => $second->id, 'quantity' => 5],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('results.0.success', true)
        ->assertJsonPath('results.1.success', true);

    expect($first->fresh()->stock_quantity)->toBe(20)
        ->and($second->fresh()->stock_quantity)->toBe(10)
        ->and(StockMovement::query()->count())->toBe(2);
});

test('bulk endpoint reports partial failures and commits successful rows', function () {
    ['product' => $product, 'first' => $first, 'second' => $second] = createBulkTestProduct(10, 3);

    $response = $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'stock-out',
        'remarks' => 'Bulk shipment',
        'items' => [
            ['product_variant_id' => $first->id, 'quantity' => 5],
            ['product_variant_id' => $second->id, 'quantity' => 10],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('results.0.success'))->toBeTrue()
        ->and($response->json('results.1.success'))->toBeFalse()
        ->and($response->json('results.1.message'))->toBe('Not enough available stock.')
        ->and($first->fresh()->stock_quantity)->toBe(5)
        ->and($second->fresh()->stock_quantity)->toBe(3);
});

test('bulk endpoint requires the action specific permission', function () {
    ['product' => $product, 'first' => $first] = createBulkTestProduct();

    $role = Role::findOrCreate('Stock In Only');
    $role->syncPermissions(['view inventory', 'stock in']);

    $user = User::factory()->create([
        'email' => 'stock-in-only@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->postJson(route('inventory.bulk'), [
            'product_id' => $product->id,
            'action' => 'damage',
            'remarks' => 'Bulk damage',
            'items' => [
                ['product_variant_id' => $first->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();
});

test('bulk endpoint requires view inventory permission', function () {
    ['product' => $product, 'first' => $first] = createBulkTestProduct();

    $role = Role::findOrCreate('Products Only');
    $role->syncPermissions(['view products']);

    $user = User::factory()->create([
        'email' => 'products-only-bulk@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->postJson(route('inventory.bulk'), [
            'product_id' => $product->id,
            'action' => 'stock-in',
            'items' => [
                ['product_variant_id' => $first->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();
});

test('bulk endpoint validates body shape', function () {
    ['product' => $product, 'first' => $first] = createBulkTestProduct();

    $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'invalid-action',
        'items' => [
            ['product_variant_id' => $first->id, 'quantity' => 1],
        ],
    ])->assertUnprocessable();

    $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'stock-in',
        'items' => [],
    ])->assertUnprocessable();
});

test('bulk adjust requires remarks', function () {
    ['product' => $product, 'first' => $first, 'second' => $second] = createBulkTestProduct(10, 5);

    $response = $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'adjust',
        'remarks' => '',
        'items' => [
            ['product_variant_id' => $first->id, 'new_quantity' => 25],
            ['product_variant_id' => $second->id, 'new_quantity' => 15],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('results.0.success'))->toBeFalse()
        ->and($response->json('results.1.success'))->toBeFalse()
        ->and($response->json('results.0.message'))->toBe('Remarks are required for stock adjustment.')
        ->and($first->fresh()->stock_quantity)->toBe(10)
        ->and($second->fresh()->stock_quantity)->toBe(5);
});
