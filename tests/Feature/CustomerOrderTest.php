<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\MovementType;
use App\Models\CustomerOrder;
use App\Models\StockMovement;
use App\Models\SupplierOrder;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('order auto reserved on store', function () {
    $cell = createTestCell(100);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Test Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 10],
            ],
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->first();
    $cell->refresh();

    expect($order->status)->toBe(CustomerOrderStatus::Reserved)
        ->and($order->items->first()->quantity_reserved)->toBe(10)
        ->and($cell->reserved_quantity)->toBe(10);
});

test('full stock becomes reserved with no PO created', function () {
    $cell = createTestCell(50);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Full Stock Customer',
            'customer_source' => 'walk_in',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 20],
            ],
        ]);

    $order = CustomerOrder::query()->first();

    expect($order->status)->toBe(CustomerOrderStatus::Reserved)
        ->and($order->supplier_order_id)->toBeNull()
        ->and(SupplierOrder::query()->count())->toBe(0);
});

test('partial stock becomes partially reserved and redirects to PO create', function () {
    $cell = createTestCell(8);

    $response = $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Partial Customer',
            'customer_source' => 'viber',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 20],
            ],
        ]);

    $order = CustomerOrder::query()->first();

    $response->assertRedirect(route('supplier-orders.create', ['from_order_id' => $order->id]))
        ->assertSessionHas('shortage_notice');

    expect($order->status)->toBe(CustomerOrderStatus::PartiallyReserved)
        ->and($order->supplier_order_id)->toBeNull()
        ->and(SupplierOrder::query()->count())->toBe(0)
        ->and($order->items->first()->quantity_reserved)->toBe(8);
});

test('fulfill calls release and stock out for each item', function () {
    $cell = createTestCell(100);
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $item = $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 10,
        'quantity_reserved' => 10,
        'status' => 'reserved',
    ]);

    $cell->update(['reserved_quantity' => 10]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('orders.fulfill', $order))
        ->assertOk()
        ->assertJsonPath('status', 'fulfilled');

    $cell->refresh();
    $item->refresh();

    expect($cell->current_stock)->toBe(90)
        ->and($cell->reserved_quantity)->toBe(0)
        ->and($item->status)->toBe('fulfilled');

    expect(StockMovement::query()->where('type', MovementType::Release)->count())->toBe(1)
        ->and(StockMovement::query()->where('type', MovementType::Out)->count())->toBe(1);
});

test('fulfill blocked when status not reserved', function () {
    $cell = createTestCell();
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Pending,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('orders.fulfill', $order))
        ->assertStatus(422);
});

test('cancel releases reserved stock', function () {
    $cell = createTestCell(100);
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 10,
        'quantity_reserved' => 10,
        'status' => 'reserved',
    ]);
    $cell->update(['reserved_quantity' => 10]);

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('orders.cancel', $order))
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');

    expect($cell->fresh()->reserved_quantity)->toBe(0);
});

test('cancel blocked when fulfilled', function () {
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Fulfilled,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('orders.cancel', $order))
        ->assertStatus(422);
});

test('source filter on orders data returns correct results', function () {
    CustomerOrder::factory()->create([
        'customer_name' => 'Facebook Buyer',
        'customer_source' => 'facebook',
        'created_by' => userWithRole('Staff')->id,
    ]);
    CustomerOrder::factory()->create([
        'customer_name' => 'Walk-in Buyer',
        'customer_source' => 'walk_in',
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('orders.data', ['source' => 'facebook']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('unauthorized role gets 403 for customer orders', function () {
    $role = Role::findOrCreate('No Orders');
    $role->syncPermissions(['view dashboard']);

    $user = User::factory()->create([
        'email' => 'no-orders@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertForbidden();
});
