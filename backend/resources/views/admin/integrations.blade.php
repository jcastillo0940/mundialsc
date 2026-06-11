@extends('admin.layout')

@section('content')
<h1>Integración Live Score API</h1>

<div class="grid cols-3">
    <div class="card"><strong>Partidos importados</strong><div>{{ $importedMatchesCount }}</div></div>
    <div class="card"><strong>Eventos commentary</strong><div>{{ $commentaryEventsCount }}</div></div>
    <div class="card"><strong>Últimos sync runs</strong><div>{{ $runs->count() }}</div></div>
</div>

<div class="card">
    <h2>Configuración operativa</h2>
    <form method="post" action="{{ route('admin.integrations.live-score') }}" class="grid">
        @csrf
        @method('put')
        <div class="row">
            <select name="is_enabled">
                <option value="1" @selected($settings?->is_enabled)>Encendido</option>
                <option value="0" @selected(! $settings?->is_enabled)>Apagado</option>
            </select>
            <input name="competition_id" value="{{ $settings?->competition_id }}" placeholder="competition_id principal">
            <input name="competition_ids" value="{{ $settings?->competition_ids }}" placeholder="competition_ids separados por coma">
            <input name="season" value="{{ $settings?->season }}" placeholder="Temporada / aÃ±o">
            <input name="lang" value="{{ $settings?->lang ?? 'es' }}" placeholder="Idioma">
        </div>
        <div class="row">
            <input type="date" name="sync_from_date" value="{{ optional($settings?->sync_from_date)->toDateString() }}">
            <input type="date" name="sync_to_date" value="{{ optional($settings?->sync_to_date)->toDateString() }}">
            <select name="auto_sync_commentary">
                <option value="1" @selected($settings?->auto_sync_commentary)>Auto commentary on</option>
                <option value="0" @selected(! $settings?->auto_sync_commentary)>Auto commentary off</option>
            </select>
            <input type="number" min="1" max="168" name="fixtures_sync_interval_hours" value="{{ $settings?->fixtures_sync_interval_hours ?? 24 }}" placeholder="Fixtures cada horas">
            <input type="number" min="1" max="60" name="live_sync_interval_minutes" value="{{ $settings?->live_sync_interval_minutes ?? 3 }}" placeholder="Live cada minutos">
            <input type="number" min="1" max="60" name="commentary_sync_interval_minutes" value="{{ $settings?->commentary_sync_interval_minutes ?? 3 }}" placeholder="Commentary cada minutos">
        </div>
        <button type="submit">Guardar configuración</button>
    </form>
</div>

<div class="card">
    <h2>Sincronización manual</h2>
    <div class="row">
        <form method="post" action="{{ route('admin.integrations.live-score.sync-fixtures') }}">
            @csrf
            <button type="submit">Sync fixtures</button>
        </form>
        <form method="post" action="{{ route('admin.integrations.live-score.sync-live') }}">
            @csrf
            <button type="submit">Sync live</button>
        </form>
        <form method="post" action="{{ route('admin.integrations.live-score.sync-commentary') }}">
            @csrf
            <button type="submit">Sync commentary</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Historial de sincronización</h2>
    <table>
        <thead><tr><th>ID</th><th>Tipo</th><th>Estado</th><th>Creados</th><th>Actualizados</th><th>Saltados</th><th>Error</th><th>Inicio</th><th>Fin</th></tr></thead>
        <tbody>
        @foreach($runs as $run)
            <tr>
                <td>{{ $run->id }}</td>
                <td>{{ $run->sync_type }}</td>
                <td>{{ $run->status }}</td>
                <td>{{ $run->records_created }}</td>
                <td>{{ $run->records_updated }}</td>
                <td>{{ $run->records_skipped }}</td>
                <td>{{ $run->error_message }}</td>
                <td>{{ $run->started_at }}</td>
                <td>{{ $run->finished_at }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
