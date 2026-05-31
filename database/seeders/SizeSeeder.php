<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            'XS',
            'S',
            'M',
            'L',
            'XL',
            '2XL',
            '3XL',
            '4XL',
            '5XL',
            'Regular Kids',
            'Upsize Kids',
            'Regular',
            'Upsize',
            '3XL(2XL)',
            '4XL(3XL)',
            '5XL(4XL)',
            '6XL(5XL)',
            '7XL(6XL)',
        ];

        foreach ($sizes as $index => $name) {
            Size::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1]
            );
        }
    }
}
