@extends('admin.layout')

@section('content')
<h1>Usuarios</h1>

<div class="card">
    <h2>Control de usuarios y sanciones</h2>
    <p class="muted">Aqui puedes dar de baja cuentas, descalificar usuarios que incumplen reglas y rehabilitarlos si corresponde.</p>
    <table>
        <thead><tr><th>Usuario</th><th>Registro</th><th>Estado</th><th>Motivo</th><th>Accion</th></tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>
                    <strong>{{ $user->full_name ?: $user->name }}</strong><br>
                    <small>{{ $user->email }}</small><br>
                    <small>{{ $user->phone ?: 'sin telefono' }}</small>
                </td>
                <td>
                    <div>Cedula: {{ $user->cedula ?: '-' }}</div>
                    <div>Alta: {{ $user->created_at }}</div>
                    <small>Ultimo login: {{ $user->last_login_at ?: '-' }}</small>
                </td>
                <td>
                    <div><span class="pill">{{ $user->is_active ? 'activo' : 'inactivo' }}</span></div>
                    <div style="margin-top:8px"><span class="pill">{{ $user->disqualified_at ? 'descalificado' : 'habilitado' }}</span></div>
                </td>
                <td>{{ $user->disqualification_reason ?: '-' }}</td>
                <td>
                    <form method="post" action="{{ route('admin.users.update', $user) }}" class="grid">
                        @csrf
                        @method('put')
                        <div class="row">
                            <select name="is_active">
                                <option value="1" @selected($user->is_active)>Activo</option>
                                <option value="0" @selected(! $user->is_active)>Inactivo</option>
                            </select>
                            <select name="disqualify_user">
                                <option value="0" @selected(! $user->disqualified_at)>Sin sancion</option>
                                <option value="1" @selected((bool) $user->disqualified_at)>Descalificar</option>
                            </select>
                        </div>
                        <input name="disqualification_reason" value="{{ $user->disqualification_reason }}" placeholder="Motivo de baja o incumplimiento">
                        <button type="submit">Guardar estado</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No hay usuarios clientes registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
