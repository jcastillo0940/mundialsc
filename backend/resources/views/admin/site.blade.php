@extends('admin.layout')

@section('content')
<h1>Sitio, Hero y SEO</h1>

<div class="card">
    <h2>Configuracion publica</h2>
    <form method="post" action="{{ route('admin.site.update') }}" class="grid">
        @csrf
        @method('put')

        <div class="card">
            <h3>Hero media</h3>
            <div class="row">
                <input name="auth_bg_youtube_id" value="{{ $settings['auth_bg_youtube_id'] }}" placeholder="YouTube ID para auth / hero">
                <input name="auth_logo_url" value="{{ $settings['auth_logo_url'] }}" placeholder="URL del logo para login publico">
                <input name="hero_video_url" value="{{ $settings['hero_video_url'] }}" placeholder="URL MP4/WebM del video hero">
            </div>
        </div>

        <div class="card">
            <h3>Marcas participantes</h3>
            <p><small>Una marca por linea. Formato: Nombre|URL del logo. Si no tienes logo, escribe solo el nombre.</small></p>
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

        <button type="submit">Guardar configuracion del sitio</button>
    </form>
</div>
@endsection
