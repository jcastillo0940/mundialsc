@extends('admin.layout')

@section('content')
<h1>Partidos</h1>

@if(session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<style>
    .match-card{background:rgba(20,32,40,.92);border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:12px}
    .match-header{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
    .match-teams{font-size:17px;font-weight:700;flex:1}
    .match-meta{color:var(--muted);font-size:12px;margin-top:2px}
    .match-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:700px){.match-actions{grid-template-columns:1fr}}
    .action-box{background:#0f171b;border:1px solid var(--line);border-radius:10px;padding:14px}
    .action-box h4{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
    .action-box.finalize{border-color:#1f6f45}
    .action-box.finalize h4{color:#2ecc71}
    .score-row{display:flex;align-items:center;gap:8px}
    .score-row input{width:60px;min-width:0;text-align:center;font-size:18px;font-weight:700;padding:8px 4px}
    .score-sep{font-size:20px;font-weight:700;color:var(--muted)}
    .btn-finalize{margin-top:10px;width:100%;background:linear-gradient(135deg,#1f8f63,#166344);padding:10px}
    .btn-finalize:disabled{opacity:.4;cursor:not-allowed}
    .status-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
    .status-badge.final{background:#1f3d2a;color:#2ecc71;border:1px solid #1f6f45}
    .status-badge.void{background:#3d1f1f;color:#ff9c8a;border:1px solid #6f2a1f}
    .status-badge.locked{background:#2d2a10;color:#f0c040;border:1px solid #5a4f10}
    .status-badge.scheduled{background:#20313a;color:var(--muted);border:1px solid var(--line)}
    .score-display{font-size:22px;font-weight:700;margin-right:8px}
    summary{cursor:pointer;color:var(--muted);font-size:12px;margin-top:8px;user-select:none}
    details[open] summary{color:var(--accent-soft)}
</style>

<div class="card" style="margin-bottom:18px">
    <h2 style="margin-top:0">Crear partido</h2>
    <form method="post" action="{{ route('admin.matches.store') }}" class="grid">
        @csrf
        <div class="row">
            <select name="phase_id" required>
                @foreach($phases as $phase)
                    <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                @endforeach
            </select>
            <input name="match_number" placeholder="Número de partido">
            <input name="group_label" placeholder="Grupo A, B, C...">
            <input type="datetime-local" name="kickoff_at" required>
        </div>
        <div class="row">
            <select name="home_team_id" required>
                <option value="">Equipo local</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
            <select name="away_team_id" required>
                <option value="">Equipo visitante</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
            <select name="favorite_side" required>
                <option value="none">Sin favorito</option>
                <option value="home">Favorito local</option>
                <option value="away">Favorito visitante</option>
            </select>
        </div>
        <button type="submit">Guardar partido</button>
    </form>
</div>

@if(($pendingApprovals ?? collect())->count())
<div class="card" style="margin-bottom:18px;border-color:#8a6a18">
    <h2 style="margin-top:0">Marcadores pendientes de aprobacion dual</h2>
    <p class="muted">Regla de los 4 ojos: un administrador propone el marcador y otro administrador debe aprobarlo.</p>
    <table>
        <thead><tr><th>Partido</th><th>Marcador propuesto</th><th>Propuesto por</th><th>Accion</th></tr></thead>
        <tbody>
        @foreach($pendingApprovals as $approval)
            <tr>
                <td>{{ $approval->match->homeTeam->name }} vs {{ $approval->match->awayTeam->name }}</td>
                <td>{{ $approval->home_score }} - {{ $approval->away_score }}</td>
                <td>{{ $approval->proposer?->name ?? 'N/D' }}</td>
                <td>
                    <div class="row">
                        <form method="post" action="{{ route('admin.matches.approvals.approve', $approval) }}">
                            @csrf
                            <button type="submit">Aprobar</button>
                        </form>
                        <form method="post" action="{{ route('admin.matches.approvals.reject', $approval) }}">
                            @csrf
                            <input name="notes" placeholder="Motivo de rechazo">
                            <button type="submit" class="danger">Rechazar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px">
    <h2 style="margin:0">Partidos ({{ $matches->count() }})</h2>
    <form method="post" action="{{ route('admin.matches.recalculate-all') }}">
        @csrf
        <button type="submit" class="ghost" style="width:auto;padding:8px 18px;font-size:13px">
            Recalcular todos los partidos finales
        </button>
    </form>
</div>

@foreach($matches as $match)
<div class="match-card">
    <div class="match-header">
        <div style="flex:1">
            <div class="match-teams">
                {{ $match->homeTeam->name }} <span style="color:var(--muted)">vs</span> {{ $match->awayTeam->name }}
            </div>
            <div class="match-meta">
                {{ $match->phase->name }}
                @if($match->group_label) · Grupo {{ $match->group_label }} @endif
                @if($match->match_number) · Partido #{{ $match->match_number }} @endif
                · {{ \Carbon\Carbon::parse($match->kickoff_at)->format('d/m/Y H:i') }}
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            @if($match->status === 'final')
                <span class="score-display">{{ $match->home_score }} - {{ $match->away_score }}</span>
            @endif
            <span class="status-badge {{ $match->status }}">{{ $match->status }}</span>
        </div>
    </div>

    <div class="match-actions">
        {{-- ACCIÓN PRINCIPAL: Finalizar --}}
        <div class="action-box finalize">
            <h4>Finalizar partido</h4>
            <form method="post" action="{{ route('admin.matches.finalize', $match) }}">
                @csrf
                <div class="score-row">
                    <div style="flex:1;text-align:center">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">{{ Str::limit($match->homeTeam->name, 12) }}</div>
                        <input name="home_score" type="number" min="0" max="20"
                               value="{{ $match->status === 'final' ? $match->home_score : '' }}"
                               placeholder="0" required>
                    </div>
                    <span class="score-sep">-</span>
                    <div style="flex:1;text-align:center">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">{{ Str::limit($match->awayTeam->name, 12) }}</div>
                        <input name="away_score" type="number" min="0" max="20"
                               value="{{ $match->status === 'final' ? $match->away_score : '' }}"
                               placeholder="0" required>
                    </div>
                </div>
                <button type="submit" class="btn-finalize">
                    {{ $match->status === 'final' ? 'Corregir marcador y recalcular' : 'Marcar como finalizado' }}
                </button>
            </form>
            @if($match->status === 'final')
                <div style="font-size:11px;color:#2ecc71;margin-top:8px;text-align:center">
                    ✓ {{ $match->predictions()->count() }} predicciones registradas
                </div>
            @endif
        </div>

        {{-- AJUSTE AVANZADO: estado, favorito, API info --}}
        <div class="action-box">
            <h4>Ajuste avanzado</h4>
            <form method="post" action="{{ route('admin.matches.update', $match) }}" class="grid">
                @csrf
                @method('put')
                <div class="row">
                    <div>
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Local</div>
                        <input name="home_score" type="number" min="0" max="20" value="{{ $match->home_score }}" placeholder="0">
                    </div>
                    <div>
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Visitante</div>
                        <input name="away_score" type="number" min="0" max="20" value="{{ $match->away_score }}" placeholder="0">
                    </div>
                    <div>
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Estado</div>
                        <select name="status">
                            <option value="scheduled" @selected($match->status === 'scheduled')>scheduled</option>
                            <option value="locked" @selected($match->status === 'locked')>locked</option>
                            <option value="final" @selected($match->status === 'final')>final</option>
                            <option value="void" @selected($match->status === 'void')>void</option>
                        </select>
                    </div>
                    <div>
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Favorito</div>
                        <select name="favorite_side">
                            <option value="none" @selected($match->favorite_side === 'none')>Ninguno</option>
                            <option value="home" @selected($match->favorite_side === 'home')>Local</option>
                            <option value="away" @selected($match->favorite_side === 'away')>Visitante</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="ghost">Guardar ajuste</button>
            </form>
            <form method="post" action="{{ route('admin.matches.void', $match) }}" style="margin-top:10px">
                @csrf
                <button type="submit" class="danger">Partido anulado / walkover sin puntos</button>
            </form>
            <details>
                <summary>Info API</summary>
                <div style="margin-top:8px;font-size:11px;color:var(--muted);line-height:1.8">
                    Fuente: {{ $match->provider ?: 'manual' }}<br>
                    Fixture: {{ $match->external_fixture_id ?? '-' }}<br>
                    Match: {{ $match->external_match_id ?? '-' }}<br>
                    Provider status: {{ $match->provider_status ?? '-' }}<br>
                    Favorito FIFA: {{ $match->homeTeam->name }} #{{ $match->homeTeam->ranking_fifa ?? '?' }}
                    vs {{ $match->awayTeam->name }} #{{ $match->awayTeam->ranking_fifa ?? '?' }}
                </div>
            </details>
        </div>
    </div>
</div>
@endforeach

@endsection
