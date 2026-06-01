<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Backoffice Super Carnes' }}</title>
    <style>
        :root{--bg:#0c1317;--panel:#142028;--panel-soft:#1b2a33;--line:#2d4550;--text:#eef4ef;--muted:#9fb4b2;--accent:#ff7a3d;--accent-soft:#ffd5b8;--ok:#1f8f63;--warn:#b23c2f}
        *{box-sizing:border-box}
        body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:radial-gradient(circle at top,#19313b 0,#0c1317 42%);color:var(--text)}
        .wrap{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{padding:28px;background:rgba(10,18,22,.92);border-right:1px solid var(--line);backdrop-filter:blur(18px)}
        .content{padding:28px}
        a{color:#ffd27a;text-decoration:none}
        .nav{display:grid;gap:8px;margin:24px 0}
        .nav a{display:block;padding:12px 14px;border-radius:12px;background:transparent;border:1px solid transparent}
        .nav a:hover{background:rgba(255,255,255,.03);border-color:var(--line)}
        .card{background:rgba(20,32,40,.92);border:1px solid var(--line);border-radius:18px;padding:20px;margin-bottom:18px;box-shadow:0 20px 50px rgba(0,0,0,.18)}
        .grid{display:grid;gap:16px}
        .grid.cols-3{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
        .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
        input,select,textarea,button{width:100%;padding:11px 12px;border-radius:12px;border:1px solid #35505c;background:#0f171b;color:var(--text)}
        textarea{min-height:120px;resize:vertical}
        button{background:linear-gradient(135deg,#ff8a4d,#d85b2a);border:0;cursor:pointer;font-weight:600}
        button.danger{background:linear-gradient(135deg,#d65e4f,#9f2e22)}
        button.ghost{background:#20313a;border:1px solid var(--line)}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
        .status{background:#20313a;padding:12px 14px;border-radius:12px;margin-bottom:16px}
        .muted{color:var(--muted)}
        .pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#20313a;border:1px solid var(--line);font-size:12px}
        .metric{font-size:30px;font-weight:700;margin-top:6px}
        form{margin:0}
        .cursor-trail{position:fixed;inset:0;z-index:1000;overflow:hidden;pointer-events:none}
        .trail-dot{position:absolute;width:7px;height:7px;border-radius:999px;background:#ffd166;box-shadow:0 0 18px rgba(255,209,102,.82);animation:cursor-trail-fade .72s ease-out forwards;transform:translate(-50%,-50%)}
        @keyframes cursor-trail-fade{from{opacity:.92;transform:translate(-50%,-50%) scale(1)}to{opacity:0;transform:translate(-50%,-50%) scale(.2)}}
        @media (hover:none),(pointer:coarse),(prefers-reduced-motion:reduce){.cursor-trail{display:none}}
        @media (max-width: 980px){.wrap{grid-template-columns:1fr}.sidebar{border-right:0;border-bottom:1px solid var(--line)}}
    </style>
</head>
<body>
<div class="cursor-trail" aria-hidden="true"></div>
<div class="wrap">
    <aside class="sidebar">
        <h2>Backoffice</h2>
        <p>{{ auth()->user()->full_name ?? 'Operador' }}</p>
        <nav class="nav">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('admin.teams') }}">Ranking FIFA</a>
            <a href="{{ route('admin.matches') }}">Partidos</a>
            <a href="{{ route('admin.rules') }}">Reglas</a>
            <a href="{{ route('admin.points-audit') }}">Auditoría de puntos</a>
            <a href="{{ route('admin.prizes') }}">Premios</a>
            <a href="{{ route('admin.winners') }}">Ganadores</a>
            <a href="{{ route('admin.integrations') }}">Integraciones</a>
            <a href="{{ route('admin.users') }}">Usuarios</a>
            <a href="{{ route('admin.fraud') }}">Antifraude</a>
            <a href="{{ route('admin.site') }}">Sitio y SEO</a>
            <a href="{{ route('admin.branches') }}">Sucursales</a>
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
<script>
    (() => {
        const trail = document.querySelector('.cursor-trail');

        if (!trail || window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        let lastTrail = 0;

        const addTrail = (x, y) => {
            const dot = document.createElement('span');
            dot.className = 'trail-dot';
            dot.style.left = `${x}px`;
            dot.style.top = `${y}px`;
            trail.appendChild(dot);
            setTimeout(() => dot.remove(), 760);
        };

        window.addEventListener('mousemove', (event) => {
            const now = performance.now();
            if (now - lastTrail > 46) {
                addTrail(event.clientX, event.clientY);
                lastTrail = now;
            }
        }, { passive: true });
    })();
</script>
</body>
</html>
