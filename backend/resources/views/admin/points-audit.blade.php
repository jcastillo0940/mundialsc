@extends('admin.layout')

@section('content')
<h1>Auditoria de puntos</h1>

<div class="grid cols-3">
    <div class="card">
        <span class="muted">Movimientos auditados</span>
        <div class="metric">{{ $summary['entries'] }}</div>
    </div>
    <div class="card">
        <span class="muted">Puntos acreditados</span>
        <div class="metric">{{ $summary['goals_awarded'] }}</div>
    </div>
    <div class="card">
        <span class="muted">Debitos de puntos</span>
        <div class="metric">{{ $summary['goals_debited'] }}</div>
    </div>
</div>

<div class="card">
    <h2>Filtros de auditoria</h2>
    <p class="muted">Consulta por usuario, origen, fase, regla aplicada y rango de fechas para entender por que se acreditaron o descontaron puntos.</p>

    <form method="get" action="{{ route('admin.points-audit') }}" class="grid">
        <div class="row">
            <input name="query" value="{{ $filters['query'] }}" placeholder="Buscar por nombre, correo o cedula">
            <select name="source">
                <option value="all" @selected($filters['source'] === 'all')>Todos los origenes</option>
                <option value="invoice" @selected($filters['source'] === 'invoice')>Facturas</option>
                <option value="prediction" @selected($filters['source'] === 'prediction')>Pronosticos</option>
                <option value="redemption" @selected($filters['source'] === 'redemption')>Canjes</option>
                <option value="game" @selected($filters['source'] === 'game')>Juegos</option>
                <option value="wallet" @selected($filters['source'] === 'wallet')>Solo wallet movements</option>
            </select>
            <select name="phase_id">
                <option value="" @selected($filters['phase_id'] === '')>Todas las fases</option>
                @foreach($phases as $phase)
                    <option value="{{ $phase->id }}" @selected((string) $phase->id === $filters['phase_id'])>{{ $phase->name }}</option>
                @endforeach
            </select>
            <select name="impact">
                <option value="all" @selected($filters['impact'] === 'all')>Todos los impactos</option>
                <option value="gain" @selected($filters['impact'] === 'gain')>Solo acreditaciones</option>
                <option value="loss" @selected($filters['impact'] === 'loss')>Solo debitos</option>
            </select>
        </div>
        <div class="row">
            <input name="rule_code" value="{{ $filters['rule_code'] }}" placeholder="Regla aplicada o codigo interno">
            <input name="date_from" type="date" value="{{ $filters['date_from'] }}">
            <input name="date_to" type="date" value="{{ $filters['date_to'] }}">
            <button type="submit">Aplicar filtros</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Bitacora completa</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Participante</th>
                <th>Origen</th>
                <th>Fase</th>
                <th>Impacto</th>
                <th>Regla aplicada</th>
                <th>Referencia</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>
                    <strong>{{ optional($row['occurred_at'])->format('Y-m-d H:i') }}</strong><br>
                    <small class="muted">{{ $row['movement_type'] }}</small>
                </td>
                <td>
                    <strong>{{ $row['user_name'] }}</strong><br>
                    <small>{{ $row['user_email'] ?: 'sin correo' }}</small><br>
                    <small class="muted">ID {{ $row['user_id'] }}</small>
                </td>
                <td>
                    <span class="pill">{{ $row['source'] }}</span><br>
                    <small>{{ $row['reason'] }}</small>
                </td>
                <td>{{ $row['phase_name'] ?: 'No aplica' }}</td>
                <td>
                    <div>Goles: <strong style="color: {{ $row['goals_delta'] >= 0 ? '#8ee2b1' : '#ffb4a8' }}">{{ $row['goals_delta'] > 0 ? '+' : '' }}{{ $row['goals_delta'] }}</strong></div>
                    <div>Tiros: <strong style="color: {{ $row['shots_delta'] >= 0 ? '#8ee2b1' : '#ffb4a8' }}">{{ $row['shots_delta'] > 0 ? '+' : '' }}{{ $row['shots_delta'] }}</strong></div>
                </td>
                <td>
                    <strong>{{ $row['rule_label'] }}</strong><br>
                    <small class="muted">{{ $row['rule_code'] }}</small>
                </td>
                <td>{{ $row['reference'] }}</td>
                <td>
                    <details>
                        <summary>Ver detalle</summary>
                        <div style="margin-top:10px">
                            <div><strong>Explicacion:</strong> {{ $row['reason'] }}</div>
                            <pre style="white-space:pre-wrap;background:#0f171b;padding:10px;border-radius:10px;max-width:560px;overflow:auto">{{ json_encode($row['detail'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="8">No hay movimientos que cumplan los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
