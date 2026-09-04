<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('active');
            $table->foreignId('automation_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('branches')->insert([
            'code' => 'MAIN',
            'name' => 'Main Branch',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['users', 'products', 'customers', 'customer_orders', 'supplier_orders', 'stock_movements', 'ai_order_drafts', 'integrations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->index()->constrained('branches')->restrictOnDelete();
            });

            DB::table($tableName)->whereNull('branch_id')->update(['branch_id' => 1]);
        }

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->string('external_source')->nullable()->after('customer_source');
            $table->string('external_id')->nullable()->after('external_source');
            $table->text('delivery_address')->nullable()->after('delivery_method');
            $table->string('payment_method_preference')->nullable()->after('delivery_address');
            $table->unique(['branch_id', 'external_source', 'external_id'], 'customer_orders_external_unique');
        });

        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('page_id')->unique();
            $table->string('name');
            $table->string('status')->default('inactive');
            $table->text('access_token')->nullable();
            $table->string('graph_api_version')->default('v23.0');
            $table->boolean('ai_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('facebook_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('facebook_page_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('event_type');
            $table->string('sender_psid')->nullable();
            $table->timestamp('meta_timestamp')->nullable();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['facebook_page_id', 'event_key']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('facebook_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('facebook_page_id')->constrained()->cascadeOnDelete();
            $table->string('psid');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state')->default('collecting');
            $table->string('control_mode')->default('ai');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_over_at')->nullable();
            $table->timestamp('returned_to_ai_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['facebook_page_id', 'psid']);
            $table->index(['branch_id', 'control_mode', 'state']);
        });

        Schema::create('facebook_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('facebook_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facebook_webhook_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meta_message_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('direction');
            $table->string('sender_type');
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->string('status')->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['facebook_conversation_id', 'meta_message_id'], 'facebook_messages_meta_unique');
        });

        Schema::create('messenger_order_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('facebook_conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('psid');
            $table->string('fulfillment_method')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('payment_method_preference')->nullable();
            $table->string('status')->default('collecting');
            $table->unsignedInteger('version')->default(1);
            $table->json('summary_data')->nullable();
            $table->text('summary_text')->nullable();
            $table->string('summary_hash')->nullable();
            $table->timestamp('summarized_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_actor_type')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmation_message_id')->nullable()->constrained('facebook_messages')->nullOnDelete();
            $table->timestamp('confirmation_expires_at')->nullable();
            $table->foreignId('customer_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('messenger_order_draft_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messenger_order_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_color_size_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->json('product_snapshot')->nullable();
            $table->unsignedInteger('available_stock_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['messenger_order_draft_id', 'product_color_size_id'], 'messenger_draft_items_unique');
        });

        Schema::create('customer_channel_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_account_id');
            $table->string('external_user_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_account_id', 'external_user_id'], 'customer_channel_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_channel_identities');
        Schema::dropIfExists('messenger_order_draft_items');
        Schema::dropIfExists('messenger_order_drafts');
        Schema::dropIfExists('facebook_messages');
        Schema::dropIfExists('facebook_conversations');
        Schema::dropIfExists('facebook_webhook_events');
        Schema::dropIfExists('facebook_pages');

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropUnique('customer_orders_external_unique');
            $table->dropColumn(['external_source', 'external_id', 'delivery_address', 'payment_method_preference']);
        });

        foreach (['users', 'products', 'customers', 'customer_orders', 'supplier_orders', 'stock_movements', 'ai_order_drafts', 'integrations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        Schema::dropIfExists('branches');
    }
};
