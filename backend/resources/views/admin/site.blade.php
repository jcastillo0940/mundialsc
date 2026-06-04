@extends('admin.layout')

@section('content')
<h1>Sitio, Hero y SEO</h1>

<div class="card">
    <h2>Configuracion publica</h2>
    <form method="post" action="{{ route('admin.site.update') }}" class="grid">
        @csrf
        @method('put')

        <div class="card">
            <h3>Login publico y hero</h3>
            <p><small>Usa URLs publicas de imagen PNG, JPG, WebP o SVG.</small></p>
            <div class="row">
                <input name="auth_bg_youtube_id" value="{{ $settings['auth_bg_youtube_id'] }}" placeholder="YouTube ID para auth / hero">
                <input name="hero_video_url" value="{{ $settings['hero_video_url'] }}" placeholder="URL MP4/WebM del video hero">
            </div>
            <div class="row">
                <label style="display:flex;flex-direction:column;gap:4px;flex:1">
                    <small><strong>Logo del header (topbar)</strong> — reemplaza el texto "Super Carnes" en la barra superior de la app.</small>
                    <input name="header_logo_url" value="{{ $settings['header_logo_url'] }}" placeholder="https://...logo-header.png">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px;flex:1">
                    <small><strong>Logo del login</strong> — aparece en la pantalla de acceso. Si no se configura el logo del header, se usa este como respaldo.</small>
                    <input name="auth_logo_url" value="{{ $settings['auth_logo_url'] }}" placeholder="https://...logo-login.png">
                </label>
            </div>
        </div>

        <div class="card">
            <h3>Marcas participantes del slider</h3>
            <p><small>Una marca por linea. Formato: Nombre|URL del logo. Si no tienes logo, escribe solo el nombre. El login las repite automaticamente en un slider infinito.</small></p>
            <textarea name="participant_brands" placeholder="Super Carnes|https://.../logo.png&#10;Importadora Virzi|https://.../logo.png">{{ $settings['participant_brands'] }}</textarea>
        </div>

        <div class="card">
            <h3>SEO base</h3>
            <div class="row">
                <input name="seo_site_title" value="{{ $settings['seo_site_title'] }}" placeholder="Titulo SEO del sitio">
                <input name="seo_meta_keywords" value="{{ $settings['seo_meta_keywords'] }}" placeholder="Keywords separadas por coma">
            </div>
            <textarea name="seo_meta_description" placeholder="Meta description">{{ $settings['seo_meta_description'] }}</textarea>
        </div>

        <div class="card">
            <h3>Open Graph</h3>
            <div class="row">
                <input name="seo_og_title" value="{{ $settings['seo_og_title'] }}" placeholder="OG title">
                <input name="seo_og_image" value="{{ $settings['seo_og_image'] }}" placeholder="URL imagen OG">
            </div>
            <textarea name="seo_og_description" placeholder="OG description">{{ $settings['seo_og_description'] }}</textarea>
        </div>

        <div class="card">
            <h3>Terminos y condiciones</h3>
            <textarea name="terms_and_conditions" placeholder="Texto legal del sitio">{{ $settings['terms_and_conditions'] }}</textarea>
        </div>

        <div class="card">
            <h3>Colores del sitio</h3>
            <p class="muted">Deja un campo vacío para usar el color por defecto del tema. Los cambios aplican en tiempo real al guardar.</p>

            @php
            $themeDefaults = [
                'theme_background'      => ['label' => 'Fondo general',        'default' => '#10131a', 'hint' => 'Pantalla de fondo principal'],
                'theme_surface_low'     => ['label' => 'Superficie cards',     'default' => '#191b23', 'hint' => 'Fondo de cards y paneles'],
                'theme_surface'         => ['label' => 'Superficie base',      'default' => '#1d1f27', 'hint' => 'Superficie intermedia'],
                'theme_surface_high'    => ['label' => 'Superficie elevada',   'default' => '#272a32', 'hint' => 'Elementos sobre cards'],
                'theme_primary'         => ['label' => 'Color primario',       'default' => '#da291c', 'hint' => 'Botones, acentos, activos'],
                'theme_secondary'       => ['label' => 'Color secundario',     'default' => '#dac769', 'hint' => 'Dorado, highlights'],
                'theme_text_main'       => ['label' => 'Texto principal',      'default' => '#e1e2ec', 'hint' => 'Color del texto general'],
                'theme_outline_variant' => ['label' => 'Bordes / separadores', 'default' => '#5c403b', 'hint' => 'Líneas y bordes de cards'],
            ];
            @endphp

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-top:14px">
                @foreach($themeDefaults as $key => $meta)
                @php $current = $settings[$key] ?: $meta['default']; @endphp
                <label style="display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:12px;color:var(--muted)">{{ $meta['label'] }}</span>
                    <div style="display:flex;align-items:center;gap:8px">
                        <input
                            type="color"
                            name="{{ $key }}"
                            value="{{ $settings[$key] ?: $meta['default'] }}"
                            style="width:44px;height:38px;padding:2px;border-radius:8px;cursor:pointer;border:1px solid var(--line);background:#0f171b"
                            oninput="this.nextElementSibling.value=this.value;this.parentElement.nextElementSibling.textContent=this.value"
                        >
                        <input
                            type="text"
                            value="{{ $settings[$key] ?: $meta['default'] }}"
                            maxlength="7"
                            style="flex:1;font-family:monospace;font-size:13px;text-transform:uppercase"
                            oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){this.previousElementSibling.value=this.value}; document.querySelector('[name={{ $key }}]').value=this.value"
                        >
                    </div>
                    <span style="font-size:11px;color:var(--muted)">{{ $meta['hint'] }} · defecto: <code style="color:#ffd27a">{{ $meta['default'] }}</code></span>
                </label>
                @endforeach
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <button type="button" class="ghost" style="width:auto;padding:8px 16px;font-size:13px"
                    onclick="
                        const defaults = {
                            theme_background:'#10131a',theme_surface_low:'#191b23',
                            theme_surface:'#1d1f27',theme_surface_high:'#272a32',
                            theme_primary:'#da291c',theme_secondary:'#dac769',
                            theme_text_main:'#e1e2ec',theme_outline_variant:'#5c403b'
                        };
                        Object.entries(defaults).forEach(([k,v])=>{
                            const el=document.querySelector('[name='+k+']');
                            if(el){el.value=v;const txt=el.parentElement.querySelector('input[type=text]');if(txt)txt.value=v;}
                        });
                    ">
                    Restaurar defaults
                </button>
                <span class="muted" style="font-size:12px">Colores base del tema oscuro Super Carnes</span>
            </div>
        </div>

        <div class="card">
            <h3>Información de contacto</h3>
            <p class="muted">Aparece en la página pública /contacto del sitio.</p>
            <div class="grid" style="margin-top:12px">
                <div class="row">
                    <div>
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Correo electrónico</label>
                        <input name="contact_email" type="email" value="{{ $settings['contact_email'] }}" placeholder="contacto@supercarnes.com">
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Teléfono</label>
                        <input name="contact_phone" value="{{ $settings['contact_phone'] }}" placeholder="(507) 6000-0000">
                    </div>
                </div>
                <div>
                    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Dirección</label>
                    <input name="contact_address" value="{{ $settings['contact_address'] }}" placeholder="Ciudad de Panamá, República de Panamá">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Horario de atención</label>
                    <input name="contact_hours" value="{{ $settings['contact_hours'] }}" placeholder="Lunes a sábado, 8:00 a.m. – 6:00 p.m.">
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Google y reCAPTCHA</h3>
            <p class="muted">Pega aqui las llaves de <strong>reCAPTCHA v2 Invisible</strong> para proteger login, registro y completar registro. La site key viaja al frontend; la secret key queda solo en backend.</p>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:14px;border:1px solid var(--line);border-radius:12px;background:#0f171b;margin-top:12px">
                <input type="checkbox" name="recaptcha_enabled" value="1"
                       @checked(($settings['recaptcha_enabled'] ?? '1') !== '0')
                       style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent)">
                <div>
                    <strong>Habilitar reCAPTCHA en autenticacion</strong>
                    <div class="muted" style="font-size:12px;margin-top:2px">Apagalo temporalmente si Google da errores y necesitas mantener login o registro operativos.</div>
                </div>
            </label>
            <div class="grid" style="margin-top:12px">
                <div>
                    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Site key publica de reCAPTCHA v2 Invisible</label>
                    <input name="recaptcha_site_key" value="{{ $settings['recaptcha_site_key'] }}" placeholder="6Lc...">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:4px">Secret key privada de reCAPTCHA v2 Invisible</label>
                    <input name="recaptcha_secret_key" value="{{ $settings['recaptcha_secret_key'] }}" placeholder="6Lc...">
                </div>
            </div>
            <p class="muted" style="margin-top:10px">La API de Google Sign-In que ya existe sigue usando su propio Client ID. Este bloque nuevo es solo para la seguridad CAPTCHA.</p>
        </div>

        <div class="card">
            <h3>Visibilidad de elementos del sitio</h3>
            <p class="muted">Activa o desactiva elementos de la interfaz sin necesidad de redesplegar.</p>
            <div class="row" style="margin-top:12px">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:14px;border:1px solid var(--line);border-radius:12px;background:#0f171b">
                    <input type="checkbox" name="show_auth_ticker" value="1"
                           @checked($settings['show_auth_ticker'] !== '0')
                           style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent)">
                    <div>
                        <strong>Ticker de marcas (pantalla de login)</strong>
                        <div class="muted" style="font-size:12px;margin-top:2px">Cinta animada con "Super Carnes · Importadora Virzi · Marcas participantes"</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:14px;border:1px solid var(--line);border-radius:12px;background:#0f171b">
                    <input type="checkbox" name="show_scanner_debug" value="1"
                           @checked($settings['show_scanner_debug'] === '1')
                           style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent)">
                    <div>
                        <strong>Info técnica del escáner</strong>
                        <div class="muted" style="font-size:12px;margin-top:2px">Panel expandible con detalles de BarcodeDetector, permisos y User-Agent. Oculto en producción por defecto.</div>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit">Guardar configuracion del sitio</button>
    </form>
</div>
@endsection
