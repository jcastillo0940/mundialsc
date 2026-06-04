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
        <button type="submit">Registrar factura asistida</button>
    </form>
</div>

<div class="grid cols-3">
    <div class="card">
        <span class="muted">Total de puntos</span>
        <div class="metric" style="color:#ffd27a">{{ number_format($wallet->goals_balance ?? 0) }}</div>
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
                <th>Origen</th>
                <th style="text-align:right">Monto</th>
                <th style="text-align:right">Puntos ganados</th>
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
                <td><span class="pill">{{ $invoice->validation_status }}</span></td>
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
                <td colspan="6" style="text-align:right"><strong>Total puntos por facturas aprobadas</strong></td>
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
                <th>Fecha</th>
                <th>Partido</th>
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
                <td>{{ $pred->updated_at?->format('d/m/Y') }}</td>
                <td>
                    <strong>{{ $match?->homeTeam?->name ?? '?' }} vs {{ $match?->awayTeam?->name ?? '?' }}</strong>
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
