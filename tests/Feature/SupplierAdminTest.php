<?php

use App\Enums\RecordStatus;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('admin can list suppliers', function () {
    createTestSupplier(['name' => 'Fabric World']);

    $this->actingAs(userWithRole('Admin'))
        ->get(route('admin.suppliers.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Admin'))
        ->getJson(route('admin.suppliers.data'))
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('admin can create supplier with all fields', function () {
    $this->actingAs(userWithRole('Admin'))
        ->post(route('admin.suppliers.store'), [
            'name' => 'Thread Co',
            'contact' => '@threadco',
            'address' => '123 Main St',
            'notes' => 'Net 30 terms',
            'status' => 'active',
        ])
        ->assertRedirect(route('admin.suppliers.index'));

    expect(Supplier::query()->where('name', 'Thread Co')->exists())->toBeTrue();
});

test('duplicate supplier name rejected', function () {
    createTestSupplier(['name' => 'Duplicate Supplier']);

    $this->actingAs(userWithRole('Admin'))
        ->post(route('admin.suppliers.store'), [
            'name' => 'Duplicate Supplier',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('name');
});

test('admin can update supplier', function () {
    $supplier = createTestSupplier(['name' => 'Old Name']);

    $this->actingAs(userWithRole('Admin'))
        ->put(route('admin.suppliers.update', $supplier), [
            'name' => 'New Name',
            'contact' => 'updated@contact.test',
            'status' => 'inactive',
        ])
        ->assertRedirect(route('admin.suppliers.index'));

    expect($supplier->fresh()->name)->toBe('New Name')
        ->and($supplier->fresh()->status)->toBe(RecordStatus::Inactive);
});

test('supplier with POs cannot be deleted', function () {
    $supplier = createTestSupplier();
    SupplierOrder::factory()->create(['supplier_id' => $supplier->id, 'created_by' => userWithRole('Admin')->id]);

    $this->actingAs(userWithRole('Admin'))
        ->deleteJson(route('admin.suppliers.destroy', $supplier))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cannot delete supplier with existing purchase orders.');
});

test('supplier with no POs can be deleted', function () {
    $supplier = createTestSupplier();

    $this->actingAs(userWithRole('Admin'))
        ->deleteJson(route('admin.suppliers.destroy', $supplier))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Supplier::query()->find($supplier->id))->toBeNull();
});

test('inactive supplier rejected on new PO', function () {
    $supplier = createTestSupplier(['status' => 'inactive']);
    $cell = createTestCell();

    $this->actingAs(userWithRole('Staff'))
        ->post(route('supplier-orders.store'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_color_size_id' => $cell->id, 'quantity_ordered' => 5],
            ],
        ])
        ->assertStatus(422);
});

test('manager can manage suppliers', function () {
    $this->actingAs(userWithRole('Manager'))
        ->get(route('admin.suppliers.index'))
        ->assertOk();
});

test('staff cannot access admin suppliers', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('admin.suppliers.index'))
        ->assertForbidden();
});

test('viewer cannot access admin suppliers', function () {
    $this->actingAs(userWithRole('Viewer'))
        ->get(route('admin.suppliers.index'))
        ->assertForbidden();
});
