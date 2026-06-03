@extends('admin.layout')

@section('content')
<h1>Reglas del Torneo</h1>
<div class="card">
    <h2>Facturación diaria</h2>
    <form method="post" action="{{ route('admin.rules.invoice') }}" class="row">
        @csrf
        @method('put')
        <select name="is_enabled">
            <option value="1" @selected($invoiceSettings?->is_enabled)>Encendido</option>
            <option value="0" @selected(! $invoiceSettings?->is_enabled)>Apagado</option>
        </select>
        <input name="goal_value" value="{{ $invoiceSettings?->goal_value ?? 1 }}" placeholder="Puntos por factura">
        <input name="min_purchase_amount" value="{{ $invoiceSettings?->min_purchase_amount ?? 25 }}" placeholder="Compra mínima">
        <input name="max_invoice_age_days" value="{{ $invoiceSettings?->max_invoice_age_days ?? 1 }}" placeholder="Antigüedad máxima en días">
        <select name="one_invoice_per_day">
            <option value="1" @selected($invoiceSettings?->one_invoice_per_day)>Una por día</option>
            <option value="0" @selected(! $invoiceSettings?->one_invoice_per_day)>Múltiples</option>
        </select>
        <select name="validation_mode">
            <option value="api" @selected($invoiceSettings?->validation_mode === 'api')>api</option>
        </select>
        <button type="submit">Guardar</button>
    </form>
    <p><small>Regla oficial vigente: factura mayor a USD 25.00 sin ITBMS, emitida el mismo día del registro o el día calendario inmediatamente anterior, y con 1 punto por factura aprobada.</small></p>
</div>

@foreach($phases as $phase)
    <div class="card">
        <h2>{{ $phase->name }}</h2>
        <form method="post" action="{{ route('admin.rules.phase', $phase) }}" class="row">
            @csrf
            @method('put')
            <input name="exact_score_points" value="{{ $phase->exact_score_points }}" placeholder="Exacto">
            <input name="outcome_points" value="{{ $phase->outcome_points }}" placeholder="Ganador/Empate">
            <select name="is_active">
                <option value="1" @selected($phase->is_active)>Activa</option>
                <option value="0" @selected(! $phase->is_active)>Inactiva</option>
            </select>
            <button type="submit">Actualizar fase</button>
        </form>
        @if($phase->slug === 'fase-grupos')
            <p><small>En fase de grupos la lógica oficial es fija: 1 punto por favorito, 2 por empate, 3 por no favorito y 3 puntos extra por marcador exacto.</small></p>
        @else
            <p><small>Esta fase también forma parte de la promoción oficial. Revisa fechas, activación y premios antes de publicar ganadores.</small></p>
        @endif
    </div>
@endforeach
@endsection
