@extends('admin.layout')

@section('content')
<h1>Newsletter</h1>

<div class="grid cols-3">
    <div class="card">
        <div class="muted">Total</div>
        <div class="metric">{{ $summary['total'] }}</div>
    </div>
    <div class="card">
        <div class="muted">Confirmados</div>
        <div class="metric">{{ $summary['confirmed'] }}</div>
    </div>
    <div class="card">
        <div class="muted">Pendientes</div>
        <div class="metric">{{ $summary['pending'] }}</div>
    </div>
</div>

<div class="card">
    <h2>Suscriptores</h2>
    <p class="muted">El newsletter usa doble opt-in. Las filas pendientes aún no están activas hasta confirmar el correo.</p>

    <form method="get" class="row" style="margin-bottom:16px">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar por correo">
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Todos</option>
            <option value="confirmed" @selected($filters['status'] === 'confirmed')>Confirmados</option>
            <option value="pending" @selected($filters['status'] === 'pending')>Pendientes</option>
            <option value="unsubscribed" @selected($filters['status'] === 'unsubscribed')>Dado de baja</option>
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Estado</th>
                <th>Fechas</th>
                <th>Origen</th>
                <th>Detalles</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($subscribers as $subscriber)
            <tr>
                <td><strong>{{ $subscriber->email }}</strong></td>
                <td>
                    @if($subscriber->unsubscribed_at)
                        <span class="pill">Baja</span>
                    @elseif($subscriber->confirmed_at)
                        <span class="pill">Confirmado</span>
                    @else
                        <span class="pill">Pendiente</span>
                    @endif
                </td>
                <td>
                    <div>Solicitado: {{ $subscriber->subscribed_at ?: '-' }}</div>
                    <div>Confirmado: {{ $subscriber->confirmed_at ?: '-' }}</div>
                    <div>Baja: {{ $subscriber->unsubscribed_at ?: '-' }}</div>
                </td>
                <td>
                    <div>{{ $subscriber->source ?: '-' }}</div>
                    <small class="muted">{{ $subscriber->ip_address ?: 'sin IP' }}</small>
                </td>
                <td>
                    <div><small class="muted">Último envío: {{ $subscriber->confirmation_sent_at ?: '-' }}</small></div>
                    <div><small class="muted">UA: {{ $subscriber->user_agent ?: '-' }}</small></div>
                </td>
                <td>
                    <div class="row" style="grid-template-columns:1fr;gap:8px">
                        <form method="post" action="{{ route('admin.newsletter.resend', $subscriber) }}">
                            @csrf
                            <button type="submit" class="ghost">Reenviar confirmación</button>
                        </form>
                        <form method="post" action="{{ route('admin.newsletter.unsubscribe', $subscriber) }}">
                            @csrf
                            <button type="submit" class="danger">Dar de baja</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No hay suscriptores registrados.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px">
        {{ $subscribers->links() }}
    </div>
</div>
@endsection
