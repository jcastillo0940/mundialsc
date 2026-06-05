<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acta de Ganadores</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;margin:32px}
        h1,h2,p{margin:0 0 12px}
        .meta{margin-bottom:24px}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{border:1px solid #bbb;padding:10px;text-align:left;vertical-align:top}
        th{background:#f2f2f2}
        .signatures{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:48px}
        .signature{padding-top:48px;border-top:1px solid #333}
        @media print {.no-print{display:none} body{margin:18px}}
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <h1>Acta de Ganadores - PRONOSTICA EL MUNDIAL Y GANA</h1>
    <div class="meta">
        <p><strong>Fase:</strong> {{ $phase?->name ?? 'No disponible' }}</p>
        <p><strong>Fecha de generación:</strong> {{ $generatedAt }}</p>
        <p><strong>Descripción:</strong> Documento de control interno de selección de ganadores de la promoción.</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Puesto</th>
                <th>Participante</th>
                <th>Cédula</th>
                <th>Correo</th>
                <th>Teléfono</th>
                <th>Puntos</th>
                <th>Exactos</th>
                <th>Facturas</th>
                <th>Token de premio</th>
                <th>Estado</th>
                <th>Razón de selección</th>
            </tr>
        </thead>
        <tbody>
        @forelse($winners as $winner)
            <tr>
                <td>{{ $winner->leaderboard_position }}</td>
                <td>{{ $winner->user->full_name }}</td>
                <td>{{ $winner->user->cedula }}</td>
                <td>{{ $winner->user->email }}</td>
                <td>{{ $winner->user->phone }}</td>
                <td>{{ number_format((float) $winner->total_points, 2) }}</td>
                <td>{{ $winner->exact_hits }}</td>
                <td>{{ $winner->invoice_count }}</td>
                <td>{{ $winner->prizeToken?->token_code ?? 'sin token' }}</td>
                <td>{{ $winner->status }}</td>
                <td>{{ $winner->selection_reason }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10">No hay ganadores registrados.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature">Firma responsable de mercadeo</div>
        <div class="signature">Firma responsable de auditoría / administración</div>
    </div>
</body>
</html>
