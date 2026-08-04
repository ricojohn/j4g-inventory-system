<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('customer_notes');
            $table->decimal('order_total', 12, 2)->default(0)->after('due_date');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('order_total');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'order_total', 'amount_paid']);
        });
    }
};
