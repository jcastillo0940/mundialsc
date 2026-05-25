@extends('admin.layout')

@section('content')
<h1>Premios por Fase</h1>
<div class="card">
    <h2>Registrar premio</h2>
    <form method="post" action="{{ route('admin.prizes.store') }}" class="grid">
        @csrf
        <div class="row">
            <select name="phase_id" required>
                @foreach($phases as $phase)
                    <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                @endforeach
            </select>
            <input name="ranking_from" placeholder="Desde">
            <input name="ranking_to" placeholder="Hasta">
        </div>
        <div class="row">
            <input name="football_role" placeholder="Goleador Estrella">
            <input name="prize_title" placeholder="Smart TV 50 pulgadas">
            <input name="prize_type" placeholder="Premio Mayor de la Fase">
            <input name="stock" placeholder="Stock">
        </div>
        <button type="submit">Guardar premio</button>
    </form>
</div>

<div class="card">
    <table>
        <thead><tr><th>Fase</th><th>Rango</th><th>Rol</th><th>Premio</th><th>Tipo</th><th>Stock</th></tr></thead>
        <tbody>
        @foreach($prizes as $prize)
            <tr>
                <td>{{ $prize->phase->name }}</td>
                <td>{{ $prize->ranking_from }} - {{ $prize->ranking_to }}</td>
                <td>{{ $prize->football_role }}</td>
                <td>{{ $prize->prize_title }}</td>
                <td>{{ $prize->prize_type }}</td>
                <td>{{ $prize->stock }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
