<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
            'status' => RecordStatus::Active,
            'created_by' => User::factory(),
        ];
    }
}
