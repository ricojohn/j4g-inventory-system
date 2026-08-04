<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Models\CustomerOrder;
use App\Models\OrderActivity;
use App\Models\OrderPayment;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can view finance page', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('finance.index'))
        ->assertOk()
        ->assertSee('Open receivables');
});

test('viewer can view finance but cannot record payment', function () {
    $order = CustomerOrder::factory()->create([
        'order_total' => 1000,
        'amount_paid' => 0,
        'created_by' => userWithRole('Admin')->id,
    ]);

    $this->actingAs(userWithRole('Viewer'))
        ->get(route('finance.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Viewer'))
        ->post(route('orders.payments.store', $order), [
            'amount' => 100,
            'method' => 'cash',
        ])
        ->assertForbidden();
});

test('recording payment increments amount paid and logs activity', function () {
    $order = CustomerOrder::factory()->create([
        'order_total' => 1000,
        'amount_paid' => 0,
        'status' => CustomerOrderStatus::Reserved,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('orders.payments.store', $order), [
            'amount' => 400,
            'method' => 'gcash',
            'reference' => 'GC-1',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('payment_status', OrderPaymentStatus::PartialDp->value);

    $order->refresh();

    expect((float) $order->amount_paid)->toBe(400.0)
        ->and($order->balanceDue())->toBe(600.0)
        ->and($order->paymentStatus())->toBe(OrderPaymentStatus::PartialDp)
        ->and(OrderPayment::query()->count())->toBe(1)
        ->and(OrderActivity::query()->where('type', 'payment_recorded')->exists())->toBeTrue();
});

test('reversing payment decreases amount paid', function () {
    $order = CustomerOrder::factory()->create([
        'order_total' => 500,
        'amount_paid' => 500,
        'created_by' => userWithRole('Staff')->id,
    ]);

    $payment = OrderPayment::factory()->create([
        'customer_order_id' => $order->id,
        'amount' => 500,
        'recorded_by' => userWithRole('Staff')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('orders.payments.reverse', [$order, $payment]), [
            'reversal_reason' => 'Entered twice',
        ])
        ->assertOk()
        ->assertJsonPath('payment_status', OrderPaymentStatus::Unpaid->value);

    expect((float) $order->fresh()->amount_paid)->toBe(0.0)
        ->and($payment->fresh()->isReversed())->toBeTrue();
});

test('finance page lists open balances', function () {
    CustomerOrder::factory()->create([
        'order_number' => 'CO-OPEN1',
        'customer_name' => 'Open Balance Co',
        'order_total' => 800,
        'amount_paid' => 100,
        'created_by' => userWithRole('Admin')->id,
    ]);

    CustomerOrder::factory()->create([
        'order_number' => 'CO-PAID1',
        'customer_name' => 'Paid Co',
        'order_total' => 200,
        'amount_paid' => 200,
        'created_by' => userWithRole('Admin')->id,
    ]);

    $this->actingAs(userWithRole('Manager'))
        ->get(route('finance.index'))
        ->assertOk()
        ->assertSee('Open Balance Co')
        ->assertDontSee('Paid Co');
});
