<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\MovementType;
use App\Enums\SupplierOrderStatus;
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

function createSupplierOrderWithItem(int $qty = 10, $cell = null): array
{
    $cell ??= createTestCell();

    $po = SupplierOrder::query()->create([
        'remarks' => null,
        'status' => SupplierOrderStatus::Draft,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $item = $po->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => $qty,
        'quantity_received' => 0,
        'customer_order_item_id' => null,
    ]);

    return compact('po', 'item', 'cell');
}

test('receiveItems calls stockIn via InventoryService', function () {
    ['po' => $po, 'item' => $item, 'cell' => $cell] = createSupplierOrderWithItem(10);
    $beforeStock = $cell->current_stock;

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$item->id => 6],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'partially_received');

    $cell->refresh();
    $item->refresh();

    expect($cell->current_stock)->toBe($beforeStock + 6)
        ->and($item->quantity_received)->toBe(6);

    expect(StockMovement::query()->where('type', MovementType::In)->count())->toBe(1);
});

test('after receipt waiting orders auto reserved FIFO', function () {
    $cell = createTestCell(0);

    $firstOrder = CustomerOrder::query()->create([
        'customer_name' => 'First Customer',
        'status' => CustomerOrderStatus::Pending,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $firstOrder->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    $this->travel(1)->second();

    $secondOrder = CustomerOrder::query()->create([
        'customer_name' => 'Second Customer',
        'status' => CustomerOrderStatus::Pending,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $secondOrder->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    ['po' => $po, 'item' => $poItem] = createSupplierOrderWithItem(7, $cell);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$poItem->id => 7],
        ])
        ->assertOk();

    $firstOrder->refresh();
    $secondOrder->refresh();
    $cell->refresh();

    expect($firstOrder->status)->toBe(CustomerOrderStatus::Reserved)
        ->and($firstOrder->items->first()->quantity_reserved)->toBe(5)
        ->and($secondOrder->status)->toBe(CustomerOrderStatus::PartiallyReserved)
        ->and($secondOrder->items->first()->quantity_reserved)->toBe(2)
        ->and($cell->reserved_quantity)->toBe(7);
});

test('fully reserved orders auto reserved after receipt but not fulfilled', function () {
    $cell = createTestCell(0);

    $order = CustomerOrder::query()->create([
        'customer_name' => 'Ready Customer',
        'status' => CustomerOrderStatus::Pending,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    ['po' => $po, 'item' => $poItem, 'cell' => $cell] = createSupplierOrderWithItem(5, $cell);

    $response = $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$poItem->id => 5],
        ])
        ->assertOk();

    $order->refresh();

    expect($order->status)->toBe(CustomerOrderStatus::Reserved)
        ->and($order->items->first()->quantity_reserved)->toBe(5)
        ->and($response->json('reserved_orders'))->toBe(1);
});

test('partially reserved orders stay partially reserved after receipt', function () {
    $cell = createTestCell(3);

    $order = CustomerOrder::query()->create([
        'customer_name' => 'Partial Customer',
        'status' => CustomerOrderStatus::Pending,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 10,
        'quantity_reserved' => 3,
        'status' => 'partially_reserved',
    ]);
    $cell->update(['reserved_quantity' => 3]);

    ['po' => $po, 'item' => $poItem] = createSupplierOrderWithItem(5, $cell);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$poItem->id => 5],
        ])
        ->assertOk();

    expect($order->fresh()->status)->toBe(CustomerOrderStatus::PartiallyReserved);
});

test('partial receipt updates PO status to partially received', function () {
    ['po' => $po, 'item' => $item] = createSupplierOrderWithItem(20);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$item->id => 8],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'partially_received');

    expect($po->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived);
});

test('full receipt updates PO status to received', function () {
    ['po' => $po, 'item' => $item] = createSupplierOrderWithItem(15);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$item->id => 15],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'received');

    expect($po->fresh()->status)->toBe(SupplierOrderStatus::Received);
});

test('receive response includes reserved orders count', function () {
    $cell = createTestCell(0);

    $order = CustomerOrder::query()->create([
        'customer_name' => 'Auto Fulfill',
        'status' => CustomerOrderStatus::Pending,
        'created_by' => userWithRole('Staff')->id,
    ]);
    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 3,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    ['po' => $po, 'item' => $poItem] = createSupplierOrderWithItem(3, $cell);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('supplier-orders.receive', $po), [
            'qtys' => [$poItem->id => 3],
        ])
        ->assertOk()
        ->assertJsonStructure(['reserved_orders', 'message']);
});

test('store with from_order_id links customer order and item references', function () {
    $cell = createTestCell(0);

    $customerOrder = CustomerOrder::query()->create([
        'customer_name' => 'Shortage Customer',
        'status' => CustomerOrderStatus::PartiallyReserved,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $customerItem = $customerOrder->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 10,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    $response = $this->actingAs(userWithRole('Staff'))
        ->post(route('supplier-orders.store'), [
            'from_order_id' => $customerOrder->id,
            'items' => [
                [
                    'product_color_size_id' => $cell->id,
                    'quantity_ordered' => 10,
                    'customer_order_item_id' => $customerItem->id,
                ],
            ],
        ]);

    $po = SupplierOrder::query()->with('items')->first();

    $response->assertRedirect(route('supplier-orders.show', $po));

    expect($customerOrder->fresh()->supplier_order_id)->toBe($po->id)
        ->and($po->items)->toHaveCount(1)
        ->and($po->items->first()->customer_order_item_id)->toBe($customerItem->id)
        ->and($po->items->first()->quantity_ordered)->toBe(10);
});

test('unauthorized role gets 403 for supplier orders', function () {
    $role = Role::findOrCreate('No Supplier Orders');
    $role->syncPermissions(['view dashboard']);

    $user = User::factory()->create([
        'email' => 'no-supplier-orders@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($user)
        ->get(route('supplier-orders.index'))
        ->assertForbidden();
});
