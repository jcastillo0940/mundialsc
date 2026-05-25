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
            <input name="ranking_from" placeholder="Desde" required>
            <input name="ranking_to" placeholder="Hasta" required>
        </div>
        <div class="row">
            <input name="football_role" placeholder="Rol futbolero" required>
            <input name="prize_title" placeholder="Nombre del premio" required>
            <input name="prize_type" placeholder="Tipo de premio" required>
            <input name="stock" placeholder="Cantidad" required>
        </div>
        <button type="submit">Guardar premio</button>
    </form>
</div>

<div class="card">
    <h2>Inventario por posicion</h2>
    <table>
        <thead><tr><th>Fase</th><th>Rango</th><th>Rol</th><th>Premio</th><th>Tipo</th><th>Cantidad</th><th>Editar</th><th>Eliminar</th></tr></thead>
        <tbody>
        @forelse($prizes as $prize)
            <tr>
                <td>{{ $prize->phase->name }}</td>
                <td>{{ $prize->ranking_from }} - {{ $prize->ranking_to }}</td>
                <td>{{ $prize->football_role }}</td>
                <td>{{ $prize->prize_title }}</td>
                <td>{{ $prize->prize_type }}</td>
                <td><span class="pill">{{ $prize->stock }}</span></td>
                <td colspan="2">
                    <div class="grid">
                        <form method="post" action="{{ route('admin.prizes.update', $prize) }}" class="grid">
                            @csrf
                            @method('put')
                            <div class="row">
                                <select name="phase_id" required>
                                    @foreach($phases as $phase)
                                        <option value="{{ $phase->id }}" @selected($phase->id === $prize->phase_id)>{{ $phase->name }}</option>
                                    @endforeach
                                </select>
                                <input name="ranking_from" value="{{ $prize->ranking_from }}" required>
                                <input name="ranking_to" value="{{ $prize->ranking_to }}" required>
                            </div>
                            <div class="row">
                                <input name="football_role" value="{{ $prize->football_role }}" required>
                                <input name="prize_title" value="{{ $prize->prize_title }}" required>
                                <input name="prize_type" value="{{ $prize->prize_type }}" required>
                                <input name="stock" value="{{ $prize->stock }}" required>
                            </div>
                            <button type="submit">Actualizar</button>
                        </form>
                        <form method="post" action="{{ route('admin.prizes.destroy', $prize) }}">
                            @csrf
                            @method('delete')
                            <button type="submit" class="danger">Eliminar premio</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8">No hay premios configurados aun.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
