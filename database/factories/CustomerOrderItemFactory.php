<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderItem>
 */
class CustomerOrderItemFactory extends Factory
{
    protected $model = CustomerOrderItem::class;

    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'product_color_size_id' => 1,
            'quantity_ordered' => fake()->numberBetween(1, 20),
            'quantity_reserved' => 0,
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'status' => 'pending',
        ];
    }
}
