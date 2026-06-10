<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_campaigns')) {
            return;
        }

        Schema::create('push_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('image_url', 255)->nullable();
            $table->string('button_text', 80)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->enum('audience_type', ['all', 'user', 'branch', 'active'])->default('all');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('only_active_users')->default(false);
            $table->dateTime('send_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_campaigns');
    }
};
