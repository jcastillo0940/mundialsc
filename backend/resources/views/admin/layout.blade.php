<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Backoffice Super Carnes' }}</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#10181c;color:#f6efe4}
        .wrap{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{padding:24px;background:#141f24;border-right:1px solid #24343b}
        .content{padding:24px}
        a{color:#ffd27a;text-decoration:none}
        .nav a{display:block;padding:10px 0}
        .card{background:#18262d;border:1px solid #24343b;border-radius:16px;padding:18px;margin-bottom:18px}
        .grid{display:grid;gap:16px}
        .grid.cols-3{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
        input,select,button{width:100%;padding:10px;border-radius:10px;border:1px solid #35505c;background:#0f171b;color:#f6efe4}
        button{background:#d85b2a;border:0;cursor:pointer}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #24343b;text-align:left;vertical-align:top}
        .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
        .status{background:#20313a;padding:10px 12px;border-radius:10px;margin-bottom:16px}
        form{margin:0}
    </style>
</head>
<body>
<div class="wrap">
    <aside class="sidebar">
        <h2>Backoffice</h2>
        <p>{{ auth()->user()->full_name ?? 'Operador' }}</p>
        <nav class="nav">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('admin.teams') }}">Ranking FIFA</a>
            <a href="{{ route('admin.matches') }}">Partidos</a>
            <a href="{{ route('admin.rules') }}">Reglas</a>
            <a href="{{ route('admin.prizes') }}">Premios</a>
            <a href="{{ route('admin.winners') }}">Ganadores</a>
            <a href="{{ route('admin.integrations') }}">Integraciones</a>
        </nav>
        <form method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">Cerrar sesión</button>
        </form>
    </aside>
    <main class="content">
        @if(session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="status">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
