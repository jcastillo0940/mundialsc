@extends('admin.layout')

@section('content')
<h1>Usuarios</h1>

<div class="card">
    <form method="get" action="{{ route('admin.users') }}" class="grid" style="margin-bottom:16px">
        <div class="row">
            <input name="query" value="{{ $query ?? '' }}" placeholder="Buscar por nombre, cédula o correo">
            <button type="submit">Buscar</button>
            @if(!empty($query))
                <a href="{{ route('admin.users') }}" style="padding:11px 12px;border-radius:12px;background:#20313a;border:1px solid var(--line);text-align:center;color:var(--text)">Limpiar</a>
            @endif
        </div>
    </form>

    <h2>Control de usuarios y sanciones</h2>
    <p class="muted">Aqui puedes buscar participantes, editar su informacion completa y tambien dar de baja cuentas o descalificarlas si corresponde.</p>
    <table>
        <thead>
            <tr><th>Usuario</th><th>Registro</th><th>Estado</th><th>Motivo</th><th>Accion</th></tr>
        </thead>
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
                    <a class="pill" href="{{ route('admin.users.edit', $user) }}" style="display:inline-block;margin-bottom:10px">Editar participante</a>
                    <a class="pill" href="{{ route('admin.users.audit', $user) }}" style="display:inline-block;margin-bottom:10px">Ver auditoria</a>
                    <form method="post" action="{{ route('admin.users.update', $user) }}" class="grid">
                        @csrf
                        @method('put')
                        <input type="hidden" name="name" value="{{ $user->name }}">
                        <input type="hidden" name="cedula" value="{{ $user->cedula }}">
                        <input type="hidden" name="document_type" value="{{ $user->document_type }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">
                        <input type="hidden" name="phone" value="{{ $user->phone }}">
                        <input type="hidden" name="branch_id" value="{{ $user->branch_id }}">
                        <input type="hidden" name="birthdate" value="{{ optional($user->birthdate)->format('Y-m-d') }}">
                        <input type="hidden" name="resides_in_panama" value="{{ (int) $user->resides_in_panama }}">
                        <input type="hidden" name="is_employee" value="{{ (int) $user->is_employee }}">
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
