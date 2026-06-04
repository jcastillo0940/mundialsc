<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registered_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('registered_invoices', 'registration_source')) {
                $table->string('registration_source', 30)->default('client')->after('dgi_response_payload');
            }

            if (! Schema::hasColumn('registered_invoices', 'registered_by_user_id')) {
                $table->unsignedBigInteger('registered_by_user_id')->nullable()->after('registration_source');
            }

            if (! Schema::hasColumn('registered_invoices', 'assisted_by_fraud_flag_id')) {
                $table->unsignedBigInteger('assisted_by_fraud_flag_id')->nullable()->after('registered_by_user_id');
            }

            if (! Schema::hasColumn('registered_invoices', 'assistance_notes')) {
                $table->text('assistance_notes')->nullable()->after('assisted_by_fraud_flag_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registered_invoices', function (Blueprint $table): void {
            foreach ([
                'assistance_notes',
                'assisted_by_fraud_flag_id',
                'registered_by_user_id',
                'registration_source',
            ] as $column) {
                if (Schema::hasColumn('registered_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
