@extends('admin.layout')

@section('content')
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="{{ route('admin.users') }}" style="padding:7px 14px;border-radius:10px;background:#20313a;border:1px solid var(--line);font-size:13px">&larr; Volver</a>
    <h1 style="margin:0">Editar participante</h1>
    @if($user->disqualified_at)
        <span class="pill" style="background:#2d1a1a;border-color:#7a2020">descalificado</span>
    @endif
    <a class="pill" href="{{ route('admin.users.audit', $user) }}">Ver auditoria completa</a>
</div>

<div class="card">
    <h2>Datos del participante</h2>
    <p class="muted">Esta pantalla es solo para administradores y permite editar la informacion completa del participante.</p>

    <form method="post" action="{{ route('admin.users.update', $user) }}" class="grid">
        @csrf
        @method('put')

        <div class="row">
            <input name="name" value="{{ old('name', $user->name) }}" placeholder="Nombre completo">
            <input name="cedula" value="{{ old('cedula', $user->cedula) }}" placeholder="Cédula">
        </div>

        <div class="row">
            <input name="document_type" value="{{ old('document_type', $user->document_type) }}" placeholder="Tipo de documento">
            <input name="email" type="email" value="{{ old('email', $user->email) }}" placeholder="Correo">
        </div>

        <div class="row">
            <input name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Teléfono">
            <select name="branch_id">
                <option value="">Sucursal opcional</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $user->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="row">
            <input name="birthdate" type="date" value="{{ old('birthdate', optional($user->birthdate)->format('Y-m-d')) }}" placeholder="Fecha de nacimiento">
            <select name="is_active">
                <option value="1" @selected(old('is_active', (int) $user->is_active) == 1)>Activo</option>
                <option value="0" @selected(old('is_active', (int) $user->is_active) == 0)>Inactivo</option>
            </select>
        </div>

        <div class="row">
            <select name="resides_in_panama">
                <option value="1" @selected(old('resides_in_panama', (int) $user->resides_in_panama) == 1)>Reside en Panama</option>
                <option value="0" @selected(old('resides_in_panama', (int) $user->resides_in_panama) == 0)>No reside en Panama</option>
            </select>
            <select name="is_employee">
                <option value="0" @selected(old('is_employee', (int) $user->is_employee) == 0)>No es empleado</option>
                <option value="1" @selected(old('is_employee', (int) $user->is_employee) == 1)>Es empleado</option>
            </select>
        </div>

        <div class="row">
            <input name="password" type="password" placeholder="Nueva contraseña (opcional)">
            <select name="disqualify_user">
                <option value="0" @selected(! old('disqualify_user', (bool) $user->disqualified_at))>Sin sancion</option>
                <option value="1" @selected(old('disqualify_user', (bool) $user->disqualified_at))>Descalificar</option>
            </select>
        </div>

        <textarea name="disqualification_reason" placeholder="Motivo de baja o incumplimiento">{{ old('disqualification_reason', $user->disqualification_reason) }}</textarea>

        <button type="submit">Guardar participante</button>
    </form>
</div>
@endsection
