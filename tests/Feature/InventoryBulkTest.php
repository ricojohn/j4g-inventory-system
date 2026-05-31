<?php

use App\Models\Product;
use App\Models\ProductColorSize;
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
 * @return array{product: Product, first: ProductColorSize, second: ProductColorSize}
 */
function createBulkTestProduct(int $firstStock = 10, int $secondStock = 20): array
{
    $product = createTestProduct(['code' => 'BLK', 'name' => 'Bulk Test Product']);

    $medium = attachTestSize($product, 'Bulk Medium', 1);
    $large = attachTestSize($product, 'Bulk Large', 2);
    $color = attachTestColor($product, 'Black', 1);

    $first = ProductColorSize::query()->where('product_color_id', $color->id)->where('product_size_id', $medium->id)->first();
    $second = ProductColorSize::query()->where('product_color_id', $color->id)->where('product_size_id', $large->id)->first();

    $first->update(['current_stock' => $firstStock]);
    $second->update(['current_stock' => $secondStock]);

    return [
        'product' => $product,
        'first' => $first->fresh(),
        'second' => $second->fresh(),
    ];
}

test('bulk endpoint applies same action across cells of the same product', function () {
    ['product' => $product, 'first' => $first, 'second' => $second] = createBulkTestProduct(10, 5);

    $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'stock-in',
        'remarks' => 'Bulk delivery',
        'items' => [
            ['cell_id' => $first->id, 'quantity' => 10],
            ['cell_id' => $second->id, 'quantity' => 5],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('results.0.success', true)
        ->assertJsonPath('results.1.success', true);

    expect($first->fresh()->current_stock)->toBe(20)
        ->and($second->fresh()->current_stock)->toBe(10)
        ->and(StockMovement::query()->count())->toBe(2);
});

test('bulk endpoint reports partial failures and commits successful rows', function () {
    ['product' => $product, 'first' => $first, 'second' => $second] = createBulkTestProduct(10, 3);

    $response = $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'stock-out',
        'remarks' => 'Bulk shipment',
        'items' => [
            ['cell_id' => $first->id, 'quantity' => 5],
            ['cell_id' => $second->id, 'quantity' => 10],
        ],
    ])->assertOk();

    expect($response->json('results.0.success'))->toBeTrue()
        ->and($response->json('results.1.success'))->toBeFalse()
        ->and($first->fresh()->current_stock)->toBe(5)
        ->and($second->fresh()->current_stock)->toBe(3);
});

test('bulk endpoint requires the action specific permission', function () {
    ['product' => $product, 'first' => $first] = createBulkTestProduct();

    $role = Role::findOrCreate('Stock In Only');
    $role->syncPermissions(['view inventory', 'stock in']);

    $user = User::factory()->create(['email' => 'stock-in-only@j4g.test', 'status' => 'active']);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->postJson(route('inventory.bulk'), [
            'product_id' => $product->id,
            'action' => 'damage',
            'items' => [['cell_id' => $first->id, 'quantity' => 1]],
        ])
        ->assertForbidden();
});

test('bulk endpoint validates body shape', function () {
    ['product' => $product, 'first' => $first] = createBulkTestProduct();

    $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'invalid-action',
        'items' => [['cell_id' => $first->id, 'quantity' => 1]],
    ])->assertUnprocessable();
});

test('bulk adjust requires remarks', function () {
    ['product' => $product, 'first' => $first, 'second' => $second] = createBulkTestProduct(10, 5);

    $response = $this->postJson(route('inventory.bulk'), [
        'product_id' => $product->id,
        'action' => 'adjust',
        'remarks' => '',
        'items' => [
            ['cell_id' => $first->id, 'new_quantity' => 25],
            ['cell_id' => $second->id, 'new_quantity' => 15],
        ],
    ])->assertOk();

    expect($response->json('results.0.success'))->toBeFalse()
        ->and($first->fresh()->current_stock)->toBe(10);
});
