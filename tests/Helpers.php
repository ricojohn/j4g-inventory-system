<?php

use App\Models\Color;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductColorSize;
use App\Models\ProductSize;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

function seedBaseData(): void
{
    (new PermissionSeeder)->run();
}

function userWithRole(string $role): User
{
    $email = match ($role) {
        'Admin' => 'admin@j4g.test',
        'Manager' => 'manager@j4g.test',
        'Staff' => 'staff@j4g.test',
        'Viewer' => 'viewer@j4g.test',
        default => throw new InvalidArgumentException("Unknown role: {$role}"),
    };

    return User::query()->where('email', $email)->firstOrFail();
}

function createTestSupplier(array $overrides = []): Supplier
{
    return Supplier::query()->create(array_merge([
        'name' => 'Test Supplier '.fake()->unique()->numerify('###'),
        'contact' => '+63 912 345 6789',
        'address' => null,
        'notes' => null,
        'status' => 'active',
        'created_by' => userWithRole('Admin')->id,
    ], $overrides));
}

function createTestProduct(array $overrides = []): Product
{
    return Product::query()->create(array_merge([
        'name' => 'Test Product',
        'code' => 'TST'.fake()->unique()->numerify('###'),
        'description' => null,
        'status' => 'active',
    ], $overrides));
}

function attachTestSize(Product $product, string $name = 'M', int $sortOrder = 1): ProductSize
{
    $size = Size::query()->firstOrCreate(['name' => $name]);

    return $product->sizes()->firstOrCreate(
        ['size_id' => $size->id],
        ['sort_order' => $sortOrder],
    );
}

function attachTestColor(Product $product, string $name = 'Black', int $sortOrder = 1): ProductColor
{
    $color = Color::query()->firstOrCreate(['name' => $name]);

    return $product->colors()->firstOrCreate(
        ['color_id' => $color->id],
        ['sort_order' => $sortOrder],
    );
}

function createTestCell(int $stock = 100, int $reserved = 0, ?Product $product = null): ProductColorSize
{
    $product ??= createTestProduct();

    $productSize = $product->sizes()->first() ?? attachTestSize($product);
    $productColor = $product->colors()->first() ?? attachTestColor($product);

    $cell = ProductColorSize::query()->firstOrCreate(
        [
            'product_color_id' => $productColor->id,
            'product_size_id' => $productSize->id,
        ],
        [
            'current_stock' => $stock,
            'reserved_quantity' => $reserved,
            'reorder_level' => 5,
        ]
    );

    $cell->update([
        'current_stock' => $stock,
        'reserved_quantity' => $reserved,
    ]);

    return $cell->fresh(['color.product', 'color.color', 'size.size']);
}

function createTestIntegration(string $provider = 'openai', array $overrides = []): Integration
{
    $defaults = [
        'name' => config("services.ai.providers.{$provider}.label", ucfirst($provider)),
        'status' => 'active',
        'credentials' => ['api_key' => 'sk-test-'.fake()->uuid()],
        'settings' => [
            'default_model' => config("services.{$provider}.default_model"),
            'is_default_provider' => $provider === 'openai',
        ],
        'connected_at' => now(),
        'created_by' => userWithRole('Admin')->id,
    ];

    return Integration::query()->updateOrCreate(
        ['provider' => $provider],
        array_merge($defaults, $overrides)
    );
}

/** @deprecated Use createTestCell() */
function createTestVariant(int $stock = 100, int $reserved = 0): ProductColorSize
{
    return createTestCell($stock, $reserved);
}

function createTestProductWithSizeAndColor(?Product $product = null): array
{
    $product ??= createTestProduct();

    $size = attachTestSize($product, 'L', 1);
    $color = attachTestColor($product, 'White', 1);

    $cell = ProductColorSize::query()->where('product_color_id', $color->id)
        ->where('product_size_id', $size->id)
        ->first();

    return compact('product', 'size', 'color', 'cell');
}
