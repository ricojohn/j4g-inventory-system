<?php

use App\Services\InventoryService;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
    $this->actingAs(userWithRole('Staff'));
    $this->service = app(InventoryService::class);
});

test('second stock out fails after first consumes available stock', function () {
    $variant = createTestVariant(stock: 5, reserved: 0);

    $this->service->stockOut($variant, 5);

    expect(fn () => $this->service->stockOut($variant->fresh(), 1))
        ->toThrow(RuntimeException::class, 'Not enough available stock.');
});
