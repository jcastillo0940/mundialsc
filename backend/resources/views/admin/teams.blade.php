@extends('admin.layout')

@section('content')
<h1>Ranking FIFA</h1>

@if(session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="status" style="border:1px solid var(--warn);color:var(--warn)">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card" style="margin-bottom:24px">
    <h2 style="margin-top:0">Importar rankings desde CSV</h2>
    <p class="muted" style="margin:0 0 14px">El archivo CSV debe tener encabezados <strong>code</strong> y <strong>ranking_fifa</strong>. El código debe coincidir con el código del equipo (ej. <code>MEXICO</code>, <code>BRAZIL</code>, <code>USA</code>).</p>
    <form method="post" action="{{ route('admin.teams.import-rankings') }}" enctype="multipart/form-data" class="row" style="align-items:end">
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
        <pre style="background:#0f171b;padding:12px;border-radius:8px;margin-top:8px;font-size:13px;overflow:auto">code,ranking_fifa
MEXICO,22
BRAZIL,1
USA,15
ARGENTINA,2
SPAIN,8</pre>
    </details>
</div>

<div class="card">
    <h2 style="margin-top:0">Equipos ({{ $teams->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>Equipo</th>
                <th>Grupo</th>
                <th>Código</th>
                <th>Ranking FIFA</th>
            </tr>
        </thead>
        <tbody>
        @foreach($teams as $team)
            <tr>
                <td>{{ $team->name }}</td>
                <td><span class="pill">{{ $team->group_label }}</span></td>
                <td><code>{{ $team->code }}</code></td>
                <td>
                    <form method="post" action="{{ route('admin.teams.ranking', $team) }}" class="row" style="align-items:center">
                        @csrf
                        @method('put')
                        <input name="ranking_fifa" type="number" min="1" max="999" value="{{ $team->ranking_fifa }}" placeholder="Ej. 14" style="max-width:100px">
                        <button type="submit" style="max-width:120px">Actualizar</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
