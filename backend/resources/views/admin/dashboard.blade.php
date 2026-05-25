@extends('admin.layout')

@section('content')
<h1>Dashboard del Torneo</h1>
<div class="grid cols-3">
    <div class="card"><strong>Fases</strong><div class="metric">{{ $phases->count() }}</div></div>
    <div class="card"><strong>Partidos</strong><div class="metric">{{ $matchesCount }}</div></div>
    <div class="card"><strong>Pronosticos</strong><div class="metric">{{ $predictionsCount }}</div></div>
    <div class="card"><strong>Facturas diarias</strong><div class="metric">{{ $invoiceGoalsCount }}</div></div>
    <div class="card"><strong>Ganadores activos</strong><div class="metric">{{ $winnersCount }}</div></div>
    <div class="card"><strong>Usuarios clientes</strong><div class="metric">{{ $usersCount }}</div><small class="muted">{{ $disqualifiedUsersCount }} descalificados</small></div>
</div>

<div class="card">
    <h2>Puntuacion por fase</h2>
    <table>
        <thead><tr><th>Fase</th><th>Exacto</th><th>Ganador/Empate</th></tr></thead>
        <tbody>
        @foreach($phases as $phase)
            <tr>
                <td>{{ $phase->name }}</td>
                <td>{{ $phase->exact_score_points }}</td>
                <td>{{ $phase->outcome_points }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Operaciones clave</h2>
    <div class="row">
        <a class="pill" href="{{ route('admin.matches') }}">Editar marcadores manualmente</a>
        <a class="pill" href="{{ route('admin.integrations') }}">Sincronizar API de partidos</a>
        <a class="pill" href="{{ route('admin.prizes') }}">Gestionar premios</a>
        <a class="pill" href="{{ route('admin.winners') }}">Editar ganadores</a>
        <a class="pill" href="{{ route('admin.users') }}">Dar de baja usuarios</a>
        <a class="pill" href="{{ route('admin.site') }}">Hero video y SEO</a>
    </div>
</div>
@endsection
