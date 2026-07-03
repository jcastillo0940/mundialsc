@extends('admin.layout')

@section('content')
<h1>Solicitudes de tienda online</h1>

<div class="grid cols-3">
    <div class="card"><strong>Pendientes</strong><div class="metric">{{ $summary['pending'] }}</div></div>
    <div class="card"><strong>Aprobadas</strong><div class="metric">{{ $summary['approved'] }}</div></div>
    <div class="card"><strong>Rechazadas</strong><div class="metric">{{ $summary['rejected'] }}</div></div>
</div>

<div class="card">
    <h2>Filtros</h2>
    <form method="get" action="{{ route('admin.online-order-claims') }}" class="grid">
        <div class="row">
            <input name="query" value="{{ $query }}" placeholder="Orden, correo, nombre o cedula">
            <select name="status">
                <option value="pending" @selected($status === 'pending')>Pendientes</option>
                <option value="approved" @selected($status === 'approved')>Aprobadas</option>
                <option value="rejected" @selected($status === 'rejected')>Rechazadas</option>
                <option value="all" @selected($status === 'all')>Todas</option>
            </select>
            <button type="submit">Buscar</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Solicitudes</h2>
    <table>
        <thead>
            <tr>
                <th>Orden</th>
                <th>Cliente</th>
                <th>Datos Magento</th>
                <th>Estado</th>
                <th>Revision</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($claims as $claim)
            @php
                $emailsMatch = strtolower((string) $claim->submitted_email) === strtolower((string) $claim->customer_email);
            @endphp
            <tr>
                <td>
                    <strong>{{ $claim->increment_id }}</strong><br>
                    <small class="muted">Reportada {{ optional($claim->submitted_at)->format('Y-m-d H:i') }}</small>
                </td>
                <td>
                    <strong>{{ $claim->user?->name ?? 'Usuario eliminado' }}</strong><br>
                    <small>{{ $claim->submitted_email ?: $claim->user?->email }}</small><br>
                    <small class="muted">ID {{ $claim->user_id }}</small>
                </td>
                <td>
                    <div>Total: <strong>${{ number_format((float) $claim->grand_total, 2) }}</strong> {{ $claim->currency }}</div>
                    <div>Estado: <span class="pill">{{ $claim->magento_status ?: 'sin dato' }}</span></div>
                    <div>Fecha: {{ optional($claim->ordered_at)->format('Y-m-d H:i') ?: 'sin dato' }}</div>
                    <div>Correo Magento: {{ $claim->customer_email ?: 'sin dato' }}</div>
                    @if($claim->customer_email)
                        <small style="color: {{ $emailsMatch ? '#8ee2b1' : '#ffb4a8' }}">
                            {{ $emailsMatch ? 'Correo coincide' : 'Correo distinto: revisar manualmente' }}
                        </small>
                    @endif
                </td>
                <td>
                    <span class="pill">{{ strtoupper($claim->status) }}</span><br>
                    <small class="muted">{{ $claim->points_awarded }} puntos</small>
                </td>
                <td>
                    @if($claim->reviewed_at)
                        <div>{{ $claim->reviewedBy?->name ?? 'Admin' }}</div>
                        <small class="muted">{{ $claim->reviewed_at->format('Y-m-d H:i') }}</small>
                        @if($claim->review_notes)
                            <p>{{ $claim->review_notes }}</p>
                        @endif
                    @else
                        <span class="muted">Pendiente</span>
                    @endif
                </td>
                <td>
                    @if($claim->status === 'pending')
                        <form method="post" action="{{ route('admin.online-order-claims.approve', $claim) }}" class="grid" style="gap:8px;margin-bottom:8px">
                            @csrf
                            <textarea name="review_notes" placeholder="Notas de aprobacion opcionales"></textarea>
                            <button type="submit">Aprobar y acreditar 5 puntos</button>
                        </form>
                        <form method="post" action="{{ route('admin.online-order-claims.reject', $claim) }}" class="grid" style="gap:8px">
                            @csrf
                            <textarea name="review_notes" placeholder="Motivo de rechazo"></textarea>
                            <button type="submit" class="danger">Rechazar</button>
                        </form>
                    @else
                        <span class="muted">Sin acciones</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="muted">No hay solicitudes con estos filtros.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px">
        {{ $claims->links() }}
    </div>
</div>
@endsection
