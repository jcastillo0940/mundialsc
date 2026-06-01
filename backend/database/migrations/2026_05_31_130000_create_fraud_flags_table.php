<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fraud_flags')) {
            return;
        }

        Schema::create('fraud_flags', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('registered_invoice_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->string('flag_type', 80);
            $table->string('source', 30)->default('system');
            $table->string('severity', 20)->default('medium');
            $table->string('status', 30)->default('open');
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity'], 'idx_fraud_flags_status_severity');
            $table->index(['user_id', 'created_at'], 'idx_fraud_flags_user_created');
            $table->index(['flag_type', 'created_at'], 'idx_fraud_flags_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
    }
};
