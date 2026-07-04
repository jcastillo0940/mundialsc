<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_store_order_claims', function (Blueprint $table): void {
            if (! Schema::hasColumn('online_store_order_claims', 'source')) {
                $table->string('source', 40)->default('client_frontend')->after('status')->index();
            }

            if (! Schema::hasColumn('online_store_order_claims', 'source_reported_at')) {
                $table->timestamp('source_reported_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('online_store_order_claims', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('reviewed_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_store_order_claims', function (Blueprint $table): void {
            if (Schema::hasColumn('online_store_order_claims', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }

            if (Schema::hasColumn('online_store_order_claims', 'source_reported_at')) {
                $table->dropColumn('source_reported_at');
            }

            if (Schema::hasColumn('online_store_order_claims', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
