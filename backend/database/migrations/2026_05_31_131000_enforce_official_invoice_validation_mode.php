<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_goal_settings')) {
            return;
        }

        DB::table('invoice_goal_settings')->update([
            'validation_mode' => 'api',
            'max_invoice_age_days' => 1,
            'goal_value' => 1,
            'min_purchase_amount' => 25,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
