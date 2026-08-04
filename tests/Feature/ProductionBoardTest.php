<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\ProductionStage;
use App\Models\CustomerOrder;
use App\Models\OrderActivity;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can view production board and board data', function () {
    CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'production_stage' => ProductionStage::Ready,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->get(route('production.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('production.board.data'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['columns']);
});

test('viewer can view production but cannot advance', function () {
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'production_stage' => ProductionStage::Ready,
        'created_by' => userWithRole('Admin')->id,
    ]);

    $this->actingAs(userWithRole('Viewer'))
        ->get(route('production.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Viewer'))
        ->postJson(route('production.advance', $order))
        ->assertForbidden();
});

test('advancing production moves to next stage and logs activity', function () {
    $order = CustomerOrder::factory()->create([
        'status' => CustomerOrderStatus::Reserved,
        'production_stage' => ProductionStage::Ready,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('production.advance', $order))
        ->assertOk()
        ->assertJsonPath('production_stage', ProductionStage::Printing->value);

    expect($order->fresh()->production_stage)->toBe(ProductionStage::Printing)
        ->and(OrderActivity::query()->where('type', 'production_advanced')->exists())->toBeTrue();
});

test('cancelled orders are excluded from production board data', function () {
    CustomerOrder::factory()->create([
        'order_number' => 'CO-ACTIVE',
        'customer_name' => 'Active Prod',
        'status' => CustomerOrderStatus::Reserved,
        'production_stage' => ProductionStage::Cutting,
        'created_by' => userWithRole('Staff')->id,
    ]);

    CustomerOrder::factory()->create([
        'order_number' => 'CO-CANCEL',
        'customer_name' => 'Cancelled Prod',
        'status' => CustomerOrderStatus::Cancelled,
        'production_stage' => ProductionStage::Cutting,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $response = $this->actingAs(userWithRole('Staff'))
        ->getJson(route('production.board.data'))
        ->assertOk();

    $cutting = collect($response->json('columns'))->firstWhere('stage', ProductionStage::Cutting->value);
    $names = collect($cutting['orders'])->pluck('customer_name');

    expect($names)->toContain('Active Prod')
        ->and($names)->not->toContain('Cancelled Prod');
});

test('reserve sets production stage to ready when null', function () {
    $cell = createTestCell(50);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_name' => 'Prod Ready Customer',
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 5, 'unit_price' => 10],
            ],
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->latest('id')->first();

    expect($order->status)->toBe(CustomerOrderStatus::Reserved)
        ->and($order->production_stage)->toBe(ProductionStage::Ready);
});
