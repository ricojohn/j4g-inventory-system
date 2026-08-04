<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderPayment>
 */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'method' => fake()->randomElement(['cash', 'gcash', 'bank_transfer', 'card']),
            'reference' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => User::factory(),
            'posted_at' => now(),
        ];
    }
}
