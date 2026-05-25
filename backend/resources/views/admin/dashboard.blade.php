@extends('admin.layout')

@section('content')
<h1>Dashboard del Torneo</h1>
<div class="grid cols-3">
    <div class="card"><strong>Fases</strong><div>{{ $phases->count() }}</div></div>
    <div class="card"><strong>Partidos</strong><div>{{ $matchesCount }}</div></div>
    <div class="card"><strong>Pronósticos</strong><div>{{ $predictionsCount }}</div></div>
    <div class="card"><strong>Facturas diarias</strong><div>{{ $invoiceGoalsCount }}</div></div>
    <div class="card"><strong>Ganadores activos</strong><div>{{ $winnersCount }}</div></div>
</div>
<div class="card">
    <h2>Puntuación por fase</h2>
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
@endsection
