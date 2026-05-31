<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\Size;
use Illuminate\Database\Seeder;

class CategorySizeSeeder extends Seeder
{
    public function run(): void
    {
        $apparelSizeNames = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];

        $maps = [
            'RJK' => ['Regular Kids', 'Upsize Kids'],
            'RJA' => [
                'Regular',
                'Upsize',
                '3XL(2XL)',
                '4XL(3XL)',
                '5XL(4XL)',
                '6XL(5XL)',
                '7XL(6XL)',
            ],
            'TSC' => $apparelSizeNames,
            'PSC' => $apparelSizeNames,
            'DFLS' => $apparelSizeNames,
            'DFH' => $apparelSizeNames,
            'DFSL' => $apparelSizeNames,
        ];

        foreach ($maps as $code => $sizeNames) {
            $category = ProductCategory::query()->where('code', $code)->first();

            if ($category === null) {
                continue;
            }

            $sizeIds = Size::query()
                ->whereIn('name', $sizeNames)
                ->orderBy('sort_order')
                ->pluck('id');

            $category->sizes()->sync($sizeIds);
        }
    }
}
