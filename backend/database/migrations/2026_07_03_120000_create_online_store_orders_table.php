<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('online_store_orders')) {
            return;
        }

        Schema::create('online_store_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('magento_order_id', 80)->nullable()->unique();
            $table->string('increment_id', 80)->nullable()->unique();
            $table->string('customer_email', 191)->index();
            $table->decimal('grand_total', 10, 2);
            $table->string('currency', 10)->nullable();
            $table->string('status', 40)->index();
            $table->dateTime('ordered_at')->nullable()->index();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('credited_at')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_store_orders');
    }
};
