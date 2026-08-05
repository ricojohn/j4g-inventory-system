<?php

use App\Enums\OrderLayoutStatus;
use App\Models\CustomerOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        CustomerOrder::query()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('id')
            ->each(function (CustomerOrder $order): void {
                $hasLayouts = DB::table('order_layouts')
                    ->where('customer_order_id', $order->id)
                    ->exists();

                if ($hasLayouts) {
                    return;
                }

                DB::table('order_layouts')->insert([
                    'customer_order_id' => $order->id,
                    'version' => 1,
                    'title' => 'Initial layout',
                    'notes' => null,
                    'file_path' => $order->image_path,
                    'status' => OrderLayoutStatus::Draft->value,
                    'approved_at' => null,
                    'approved_by' => null,
                    'approval_channel' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Keep layout rows; original image_path values remain on customer_orders.
    }
};
