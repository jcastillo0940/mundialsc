<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'email_otp_code_hash')) {
                $table->string('email_otp_code_hash')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'email_otp_expires_at')) {
                $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp_code_hash');
            }
            if (! Schema::hasColumn('users', 'email_otp_verified_at')) {
                $table->timestamp('email_otp_verified_at')->nullable()->after('email_otp_expires_at');
            }
        });

        Schema::table('registered_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('registered_invoices', 'issuer_ruc')) {
                $table->string('issuer_ruc', 60)->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('registered_invoices', 'issuer_name')) {
                $table->string('issuer_name', 180)->nullable()->after('issuer_ruc');
            }
            if (! Schema::hasColumn('registered_invoices', 'last_reverified_at')) {
                $table->timestamp('last_reverified_at')->nullable()->after('dgi_checked_at');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'previous_hash')) {
                $table->string('previous_hash', 64)->nullable()->after('payload');
            }
            if (! Schema::hasColumn('audit_logs', 'entry_hash')) {
                $table->string('entry_hash', 64)->nullable()->after('previous_hash');
            }
        });

        if (! Schema::hasTable('match_result_approvals')) {
            Schema::create('match_result_approvals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tournament_match_id');
                $table->unsignedBigInteger('proposed_by_user_id');
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->unsignedTinyInteger('home_score');
                $table->unsignedTinyInteger('away_score');
                $table->string('status', 30)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->index(['tournament_match_id', 'status'], 'idx_match_result_approvals_match_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_result_approvals');
    }
};
