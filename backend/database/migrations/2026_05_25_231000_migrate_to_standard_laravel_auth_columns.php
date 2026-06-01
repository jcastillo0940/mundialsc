<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasName = Schema::hasColumn('users', 'name');
        $hasFullName = Schema::hasColumn('users', 'full_name');
        $hasPassword = Schema::hasColumn('users', 'password');
        $hasPasswordHash = Schema::hasColumn('users', 'password_hash');

        if ($hasName && $hasFullName) {
            DB::statement("UPDATE users SET name = full_name WHERE (name IS NULL OR name = '') AND full_name IS NOT NULL AND full_name != ''");
        }

        if ($hasPassword && $hasPasswordHash) {
            DB::statement("UPDATE users SET password = password_hash WHERE (password IS NULL OR password = '') AND password_hash IS NOT NULL AND password_hash != ''");
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name', 150)->nullable()->default(null)->change();
            }

            if (Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable()->default(null)->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name', 150)->nullable(false)->change();
            }

            if (Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable(false)->change();
            }
        });
    }
};
