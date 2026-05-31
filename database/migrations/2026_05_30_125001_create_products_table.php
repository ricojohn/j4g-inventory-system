<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('item_code')->unique();
            $table->string('name');
            $table->string('color');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('product_category_id');
            $table->index('item_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
