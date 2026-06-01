<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prize_tokens')) {
            Schema::create('prize_tokens', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('phase_id');
                $table->unsignedBigInteger('phase_prize_id')->nullable();
                $table->string('token_code', 80)->unique();
                $table->string('prize_title', 150);
                $table->string('prize_type', 120);
                $table->string('status', 30)->default('available');
                $table->unsignedBigInteger('current_promo_winner_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->unsignedBigInteger('reassigned_from_promo_winner_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['phase_id', 'status']);
            });
        }

        Schema::table('promo_winners', function (Blueprint $table): void {
            if (! Schema::hasColumn('promo_winners', 'prize_token_id')) {
                $table->unsignedBigInteger('prize_token_id')->nullable()->unique()->after('phase_id');
            }

            if (! Schema::hasColumn('promo_winners', 'prize_delivered_at')) {
                $table->timestamp('prize_delivered_at')->nullable()->after('responded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promo_winners', function (Blueprint $table): void {
            if (Schema::hasColumn('promo_winners', 'prize_delivered_at')) {
                $table->dropColumn('prize_delivered_at');
            }

            if (Schema::hasColumn('promo_winners', 'prize_token_id')) {
                $table->dropUnique(['prize_token_id']);
                $table->dropColumn('prize_token_id');
            }
        });

        Schema::dropIfExists('prize_tokens');
    }
};
