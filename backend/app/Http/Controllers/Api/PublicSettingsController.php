<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Throwable;

class PublicSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $officialTerms = $this->officialTerms();

            return response()->json([
                'auth_bg_youtube_id' => SiteSetting::get('auth_bg_youtube_id', ''),
                'auth_logo_url' => SiteSetting::get('auth_logo_url', ''),
                'header_logo_url' => SiteSetting::get('header_logo_url', ''),
                'participant_brands' => SiteSetting::get('participant_brands', ''),
                'hero_video_url' => SiteSetting::get('hero_video_url', ''),
                'seo_site_title' => SiteSetting::get('seo_site_title', ''),
                'seo_meta_description' => SiteSetting::get('seo_meta_description', ''),
                'seo_meta_keywords' => SiteSetting::get('seo_meta_keywords', ''),
                'seo_og_title' => SiteSetting::get('seo_og_title', ''),
                'seo_og_description' => SiteSetting::get('seo_og_description', ''),
                'seo_og_image' => SiteSetting::get('seo_og_image', ''),
                'terms_and_conditions' => SiteSetting::get('terms_and_conditions', '') ?: $officialTerms,
                'recaptcha_site_key' => config('contest.recaptcha_site_key', ''),
                'allow_google_auth' => (bool) config('contest.allow_google_auth', false),
                'google_client_id' => config('services.google.client_id', ''),
            ]);
        } catch (Throwable) {
            return response()->json([
                'auth_bg_youtube_id' => env('AUTH_BG_YOUTUBE_ID', 'O9diw9_5pys'),
                'auth_logo_url' => env('AUTH_LOGO_URL', ''),
                'header_logo_url' => env('HEADER_LOGO_URL', ''),
                'participant_brands' => env('PARTICIPANT_BRANDS', ''),
                'hero_video_url' => '',
                'seo_site_title' => '',
                'seo_meta_description' => '',
                'seo_meta_keywords' => '',
                'seo_og_title' => '',
                'seo_og_description' => '',
                'seo_og_image' => '',
                'terms_and_conditions' => $this->officialTerms(),
                'recaptcha_site_key' => '',
                'allow_google_auth' => false,
                'google_client_id' => '',
            ]);
        }
    }

    public function branches(): JsonResponse
    {
        return response()->json([
            'data' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function updateYoutubeId(): JsonResponse
    {
        $validated = request()->validate([
            'youtube_id' => ['required', 'string', 'max:20'],
        ]);

        SiteSetting::set('auth_bg_youtube_id', $validated['youtube_id']);

        return response()->json(['message' => 'Video actualizado.']);
    }

    private function officialTerms(): string
    {
        return <<<'TERMS'
TÉRMINOS Y CONDICIONES: POLLA MUNDIALISTA SUPER CARNES 2026

1. GENERALIDADES DEL CONCURSO
La promoción comercial denominada "Polla Mundialista Super Carnes 2026" es organizada por Super Carnes y tendrá vigencia desde el 11 de junio de 2026 hasta el 20 de julio de 2026, correspondiente al período oficial de la FIFA de Grupos y de Eliminatoria de la Copa Mundial de la FIFA 2026.
Super Carnes podrá modificar la vigencia por razones operativas, técnicas, regulatorias o de fuerza mayor, previa comunicación al público participante.

2. ELEGIBILIDAD
Podrán participar únicamente personas naturales mayores de 18 años, residentes en la República de Panamá, portadoras de cédula de identidad personal o pasaporte vigente, que completen correctamente el proceso de registro.
No podrán participar colaboradores directos de Super Carnes, personas vinculadas directa o indirectamente con la organización, administración o auditoría de la promoción, ni sus familiares dentro del cuarto grado de consanguinidad y segundo de afinidad.

3. MECÁNICA DE PARTICIPACIÓN
La promoción premia el conocimiento y habilidad de los participantes respecto a los resultados deportivos de la Copa Mundial de la FIFA 2026, incluyendo la Fase de Grupos y la Fase Eliminatoria.
Cada participante deberá registrarse en la plataforma oficial antes del inicio del primer partido de la Fase de Grupos, con fecha límite el 10 de junio de 2026 a las 11:59 p. m., completando nombre, documento, correo, teléfono, pronósticos de partidos y predicción del total de goles anotados durante la Fase de Grupos.

4. NATURALEZA DEL CONCURSO
La promoción constituye un concurso basado en habilidad, conocimiento, análisis y destreza deportiva. La asignación de premios se determina exclusivamente conforme al sistema de puntuación establecido en estos términos y condiciones.

5. SISTEMA DE PUNTUACIÓN
Los participantes acumularán puntos conforme a la precisión de sus pronósticos en los partidos de la Fase de Grupos:
- 1 punto por acertar la victoria del equipo Favorito.
- 2 puntos por acertar que el partido finalizará en empate.
- 3 puntos por acertar la victoria del equipo No Favorito.
- 3 puntos adicionales por acertar el marcador exacto.
Para efectos exclusivos de la promoción, se considerará Favorito al equipo que ocupe la mejor posición en el Ranking Mundial Masculino de la FIFA vigente al inicio de la promoción. Esa clasificación permanecerá fija durante toda la Fase de Grupos.
Además, el participante podrá acumular 1 punto adicional por cada factura válida registrada en la plataforma, siempre que la compra sea en Super Carnes, supere USD 25.00 sin ITBMS, haya sido emitida dentro del día calendario previo al registro y el CUFE sea válido.

6. VALIDACIÓN DE FACTURAS
Todas las facturas registradas serán verificadas contra el sistema de la Dirección General de Ingresos (DGI). Solo serán válidas las facturas legítimas, con CUFE verificable, no registradas previamente y que cumplan con los montos y fechas requeridas.
El intento de usar facturas falsas, alteradas, duplicadas o pertenecientes a terceros constituye causal inmediata de descalificación.

7. PREMIOS
Se premiará a los 20 participantes con mayor cantidad de puntos al finalizar la Fase de Grupos y a los 20 participantes con mayor cantidad de puntos al finalizar la Fase Eliminatoria.
Cada ganador de la Fase de Grupos recibirá 1 televisor nuevo de 50 pulgadas, con valor comercial aproximado de B/.500.00.
Cada ganador de la Fase Eliminatoria recibirá 1 televisor nuevo de 50 pulgadas, con valor comercial aproximado de B/.500.00, más un bono de mercancía de B/.200.00.
Los premios no son transferibles, no son canjeables por dinero en efectivo y no podrán ser sustituidos por otros bienes o servicios. Los ganadores podrán reclamar su premio dentro de los cinco días posteriores a la finalización de cada fase en la sucursal de Super Carnes más cercana, presentando su documento de identidad.

8. CRITERIOS DE DESEMPATE
En caso de empate, se aplicarán sucesivamente estos criterios:
- Mayor cantidad de marcadores exactos acertados.
- Mayor cantidad de facturas válidas registradas.
- Mayor monto acumulado en compras válidas.
- Mayor aproximación al total de goles anotados en la Fase de Grupos, aplicable solo para desempates de la Fase de Grupos.
- Fecha y hora de registro más temprana en el sistema oficial de la plataforma.

9. NOTIFICACIÓN Y ENTREGA DE PREMIOS
Los ganadores oficiales de la Fase de Grupos serán anunciados el 29 de junio de 2026. Los ganadores oficiales de la Fase Eliminatoria serán anunciados el 20 de julio de 2026, ambos a través de las redes sociales oficiales de Super Carnes.
Además, serán contactados vía telefónica y/o correo electrónico. Si un ganador potencial no responde dentro de las 24 horas siguientes al primer intento de contacto, perderá el derecho al premio y Super Carnes podrá adjudicarlo al siguiente participante con mayor puntuación.

10. DESCALIFICACIÓN
Super Carnes podrá descalificar inmediatamente a cualquier participante que incumpla estos términos y condiciones, proporcione información falsa o incompleta, intente manipular la plataforma o el sistema de puntuación, registre facturas fraudulentas o pertenecientes a terceros, o realice actos que afecten la transparencia o integridad de la promoción.

11. PROTECCIÓN DE DATOS PERSONALES
Los datos personales suministrados serán utilizados exclusivamente para la administración, desarrollo y ejecución de la promoción, así como para la validación de identidad y entrega de premios, de conformidad con la Ley 81 de 2019 y demás normas aplicables de la República de Panamá.

12. ACEPTACIÓN DE LOS TÉRMINOS Y CONDICIONES
La participación en la promoción implica el conocimiento, aceptación plena e incondicional de los presentes términos y condiciones.
TERMS;
    }
}
