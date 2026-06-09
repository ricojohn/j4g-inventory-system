<?php

namespace Database\Factories;

use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrderItem>
 */
class SupplierOrderItemFactory extends Factory
{
    protected $model = SupplierOrderItem::class;

    public function definition(): array
    {
        return [
            'supplier_order_id' => SupplierOrder::factory(),
            'product_color_size_id' => 1,
            'quantity_ordered' => fake()->numberBetween(1, 20),
            'quantity_received' => 0,
            'customer_order_item_id' => null,
        ];
    }
}
