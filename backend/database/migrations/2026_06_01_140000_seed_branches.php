<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $branches = [
            ['name' => 'Canto del Llano',      'code' => 'CANTO_LLANO'],
            ['name' => 'Albrook',               'code' => 'ALBROOK'],
            ['name' => 'Aguadulce',             'code' => 'AGUADULCE'],
            ['name' => 'Las Tablas',            'code' => 'LAS_TABLAS'],
            ['name' => 'Chitré',                'code' => 'CHITRE'],
            ['name' => 'Penonomé',              'code' => 'PENONOME'],
            ['name' => 'La Chorrera',           'code' => 'LA_CHORRERA'],
            ['name' => 'El Trapichito',         'code' => 'TRAPICHITO'],
            ['name' => 'Vista Alegre',          'code' => 'VISTA_ALEGRE'],
            ['name' => 'Calle 10 Santiago',     'code' => 'CALLE10_SGO'],
            ['name' => 'Central Santiago',      'code' => 'CENTRAL_SGO'],
            ['name' => 'Mercado Santiago',      'code' => 'MERCADO_SGO'],
            ['name' => 'Plaza Palermo Santiago','code' => 'PALERMO_SGO'],
        ];

        foreach ($branches as $branch) {
            Branch::query()->firstOrCreate(['code' => $branch['code']], [
                'name'      => $branch['name'],
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Branch::query()->whereIn('code', [
            'CANTO_LLANO','ALBROOK','AGUADULCE','LAS_TABLAS','CHITRE',
            'PENONOME','LA_CHORRERA','TRAPICHITO','VISTA_ALEGRE',
            'CALLE10_SGO','CENTRAL_SGO','MERCADO_SGO','PALERMO_SGO',
        ])->delete();
    }
};
