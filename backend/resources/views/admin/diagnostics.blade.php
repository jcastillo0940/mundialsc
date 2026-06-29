@extends('admin.layout')

@php
    $categoryLabels = [
        'favorite' => 'Ganó el favorito',
        'underdog' => 'Ganó el no favorito (sorpresa)',
        'draw' => 'Empate',
        'sin_favorito' => 'Sin favorito definido',
    ];
@endphp

@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
    <h1 style="margin:0">Diagnóstico del sistema</h1>
    <a href="{{ route('admin.diagnostics.export') }}" style="padding:11px 16px;border-radius:12px;background:linear-gradient(135deg,#ff8a4d,#d85b2a);font-weight:600;color:#1a0f08">⬇ Descargar como texto</a>
</div>
<p class="muted">Generado: {{ $report['generated_at']->format('Y-m-d H:i') }} @if($report['active_phase']) · Fase activa de puntuación: {{ $report['active_phase']->name }} @endif</p>

{{-- 1. Participantes --}}
<div class="card">
    <h2>1) Participantes</h2>
    <div class="grid cols-3">
        <div class="card"><strong>Usuarios totales</strong><div class="metric">{{ number_format($report['participants']['total_users']) }}</div><small class="muted">{{ $report['participants']['admins'] }} admins + {{ $report['participants']['clients'] }} clientes</small></div>
        <div class="card"><strong>Clientes activos</strong><div class="metric">{{ number_format($report['participants']['active_clients']) }}</div><small class="muted">{{ $report['participants']['inactive_clients'] }} inactivos · {{ $report['participants']['disqualified_clients'] }} descalificados</small></div>
        <div class="card"><strong>Registro completado</strong><div class="metric">{{ number_format($report['participants']['registration_completed']) }}</div><small class="muted">de {{ $report['participants']['clients'] }} clientes</small></div>
        <div class="card"><strong>Con al menos 1 predicción</strong><div class="metric">{{ number_format($report['participants']['clients_with_predictions']) }}</div></div>
        <div class="card"><strong>Sin ninguna predicción</strong><div class="metric">{{ number_format($report['participants']['clients_without_predictions']) }}</div></div>
        <div class="card"><strong>Nunca iniciaron sesión*</strong><div class="metric">{{ number_format($report['participants']['clients_never_logged_in']) }}</div><small class="muted">*campo de último acceso, puede no actualizarse en todos los flujos</small></div>
    </div>
</div>

{{-- 2. Facturas --}}
<div class="card">
    <h2>2) Facturas registradas</h2>
    <div class="grid cols-3">
        <div class="card"><strong>Total de facturas</strong><div class="metric">{{ number_format($report['invoices']['total']) }}</div></div>
        <div class="card"><strong>Monto total aprobado</strong><div class="metric">B/. {{ number_format($report['invoices']['approved_amount'], 2) }}</div></div>
        <div class="card"><strong>Clientes con facturas</strong><div class="metric">{{ number_format($report['invoices']['users_with_invoices']) }}</div><small class="muted">de {{ $report['participants']['clients'] }} clientes</small></div>
        <div class="card"><strong>Sin ninguna factura</strong><div class="metric">{{ number_format($report['invoices']['users_without_invoices']) }}</div></div>
        <div class="card"><strong>Con 1 a 4 facturas</strong><div class="metric">{{ number_format($report['invoices']['users_with_1_to_4']) }}</div></div>
        <div class="card"><strong>Con más de 4 facturas</strong><div class="metric">{{ number_format($report['invoices']['users_with_5_or_more']) }}</div><small class="muted">máximo individual: {{ $report['invoices']['max_invoices_single_user'] }}</small></div>
    </div>
    <table style="margin-top:12px">
        <thead><tr><th>Estado de validación</th><th style="text-align:right">Facturas</th></tr></thead>
        <tbody>
        @foreach($report['invoices']['by_validation_status'] as $status => $count)
            <tr><td>{{ $status }}</td><td style="text-align:right">{{ number_format($count) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- 3. Predicciones --}}
<div class="card">
    <h2>3) Predicciones de partidos</h2>
    <div class="grid cols-3">
        <div class="card"><strong>Partidos finalizados</strong><div class="metric">{{ $report['predictions']['finalized_matches'] }}</div><small class="muted">{{ $report['predictions']['scheduled_matches'] }} por jugarse</small></div>
        <div class="card"><strong>Marcador exacto</strong><div class="metric">{{ number_format($report['predictions']['exact_total']) }}</div></div>
        <div class="card"><strong>Solo resultado (sin exacto)</strong><div class="metric">{{ number_format($report['predictions']['outcome_total']) }}</div></div>
        <div class="card"><strong>Fallos completos</strong><div class="metric">{{ number_format($report['predictions']['miss_total']) }}</div></div>
        <div class="card"><strong>Predicciones evaluadas</strong><div class="metric">{{ number_format($report['predictions']['graded_predictions']) }}</div></div>
        <div class="card"><strong>Predicciones pendientes</strong><div class="metric">{{ number_format($report['predictions']['pending_predictions']) }}</div><small class="muted">partidos aún sin jugar</small></div>
    </div>
    <table style="margin-top:12px">
        <thead><tr><th>Tipo de partido</th><th style="text-align:right">Partidos</th><th style="text-align:right">Exactos</th><th style="text-align:right">Solo resultado</th><th style="text-align:right">Fallos</th></tr></thead>
        <tbody>
        @forelse($report['predictions']['categories'] as $key => $cat)
            <tr>
                <td>{{ $categoryLabels[$key] ?? $key }}</td>
                <td style="text-align:right">{{ $cat['matches'] }}</td>
                <td style="text-align:right">{{ number_format($cat['exact']) }}</td>
                <td style="text-align:right">{{ number_format($cat['outcome']) }}</td>
                <td style="text-align:right">{{ number_format($cat['miss']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Aún no hay partidos finalizados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- 4. Puntos --}}
@if($report['points'])
<div class="card">
    <h2>4) Puntos otorgados — {{ $report['points']['phase_name'] }}</h2>
    <div class="grid cols-3">
        <div class="card"><strong>Puntos por predicciones</strong><div class="metric">{{ number_format($report['points']['prediction_points_total']) }}</div></div>
        <div class="card"><strong>Puntos por facturas</strong><div class="metric">{{ number_format($report['points']['invoice_points_total']) }}</div></div>
        <div class="card"><strong>Total repartido</strong><div class="metric" style="color:#ffd27a">{{ number_format($report['points']['prediction_points_total'] + $report['points']['invoice_points_total']) }}</div></div>
        <div class="card"><strong>Clientes con puntos</strong><div class="metric">{{ number_format($report['points']['clients_with_points']) }}</div></div>
        <div class="card"><strong>Clientes sin puntos</strong><div class="metric">{{ number_format($report['points']['clients_without_points']) }}</div></div>
    </div>
</div>
@endif

{{-- 5. Top ranking --}}
@if($report['top_ranking']->isNotEmpty())
<div class="card">
    <h2>5) Top 10 del ranking actual</h2>
    <table>
        <thead><tr><th>#</th><th>Participante</th><th style="text-align:right">Total</th><th style="text-align:right">Pred.</th><th style="text-align:right">Fact.</th><th style="text-align:right">Exactos</th><th style="text-align:right">Facturas</th></tr></thead>
        <tbody>
        @foreach($report['top_ranking'] as $row)
            <tr>
                <td>{{ $row['position'] }}</td>
                <td>{{ $row['full_name'] }}</td>
                <td style="text-align:right"><strong>{{ number_format($row['total_points']) }}</strong></td>
                <td style="text-align:right">{{ number_format($row['prediction_points']) }}</td>
                <td style="text-align:right">{{ number_format($row['invoice_points']) }}</td>
                <td style="text-align:right">{{ $row['exact_hits'] }}</td>
                <td style="text-align:right">{{ $row['invoice_count'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- 6. Antifraude --}}
<div class="card">
    <h2>6) Alertas antifraude (informativo)</h2>
    <div class="grid cols-3">
        <div class="card"><strong>Alertas totales</strong><div class="metric">{{ number_format($report['fraud']['total_flags']) }}</div></div>
        <div class="card"><strong>Usuarios con alguna alerta</strong><div class="metric">{{ number_format($report['fraud']['distinct_users_flagged']) }}</div></div>
    </div>
    <table style="margin-top:12px">
        <thead><tr><th>Tipo</th><th style="text-align:right">Cantidad</th></tr></thead>
        <tbody>
        @foreach($report['fraud']['by_type'] as $type => $count)
            <tr><td>{{ $type }}</td><td style="text-align:right">{{ number_format($count) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
