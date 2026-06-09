<?php

use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('staff can upload an image for a color variant', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $product = $cell->color->product;
    $color = $cell->color;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $color->refresh();

    expect($color->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($color->image_path);
});

test('uploading a new image replaces and deletes the previous one', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $product = $cell->color->product;
    $color = $cell->color;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->image('first.jpg'),
        ])
        ->assertOk();

    $firstPath = $color->fresh()->image_path;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->image('second.jpg'),
        ])
        ->assertOk();

    $secondPath = $color->fresh()->image_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('staff can remove a color image', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $product = $cell->color->product;
    $color = $cell->color;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk();

    $path = $color->fresh()->image_path;

    $this->actingAs(userWithRole('Staff'))
        ->deleteJson(route('products.colors.image.destroy', [$product, $color]))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($color->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('viewer cannot upload a color image', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $product = $cell->color->product;
    $color = $cell->color;

    $this->actingAs(userWithRole('Viewer'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertForbidden();
});

test('upload rejects non-image files', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $product = $cell->color->product;
    $color = $cell->color;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('image');

    expect($color->fresh()->image_path)->toBeNull();
});

test('inventory data includes the color image url', function () {
    Storage::fake('public');

    $cell = createTestCell();
    $product = $cell->color->product;
    $color = $cell->color;

    $this->actingAs(userWithRole('Staff'))
        ->post(route('products.colors.image.upload', [$product, $color]), [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])
        ->assertOk();

    $this->actingAs(userWithRole('Staff'))
        ->getJson(route('products.inventory.data', $product))
        ->assertOk()
        ->assertJsonPath('colors.0.image_url', fn ($url) => is_string($url) && str_contains($url, '/storage/'));
});
