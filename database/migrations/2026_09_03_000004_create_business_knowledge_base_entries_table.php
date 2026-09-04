<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_knowledge_base_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('category', 80)->default('general');
            $table->string('title');
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'category', 'is_active'], 'kb_branch_cat_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_knowledge_base_entries');
    }
};
