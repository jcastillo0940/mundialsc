<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('push_title', 150)->nullable()->after('redemption_enabled');
            $table->text('push_description')->nullable()->after('push_title');
            $table->string('push_image_url', 255)->nullable()->after('push_description');
            $table->string('push_button_text', 80)->nullable()->after('push_image_url');
            $table->string('push_button_url', 255)->nullable()->after('push_button_text');
            $table->enum('push_audience_type', ['all', 'user', 'branch', 'active'])->default('all')->after('push_button_url');
            $table->foreignId('push_target_user_id')->nullable()->after('push_audience_type')->constrained('users')->nullOnDelete();
            $table->foreignId('push_target_branch_id')->nullable()->after('push_target_user_id')->constrained('branches')->nullOnDelete();
            $table->boolean('push_only_active_users')->default(false)->after('push_target_branch_id');
            $table->dateTime('push_send_at')->nullable()->after('push_target_user_id');
            $table->enum('push_status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft')->after('push_send_at');
            $table->unsignedInteger('push_sent_count')->default(0)->after('push_status');
            $table->unsignedInteger('push_failed_count')->default(0)->after('push_sent_count');
            $table->text('push_error_message')->nullable()->after('push_failed_count');
            $table->timestamp('push_sent_at')->nullable()->after('push_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('push_target_user_id');
            $table->dropConstrainedForeignId('push_target_branch_id');
            $table->dropColumn([
                'push_title',
                'push_description',
                'push_image_url',
                'push_button_text',
                'push_button_url',
                'push_audience_type',
                'push_only_active_users',
                'push_send_at',
                'push_status',
                'push_sent_count',
                'push_failed_count',
                'push_error_message',
                'push_sent_at',
            ]);
        });
    }
};
