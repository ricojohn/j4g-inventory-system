<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('customer_notes');
        });

        Schema::table('ai_order_drafts', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('customer_notes');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('ai_order_drafts', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
