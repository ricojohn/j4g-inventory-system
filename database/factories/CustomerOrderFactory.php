<?php

namespace Database\Factories;

use App\Enums\CustomerOrderStatus;
use App\Enums\ProductionStage;
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
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'order_total' => 0,
            'amount_paid' => 0,
            'status' => CustomerOrderStatus::Pending,
            'production_stage' => ProductionStage::Ready,
            'production_blocked' => false,
            'created_by' => User::factory(),
        ];
    }
}
