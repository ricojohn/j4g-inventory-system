<?php

namespace Database\Factories;

use App\Enums\SupplierOrderStatus;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrder>
 */
class SupplierOrderFactory extends Factory
{
    protected $model = SupplierOrder::class;

    public function definition(): array
    {
        return [
            'supplier_id' => null,
            'remarks' => fake()->optional()->sentence(),
            'status' => SupplierOrderStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
