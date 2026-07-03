<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_store_order_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_store_order_id')->nullable()->constrained('online_store_orders')->nullOnDelete();
            $table->string('increment_id')->index();
            $table->string('submitted_email')->nullable();
            $table->string('magento_order_id')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->string('currency', 12)->default('USD');
            $table->string('magento_status')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_store_order_claims');
    }
};
