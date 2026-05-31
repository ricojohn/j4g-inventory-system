<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductColorSize;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Reversible Adult',
                'code' => 'RJA',
                'sizes' => ['Regular', 'Upsize', '3XL(2XL)', '4XL(3XL)', '5XL(4XL)', '6XL(5XL)', '7XL(6XL)'],
                'colors' => [
                    'BLACK / WHITE', 'SKY B./WHITE', 'NAVY B./WHITE', 'GRAY/WHITE', 'TEAL / WHITE',
                    'TORQUISE T./WHITE', 'MAROON /WHITE', 'ROYAL B./WHITE', 'PUMPKIN /WHITE', 'YELLOW G/ WHITE',
                    'RED / WHITE', 'VIOLET / WHITE', 'ROYAL B./BLACK', 'ORANGE / BLACK', 'RED / BLACK',
                    'YELLOW G / BLACK', 'EME. GREEN/BLACK', 'GREEN/BLACK', 'GRAY / BLACK', 'MOCHA / BLACK',
                    'BLUE / BLACK', 'MAROON/ BLACK', 'YELLOW G / NAVY B', 'ORANGE / NAVY B', 'PUMPKIN / NAVY B',
                    'YELLOW G / ROYAL B', 'RED / ROYAL B', 'SKY B / PINK', 'MAROON/ MOCHA', 'EME. GREEN / GRAY',
                    'EME. GREEN / LEMON', 'EME. GREEN/WHITE', 'SKY B/ BLACK', 'MOCHA / NAVY B', 'TORQUISE / PINK',
                    'VIOLET/ YELLOW G', 'PINK / WHITE', 'PINK/BLACK',
                ],
            ],
            [
                'name' => 'Reversible Kids',
                'code' => 'RJK',
                'sizes' => ['Regular Kids', 'Upsize Kids'],
                'colors' => [
                    'BLACK / WHITE', 'YELLOW G./WHITE', 'RED/WHITE', 'PINK/BLACK', 'MOCHA/BLACK',
                    'ROYAL B./BLACK', 'YELLOW G./BLACK', 'NAVY B./YELLOW G.', 'ROYAL B./WHITE', 'EME. GREEN',
                ],
            ],
            [
                'name' => 'T-Shirt',
                'code' => 'TSC',
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'],
                'colors' => [
                    'BLACK', 'WHITE', 'ORANGE', 'SALMON', 'PEACH', 'LIGHT BLUE', 'AQUA BLUE', 'SUMMER BLUE',
                    'MAROON', 'YELLOW GOLD', 'BANANA YELLOW', 'SMOKE GRAY', 'LAVENDER', 'MINT GREEN',
                    'HAWAIIAN PINK', 'APPLE GREEN', 'TITANIUM', 'VIOLET', 'FUTURE DUST', 'MOUSSE',
                    'ROYAL BLUE', 'NAVY BLUE', 'FUCHSIA', 'MOCHA', 'CHOCO BROWN', 'TEAL GREEN',
                    'EME. GREEN', 'RED', 'LIGHT PINK', 'MOUSSE GREEN', 'CRIMSON RED', 'STONE',
                ],
            ],
            [
                'name' => 'Polo Shirt',
                'code' => 'PSC',
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'],
                'colors' => ['WHITE', 'NAVY BLUE', 'YELLOW GOLD', 'AQUA BLUE', 'SMOKE GRAY'],
            ],
            [
                'name' => 'Dry Fit Long Sleeves',
                'code' => 'DFLS',
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'],
                'colors' => ['ROYAL B.', 'RED', 'BLACK', 'WHITE', 'NAVY BLUE', 'MAROON', 'YELLOW GOLD'],
            ],
            [
                'name' => 'Dry Fit Hoodie',
                'code' => 'DFH',
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'],
                'colors' => ['BLACK'],
            ],
            [
                'name' => 'Dry Fit Short Sleeves',
                'code' => 'DFSL',
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'],
                'colors' => [
                    'WHITE', 'BLACK', 'GRAY', 'EME. GREEN', 'ROYAL BLUE', 'RED', 'VIOLET', 'LEMON',
                    'NAVY BLUE', 'HAWAIIAN', 'YELLOW GOLD', 'NEON ORANGE', 'METALLIC', 'PURPLE',
                ],
            ],
        ];

        DB::transaction(function () use ($products): void {
            Product::query()->delete();

            foreach ($products as $spec) {
                $product = Product::query()->create([
                    'name' => $spec['name'],
                    'code' => $spec['code'],
                    'description' => null,
                    'status' => 'active',
                ]);

                foreach (array_values($spec['sizes']) as $index => $sizeName) {
                    $size = Size::query()->firstOrCreate(['name' => $sizeName]);
                    $product->sizes()->firstOrCreate(
                        ['size_id' => $size->id],
                        ['sort_order' => $index + 1],
                    );
                }

                foreach (array_values($spec['colors']) as $index => $colorName) {
                    $color = Color::query()->firstOrCreate(['name' => $colorName]);
                    $product->colors()->firstOrCreate(
                        ['color_id' => $color->id],
                        ['sort_order' => $index + 1],
                    );
                }

                ProductColorSize::query()
                    ->whereIn('product_color_id', $product->colors()->pluck('id'))
                    ->update(['reorder_level' => 5]);
            }
        });
    }
}
