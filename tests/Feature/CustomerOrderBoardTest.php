<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\SupplierOrderStatus;
use App\Models\CustomerOrder;
use App\Models\SupplierOrder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can access customer order board page', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('orders.board'))
        ->assertOk()
        ->assertSee('Needs Attention', false)
        ->assertSee('Board', false);
});

test('viewer can view board but cannot fulfill or cancel', function () {
    $cell = createTestCell(100);
    $staff = userWithRole('Staff');

    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'created_by' => $staff->id,
    ]);

    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 5,
        'status' => 'reserved',
    ]);

    $this->actingAs(userWithRole('Viewer'))
        ->get(route('orders.board'))
        ->assertOk();

    $this->actingAs(userWithRole('Viewer'))
        ->getJson(route('orders.board.data'))
        ->assertOk()
        ->assertJsonPath('columns.2.orders.0.can_fulfill', false)
        ->assertJsonPath('columns.2.orders.0.can_cancel', false);
});

test('board data returns five status columns and attention items', function () {
    $cell = createTestCell(5);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Shortage Customer',
            'customer_source' => 'facebook',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 20],
            ],
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->first();
    expect($order->status)->toBe(CustomerOrderStatus::PartiallyReserved);

    $response = $this->actingAs(userWithRole('Staff'))
        ->getJson(route('orders.board.data'))
        ->assertOk()
        ->assertJsonPath('success', true);

    $columns = collect($response->json('columns'));

    expect($columns)->toHaveCount(5)
        ->and($columns->pluck('status')->all())->toBe([
            'pending',
            'partially_reserved',
            'reserved',
            'fulfilled',
            'cancelled',
        ]);

    $partialColumn = $columns->firstWhere('status', 'partially_reserved');
    expect($partialColumn['count'])->toBe(1)
        ->and($partialColumn['orders'][0]['order_number'])->toBe($order->order_number)
        ->and($partialColumn['orders'][0]['has_shortage'])->toBeTrue()
        ->and($partialColumn['orders'][0]['can_fulfill'])->toBeTrue()
        ->and($partialColumn['orders'][0]['can_cancel'])->toBeFalse();

    $attention = collect($response->json('attention'));
    expect($attention)->not->toBeEmpty()
        ->and($attention->first()['has_shortage'])->toBeTrue();
});

test('manager board cards can cancel reserved orders', function () {
    $cell = createTestCell(100);
    $manager = userWithRole('Manager');

    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'created_by' => $manager->id,
    ]);

    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 5,
        'status' => 'reserved',
    ]);

    $this->actingAs($manager)
        ->getJson(route('orders.board.data'))
        ->assertOk()
        ->assertJsonPath('columns.2.orders.0.can_fulfill', true)
        ->assertJsonPath('columns.2.orders.0.can_cancel', true);
});

test('board attention includes draft purchase order blockers', function () {
    $cell = createTestCell(100);
    $staff = userWithRole('Staff');

    $po = SupplierOrder::factory()->create([
        'status' => SupplierOrderStatus::Draft,
        'created_by' => $staff->id,
    ]);

    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'supplier_order_id' => $po->id,
        'created_by' => $staff->id,
    ]);

    $order->items()->create([
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 5,
        'status' => 'reserved',
    ]);

    $this->actingAs($staff)
        ->getJson(route('orders.board.data'))
        ->assertOk()
        ->assertJsonFragment([
            'order_number' => $order->order_number,
            'has_draft_po' => true,
        ]);
});

test('kanban transition rules allow fulfill and cancel only from valid statuses', function () {
    expect(CustomerOrderStatus::Pending->allowsFulfill())->toBeFalse()
        ->and(CustomerOrderStatus::Pending->allowsCancel())->toBeTrue()
        ->and(CustomerOrderStatus::Reserved->allowsFulfill())->toBeTrue()
        ->and(CustomerOrderStatus::PartiallyReserved->allowsFulfill())->toBeTrue()
        ->and(CustomerOrderStatus::Fulfilled->allowsCancel())->toBeFalse()
        ->and(CustomerOrderStatus::Cancelled->kanbanTargets())->toBe([]);
});

test('table index page includes board toggle link', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee(route('orders.board'), false);
});
