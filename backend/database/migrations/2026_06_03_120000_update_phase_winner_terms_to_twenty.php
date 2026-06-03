<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $terms = DB::table('site_settings')
            ->where('key', 'terms_and_conditions')
            ->value('value');

        if (! is_string($terms) || trim($terms) === '') {
            return;
        }

        $terms = str_replace(
            'Se premiará a los 10 participantes con mayor cantidad de puntos al finalizar la Fase de Grupos y a los 10 participantes con mayor cantidad de puntos al finalizar la Fase Eliminatoria.',
            'Se premiará a los 20 participantes con mayor cantidad de puntos al finalizar la Fase de Grupos y a los 20 participantes con mayor cantidad de puntos al finalizar la Fase Eliminatoria.',
            $terms,
        );

        $terms = str_replace(
            '- Mayor aproximación al total de goles anotados en la Fase de Grupos.',
            '- Mayor aproximación al total de goles anotados en la Fase de Grupos, aplicable solo para desempates de la Fase de Grupos.',
            $terms,
        );

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'terms_and_conditions'],
            ['value' => $terms, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        // Conserva el texto legal vigente; no se revierte automaticamente.
    }
};
