<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Throwable;

class PublicSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'auth_bg_youtube_id' => SiteSetting::get('auth_bg_youtube_id', ''),
                'auth_logo_url' => SiteSetting::get('auth_logo_url', ''),
                'participant_brands' => SiteSetting::get('participant_brands', ''),
                'hero_video_url' => SiteSetting::get('hero_video_url', ''),
                'seo_site_title' => SiteSetting::get('seo_site_title', ''),
                'seo_meta_description' => SiteSetting::get('seo_meta_description', ''),
                'seo_meta_keywords' => SiteSetting::get('seo_meta_keywords', ''),
                'seo_og_title' => SiteSetting::get('seo_og_title', ''),
                'seo_og_description' => SiteSetting::get('seo_og_description', ''),
                'seo_og_image' => SiteSetting::get('seo_og_image', ''),
                'terms_and_conditions' => SiteSetting::get('terms_and_conditions', $this->officialTerms()),
                'recaptcha_site_key' => config('contest.recaptcha_site_key', ''),
                'allow_google_auth' => (bool) config('contest.allow_google_auth', false),
                'google_client_id' => config('services.google.client_id', ''),
            ]);
        } catch (Throwable) {
            return response()->json([
                'auth_bg_youtube_id' => env('AUTH_BG_YOUTUBE_ID', 'O9diw9_5pys'),
                'auth_logo_url' => env('AUTH_LOGO_URL', ''),
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
La promoción comercial denominada "Polla Mundialista Super Carnes" es organizada por Super Carnes. El objetivo es premiar la fidelidad y el conocimiento futbolístico de nuestros clientes durante la Fase de Grupos de la Copa Mundial de la FIFA 2026.

2. PREMIOS
Se premiará a los participantes que acumulen la mayor cantidad de puntos al finalizar la Fase de Grupos. Los premios no son transferibles ni canjeables por dinero en efectivo.

3. ELEGIBILIDAD Y PARTICIPACIÓN
Podrán participar personas naturales, mayores de 18 años, residentes en Panamá, que posean Cédula de Identidad Personal o Pasaporte vigente. No podrán participar empleados directos de Super Carnes. El registro debe completarse a más tardar el 10 de junio de 2026, incluyendo datos de contacto y pronóstico del total de goles de la fase de grupos.

4. SISTEMA DE PUNTUACIÓN
1 punto por acertar la victoria del equipo Favorito, definido exclusivamente por el Ranking Mundial Masculino de la FIFA vigente al lanzamiento de la promoción.
2 puntos por acertar empate.
3 puntos por acertar victoria del No Favorito.
3 puntos adicionales por acertar marcador exacto.
1 punto adicional por registrar un CUFE válido de factura de Super Carnes mayor a $25.00 sin ITBMS, con fecha de emisión de máximo un día anterior al registro.

5. CRITERIOS DE DESEMPATE
Mayor cantidad de marcadores exactos, mayor volumen de facturas válidas, mayor valor acumulado de compras, mayor cercanía al total de goles de la Fase de Grupos y timestamp de registro de pronósticos.

6. NOTIFICACIÓN Y ENTREGA DEL PREMIO
Los ganadores oficiales serán anunciados el 10 de julio de 2026 en las redes sociales oficiales de Super Carnes. Si un potencial ganador no responde en 24 horas, perderá el derecho al premio y se adjudicará al siguiente participante elegible.

7. PROTECCIÓN DE DATOS Y ACEPTACIÓN
Al registrarse, el participante acepta estos términos y autoriza el uso de sus datos personales exclusivamente para fines del concurso y contacto comercial.

8. VALIDACIÓN DE FACTURAS (CUFE)
Toda factura será verificada estrictamente contra DGI. Solo serán válidos CUFE legítimos, no registrados previamente y que cumplan montos y fechas. El intento de registro de un CUFE falso, alterado o perteneciente a otra persona resultará en descalificación inmediata.
TERMS;
    }
}
