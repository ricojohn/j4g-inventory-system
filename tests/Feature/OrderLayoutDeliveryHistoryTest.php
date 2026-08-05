<?php

use App\Models\CustomerOrder;
use App\Models\OrderActivity;
use App\Models\OrderLayout;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    Storage::fake('public');
});

test('staff can upload and approve a layout version', function () {
    $order = CustomerOrder::factory()->create([
        'created_by' => userWithRole('Admin')->id,
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.layouts.store', $order), [
            'title' => 'Falcons jersey V1',
            'notes' => 'Initial proof',
            'layout_file' => UploadedFile::fake()->image('layout.jpg'),
        ])
        ->assertRedirect();

    $layout = OrderLayout::query()->where('customer_order_id', $order->id)->first();
    expect($layout)->not->toBeNull()
        ->and($layout->version)->toBe(1);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.layouts.approve', [$order, $layout]), [
            'approval_channel' => 'Messenger',
        ])
        ->assertRedirect();

    expect($layout->fresh()->status->value)->toBe('approved')
        ->and(OrderActivity::query()->where('customer_order_id', $order->id)->where('type', 'layout_approved')->exists())->toBeTrue();
});

test('release is blocked when balance remains without override', function () {
    $order = CustomerOrder::factory()->create([
        'order_total' => 1000,
        'amount_paid' => 0,
        'created_by' => userWithRole('Admin')->id,
        'delivery_method' => 'Shop pickup',
        'receiver_name' => 'Mika',
    ]);

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.release', $order), [])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->fresh()->released_at)->toBeNull();

    $this->actingAs(userWithRole('Staff'))
        ->post(route('orders.release', $order), [
            'release_override_reason' => 'Owner approved early release',
        ])
        ->assertRedirect();

    expect($order->fresh()->released_at)->not->toBeNull()
        ->and(OrderActivity::query()->where('customer_order_id', $order->id)->where('type', 'order_released')->exists())->toBeTrue();
});

test('order show tabs render for layouts invoice production delivery and history', function () {
    $order = CustomerOrder::factory()->create([
        'created_by' => userWithRole('Admin')->id,
    ]);

    foreach (['overview', 'items', 'layouts', 'invoice', 'production', 'delivery', 'history'] as $tab) {
        $this->actingAs(userWithRole('Staff'))
            ->get(route('orders.show', ['order' => $order, 'tab' => $tab]))
            ->assertOk();
    }
});
