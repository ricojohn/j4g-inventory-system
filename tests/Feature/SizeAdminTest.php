<?php

use App\Models\Color;
use App\Models\Size;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('admin can list and create sizes', function () {
    $this->actingAs(userWithRole('Admin'))
        ->get(route('admin.sizes.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('admin.sizes.store'), ['name' => 'Admin Size'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Size::query()->where('name', 'Admin Size')->exists())->toBeTrue();
});

test('admin cannot delete size attached to a product', function () {
    $product = createTestProduct();
    $productSize = attachTestSize($product, 'Attached Size', 1);
    $size = Size::query()->findOrFail($productSize->size_id);

    $this->actingAs(userWithRole('Admin'))
        ->deleteJson(route('admin.sizes.destroy', $size))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(Size::query()->whereKey($size->id)->exists())->toBeTrue();
});

test('admin can delete unattached size', function () {
    $size = Size::query()->create(['name' => 'Orphan Size']);

    $this->actingAs(userWithRole('Admin'))
        ->deleteJson(route('admin.sizes.destroy', $size))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Size::query()->whereKey($size->id)->exists())->toBeFalse();
});

test('admin can list and create colors', function () {
    $this->actingAs(userWithRole('Admin'))
        ->get(route('admin.colors.index'))
        ->assertOk();

    $this->actingAs(userWithRole('Admin'))
        ->postJson(route('admin.colors.store'), ['name' => 'Admin Color'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Color::query()->where('name', 'Admin Color')->exists())->toBeTrue();
});

test('admin cannot delete color attached to a product', function () {
    $product = createTestProduct();
    $productColor = attachTestColor($product, 'Attached Color', 1);
    $color = Color::query()->findOrFail($productColor->color_id);

    $this->actingAs(userWithRole('Admin'))
        ->deleteJson(route('admin.colors.destroy', $color))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(Color::query()->whereKey($color->id)->exists())->toBeTrue();
});

test('staff cannot access admin sizes', function () {
    $this->actingAs(userWithRole('Staff'))
        ->get(route('admin.sizes.index'))
        ->assertForbidden();
});
