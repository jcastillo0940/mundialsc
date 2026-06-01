@extends('admin.layout')

@section('content')
<h1>Sucursales</h1>

<div class="card">
    <h2>Nueva sucursal</h2>
    <form method="post" action="{{ route('admin.branches.store') }}" class="grid">
        @csrf
        <div class="row">
            <input name="name" placeholder="Nombre (ej: Albrook)" required>
            <input name="code" placeholder="Código único en MAYUSCULAS_SIN_ESPACIOS (ej: ALBROOK)" required style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9_]/g,'')">
            <input name="address" placeholder="Dirección (opcional)">
            <input name="phone" placeholder="Teléfono (opcional)">
        </div>
        <button type="submit" style="max-width:220px">Crear sucursal</button>
    </form>
</div>

<div class="card">
    <h2>Sucursales registradas</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Código</th>
                <th>Dirección</th>
                <th>Teléfono</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($branches as $branch)
            <tr>
                <td class="muted">{{ $branch->id }}</td>
                <td>{{ $branch->name }}</td>
                <td><span class="pill">{{ $branch->code }}</span></td>
                <td>{{ $branch->address ?? '—' }}</td>
                <td>{{ $branch->phone ?? '—' }}</td>
                <td>
                    @if($branch->is_active)
                        <span class="pill" style="border-color:#1f8f63;color:#4dd4a0">Activa</span>
                    @else
                        <span class="pill" style="border-color:#b23c2f;color:#f08080">Inactiva</span>
                    @endif
                </td>
                <td>
                    <form method="post" action="{{ route('admin.branches.update', $branch) }}" style="display:grid;gap:6px">
                        @csrf
                        @method('put')
                        <input name="name" value="{{ $branch->name }}" required placeholder="Nombre">
                        <input name="address" value="{{ $branch->address }}" placeholder="Dirección">
                        <input name="phone" value="{{ $branch->phone }}" placeholder="Teléfono">
                        <select name="is_active">
                            <option value="1" @selected($branch->is_active)>Activa</option>
                            <option value="0" @selected(!$branch->is_active)>Inactiva</option>
                        </select>
                        <button type="submit" class="ghost" style="max-width:120px">Guardar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
