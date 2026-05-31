<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Reversible Adult', 'code' => 'RJA', 'low_stock_threshold' => 10],
            ['name' => 'Reversible Kids', 'code' => 'RJK', 'low_stock_threshold' => 10],
            ['name' => 'T-Shirt', 'code' => 'TSC', 'low_stock_threshold' => 15],
            ['name' => 'Polo Shirt', 'code' => 'PSC', 'low_stock_threshold' => 15],
            ['name' => 'Dry Fit Long Sleeves', 'code' => 'DFLS', 'low_stock_threshold' => 10],
            ['name' => 'Dry Fit Hoodie', 'code' => 'DFH', 'low_stock_threshold' => 8],
            ['name' => 'Dry Fit Short Sleeves', 'code' => 'DFSL', 'low_stock_threshold' => 10],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'low_stock_threshold' => $category['low_stock_threshold'],
                    'status' => 'active',
                ]
            );
        }
    }
}
