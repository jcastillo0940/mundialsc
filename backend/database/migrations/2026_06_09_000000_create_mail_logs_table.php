<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_logs')) {
            Schema::create('mail_logs', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 50);
                $table->string('event_type', 80);
                $table->string('recipient_email', 150)->index();
                $table->string('subject', 255)->nullable();
                $table->string('status', 40)->default('queued');
                $table->unsignedBigInteger('subscriber_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->json('meta')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
