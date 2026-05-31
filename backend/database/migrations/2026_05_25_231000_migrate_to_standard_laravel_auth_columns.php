<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copiar datos legacy a columnas estándar de Laravel donde estén vacíos
        DB::statement("UPDATE users SET name = full_name WHERE (name IS NULL OR name = '') AND full_name IS NOT NULL AND full_name != ''");
        DB::statement("UPDATE users SET password = password_hash WHERE (password IS NULL OR password = '') AND password_hash IS NOT NULL AND password_hash != ''");

        // Hacer legacy columns nullable (ya no son la fuente de verdad)
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name', 150)->nullable()->default(null)->change();
            $table->string('password_hash')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name', 150)->nullable(false)->change();
            $table->string('password_hash')->nullable(false)->change();
        });
    }
};
