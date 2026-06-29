<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empleados y participación</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;margin:32px}
        h1,h2,p{margin:0 0 12px}
        .meta{margin-bottom:24px}
        table{width:100%;border-collapse:collapse;margin-top:16px;font-size:13px}
        th,td{border:1px solid #bbb;padding:8px;text-align:left;vertical-align:top}
        th{background:#f2f2f2}
        td.num{text-align:right}
        .summary{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
        .summary div{border:1px solid #bbb;border-radius:8px;padding:10px 12px}
        .summary strong{display:block;font-size:20px}
        @media print {.no-print{display:none} body{margin:18px} table{font-size:11px}}
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <h1>Empleados y participación — PRONOSTICA EL MUNDIAL Y GANA</h1>
    <div class="meta">
        <p><strong>Fecha de generación:</strong> {{ $generatedAt }}</p>
        <p><strong>Filtro aplicado:</strong> {{ $filter === 'yes' ? 'Solo participan' : ($filter === 'no' ? 'Solo no participan' : 'Todos') }}</p>
    </div>

    <div class="summary">
        <div>Empleados cargados<strong>{{ $summary['total'] }}</strong></div>
        <div>Participando<strong>{{ $summary['participating'] }}</strong></div>
        <div>No participando<strong>{{ $summary['not_participating'] }}</strong></div>
        <div>Dentro de ganadores<strong>{{ $summary['winners'] }}</strong></div>
        <div>Descalificados<strong>{{ $summary['disqualified'] }}</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Participa</th>
                <th>Fecha de registro</th>
                <th>Facturas</th>
                <th>Pts. facturas</th>
                <th>Pts. pronósticos</th>
                <th>Total puntos</th>
                <th>Posición</th>
                <th>¿Ganador?</th>
                <th>Desplaza a</th>
                <th>Descalificado</th>
            </tr>
        </thead>
        <tbody>
        @forelse($employees as $row)
            <tr>
                <td>{{ $row['cedula'] }}</td>
                <td>{{ $row['name'] ?: '—' }}</td>
                <td>{{ $row['is_participating'] ? 'Sí' : 'No' }}</td>
                <td>{{ $row['registered_at'] ? $row['registered_at']->format('Y-m-d H:i') : '—' }}</td>
                <td class="num">{{ $row['invoice_count'] }}</td>
                <td class="num">{{ number_format($row['invoice_points']) }}</td>
                <td class="num">{{ number_format($row['prediction_points']) }}</td>
                <td class="num">{{ number_format($row['total_points']) }}</td>
                <td class="num">{{ $row['position'] ?? '—' }}</td>
                <td>{{ $row['is_winner'] ? 'Sí' : 'No' }}</td>
                <td>{{ $row['displaced_client'] ? $row['displaced_client']['name'].' (puesto '.$row['displaced_client']['position'].')' : '—' }}</td>
                <td>{{ $row['is_disqualified'] ? 'Sí' : 'No' }}</td>
            </tr>
        @empty
            <tr><td colspan="12">No hay empleados que coincidan con este filtro.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
