<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_size', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_category_id', 'size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_size');
    }
};
