<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_conversations', function (Blueprint $table): void {
            $table->timestamp('last_read_at')->nullable()->after('last_outbound_at');
        });

        Schema::create('facebook_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->default('gray');
            $table->timestamps();
            $table->unique(['branch_id', 'slug']);
        });

        Schema::create('facebook_conversation_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facebook_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facebook_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['facebook_conversation_id', 'facebook_tag_id'], 'fb_conv_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_conversation_tag');
        Schema::dropIfExists('facebook_tags');

        Schema::table('facebook_conversations', function (Blueprint $table): void {
            $table->dropColumn('last_read_at');
        });
    }
};
