@extends('admin.layout')

@section('content')
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px">
    <h1 style="margin:0">Empleados</h1>
    @if($summary['total'] > 0)
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="{{ route('admin.employees.print', ['participates' => $filter]) }}" target="_blank" style="padding:11px 16px;border-radius:12px;background:#20313a;border:1px solid var(--line);font-weight:600;color:var(--text)">🖨 Imprimir / PDF</a>
            <a href="{{ route('admin.employees.export', ['participates' => $filter]) }}" style="padding:11px 16px;border-radius:12px;background:linear-gradient(135deg,#ff8a4d,#d85b2a);font-weight:600;color:#1a0f08">⬇ Descargar CSV</a>
        </div>
    @endif
</div>

<div class="card" style="margin-bottom:24px">
    <h2 style="margin-top:0">Cargar lista de empleados (CSV)</h2>
    <p class="muted" style="margin:0 0 14px">El archivo CSV debe tener encabezados <strong>cedula</strong> y <strong>name</strong>. Si una cédula ya existe, se actualiza el nombre.</p>
    <form method="post" action="{{ route('admin.employees.import') }}" enctype="multipart/form-data" class="row" style="align-items:end">
        @csrf
        <div>
            <label style="display:block;margin-bottom:6px;color:var(--muted);font-size:13px">Archivo CSV</label>
            <input type="file" name="csv_file" accept=".csv,.txt" required style="padding:8px 12px">
        </div>
        <div>
            <button type="submit">Importar CSV</button>
        </div>
    </form>
    <details style="margin-top:14px">
        <summary style="cursor:pointer;color:var(--muted);font-size:13px">Ver formato del CSV</summary>
        <pre style="background:#0f171b;padding:12px;border-radius:8px;margin-top:8px;font-size:13px;overflow:auto">cedula,name
8-864-1164,Juan Perez
4-123-456,Maria Gonzalez</pre>
    </details>
</div>

@if($summary['total'] > 0)
    <div class="card" style="margin-bottom:24px;border-color:#ffcf24">
        <h2 style="margin-top:0">⚠ Resultado de la comparación</h2>
        <p style="margin:0;font-size:16px">
            De <strong>{{ $summary['total'] }}</strong> empleados cargados,
            <strong style="color:#ffd27a;font-size:20px">{{ $summary['participating'] }}</strong>
            están participando en la promoción (su cédula coincide con un registro de cliente).
        </p>
        <p class="muted" style="margin:6px 0 0">
            {{ $summary['not_participating'] }} empleados no tienen una cuenta registrada con esa cédula
            · <strong style="color:#ffd27a">{{ $summary['winners'] }}</strong> están actualmente dentro de las posiciones premiadas
            · <strong style="color:#ff9f7a">{{ $summary['disqualified'] }}</strong> están descalificados.
        </p>
    </div>
@endif

<div class="card" style="margin-bottom:24px">
    <form method="get" action="{{ route('admin.employees') }}" class="row" style="align-items:end">
        <div>
            <label style="display:block;margin-bottom:6px;color:var(--muted);font-size:13px">Filtrar por participación</label>
            <select name="participates">
                <option value="all" @selected($filter === 'all')>Todos</option>
                <option value="yes" @selected($filter === 'yes')>Solo participan</option>
                <option value="no" @selected($filter === 'no')>Solo no participan</option>
            </select>
        </div>
        <div>
            <button type="submit">Filtrar</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Listado — {{ $employees->count() }} empleados</h2>
    <table>
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Participando</th>
                <th>Fecha de registro</th>
                <th style="text-align:right">Facturas</th>
                <th style="text-align:right">Pts. facturas</th>
                <th style="text-align:right">Pts. pronósticos</th>
                <th style="text-align:right">Total puntos</th>
                <th style="text-align:right">Posición</th>
                <th>¿Ganador?</th>
                <th>Desplaza a</th>
                <th>Descalificado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($employees as $row)
            <tr>
                <td>{{ $row['cedula'] }}</td>
                <td>{{ $row['name'] ?: '—' }}</td>
                <td>
                    @if($row['is_participating'])
                        <span class="pill" style="background:#1a3a2a;border-color:#1f8f63">Sí participa</span>
                    @else
                        <span class="pill">No participa</span>
                    @endif
                </td>
                <td>{{ $row['registered_at'] ? $row['registered_at']->format('Y-m-d H:i') : '—' }}</td>
                <td style="text-align:right">{{ $row['invoice_count'] }}</td>
                <td style="text-align:right">{{ number_format($row['invoice_points']) }}</td>
                <td style="text-align:right">{{ number_format($row['prediction_points']) }}</td>
                <td style="text-align:right"><strong>{{ number_format($row['total_points']) }}</strong></td>
                <td style="text-align:right">{{ $row['position'] ?? '—' }}</td>
                <td>
                    @if($row['is_winner'])
                        <span class="pill" style="background:#3a2a1a;border-color:#ffcf24">🏆 Sí</span>
                    @else
                        <span class="pill">No</span>
                    @endif
                </td>
                <td>
                    @if($row['displaced_client'])
                        <small>{{ $row['displaced_client']['name'] }} (puesto {{ $row['displaced_client']['position'] }})</small>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td>
                    @if($row['is_disqualified'])
                        <span class="pill" style="background:#2d1a1a;border-color:#7a2020">Sí</span>
                    @else
                        <span class="pill">No</span>
                    @endif
                </td>
                <td>
                    @if($row['is_participating'] && ! $row['is_disqualified'])
                        <form method="post" action="{{ route('admin.employees.disqualify', $row['id']) }}" onsubmit="return confirm('¿Descalificar a {{ $row['name'] ?: $row['cedula'] }} por ser empleado?')">
                            @csrf
                            <button type="submit" class="danger" style="padding:6px 12px;font-size:13px;white-space:nowrap">Descalificar</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="12" class="muted">No hay empleados que coincidan con este filtro.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
