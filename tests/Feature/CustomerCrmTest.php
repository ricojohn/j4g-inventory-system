<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can list and search customers via data endpoint', function () {
    Customer::factory()->create(['name' => 'Alpha Prints']);
    Customer::factory()->create(['name' => 'Beta Wear']);

    $this->actingAs(userWithRole('Staff'))
        ->get(route('customers.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('customers.data', ['search' => 'Alpha']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data');
});

test('staff can create update and view customer', function () {
    $this->actingAs(userWithRole('Staff'))
        ->post(route('customers.store'), [
            'name' => 'J4G Client',
            'handle' => '@j4gclient',
            'contact' => '09171234567',
            'source' => 'facebook',
            'notes' => 'VIP',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('name', 'J4G Client')->first();
    expect($customer)->not->toBeNull();

    $this->actingAs(userWithRole('Staff'))
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('J4G Client');

    $this->actingAs(userWithRole('Staff'))
        ->put(route('customers.update', $customer), [
            'name' => 'J4G Client Updated',
            'handle' => '@j4gclient',
            'contact' => '09171234567',
            'source' => 'viber',
            'notes' => 'VIP',
        ])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->fresh()->name)->toBe('J4G Client Updated');
});

test('viewer can view customers but cannot manage', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole('Viewer'))
        ->get(route('customers.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Viewer'))
        ->get(route('customers.create'))
        ->assertForbidden();

    $this->actingAs(userWithRole('Viewer'))
        ->post(route('customers.store'), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs(userWithRole('Viewer'))
        ->deleteJson(route('customers.destroy', $customer))
        ->assertForbidden();
});

test('customer with orders cannot be deleted', function () {
    $customer = Customer::factory()->create();
    CustomerOrder::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->deleteJson(route('customers.destroy', $customer))
        ->assertStatus(422);

    expect(Customer::query()->find($customer->id))->not->toBeNull();
});

test('order store accepts customer_id and unit prices', function () {
    $cell = createTestCell(100);
    $customer = Customer::factory()->create(['name' => 'Linked Customer']);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'customer_name' => 'Linked Customer',
            'customer_source' => 'walk_in',
            'due_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'product_color_size_id' => $cell->id,
                    'quantity_ordered' => 2,
                    'unit_price' => 150,
                ],
            ],
        ])
        ->assertRedirect();

    $order = CustomerOrder::query()->latest('id')->first();

    expect($order->customer_id)->toBe($customer->id)
        ->and((float) $order->order_total)->toBe(300.0)
        ->and((float) $order->items->first()->unit_price)->toBe(150.0);
});
