<?php

namespace Database\Factories;

use App\Enums\OrderLayoutStatus;
use App\Models\CustomerOrder;
use App\Models\OrderLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLayout>
 */
class OrderLayoutFactory extends Factory
{
    protected $model = OrderLayout::class;

    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'version' => 1,
            'title' => fake()->words(3, true),
            'notes' => fake()->optional()->sentence(),
            'file_path' => 'order-layouts/'.fake()->uuid().'.pdf',
            'status' => OrderLayoutStatus::Draft,
        ];
    }
}
