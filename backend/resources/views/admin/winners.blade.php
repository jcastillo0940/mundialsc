@extends('admin.layout')

@section('content')
<h1>Ganadores y Comunicaciones</h1>

<div class="card">
    <h2>Resumen de la promo</h2>
    @if($phase)
        <p><strong>Fase activa:</strong> {{ $phase->name }}</p>
        <p><strong>Max_Premios:</strong> {{ $winnerSlots }} | La tabla oficial se corta estrictamente en ese limite.</p>
        <p><a href="{{ route('admin.winners.acta') }}" target="_blank">Abrir acta general de ganadores</a></p>
        <form method="post" action="{{ route('admin.winners.generate') }}">
            @csrf
            <button type="submit">Generar seleccion inicial</button>
        </form>
    @else
        <p>No hay fase de grupos activa configurada.</p>
    @endif
</div>

<div class="card">
    <h2>Ranking oficial limitado</h2>
    <table>
        <thead><tr><th>Posicion</th><th>Participante</th><th>Puntos</th><th>Exactos</th><th>Facturas</th><th>Rol</th></tr></thead>
        <tbody>
        @forelse($leaderboard as $row)
            <tr>
                <td>{{ $row['position'] }}</td>
                <td>
                    <div>{{ $row['full_name'] }}</div>
                    <small>{{ $row['email'] }} | {{ $row['phone'] ?: 'sin telefono' }}</small>
                </td>
                <td>{{ $row['goals'] }}</td>
                <td>{{ $row['exact_hits'] }}</td>
                <td>{{ $row['invoice_count'] }}</td>
                <td>{{ $row['football_role'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Todavia no hay ranking disponible.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Tokens fisicos de premio</h2>
    <table>
        <thead><tr><th>Token</th><th>Premio</th><th>Estado</th><th>Asignado a</th></tr></thead>
        <tbody>
        @forelse($prizeTokens as $token)
            <tr>
                <td>{{ $token->token_code }}</td>
                <td>{{ $token->prize_title }} / {{ $token->prize_type }}</td>
                <td>{{ $token->status }}</td>
                <td>{{ $token->assigned_user_id ?: 'sin asignar' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Los tokens se crean automaticamente desde el inventario al generar ganadores.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Ganadores seleccionados</h2>
    @forelse($winners as $winner)
        <div class="card">
            <div class="row">
                <div>
                    <strong>{{ $winner->user->full_name }}</strong><br>
                    <small>Puesto #{{ $winner->leaderboard_position }} | {{ number_format((float) $winner->total_points, 2) }} pts | exactos: {{ $winner->exact_hits }} | facturas: {{ $winner->invoice_count }}</small><br>
                    <small>Estado: {{ $winner->status }} | razon: {{ $winner->selection_reason }} | token: {{ $winner->prizeToken?->token_code ?? 'sin token' }}</small>
                </div>
                <div>
                    <strong>Contacto</strong><br>
                    <small>{{ $winner->user->email }}</small><br>
                    <small>{{ $winner->user->phone ?: 'sin telefono' }}</small>
                    <br><small><a href="{{ route('admin.winners.communications-acta', $winner) }}" target="_blank">Abrir acta de comunicaciones</a></small>
                </div>
            </div>

            <div class="grid">
                <form method="post" action="{{ route('admin.winners.update', $winner) }}" class="grid">
                    @csrf
                    @method('put')
                    <div class="row">
                        <input name="leaderboard_position" value="{{ $winner->leaderboard_position }}" placeholder="Posicion" required>
                        <input name="total_points" value="{{ $winner->total_points }}" placeholder="Puntos" required>
                        <input name="exact_hits" value="{{ $winner->exact_hits }}" placeholder="Exactos" required>
                        <input name="invoice_count" value="{{ $winner->invoice_count }}" placeholder="Facturas" required>
                    </div>
                    <div class="row">
                        <select name="status" required>
                            <option value="selected" @selected($winner->status === 'selected')>selected</option>
                            <option value="contacting" @selected($winner->status === 'contacting')>contacting</option>
                            <option value="confirmed" @selected($winner->status === 'confirmed')>confirmed</option>
                            <option value="delivered" @selected($winner->status === 'delivered')>premio entregado</option>
                            <option value="disqualified" @selected($winner->status === 'disqualified')>disqualified</option>
                        </select>
                        <select name="selection_reason" required>
                            <option value="rank" @selected($winner->selection_reason === 'rank')>rank</option>
                            <option value="draw" @selected($winner->selection_reason === 'draw')>draw</option>
                            <option value="replacement" @selected($winner->selection_reason === 'replacement')>replacement</option>
                            <option value="manual" @selected($winner->selection_reason === 'manual')>manual</option>
                        </select>
                        <input name="notes" value="{{ $winner->notes }}" placeholder="Notas internas">
                    </div>
                    <button type="submit">Guardar edicion manual</button>
                </form>

                <form method="post" action="{{ route('admin.winners.contact', $winner) }}" class="row">
                    @csrf
                    <select name="contact_type">
                        <option value="call">Llamada</option>
                        <option value="email">Correo</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="other">Otro</option>
                    </select>
                    <select name="contact_status">
                        <option value="attempted">Intentado</option>
                        <option value="answered">Respondio</option>
                        <option value="no_answer">No respondio</option>
                        <option value="sent">Enviado</option>
                        <option value="bounced">Rebotado</option>
                    </select>
                    <input type="datetime-local" name="contacted_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                    <input name="notes" placeholder="Notas del contacto">
                    <button type="submit">Registrar gestion</button>
                </form>

                <div class="row">
                    <form method="post" action="{{ route('admin.winners.confirm', $winner) }}">
                        @csrf
                        <button type="submit">Confirmar ganador</button>
                    </form>
                    <form method="post" action="{{ route('admin.winners.deliver', $winner) }}">
                        @csrf
                        <button type="submit">Premio entregado</button>
                    </form>
                    <form method="post" action="{{ route('admin.winners.disqualify', $winner) }}" class="row">
                        @csrf
                        <input name="reason" placeholder="Motivo de descarte" required>
                        <button type="submit" class="danger">Descartar y pasar al siguiente</button>
                    </form>
                </div>
            </div>

            <table>
                <thead><tr><th>Fecha</th><th>Canal</th><th>Estado</th><th>Notas</th></tr></thead>
                <tbody>
                @forelse($winner->contacts->sortByDesc('contacted_at') as $contact)
                    <tr>
                        <td>{{ $contact->contacted_at }}</td>
                        <td>{{ $contact->contact_type }}</td>
                        <td>{{ $contact->contact_status }}</td>
                        <td>{{ $contact->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Sin comunicaciones registradas aun.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p>No hay ganadores seleccionados todavia.</p>
    @endforelse
</div>
@endsection
