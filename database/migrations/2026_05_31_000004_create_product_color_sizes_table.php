<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_color_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_color_id')->constrained('product_color')->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_size')->cascadeOnDelete();
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->timestamps();

            $table->unique(['product_color_id', 'product_size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_color_sizes');
    }
};
