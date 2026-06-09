@extends('admin.layout')

@section('content')
<h1>Antifraude y Auditoria de Puntos</h1>

<div class="grid cols-3">
    <div class="card">
        <span class="muted">Casos abiertos</span>
        <div class="metric">{{ $summary['open'] }}</div>
    </div>
    <div class="card">
        <span class="muted">Criticos abiertos</span>
        <div class="metric">{{ $summary['critical'] }}</div>
    </div>
    <div class="card">
        <span class="muted">Cerrados</span>
        <div class="metric">{{ $summary['resolved'] }}</div>
    </div>
</div>

<div class="card">
    <div class="row" style="align-items:center">
        <div>
            <h2 style="margin-top:0">Cola de revision</h2>
            <p class="muted">El sistema solo genera flags. No descalifica, no bloquea cuentas y no quita puntos ya otorgados sin revision humana.</p>
        </div>
        <div>
            <a class="pill" href="{{ route('admin.fraud.export') }}">Exportar CSV</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Caso</th>
                <th>Participante</th>
                <th>Factura</th>
                <th>Soporte</th>
                <th>Estado</th>
                <th>Revision</th>
            </tr>
        </thead>
        <tbody>
        @forelse($flags as $flag)
            <tr>
                <td>{{ $flag->created_at }}</td>
                <td>
                    <strong>{{ $flag->title }}</strong><br>
                    <small class="muted">{{ $flag->flag_type }} | {{ $flag->severity }}</small><br>
                    <small>{{ $flag->description }}</small>
                    @if($flag->evidence)
                        <pre style="white-space:pre-wrap;background:#0f171b;padding:8px;border-radius:8px;max-width:420px">{{ json_encode($flag->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                </td>
                <td>
                    {{ $flag->user?->name ?? 'N/D' }}<br>
                    <small>{{ $flag->user?->cedula }} | {{ $flag->user?->email }}</small>
                </td>
                <td>
                    @if($flag->invoice)
                        <small>{{ $flag->invoice->cufe }}</small><br>
                        @if($flag->invoice->validation_status === 'approved')
                            <span class="pill" style="background:#1a2d1a;border-color:#2d5a2d;color:#8ee2b1">aprobada, +{{ number_format((float) $flag->invoice->points_awarded) }} punto(s)</span><br>
                        @elseif($flag->invoice->validation_status === 'pending')
                            <span class="pill" style="background:#2d2a1a;border-color:#7a6a20;color:#ffd27a">pendiente, 0 puntos</span><br>
                        @elseif($flag->invoice->validation_status === 'disqualify')
                            <span class="pill" style="background:#2d1a1a;border-color:#7a2020;color:#ff9d9d">revision antifraude, 0 puntos</span><br>
                        @else
                            <span class="pill" style="background:#2d1a1a;border-color:#7a2020;color:#ff9d9d">no aprobada, 0 puntos</span><br>
                        @endif
                        <small class="muted">{{ $flag->invoice->validation_status }} | ${{ number_format((float) $flag->invoice->purchase_amount, 2) }}</small><br>
                        <small>Origen: {{ $flag->invoice->registration_source === 'admin_assisted' ? 'asistida por admin' : 'cliente' }}</small>
                    @else
                        <span class="muted">Sin factura creada</span>
                    @endif
                </td>
                <td>
                    @php
                        $message = 'Hola '.($flag->user?->name ?? '').', te escribimos desde Super Carnes para ayudarte con el registro de tu factura en la promocion.';
                        $whatsappUrl = $flag->user?->whatsappUrl($message);
                    @endphp
                    @if($whatsappUrl)
                        <a class="pill" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp</a><br>
                    @else
                        <span class="muted">Sin WhatsApp</span><br>
                    @endif
                    <a class="pill" href="{{ route('admin.player-points.detail', $flag->user_id) }}">Ver participante</a>
                </td>
                <td>
                    <span class="pill">{{ $flag->status }}</span>
                    @if($flag->reviewer)
                        <br><small>Por {{ $flag->reviewer->name }}</small>
                    @endif
                </td>
                <td>
                    <form method="post" action="{{ route('admin.users.assisted-invoices.store', $flag->user_id) }}" class="grid" style="margin-bottom:12px">
                        @csrf
                        <input type="hidden" name="fraud_flag_id" value="{{ $flag->id }}">
                        <input name="qr_raw_text" placeholder="CUFE o texto del QR" value="{{ old('fraud_flag_id') == $flag->id ? old('qr_raw_text') : '' }}">
                        <input name="branch_id" placeholder="Sucursal (opcional)" value="{{ old('fraud_flag_id') == $flag->id ? old('branch_id') : '' }}">
                        <textarea name="assistance_notes" placeholder="Motivo y detalle del apoyo">{{ old('fraud_flag_id') == $flag->id ? old('assistance_notes') : 'Cliente asistido desde cola antifraude.' }}</textarea>
                        <button type="submit">Registrar factura asistida</button>
                        @if(old('fraud_flag_id') == $flag->id && $errors->any())
                            <div class="status" style="margin:0;background:rgba(178,60,47,.15);border:1px solid rgba(178,60,47,.45)">
                                {{ $errors->first() }}
                            </div>
                        @endif
                    </form>
                    <form method="post" action="{{ route('admin.fraud.update', $flag) }}" class="grid">
                        @csrf
                        @method('put')
                        <select name="status">
                            <option value="open" @selected($flag->status === 'open')>open</option>
                            <option value="reviewing" @selected($flag->status === 'reviewing')>reviewing</option>
                            <option value="resolved" @selected($flag->status === 'resolved')>resolved</option>
                            <option value="dismissed" @selected($flag->status === 'dismissed')>dismissed</option>
                        </select>
                        <textarea name="resolution_notes" placeholder="Notas de revision">{{ $flag->resolution_notes }}</textarea>
                        <select name="disqualify_user">
                            <option value="0">No descalificar</option>
                            <option value="1">Descalificar participante</option>
                        </select>
                        <button type="submit">Guardar revision</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No hay casos antifraude registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Reglas antifraude activas</h2>
    <table>
        <thead>
            <tr><th>Regla</th><th>Accion automatica</th><th>Decision humana</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>CUFE no extraible o formato invalido</td>
                <td>Rechaza el intento y crea flag <span class="pill">invalid_cufe_format</span>.</td>
                <td>Auditor decide si fue error de lectura o intento malicioso.</td>
            </tr>
            <tr>
                <td>CUFE no confirmado por DGI</td>
                <td>Rechaza el intento y crea flag critico <span class="pill">dgi_invoice_resolution_failed</span>.</td>
                <td>Auditor puede descartar falso positivo o sancionar.</td>
            </tr>
            <tr>
                <td>CUFE duplicado</td>
                <td>Rechaza el intento y crea flag <span class="pill">duplicate_cufe_attempt</span>.</td>
                <td>Auditor revisa si hubo reintento accidental, cuenta duplicada o apropiacion de factura.</td>
            </tr>
            <tr>
                <td>Factura fuera de antiguedad permitida</td>
                <td>Rechaza el punto y crea flag <span class="pill">invoice_outside_allowed_age</span>.</td>
                <td>Auditor valida si aplica excepcion operativa.</td>
            </tr>
            <tr>
                <td>DGI marca CUFE invalido, alterado o de titular no coincidente</td>
                <td>Registra la factura como rechazada, no suma puntos y crea flag critico <span class="pill">critical_invoice_validation</span>.</td>
                <td>Solo el auditor puede descalificar desde esta pantalla.</td>
            </tr>
            <tr>
                <td>Volumen inusual de facturas aprobadas</td>
                <td>Crea flag <span class="pill">velocity_invoice_submissions</span> si hay 5 o mas facturas aprobadas en 10 minutos.</td>
                <td>Auditor revisa patron antes de tomar accion.</td>
            </tr>
            <tr>
                <td>Telefono compartido entre cuentas</td>
                <td>Crea flag <span class="pill">shared_phone</span>.</td>
                <td>Auditor confirma si es familia, error de captura o multi-cuenta.</td>
            </tr>
            <tr>
                <td>Favorito FIFA sin ranking oficial</td>
                <td>Bloquea finalizacion o recalculo del partido con ganador.</td>
                <td>Admin debe cargar ranking FIFA oficial abril/mayo 2026.</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
