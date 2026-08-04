<?php

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'handle' => fake()->optional()->userName(),
            'contact' => fake()->optional()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
            'source' => fake()->optional()->randomElement(array_column(CustomerSource::cases(), 'value')),
        ];
    }
}
