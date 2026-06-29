@extends('admin.layout')

@section('content')
<h1>Integración Live Score API</h1>

<style>
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px}
.badge-ok{background:#1a5c3a;color:#4ade80}
.badge-fail{background:#5c1a1a;color:#f87171}
.badge-running{background:#1a3a5c;color:#60a5fa}
.badge-fixtures{background:#2d2a10;color:#facc15}
.badge-live{background:#1a2e1a;color:#4ade80}
.badge-commentary{background:#2a1a3a;color:#c084fc}
.sync-stat{display:flex;flex-direction:column;gap:2px}
.sync-stat small{color:var(--muted);font-size:11px}
.sync-stat strong{font-size:15px}
.log-error{color:#f87171;font-size:12px;max-width:280px;word-break:break-word}
.log-context{color:var(--muted);font-size:11px;font-family:monospace;max-width:200px;word-break:break-word}
.duration{color:var(--muted);font-size:12px}
.filter-tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.filter-tabs a{padding:6px 14px;border-radius:20px;font-size:12px;border:1px solid var(--line);color:var(--muted);text-decoration:none}
.filter-tabs a.active{background:var(--accent);color:#fff;border-color:var(--accent)}
</style>

<div class="grid cols-3" style="margin-bottom:18px">
    <div class="card">
        <div class="sync-stat">
            <small>Partidos importados</small>
            <strong>{{ $importedMatchesCount }}</strong>
        </div>
    </div>
    <div class="card" style="{{ $failedRunsCount > 0 ? 'border-color:#7f1d1d' : '' }}">
        <div class="sync-stat">
            <small>Fallos últimas 24h</small>
            <strong style="{{ $failedRunsCount > 0 ? 'color:#f87171' : 'color:#4ade80' }}">{{ $failedRunsCount }}</strong>
        </div>
    </div>
</div>

<div class="grid cols-3" style="margin-bottom:18px">
    <div class="card">
        <div class="sync-stat">
            <small>Último sync <strong style="color:#facc15">fixtures</strong></small>
            @if($lastFixturesRun)
                <strong>{{ $lastFixturesRun->finished_at?->diffForHumans() }}</strong>
                <small>+{{ $lastFixturesRun->records_created }} creados · {{ $lastFixturesRun->records_updated }} actualizados</small>
            @else
                <strong class="muted">Nunca</strong>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="sync-stat">
            <small>Último sync <strong style="color:#4ade80">live</strong></small>
            @if($lastLiveRun)
                <strong>{{ $lastLiveRun->finished_at?->diffForHumans() }}</strong>
                <small>{{ $lastLiveRun->records_updated }} actualizados</small>
            @else
                <strong class="muted">Nunca</strong>
            @endif
        </div>
    </div>
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
            <input name="season" value="{{ $settings?->season }}" placeholder="Temporada / año">
            <input name="lang" value="{{ $settings?->lang ?? 'es' }}" placeholder="Idioma">
        </div>
        <div class="row">
            <input type="date" name="sync_from_date" value="{{ optional($settings?->sync_from_date)->toDateString() }}">
            <input type="date" name="sync_to_date" value="{{ optional($settings?->sync_to_date)->toDateString() }}">
            <input type="number" min="1" max="168" name="fixtures_sync_interval_hours" value="{{ $settings?->fixtures_sync_interval_hours ?? 24 }}" placeholder="Fixtures cada horas">
            <input type="number" min="1" max="60" name="live_sync_interval_minutes" value="{{ $settings?->live_sync_interval_minutes ?? 3 }}" placeholder="Live cada minutos">
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
    </div>
</div>

<div class="card">
    <h2>Log de sincronización</h2>

    <div class="filter-tabs">
        <a href="{{ route('admin.integrations') }}" class="{{ !$filterType ? 'active' : '' }}">Todos</a>
        <a href="{{ route('admin.integrations', ['type' => 'live']) }}" class="{{ $filterType === 'live' ? 'active' : '' }}">Live</a>
        <a href="{{ route('admin.integrations', ['type' => 'fixtures']) }}" class="{{ $filterType === 'fixtures' ? 'active' : '' }}">Fixtures</a>
    </div>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Creados</th>
                    <th>Actualizados</th>
                    <th>Saltados</th>
                    <th>Duración</th>
                    <th>Inicio</th>
                    <th>Error / Contexto</th>
                </tr>
            </thead>
            <tbody>
            @forelse($runs as $run)
                @php
                    $duration = $run->started_at && $run->finished_at
                        ? $run->started_at->diffInSeconds($run->finished_at).'s'
                        : '—';
                    $typeBadge = match($run->sync_type) {
                        'live' => 'badge-live',
                        'fixtures' => 'badge-fixtures',
                        default => '',
                    };
                    $statusBadge = match($run->status) {
                        'completed' => 'badge-ok',
                        'failed' => 'badge-fail',
                        default => 'badge-running',
                    };
                @endphp
                <tr>
                    <td class="muted">{{ $run->id }}</td>
                    <td><span class="badge {{ $typeBadge }}">{{ $run->sync_type }}</span></td>
                    <td><span class="badge {{ $statusBadge }}">{{ $run->status }}</span></td>
                    <td>{{ $run->records_created ?? '—' }}</td>
                    <td>{{ $run->records_updated ?? '—' }}</td>
                    <td>{{ $run->records_skipped ?? '—' }}</td>
                    <td class="duration">{{ $duration }}</td>
                    <td class="muted" style="white-space:nowrap;font-size:12px">{{ $run->started_at?->format('d/m H:i:s') }}</td>
                    <td>
                        @if($run->error_message)
                            <span class="log-error">{{ Str::limit($run->error_message, 120) }}</span>
                        @elseif($run->context)
                            <span class="log-context">{{ json_encode($run->context, JSON_UNESCAPED_UNICODE) }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
