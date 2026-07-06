@extends('admin.layout')

@section('content')
<h1>Solicitudes de tienda online</h1>

<div class="grid cols-3">
    <div class="card"><strong>Pendientes</strong><div class="metric">{{ $summary['pending'] }}</div></div>
    <div class="card"><strong>Aprobadas</strong><div class="metric">{{ $summary['approved'] }}</div></div>
    <div class="card"><strong>Rechazadas</strong><div class="metric">{{ $summary['rejected'] }}</div></div>
</div>

<div class="card">
    <h2>Registrar compra reportada por WhatsApp</h2>
    <p class="muted">
        Usa este formulario solo para reportes recibidos por WhatsApp. La compra debe ser de $25.00 o mas y estar entre el 3 y el 6 de julio; el estado Magento no bloquea la acreditacion.
    </p>
    <form method="post" action="{{ route('admin.online-order-claims.whatsapp.store') }}" class="grid" style="gap:12px">
        @csrf
        <div class="grid cols-2">
            <label>
                Cedula o documento del cliente
                <input name="cedula" value="{{ old('cedula') }}" placeholder="Ej: 8-123-456" required>
            </label>
            <label>
                Numero exacto de orden Magento
                <input name="order_number" value="{{ old('order_number') }}" placeholder="Ej: 10000000193" required>
            </label>
        </div>
        <div class="grid cols-2">
            <label>
                Fecha/hora del reporte por WhatsApp
                <input type="datetime-local" name="source_reported_at" value="{{ old('source_reported_at') }}" required>
            </label>
            <label>
                Nota de auditoria
                <textarea name="review_notes" placeholder="Ej: Cliente reporto la compra por WhatsApp al 68982167." required>{{ old('review_notes') }}</textarea>
            </label>
        </div>
        <div>
            <button type="submit">Validar Magento y acreditar 5 puntos</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Acreditacion manual sin Magento</h2>
    <p class="muted">
        Usa esta opcion solo cuando el equipo confirme la compra fuera del API de Magento. No consulta Magento y queda auditada como bono online manual.
    </p>
    <form method="get" action="{{ route('admin.online-order-claims') }}" class="grid" style="gap:12px;margin-bottom:14px">
        <div class="row">
            <input name="manual_cedula" value="{{ old('manual_cedula', $manualCedula) }}" placeholder="Cedula o documento del cliente" required>
            <button type="submit">Buscar cliente</button>
        </div>
    </form>

    @if($manualCedula !== '' && ! $manualUser)
        <div style="margin-bottom:14px;padding:12px 16px;background:#2d1a1a;border:1px solid #7a2020;border-radius:10px;color:#f87171;font-size:13px">
            No encontramos un cliente registrado con esa cedula o documento.
        </div>
    @endif

    @if($manualUser)
        <div style="margin-bottom:14px;padding:12px 16px;border:1px solid var(--line);border-radius:10px;background:#10202a">
            <strong>{{ $manualUser->name }}</strong><br>
            <small>{{ $manualUser->email }} · ID {{ $manualUser->id }} · Cedula {{ $manualUser->cedula }}</small>
            @if($manualUser->disqualified_at)
                <div style="margin-top:6px;color:#f87171">Cliente descalificado: no debe recibir puntos.</div>
            @endif
        </div>

        <form method="post" action="{{ route('admin.online-order-claims.manual.store') }}" class="grid" style="gap:12px">
            @csrf
            <input type="hidden" name="manual_user_id" value="{{ $manualUser->id }}">
            <div class="grid cols-2">
                <label>
                    Numero de orden o referencia
                    <input name="manual_order_reference" value="{{ old('manual_order_reference') }}" placeholder="Ej: WhatsApp-13000001729" required>
                </label>
                <label>
                    Puntos a acreditar
                    <input name="manual_points" type="number" min="1" max="50" step="1" value="{{ old('manual_points', 5) }}" required>
                </label>
            </div>
            <label>
                Nota de auditoria obligatoria
                <textarea name="manual_review_notes" placeholder="Ej: Compra confirmada manualmente por soporte. No se consulto Magento." required>{{ old('manual_review_notes') }}</textarea>
            </label>
            <div>
                <button type="submit" @disabled($manualUser->disqualified_at !== null)>Acreditar puntos sin Magento</button>
            </div>
        </form>
    @endif
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
                $sourceLabels = [
                    'client_frontend' => 'Cliente / Frontend',
                    'admin_whatsapp' => 'Admin / WhatsApp',
                    'admin_manual_online_order' => 'Admin / Manual sin Magento',
                ];
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
                    <div style="margin-top:6px">
                        <small class="muted">Origen: {{ $sourceLabels[$claim->source] ?? ($claim->source ?: 'sin dato') }}</small><br>
                        @if($claim->source_reported_at)
                            <small class="muted">Reporte: {{ $claim->source_reported_at->format('Y-m-d H:i') }}</small>
                        @endif
                    </div>
                </td>
                <td>
                    @if($claim->createdBy)
                        <div>Creada por {{ $claim->createdBy->name }}</div>
                        <small class="muted">Gobernanza manual</small>
                    @endif
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
