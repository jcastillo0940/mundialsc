@extends('admin.layout')

@section('content')
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="{{ route('admin.player-points') }}" style="padding:7px 14px;border-radius:10px;background:#20313a;border:1px solid var(--line);font-size:13px">&larr; Volver</a>
    <h1 style="margin:0">{{ $user->name }}</h1>
    @if($user->disqualified_at)
        <span class="pill" style="background:#2d1a1a;border-color:#7a2020">descalificado</span>
    @endif
</div>

<div class="card">
    <h2>Datos del participante</h2>
    <div class="row">
        <div>
            <div class="muted" style="font-size:12px">Nombre completo</div>
            <div><strong>{{ $user->name }}</strong></div>
        </div>
        <div>
            <div class="muted" style="font-size:12px">Cedula</div>
            <div><strong>{{ $user->cedula ?: '-' }}</strong></div>
        </div>
        <div>
            <div class="muted" style="font-size:12px">Correo</div>
            <div>{{ $user->email ?: '-' }}</div>
        </div>
        <div>
            <div class="muted" style="font-size:12px">Telefono</div>
            <div>{{ $user->phone ?: '-' }}</div>
        </div>
    </div>
    @php
        $detailWhatsapp = $user->whatsappUrl('Hola '.$user->name.', te escribimos desde Super Carnes para ayudarte con el registro de tu factura en la promocion.');
    @endphp
    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        @if($detailWhatsapp)
            <a class="pill" href="{{ $detailWhatsapp }}" target="_blank" rel="noopener noreferrer">WhatsApp del cliente</a>
        @endif
        <a class="pill" href="{{ route('admin.fraud') }}">Ir a antifraude</a>
    </div>
</div>

<div class="card">
    <h2>Registrar factura asistida</h2>
    <p class="muted">Esta carga queda marcada como asistencia administrativa, con responsable, notas y enlace opcional al caso antifraude.</p>

    @if($errors->has('cufe'))
        <div style="margin-bottom:14px;padding:12px 16px;background:#2d1a1a;border:1px solid #7a2020;border-radius:10px;color:#f87171;font-size:13px">
            <strong>Error de DGI:</strong> {{ $errors->first('cufe') }}
            <br><small style="color:#9ca3af;margin-top:4px;display:block">Si la factura es válida, activa el <strong>registro forzado</strong> abajo e ingresa el monto y fecha manualmente.</small>
        </div>
    @endif

    <form method="post" action="{{ route('admin.users.assisted-invoices.store', $user) }}" class="grid">
        @csrf
        <div class="row">
            <input name="qr_raw_text" placeholder="CUFE o texto completo del QR" value="{{ old('qr_raw_text') }}">
            <select name="fraud_flag_id">
                <option value="">Sin caso antifraude asociado</option>
                @foreach($relatedFraudFlags as $flag)
                    <option value="{{ $flag->id }}" @selected((string) old('fraud_flag_id') === (string) $flag->id)>
                        #{{ $flag->id }} - {{ $flag->title }} ({{ $flag->status }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="row">
            <select name="branch_id">
                <option value="">Sucursal opcional</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <textarea name="assistance_notes" placeholder="Describe la ayuda prestada y el canal de contacto.">{{ old('assistance_notes', 'Cliente asistido por soporte para registrar la factura.') }}</textarea>

        <details @if(old('force_override')) open @endif style="border:1px solid #2d3f50;border-radius:10px;padding:14px">
            <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#f0c040;user-select:none">⚠ Registro forzado (omitir validación DGI)</summary>
            <p style="color:#9ca3af;font-size:12px;margin:8px 0 12px">Usar solo cuando DGI rechaza la factura pero el admin confirma que es válida. El punto se acredita con estado <em>manual_approved</em>.</p>
            <div class="row">
                <input name="purchase_amount" type="number" step="0.01" min="0.01" placeholder="Monto pagado (obligatorio si forzado)" value="{{ old('purchase_amount') }}">
                <input name="issued_at" type="date" value="{{ old('issued_at', now()->toDateString()) }}">
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-top:8px;cursor:pointer">
                <input type="checkbox" name="force_override" value="1" @checked(old('force_override'))>
                Confirmo que esta factura es válida y autorizo el registro sin DGI
            </label>
        </details>

        <button type="submit">Registrar factura asistida</button>
    </form>
</div>

<div class="card">
    <h2>Registrar factura sin CUFE (por numero de serie)</h2>
    <p class="muted">Para facturas internas que no tienen CUFE registrado. Se consulta la API de DGI con el numero de autorizacion. Si DGI no la encuentra se genera un CUFE sintetico y queda registrada igualmente.</p>
    @if(session('status') && str_contains(session('status'), 'manual'))
        <div class="alert alert-success" style="margin-bottom:12px">{{ session('status') }}</div>
    @endif
    <form method="post" action="{{ route('admin.users.manual-invoices.store', $user) }}" class="grid">
        @csrf
        <div class="row">
            <input name="invoice_serial" placeholder="Numero interno de factura (ej. BCCT-412146)" value="{{ old('invoice_serial') }}" required>
            <input name="purchase_amount" type="number" step="0.01" min="0.01" placeholder="Monto pagado (ej. 25.50)" value="{{ old('purchase_amount') }}" required>
        </div>
        <div class="row">
            <input name="issued_at" type="date" value="{{ old('issued_at', now()->toDateString()) }}" required>
            <select name="branch_id">
                <option value="">Sucursal opcional</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <textarea name="assistance_notes" placeholder="Notas adicionales (opcional)">{{ old('assistance_notes') }}</textarea>
        <button type="submit">Registrar factura manual</button>
    </form>
</div>

<div class="grid cols-3">
    <div class="card">
        <span class="muted">Total de puntos</span>
        <div class="metric" style="color:#ffd27a">{{ number_format($invoicePoints + $predictionPoints) }}</div>
    </div>
    <div class="card">
        <span class="muted">Puntos por facturas</span>
        <div class="metric" style="color:#8ee2b1">{{ number_format($invoicePoints) }}</div>
        <small class="muted">{{ $invoices->where('validation_status', 'approved')->count() }} factura(s) aprobada(s)</small>
    </div>
    <div class="card">
        <span class="muted">Puntos por pronosticos</span>
        <div class="metric" style="color:#7ac8ff">{{ number_format($predictionPoints) }}</div>
        <small class="muted">{{ $predictions->count() }} acierto(s)</small>
    </div>
</div>

<div class="card">
    <h2>Historial de facturas</h2>
    @if($invoices->isEmpty())
        <p class="muted">No hay facturas registradas para este participante.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>No. Factura</th>
                <th>Emisor</th>
                <th>Estado</th>
                <th>Decisión y puntos</th>
                <th>Origen</th>
                <th style="text-align:right">Monto</th>
                <th style="text-align:right">Puntos</th>
            </tr>
        </thead>
        <tbody>
        @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice->issued_at?->format('d/m/Y') ?: $invoice->created_at?->format('d/m/Y') }}</td>
                <td>
                    <strong>{{ $invoice->invoice_number ?: '-' }}</strong><br>
                    <small class="muted" style="font-size:11px">{{ Str::limit($invoice->cufe, 30) }}</small>
                </td>
                <td>{{ $invoice->issuer_name ?: '-' }}</td>
                <td>
                    @if($invoice->validation_status === 'approved')
                        <span class="pill" style="background:#1a2d1a;border-color:#2d5a2d;color:#8ee2b1">aprobada</span>
                    @elseif($invoice->validation_status === 'pending')
                        <span class="pill" style="background:#2d2a1a;border-color:#7a6a20;color:#ffd27a">pendiente</span>
                    @elseif($invoice->validation_status === 'disqualify')
                        <span class="pill" style="background:#2d1a1a;border-color:#7a2020;color:#ff9d9d">revision</span>
                    @else
                        <span class="pill">{{ $invoice->validation_status }}</span>
                    @endif
                </td>
                <td>
                    @if($invoice->validation_status === 'approved')
                        <span class="pill" style="background:#1a2d1a;border-color:#2d5a2d;color:#8ee2b1">aprobada, +{{ number_format($invoice->points_awarded) }} punto(s)</span>
                    @elseif($invoice->validation_status === 'pending')
                        <span class="pill" style="background:#2d2a1a;border-color:#7a6a20;color:#ffd27a">pendiente, 0 puntos</span>
                    @else
                        <span class="pill" style="background:#2d1a1a;border-color:#7a2020;color:#ff9d9d">no aprobada, 0 puntos</span>
                    @endif
                </td>
                <td>
                    @if($invoice->registration_source === 'admin_assisted')
                        <strong>Admin asistido</strong><br>
                        <small class="muted">{{ $invoice->registeredBy?->name ?: 'Sin responsable' }}</small>
                        @if($invoice->assistedByFraudFlag)
                            <br><small class="muted">Flag #{{ $invoice->assistedByFraudFlag->id }}</small>
                        @endif
                        @if($invoice->assistance_notes)
                            <br><small class="muted">{{ $invoice->assistance_notes }}</small>
                        @endif
                    @else
                        <span>Cliente</span>
                    @endif
                </td>
                <td style="text-align:right">${{ number_format((float) $invoice->purchase_amount, 2) }}</td>
                <td style="text-align:right">
                    <strong style="color:#8ee2b1">+{{ number_format($invoice->points_awarded) }}</strong>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" style="text-align:right"><strong>Total puntos por facturas aprobadas</strong></td>
                <td style="text-align:right"><strong style="color:#8ee2b1">{{ number_format($invoicePoints) }}</strong></td>
            </tr>
        </tfoot>
    </table>
    @endif
</div>

<div class="card">
    <h2>Pronosticos acertados</h2>
    @if($predictions->isEmpty())
        <p class="muted">No hay pronosticos con puntos para este participante.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Partido</th>
                <th>Enviado</th>
                <th>Fase</th>
                <th>Pronostico</th>
                <th>Resultado real</th>
                <th>Tipo</th>
                <th style="text-align:right">Puntos</th>
            </tr>
        </thead>
        <tbody>
        @foreach($predictions as $pred)
            @php $match = $pred->match; @endphp
            <tr>
                <td>
                    <strong>{{ $match?->homeTeam?->name ?? '?' }} vs {{ $match?->awayTeam?->name ?? '?' }}</strong>
                    @if($match?->kickoff_at)
                        <br><small class="muted">{{ $match->kickoff_at->setTimezone('America/Panama')->format('d/m/Y H:i') }}</small>
                    @endif
                </td>
                <td>
                    {{ $pred->created_at?->setTimezone('America/Panama')->format('d/m/Y') }}<br>
                    <small class="muted">{{ $pred->created_at?->setTimezone('America/Panama')->format('H:i') }}</small>
                </td>
                <td>{{ $match?->phase?->name ?? '-' }}</td>
                <td style="text-align:center">
                    <span class="pill">{{ $pred->predicted_home_score }} - {{ $pred->predicted_away_score }}</span>
                </td>
                <td style="text-align:center">
                    @if($match && $match->home_score !== null)
                        <span class="pill" style="background:#1a2d1a;border-color:#2d5a2d">{{ $match->home_score }} - {{ $match->away_score }}</span>
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
                <td>
                    @if($pred->result_type === 'exact')
                        <span class="pill" style="background:#1a2a1a;border-color:#3a7a3a;color:#8ee2b1">Exacto</span>
                    @elseif($pred->result_type === 'outcome')
                        <span class="pill" style="background:#1a1a2a;border-color:#3a3a7a;color:#7ac8ff">Resultado</span>
                    @else
                        <span class="pill">{{ $pred->result_type }}</span>
                    @endif
                </td>
                <td style="text-align:right">
                    <strong style="color:#7ac8ff">+{{ number_format($pred->points_awarded) }}</strong>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align:right"><strong>Total puntos por pronosticos</strong></td>
                <td style="text-align:right"><strong style="color:#7ac8ff">{{ number_format($predictionPoints) }}</strong></td>
            </tr>
        </tfoot>
    </table>
    @endif
</div>
@endsection
