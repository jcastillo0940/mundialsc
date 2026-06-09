@extends('admin.layout')

@section('content')
<h1>Vista previa de emails</h1>
<div class="card">
    <h2>Recuperación de contraseña</h2>
    <iframe
        title="Preview reset email"
        style="width:100%;min-height:820px;border:1px solid var(--line);border-radius:16px;background:#fff"
        srcdoc="{{ e($resetPreviewHtml) }}"
    ></iframe>
</div>

<div class="card">
    <h2>Confirmación de newsletter</h2>
    <iframe
        title="Preview newsletter email"
        style="width:100%;min-height:820px;border:1px solid var(--line);border-radius:16px;background:#fff"
        srcdoc="{{ e($newsletterPreviewHtml) }}"
    ></iframe>
</div>
@endsection
