<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OFFICIAL_TERMS = <<<'TERMS'
TÉRMINOS Y CONDICIONES: POLLA MUNDIALISTA SUPER CARNES 2026

1. GENERALIDADES DEL CONCURSO
La promoción comercial denominada "Polla Mundialista Super Carnes 2026" es organizada por Super Carnes y tendrá vigencia desde el 4 de junio de 2026 hasta el 29 de junio de 2026.
Super Carnes podrá modificar la vigencia por razones operativas, técnicas, regulatorias o de fuerza mayor, previa comunicación al público participante.

2. ELEGIBILIDAD
Podrán participar únicamente personas naturales mayores de 18 años, residentes en la República de Panamá, portadoras de cédula de identidad personal o pasaporte vigente, que completen correctamente el proceso de registro.
No podrán participar colaboradores directos de Super Carnes, personas vinculadas directa o indirectamente con la organización, administración o auditoría de la promoción, ni sus familiares dentro del cuarto grado de consanguinidad y segundo de afinidad.

3. MECÁNICA DE PARTICIPACIÓN
La promoción premia el conocimiento y habilidad de los participantes respecto a los resultados deportivos de los partidos habilitados en la plataforma oficial.
La primera ronda de pronósticos estará habilitada desde el 4 de junio de 2026 hasta el 10 de junio de 2026 a las 11:59 p. m.
La segunda ronda de pronósticos estará habilitada desde el 28 de junio de 2026 hasta el 29 de junio de 2026, una vez definidos los clasificados al cierre de la fase de grupos del 27 de junio de 2026.
Cada participante deberá completar correctamente su registro en la plataforma oficial con nombre, documento, correo y teléfono, además de registrar sus pronósticos dentro de las fechas habilitadas para cada ronda.

4. NATURALEZA DEL CONCURSO
La promoción constituye un concurso basado en habilidad, conocimiento, análisis y destreza deportiva. La asignación de premios se determina exclusivamente conforme al sistema de puntuación establecido en estos términos y condiciones.

5. SISTEMA DE PUNTUACIÓN
Los participantes acumularán puntos conforme a la precisión de sus pronósticos en los partidos habilitados en cada ronda:
- 1 punto por acertar la victoria del equipo Favorito.
- 2 puntos por acertar que el partido finalizará en empate.
- 3 puntos por acertar la victoria del equipo No Favorito.
- 3 puntos adicionales por acertar el marcador exacto.
Para efectos exclusivos de la promoción, se considerará Favorito al equipo que ocupe la mejor posición en el Ranking Mundial Masculino de la FIFA vigente al inicio de la promoción.
Además, el participante podrá acumular 1 punto adicional por cada factura válida registrada en la plataforma, siempre que la compra sea en Super Carnes, supere USD 25.00 sin ITBMS, que la factura haya sido emitida el mismo día del registro o el día calendario inmediatamente anterior, y que el CUFE sea válido.

6. VALIDACIÓN DE FACTURAS
Todas las facturas registradas serán verificadas contra el sistema de la Dirección General de Ingresos (DGI). Solo serán válidas las facturas legítimas, con CUFE verificable, no registradas previamente y que cumplan con los montos y fechas requeridas.
El intento de usar facturas falsas, alteradas, duplicadas o pertenecientes a terceros constituye causal inmediata de descalificación.

7. PREMIOS
Los premios de cada ronda se otorgarán de manera independiente.
Al finalizar la primera ronda, los participantes ubicados entre las posiciones 1 y 10 de la tabla de puntuación recibirán 1 televisor nuevo de 50 pulgadas cada uno.
Al finalizar la primera ronda, los participantes ubicados entre las posiciones 11 y 110 de la tabla de puntuación recibirán 1 balón original cada uno.
Al finalizar la segunda ronda, los 20 participantes con mayor cantidad de puntos recibirán 1 tarjeta de regalo para compras en Super Carnes por USD 200.00 cada uno.
Los premios no son transferibles, no son canjeables por dinero en efectivo y no podrán ser sustituidos por otros bienes o servicios. Los ganadores podrán reclamar su premio dentro de los cinco días calendario posteriores al anuncio oficial de la ronda correspondiente, presentando su documento de identidad.

8. CRITERIOS DE DESEMPATE
En caso de empate, se aplicarán sucesivamente estos criterios:
- Mayor cantidad de marcadores exactos acertados.
- Mayor cantidad de facturas válidas registradas.
- Mayor monto acumulado en compras válidas.
- Mayor aproximación al total de goles anotados en la primera ronda, aplicable solo para desempates de la primera ronda.
- Fecha y hora de registro más temprana en el sistema oficial de la plataforma.

9. NOTIFICACIÓN Y ENTREGA DE PREMIOS
Los ganadores oficiales de cada ronda serán anunciados dentro de los cinco días calendario siguientes al cierre de la ronda correspondiente, a través de las redes sociales oficiales de Super Carnes.
Además, serán contactados vía telefónica y/o correo electrónico. Si un ganador potencial no responde dentro de los cinco días calendario siguientes al primer intento de contacto, perderá el derecho al premio y Super Carnes podrá adjudicarlo al siguiente participante con mayor puntuación.

10. DESCALIFICACIÓN
Super Carnes podrá descalificar inmediatamente a cualquier participante que incumpla estos términos y condiciones, proporcione información falsa o incompleta, intente manipular la plataforma o el sistema de puntuación, registre facturas fraudulentas o pertenecientes a terceros, o realice actos que afecten la transparencia o integridad de la promoción.

11. PROTECCIÓN DE DATOS PERSONALES
Los datos personales suministrados serán utilizados exclusivamente para la administración, desarrollo y ejecución de la promoción, así como para la validación de identidad y entrega de premios, de conformidad con la Ley 81 de 2019 y demás normas aplicables de la República de Panamá.

12. ACEPTACIÓN DE LOS TÉRMINOS Y CONDICIONES
La participación en la promoción implica el conocimiento, aceptación plena e incondicional de los presentes términos y condiciones.
TERMS;

    public function up(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'terms_and_conditions'],
            ['value' => self::OFFICIAL_TERMS, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        // Conserva el texto legal más reciente; no se revierte automáticamente.
    }
};
