<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_order_drafts', function (Blueprint $table) {
            $table->id();
            $table->longText('raw_message');
            $table->json('parsed_json')->nullable();
            $table->json('matched_json')->nullable();
            $table->decimal('confidence_score', 4, 2)->nullable();
            $table->string('status')->default('draft');
            $table->string('customer_name')->nullable();
            $table->string('customer_contact')->nullable();
            $table->string('customer_source')->default('facebook');
            $table->text('customer_notes')->nullable();
            $table->foreignId('customer_order_id')->nullable()->constrained('customer_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_order_drafts');
    }
};
