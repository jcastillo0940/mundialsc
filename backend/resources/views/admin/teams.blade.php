@extends('admin.layout')

@section('content')
<h1>Ranking FIFA</h1>

<div class="card">
    <h2>Equipos</h2>
    <table>
        <thead><tr><th>Equipo</th><th>Grupo</th><th>Código</th><th>Ranking FIFA</th></tr></thead>
        <tbody>
        @foreach($teams as $team)
            <tr>
                <td>{{ $team->name }}</td>
                <td>{{ $team->group_label ?? '-' }}</td>
                <td>{{ $team->code }}</td>
                <td>
                    <form method="post" action="{{ route('admin.teams.ranking', $team) }}" class="row">
                        @csrf
                        @method('put')
                        <input name="ranking_fifa" type="number" min="1" max="999" value="{{ $team->ranking_fifa }}" placeholder="Ej. 14">
                        <button type="submit">Actualizar</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
