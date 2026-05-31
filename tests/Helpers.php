<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SizeSeeder;

function seedBaseData(): void
{
    (new PermissionSeeder)->run();
    (new SizeSeeder)->run();
}

function createTestVariant(int $stock = 100, int $reserved = 0): ProductVariant
{
    $category = ProductCategory::query()->create([
        'name' => 'T-Shirt',
        'code' => 'TSHIRT-TEST',
        'low_stock_threshold' => 10,
        'status' => 'active',
    ]);

    $category->sizes()->sync(Size::query()->pluck('id'));

    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'item_code' => 'ITEM-001',
        'name' => 'Black T-Shirt',
        'color' => 'Black',
        'description' => null,
        'status' => 'active',
    ]);

    $size = Size::query()->firstOrFail();

    return ProductVariant::query()->create([
        'product_id' => $product->id,
        'size_id' => $size->id,
        'stock_quantity' => $stock,
        'reserved_quantity' => $reserved,
    ]);
}

function userWithRole(string $role): User
{
    $emails = [
        'Admin' => 'admin@j4g.test',
        'Manager' => 'manager@j4g.test',
        'Staff' => 'staff@j4g.test',
        'Viewer' => 'viewer@j4g.test',
    ];

    return User::query()->where('email', $emails[$role])->firstOrFail();
}
