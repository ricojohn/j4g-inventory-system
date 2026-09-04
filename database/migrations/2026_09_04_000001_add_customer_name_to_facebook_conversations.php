<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_conversations', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('psid');
            $table->index(['branch_id', 'customer_name'], 'fb_conv_branch_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_conversations', function (Blueprint $table): void {
            $table->dropIndex('fb_conv_branch_name_idx');
            $table->dropColumn('customer_name');
        });
    }
};
