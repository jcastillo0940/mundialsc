<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Backoffice</title>
    <style>
        body{margin:0;display:grid;place-items:center;min-height:100vh;background:#0f171b;color:#fff7ea;font-family:Arial,sans-serif}
        .card{width:min(420px,92vw);background:#18262d;border:1px solid #24343b;border-radius:18px;padding:24px}
        input,button{width:100%;padding:12px;border-radius:10px;border:1px solid #35505c;background:#10181c;color:#fff7ea;margin-top:10px}
        button{background:#d85b2a;border:0;cursor:pointer}
    </style>
</head>
<body>
    <form class="card" method="post" action="{{ route('admin.login.submit') }}">
        @csrf
        <h1>Backoffice Super Carnes</h1>
        <p>Administra partidos, reglas y premios del torneo.</p>
        @if($errors->any())
            <p>{{ $errors->first() }}</p>
        @endif
        <input type="email" name="email" placeholder="Correo" value="{{ old('email') }}" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
