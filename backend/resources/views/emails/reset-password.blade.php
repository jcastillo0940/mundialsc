<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recupera tu contraseña</title>
</head>
<body style="margin:0;background:#0c1317;font-family:Segoe UI,Arial,sans-serif;color:#eef4ef">
    <div style="background:radial-gradient(circle at top,#1d3b46 0,#0c1317 55%);padding:40px 16px">
        <div style="max-width:640px;margin:0 auto;background:rgba(20,32,40,.94);border:1px solid #2d4550;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.35)">
            <div style="padding:28px 28px 0">
                <div style="display:inline-block;padding:6px 12px;border-radius:999px;background:#20313a;border:1px solid #2d4550;color:#ffd27a;font-size:12px;letter-spacing:.12em;text-transform:uppercase">Super Carnes</div>
                <h1 style="margin:18px 0 10px;font-size:34px;line-height:1.05">Recupera tu acceso</h1>
                <p style="margin:0;color:#9fb4b2;font-size:16px;line-height:1.6">Hola {{ $name }}, recibimos una solicitud para restablecer tu contraseña en {{ $appName }}.</p>
            </div>

            <div style="padding:28px">
                <div style="background:linear-gradient(135deg,rgba(255,122,61,.18),rgba(218,201,105,.12));border:1px solid rgba(218,201,105,.32);border-radius:18px;padding:20px">
                    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#eef4ef">Haz clic en el botón para crear una nueva contraseña. El enlace expira por seguridad y solo debe usarse una vez.</p>
                    <a href="{{ $url }}" style="display:inline-block;background:linear-gradient(135deg,#ff8a4d,#d85b2a);color:#fff;text-decoration:none;padding:14px 20px;border-radius:14px;font-weight:700">Cambiar contraseña</a>
                </div>

                <div style="margin-top:20px;padding:18px;border:1px solid #2d4550;border-radius:16px;background:#10181d">
                    <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#ffd27a;margin-bottom:8px">Seguridad</div>
                    <p style="margin:0;color:#9fb4b2;line-height:1.6">Si no solicitaste este cambio, ignora este correo. No necesitas responderlo.</p>
                </div>
            </div>

            <div style="padding:0 28px 28px;color:#9fb4b2;font-size:13px;line-height:1.6">
                <p style="margin:0">Soporte: <a href="mailto:{{ $supportEmail }}" style="color:#ffd27a">{{ $supportEmail }}</a></p>
            </div>
        </div>
    </div>
</body>
</html>
