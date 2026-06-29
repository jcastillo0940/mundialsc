@extends('admin.layout')

@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
    <h1 style="margin:0">Puntos por participante</h1>
</div>

<div class="card">
    <form method="get" action="{{ route('admin.player-points') }}" class="grid">
        <div class="row">
            <input name="query" value="{{ $query }}" placeholder="Buscar por nombre, cédula o correo">
            <select name="phase_id">
                <option value="">Todas las fases (predicciones)</option>
                @foreach($phases as $phase)
                    <option value="{{ $phase->id }}" @selected((string)$phase->id === (string)$phaseId)>{{ $phase->name }}</option>
                @endforeach
            </select>
            <button type="submit">Buscar</button>
            @if($query || $phaseId)
                <a href="{{ route('admin.player-points') }}" style="padding:11px 12px;border-radius:12px;background:#20313a;border:1px solid var(--line);text-align:center;color:var(--text)">Limpiar</a>
            @endif
        </div>
    </form>
</div>

<div class="card">
    <h2>Participantes — {{ $users->total() }} encontrados</h2>
    <table>
        <thead>
            <tr>
                <th>Participante</th>
                <th>Cédula</th>
                <th>Sucursal</th>
                <th style="text-align:right">Pts. Facturas</th>
                <th style="text-align:right">Facturas</th>
                <th style="text-align:right">Pts. Pronósticos</th>
                <th style="text-align:right">Aciertos</th>
                <th style="text-align:right">Total puntos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $row)
            <tr @if($row->disqualified_at) style="opacity:.5" @endif>
                <td>
                    <strong>{{ $row->name }}</strong><br>
                    <small class="muted">{{ $row->email }}</small>
                    @if($row->disqualified_at)
                        <br><span class="pill" style="background:#2d1a1a;border-color:#7a2020">descalificado</span>
                    @endif
                </td>
                <td>{{ $row->cedula ?: '—' }}</td>
                <td>{{ $row->branch_name ?: '—' }}</td>
                <td style="text-align:right">
                    <strong>{{ number_format($row->invoice_points) }}</strong>
                </td>
                <td style="text-align:right">
                    <span class="pill">{{ $row->invoice_count }}</span>
                </td>
                <td style="text-align:right">
                    <strong>{{ number_format($row->pred_points) }}</strong>
                </td>
                <td style="text-align:right">
                    <span class="pill">{{ $row->pred_hits }}</span>
                </td>
                <td style="text-align:right">
                    <strong style="font-size:18px;color:#ffd27a">{{ number_format($row->total_points) }}</strong>
                </td>
                <td>
                    <a href="{{ route('admin.player-points.detail', $row->id) }}"
                       style="padding:6px 14px;border-radius:10px;background:#20313a;border:1px solid var(--line);font-size:13px;white-space:nowrap">
                        Ver historial
                    </a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No hay participantes que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
        {{ $users->links() }}
    </div>
</div>
@endsection
