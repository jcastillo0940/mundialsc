<!doctype html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Recupera tu contraseña · {{ $appName }}</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style>
    body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
    table,td{mso-table-lspace:0;mso-table-rspace:0}
    img{-ms-interpolation-mode:bicubic;border:0;outline:none;text-decoration:none}
    body{margin:0!important;padding:0!important;background-color:#020b27}
    a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important}
    @media only screen and (max-width:620px){
      .email-container{width:100%!important;max-width:100%!important}
      .fluid{max-width:100%!important;height:auto!important}
      .stack-column,.stack-column-center{display:block!important;width:100%!important;max-width:100%!important}
      .pad-sides{padding-left:20px!important;padding-right:20px!important}
      .btn-main{padding:16px 28px!important;font-size:16px!important}
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#020b27;word-break:break-word">

<!-- Preheader -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;color:#020b27;line-height:1px">
  Hola {{ $name }}, sigue el enlace para restablecer tu contraseña en {{ $appName }}.&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌
</div>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#020b27">
  <tr>
    <td align="center" style="padding:32px 16px">

      <!-- ── Card ── -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width:600px;background-color:#031540;border-radius:20px;border:1px solid #0d2d6e;overflow:hidden">

        <!-- ── Header strip ── -->
        <tr>
          <td style="background-color:#042080;padding:0">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
              <tr>
                <!-- Logo cell -->
                <td width="72" style="padding:20px 0 20px 24px;vertical-align:middle">
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                      <td style="background-color:#020b27;border-radius:12px;width:52px;height:52px;text-align:center;vertical-align:middle">
                        <!-- SC logotype text fallback -->
                        <span style="font-family:Arial,sans-serif;font-size:18px;font-weight:900;color:#ffd454;letter-spacing:-1px;display:block;line-height:52px;width:52px">SC</span>
                      </td>
                    </tr>
                  </table>
                </td>
                <!-- Brand name -->
                <td style="padding:20px 24px 20px 12px;vertical-align:middle">
                  <p style="margin:0;font-family:Arial,sans-serif;font-size:11px;font-weight:700;color:#ffd454;letter-spacing:0.14em;text-transform:uppercase;line-height:1">SUPER CARNES</p>
                  <p style="margin:4px 0 0;font-family:Arial,sans-serif;font-size:18px;font-weight:900;color:#ffffff;letter-spacing:-0.02em;line-height:1">MUNDIAL 2026</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── Gold divider ── -->
        <tr>
          <td style="background-color:#ffcf24;height:3px;font-size:0;line-height:0">&nbsp;</td>
        </tr>

        <!-- ── Hero ── -->
        <tr>
          <td style="padding:36px 32px 8px" class="pad-sides">
            <!-- Tag -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
              <tr>
                <td style="background-color:#0a1e5e;border:1px solid #1a3b8a;border-radius:999px;padding:5px 14px">
                  <span style="font-family:Arial,sans-serif;font-size:11px;font-weight:700;color:#7ab8ff;letter-spacing:0.1em;text-transform:uppercase">Seguridad de cuenta</span>
                </td>
              </tr>
            </table>
            <h1 style="margin:16px 0 12px;font-family:Arial,sans-serif;font-size:28px;font-weight:900;color:#ffffff;line-height:1.1;letter-spacing:-0.02em">Recupera tu<br>contraseña</h1>
            <p style="margin:0;font-family:Arial,sans-serif;font-size:15px;color:#7a9ab8;line-height:1.65">
              Hola <strong style="color:#e8eeff">{{ $name }}</strong>, recibimos una solicitud para restablecer tu contraseña en <strong style="color:#e8eeff">{{ $appName }}</strong>.
            </p>
          </td>
        </tr>

        <!-- ── CTA card ── -->
        <tr>
          <td style="padding:24px 32px" class="pad-sides">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#041d6e;border:1px solid #1a3b8a;border-radius:16px;overflow:hidden">
              <tr>
                <td style="padding:24px 24px 8px">
                  <p style="margin:0;font-family:Arial,sans-serif;font-size:15px;color:#c4d8f0;line-height:1.65">
                    Haz clic en el botón para crear una nueva contraseña. El enlace es de un solo uso y expira por seguridad.
                  </p>
                </td>
              </tr>
              <tr>
                <td style="padding:16px 24px 24px">
                  <!-- Button -->
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                      <td style="border-radius:12px;background-color:#ffcf24">
                        <a href="{{ $url }}" class="btn-main" style="display:inline-block;padding:15px 32px;font-family:Arial,sans-serif;font-size:15px;font-weight:900;color:#020b27;text-decoration:none;border-radius:12px;letter-spacing:0.01em">
                          🔑&nbsp; Cambiar contraseña
                        </a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── Link fallback ── -->
        <tr>
          <td style="padding:0 32px 24px" class="pad-sides">
            <p style="margin:0;font-family:Arial,sans-serif;font-size:12px;color:#4a6a8a;line-height:1.6">
              Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
              <a href="{{ $url }}" style="color:#7ab8ff;word-break:break-all">{{ $url }}</a>
            </p>
          </td>
        </tr>

        <!-- ── Security notice ── -->
        <tr>
          <td style="padding:0 32px 32px" class="pad-sides">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#020b27;border:1px solid #0d2d6e;border-radius:14px">
              <tr>
                <td style="padding:16px 20px">
                  <p style="margin:0 0 4px;font-family:Arial,sans-serif;font-size:11px;font-weight:700;color:#ffd454;letter-spacing:0.1em;text-transform:uppercase">Aviso de seguridad</p>
                  <p style="margin:0;font-family:Arial,sans-serif;font-size:13px;color:#4a6a8a;line-height:1.6">Si no solicitaste este cambio, ignora este correo. Nadie puede acceder a tu cuenta sin el enlace.</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── Footer ── -->
        <tr>
          <td style="background-color:#020d35;border-top:1px solid #0d2d6e;padding:20px 32px" class="pad-sides">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
              <tr>
                <td>
                  <p style="margin:0;font-family:Arial,sans-serif;font-size:12px;color:#2e4d70;line-height:1.6">
                    © {{ date('Y') }} {{ $appName }} · Todos los derechos reservados<br>
                    ¿Necesitas ayuda? Escríbenos a <a href="mailto:{{ $supportEmail }}" style="color:#4a7ab8;text-decoration:none">{{ $supportEmail }}</a>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!-- /Card -->

    </td>
  </tr>
</table>
</body>
</html>
