<?php

use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('dashboard stats endpoint returns all stat keys', function () {
    createTestCell(stock: 100, reserved: 10);

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('dashboard.stats'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'total_products',
                'total_stock',
                'total_reserved',
                'total_available',
                'low_stock_count',
                'out_of_stock_count',
            ],
        ])
        ->assertJsonPath('data.total_stock', 100)
        ->assertJsonPath('data.total_reserved', 10)
        ->assertJsonPath('data.total_available', 90);
});

test('dashboard stats endpoint requires view dashboard permission', function () {
    $role = Role::findOrCreate('No Dashboard');
    $role->syncPermissions(['view products']);

    $user = User::factory()->create([
        'email' => 'no-dashboard@j4g.test',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->getJson(route('dashboard.stats'))
        ->assertForbidden();
});

test('dashboard stats reflect stock movement changes', function () {
    $cell = createTestCell(stock: 10);
    $service = app(InventoryService::class);

    $this->actingAs(userWithRole('Staff'));

    $service->stockIn($cell, 5);

    $this->getJson(route('dashboard.stats'))
        ->assertOk()
        ->assertJsonPath('data.total_stock', 15);
});
