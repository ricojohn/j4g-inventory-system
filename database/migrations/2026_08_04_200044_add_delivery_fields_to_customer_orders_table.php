<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->string('delivery_method')->nullable()->after('amount_paid');
            $table->string('receiver_name')->nullable()->after('delivery_method');
            $table->string('proof_or_tracking')->nullable()->after('receiver_name');
            $table->timestamp('released_at')->nullable()->after('proof_or_tracking');
            $table->text('release_override_reason')->nullable()->after('released_at');
            $table->foreignId('release_override_by')->nullable()->after('release_override_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('release_override_by');
            $table->dropColumn([
                'delivery_method',
                'receiver_name',
                'proof_or_tracking',
                'released_at',
                'release_override_reason',
            ]);
        });
    }
};
