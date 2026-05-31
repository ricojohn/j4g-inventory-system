<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type');
            $table->integer('quantity');
            $table->unsignedInteger('before_stock');
            $table->unsignedInteger('after_stock');
            $table->unsignedInteger('before_reserved');
            $table->unsignedInteger('after_reserved');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('product_variant_id');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
