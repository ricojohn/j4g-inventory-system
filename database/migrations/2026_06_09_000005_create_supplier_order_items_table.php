<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_color_size_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->foreignId('customer_order_item_id')->nullable()->constrained('customer_order_items')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_items');
    }
};
