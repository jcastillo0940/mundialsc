@extends('admin.layout')

@section('content')
<h1>Log de envíos</h1>

<div class="grid cols-3">
    <div class="card"><div class="muted">Total</div><div class="metric">{{ $summary['total'] }}</div></div>
    <div class="card"><div class="muted">En cola</div><div class="metric">{{ $summary['queued'] }}</div></div>
    <div class="card"><div class="muted">Confirmados</div><div class="metric">{{ $summary['confirmed'] }}</div></div>
</div>

<div class="card">
    <h2>Filtros</h2>
    <form method="get" class="row" style="margin-bottom:16px">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar correo">
        <select name="channel">
            <option value="all" @selected($filters['channel'] === 'all')>Todos los canales</option>
            <option value="password_reset" @selected($filters['channel'] === 'password_reset')>Password reset</option>
            <option value="newsletter" @selected($filters['channel'] === 'newsletter')>Newsletter</option>
            <option value="smtp_test" @selected($filters['channel'] === 'smtp_test')>Prueba SMTP</option>
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Todos los estados</option>
            <option value="queued" @selected($filters['status'] === 'queued')>En cola</option>
            <option value="completed" @selected($filters['status'] === 'completed')>Completados</option>
            <option value="confirmed" @selected($filters['status'] === 'confirmed')>Confirmados</option>
            <option value="resent" @selected($filters['status'] === 'resent')>Reenviados</option>
            <option value="unsubscribed" @selected($filters['status'] === 'unsubscribed')>Bajas</option>
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Canal</th>
                <th>Evento</th>
                <th>Correo</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Meta</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td><span class="pill">{{ $log->channel }}</span></td>
                <td>{{ $log->event_type }}</td>
                <td>{{ $log->recipient_email }}</td>
                <td><span class="pill">{{ $log->status }}</span></td>
                <td>{{ $log->sent_at ?: $log->created_at }}</td>
                <td><small class="muted">{{ json_encode($log->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</small></td>
            </tr>
        @empty
            <tr><td colspan="6">No hay envíos registrados.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px">{{ $logs->links() }}</div>
</div>
@endsection
