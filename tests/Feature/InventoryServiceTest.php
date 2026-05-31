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

    $cell = createTestCell(stock: 10);

    $updated = $this->service->stockIn($cell, 5, 'Initial delivery');

    expect($updated->current_stock)->toBe(15)
        ->and($updated->reserved_quantity)->toBe(0);

    $movement = StockMovement::query()->latest('id')->first();
    expect($movement->type)->toBe(MovementType::In)
        ->and($movement->before_stock)->toBe(10)
        ->and($movement->after_stock)->toBe(15)
        ->and($movement->created_by)->toBe(User::query()->where('email', 'staff@j4g.test')->value('id'));

    Event::assertDispatched(StockUpdated::class, function (StockUpdated $event) use ($cell) {
        return $event->payload['cell_id'] === $cell->id
            && $event->payload['movement_type'] === 'IN'
            && $event->payload['quantity'] === 5
            && $event->payload['product_name'] === $cell->fresh()->color->product->name
            && isset($event->payload['user_name'])
            && isset($event->payload['size_name'])
            && isset($event->payload['created_at_human']);
    });
});

test('stock out rejects insufficient available stock', function () {
    $cell = createTestCell(stock: 10, reserved: 8);

    expect(fn () => $this->service->stockOut($cell, 5))
        ->toThrow(RuntimeException::class, 'Not enough available stock.');
});

test('reserve and release update reserved quantity', function () {
    $cell = createTestCell(stock: 20, reserved: 0);

    $this->service->reserve($cell, 5);
    $cell->refresh();
    expect($cell->reserved_quantity)->toBe(5);

    $this->service->release($cell, 3);
    $cell->refresh();
    expect($cell->reserved_quantity)->toBe(2);
});

test('adjust requires valid stock level', function () {
    $cell = createTestCell(stock: 20, reserved: 5);

    $updated = $this->service->adjust($cell, 25, 'Physical count correction');
    expect($updated->current_stock)->toBe(25);
});

test('stock status reflects reorder level', function () {
    $cell = createTestCell(stock: 11, reserved: 0);
    $cell->update(['reorder_level' => 10]);
    $cell->refresh();
    expect($this->service->getStockStatus($cell))->toBe(StockStatus::Ok);

    $cell->update(['current_stock' => 10, 'reserved_quantity' => 0]);
    $cell->refresh();
    expect($this->service->getStockStatus($cell))->toBe(StockStatus::LowStock);

    $cell->update(['current_stock' => 5, 'reserved_quantity' => 5]);
    $cell->refresh();
    expect($this->service->getStockStatus($cell))->toBe(StockStatus::OutOfStock);
});
