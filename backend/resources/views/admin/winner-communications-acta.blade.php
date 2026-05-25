<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acta de Comunicaciones del Ganador</title>
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

    <h1>Acta de Comunicaciones del Ganador</h1>
    <div class="meta">
        <p><strong>Participante:</strong> {{ $winner->user->full_name }}</p>
        <p><strong>Fase:</strong> {{ $winner->phase->name }}</p>
        <p><strong>Cédula:</strong> {{ $winner->user->cedula }}</p>
        <p><strong>Correo:</strong> {{ $winner->user->email }}</p>
        <p><strong>Teléfono:</strong> {{ $winner->user->phone }}</p>
        <p><strong>Estado actual:</strong> {{ $winner->status }}</p>
        <p><strong>Fecha de generación:</strong> {{ $generatedAt }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Canal</th>
                <th>Resultado</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
        @forelse($winner->contacts->sortBy('contacted_at') as $contact)
            <tr>
                <td>{{ $contact->contacted_at }}</td>
                <td>{{ $contact->contact_type }}</td>
                <td>{{ $contact->contact_status }}</td>
                <td>{{ $contact->notes }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No hay gestiones registradas todavía.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature">Firma del operador responsable</div>
        <div class="signature">Firma de verificación / supervisión</div>
    </div>
</body>
</html>
