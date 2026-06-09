<?php

namespace Database\Factories;

use App\Enums\CustomerOrderStatus;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrder>
 */
class CustomerOrderFactory extends Factory
{
    protected $model = CustomerOrder::class;

    public function definition(): array
    {
        return [
            'customer_name' => fake()->company(),
            'customer_contact' => fake()->optional()->phoneNumber(),
            'customer_source' => fake()->optional()->randomElement(['facebook', 'instagram', 'viber', 'whatsapp', 'walk_in', 'referral', 'other']),
            'customer_notes' => fake()->optional()->sentence(),
            'status' => CustomerOrderStatus::Pending,
            'created_by' => User::factory(),
        ];
    }
}
