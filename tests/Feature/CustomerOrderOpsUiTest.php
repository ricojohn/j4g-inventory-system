<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerSource;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Support\OrderOpsPresenter;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

it('includes next action fields on the orders data endpoint', function () {
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Pending,
        'customer_source' => CustomerSource::Facebook,
        'created_by' => userWithRole('Admin')->id,
    ]);

    $cell = createTestCell(10, 0);
    CustomerOrderItem::query()->create([
        'customer_order_id' => $order->id,
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 5,
        'quantity_reserved' => 0,
        'status' => 'pending',
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('orders.data'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'order_number',
                    'next_action_label',
                    'next_action_tag',
                    'show_url',
                ],
            ],
        ]);
});

it('shows tabbed order detail overview', function () {
    $order = CustomerOrder::factory()->create([
        'created_by' => userWithRole('Admin')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Next best action')
        ->assertSee('Order readiness')
        ->assertSee('Items & stock');
});

it('resolves shortage next actions via presenter', function () {
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::PartiallyReserved,
        'created_by' => userWithRole('Admin')->id,
        'supplier_order_id' => null,
    ]);

    $cell = createTestCell(10, 0);
    CustomerOrderItem::query()->create([
        'customer_order_id' => $order->id,
        'product_color_size_id' => $cell->id,
        'quantity_ordered' => 10,
        'quantity_reserved' => 2,
        'status' => 'partially_reserved',
    ]);

    $order->load(['items', 'supplierOrder']);
    $action = app(OrderOpsPresenter::class)->nextAction($order);

    expect($action['tag'])->toBe('Shortage')
        ->and($action['priority'])->toBe('high');
});
