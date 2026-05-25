@extends('admin.layout')

@section('content')
<h1>Partidos</h1>

<div class="card">
    <h2>Crear partido</h2>
    <form method="post" action="{{ route('admin.matches.store') }}" class="grid">
        @csrf
        <div class="row">
            <select name="phase_id" required>
                @foreach($phases as $phase)
                    <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                @endforeach
            </select>
            <input name="match_number" placeholder="Numero de partido">
            <input name="group_label" placeholder="Grupo A, B, C...">
            <input type="datetime-local" name="kickoff_at" required>
        </div>
        <div class="row">
            <select name="home_team_id" required>
                <option value="">Equipo local</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
            <select name="away_team_id" required>
                <option value="">Equipo visitante</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
            <select name="favorite_side" required>
                <option value="none">Sin favorito</option>
                <option value="home">Favorito local</option>
                <option value="away">Favorito visitante</option>
            </select>
        </div>
        <button type="submit">Guardar partido</button>
    </form>
</div>

<div class="card">
    <h2>Control manual y respaldo por falla de API</h2>
    <p class="muted">Desde aqui puedes ajustar marcador, estado y favorito manualmente si Live Score API no devuelve datos correctos o llega tarde.</p>
    <table>
        <thead><tr><th>Partido</th><th>Fase</th><th>Hora</th><th>Fuente API</th><th>Ranking FIFA</th><th>Resultado</th><th>Estado</th><th>Actualizacion manual</th></tr></thead>
        <tbody>
        @foreach($matches as $match)
            <tr>
                <td>
                    <strong>{{ $match->homeTeam->name }} vs {{ $match->awayTeam->name }}</strong><br>
                    <small class="muted">Grupo: {{ $match->group_label ?: '-' }} | Match #: {{ $match->match_number ?: '-' }}</small>
                </td>
                <td>{{ $match->phase->name }}</td>
                <td>{{ $match->kickoff_at }}</td>
                <td>
                    <div>{{ $match->provider ?: 'manual' }}</div>
                    <small>fixture: {{ $match->external_fixture_id ?? '-' }}</small><br>
                    <small>match: {{ $match->external_match_id ?? '-' }}</small><br>
                    <small>provider status: {{ $match->provider_status ?? '-' }}</small>
                </td>
                <td>
                    <div>{{ $match->homeTeam->name }}: {{ $match->homeTeam->ranking_fifa ?? '-' }}</div>
                    <div>{{ $match->awayTeam->name }}: {{ $match->awayTeam->ranking_fifa ?? '-' }}</div>
                    <small class="muted">Favorito actual: {{ $match->favorite_side }}</small>
                </td>
                <td><span class="pill">{{ $match->home_score ?? '-' }} - {{ $match->away_score ?? '-' }}</span></td>
                <td>{{ $match->status }}</td>
                <td>
                    <form method="post" action="{{ route('admin.matches.update', $match) }}" class="grid">
                        @csrf
                        @method('put')
                        <div class="row">
                            <input name="home_score" value="{{ $match->home_score }}" placeholder="Local">
                            <input name="away_score" value="{{ $match->away_score }}" placeholder="Visitante">
                            <select name="status">
                                <option value="scheduled" @selected($match->status === 'scheduled')>scheduled</option>
                                <option value="locked" @selected($match->status === 'locked')>locked</option>
                                <option value="final" @selected($match->status === 'final')>final</option>
                            </select>
                            <select name="favorite_side">
                                <option value="none" @selected($match->favorite_side === 'none')>Sin favorito</option>
                                <option value="home" @selected($match->favorite_side === 'home')>Local favorito</option>
                                <option value="away" @selected($match->favorite_side === 'away')>Visitante favorito</option>
                            </select>
                        </div>
                        <button type="submit">Guardar ajuste manual</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
