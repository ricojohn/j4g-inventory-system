<?php

use App\Enums\MovementType;
use App\Enums\StockStatus;
use App\Events\StockUpdated;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    $this->actingAs(userWithRole('Staff'));
    $this->service = app(InventoryService::class);
});

test('stock in increases quantity and creates movement', function () {
    Event::fake([StockUpdated::class]);

    $variant = createTestVariant(stock: 10);

    $updated = $this->service->stockIn($variant, 5, 'Initial delivery');

    expect($updated->stock_quantity)->toBe(15)
        ->and($updated->reserved_quantity)->toBe(0);

    $movement = StockMovement::query()->latest('id')->first();
    expect($movement->movement_type)->toBe(MovementType::In)
        ->and($movement->before_stock)->toBe(10)
        ->and($movement->after_stock)->toBe(15)
        ->and($movement->created_by)->toBe(User::query()->where('email', 'staff@j4g.test')->value('id'));

    Event::assertDispatched(StockUpdated::class, function (StockUpdated $event) use ($variant) {
        return $event->payload['variant_id'] === $variant->id
            && $event->payload['movement_type'] === 'IN'
            && $event->payload['quantity'] === 5
            && $event->payload['product_name'] === $variant->fresh()->product->name
            && isset($event->payload['user_name'])
            && isset($event->payload['size_name'])
            && isset($event->payload['created_at_human']);
    });
});

test('stock out rejects insufficient available stock', function () {
    $variant = createTestVariant(stock: 10, reserved: 8);

    expect(fn () => $this->service->stockOut($variant, 5))
        ->toThrow(RuntimeException::class, 'Not enough available stock.');
});

test('reserve and release update reserved quantity', function () {
    $variant = createTestVariant(stock: 20, reserved: 0);

    $this->service->reserve($variant, 5);
    $variant->refresh();
    expect($variant->reserved_quantity)->toBe(5);

    $this->service->release($variant, 3);
    $variant->refresh();
    expect($variant->reserved_quantity)->toBe(2);
});

test('adjust requires valid stock level', function () {
    $variant = createTestVariant(stock: 20, reserved: 5);

    $updated = $this->service->adjust($variant, 25, 'Physical count correction');
    expect($updated->stock_quantity)->toBe(25);
});

test('stock status reflects thresholds', function () {
    $variant = createTestVariant(stock: 11, reserved: 0);
    expect($this->service->getStockStatus($variant))->toBe(StockStatus::Ok);

    $variant->update(['stock_quantity' => 10, 'reserved_quantity' => 0]);
    $variant->refresh();
    expect($this->service->getStockStatus($variant))->toBe(StockStatus::LowStock);

    $variant->update(['stock_quantity' => 5, 'reserved_quantity' => 5]);
    $variant->refresh();
    expect($this->service->getStockStatus($variant))->toBe(StockStatus::OutOfStock);
});
