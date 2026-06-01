<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'registration_order_key')) {
                $table->string('registration_order_key', 32)->nullable()->after('registration_completed_at');
                $table->index('registration_order_key', 'idx_users_registration_order_key');
            }
        });

        Schema::table('teams', function (Blueprint $table): void {
            if (! Schema::hasColumn('teams', 'frozen_ranking_fifa')) {
                $table->unsignedInteger('frozen_ranking_fifa')->nullable()->after('ranking_fifa');
            }
            if (! Schema::hasColumn('teams', 'ranking_frozen_at')) {
                $table->timestamp('ranking_frozen_at')->nullable()->after('frozen_ranking_fifa');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
