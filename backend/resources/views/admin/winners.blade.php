@extends('admin.layout')

@section('content')
<h1>Ganadores y Comunicaciones</h1>

<div class="card">
    <h2>Resumen de la promo</h2>
    @if($phase)
        <p><strong>Fase activa:</strong> {{ $phase->name }}</p>
        <p><strong>Cupos de ganadores:</strong> {{ $winnerSlots }}</p>
        <p><a href="{{ route('admin.winners.acta') }}" target="_blank">Abrir acta general de ganadores</a></p>
        <form method="post" action="{{ route('admin.winners.generate') }}">
            @csrf
            <button type="submit">Generar selección inicial</button>
        </form>
    @else
        <p>No hay fase de grupos activa configurada.</p>
    @endif
</div>

@if($tieContext['requires_draw'] ?? false)
    <div class="card">
        <h2>Empate técnico pendiente</h2>
        <p>Se detectó un empate total en puntos, exactos y facturas. Debes resolverlo por sorteo para completar los {{ $tieContext['remaining_slots'] }} cupos pendientes.</p>
        <table>
            <thead><tr><th>Posición</th><th>Participante</th><th>Puntos</th><th>Exactos</th><th>Facturas</th></tr></thead>
            <tbody>
            @foreach($tieContext['tied_candidates'] as $candidate)
                <tr>
                    <td>{{ $candidate['position'] }}</td>
                    <td>{{ $candidate['full_name'] }}</td>
                    <td>{{ $candidate['goals'] }}</td>
                    <td>{{ $candidate['exact_hits'] }}</td>
                    <td>{{ $candidate['invoice_count'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <form method="post" action="{{ route('admin.winners.resolve-draw') }}" class="grid">
            @csrf
            <input type="hidden" name="slots" value="{{ $tieContext['remaining_slots'] }}">
            @foreach($tieContext['tied_candidates'] as $candidate)
                <input type="hidden" name="candidate_user_ids[]" value="{{ $candidate['user_id'] }}">
            @endforeach
            <button type="submit">Resolver por sorteo</button>
        </form>
    </div>
@endif

<div class="card">
    <h2>Ranking oficial</h2>
    <table>
        <thead><tr><th>Posición</th><th>Participante</th><th>Puntos</th><th>Exactos</th><th>Facturas</th><th>Rol</th></tr></thead>
        <tbody>
        @forelse($leaderboard as $row)
            <tr>
                <td>{{ $row['position'] }}</td>
                <td>
                    <div>{{ $row['full_name'] }}</div>
                    <small>{{ $row['email'] }} | {{ $row['phone'] ?: 'sin teléfono' }}</small>
                </td>
                <td>{{ $row['goals'] }}</td>
                <td>{{ $row['exact_hits'] }}</td>
                <td>{{ $row['invoice_count'] }}</td>
                <td>{{ $row['football_role'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Todavía no hay ranking disponible.</td></tr>
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
                    <small>Estado: {{ $winner->status }} | razón: {{ $winner->selection_reason }}</small>
                </div>
                <div>
                    <strong>Contacto</strong><br>
                    <small>{{ $winner->user->email }}</small><br>
                    <small>{{ $winner->user->phone ?: 'sin teléfono' }}</small>
                    <br><small><a href="{{ route('admin.winners.communications-acta', $winner) }}" target="_blank">Abrir acta de comunicaciones</a></small>
                </div>
            </div>

            <div class="grid">
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
                        <option value="answered">Respondió</option>
                        <option value="no_answer">No respondió</option>
                        <option value="sent">Enviado</option>
                        <option value="bounced">Rebotado</option>
                    </select>
                    <input type="datetime-local" name="contacted_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                    <input name="notes" placeholder="Notas del contacto">
                    <button type="submit">Registrar gestión</button>
                </form>

                <div class="row">
                    <form method="post" action="{{ route('admin.winners.confirm', $winner) }}">
                        @csrf
                        <button type="submit">Confirmar ganador</button>
                    </form>
                    <form method="post" action="{{ route('admin.winners.disqualify', $winner) }}" class="row">
                        @csrf
                        <input name="reason" placeholder="Motivo de descarte: no respondió, correo inválido, etc." required>
                        <button type="submit">Descartar y pasar al siguiente</button>
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
                    <tr><td colspan="4">Sin comunicaciones registradas aún.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p>No hay ganadores seleccionados todavía.</p>
    @endforelse
</div>
@endsection
