<?php

use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('viewer cannot stock in', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => 1,
            'quantity' => 1,
        ])
        ->assertForbidden();
});

test('staff can access inventory stock in route with permission', function () {
    $cell = createTestCell();

    $this->actingAs(userWithRole('Staff'))
        ->postJson(route('inventory.stock-in'), [
            'cell_id' => $cell->id,
            'quantity' => 5,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('admin can access user management', function () {
    $this->actingAs(userWithRole('Admin'))
        ->get(route('admin.users.index'))
        ->assertOk();
});

test('viewer cannot access user management', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
