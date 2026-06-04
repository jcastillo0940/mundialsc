# Auth reCAPTCHA v2 Invisible

## Objetivo

Reemplazar la integracion actual de reCAPTCHA v3 por reCAPTCHA v2 Invisible en los flujos publicos de autenticacion del frontend: inicio de sesion, registro y finalizacion de registro de Google, manteniendo la experiencia visual actual del formulario.

## Alcance

- Cambiar la carga del script de Google en la SPA para usar la variante v2 Invisible.
- Cambiar la obtencion del token CAPTCHA en el flujo de submit del login, registro y Google complete registration.
- Adaptar la validacion del backend para aceptar la respuesta de siteverify de v2 Invisible.
- Mantener el uso de la configuracion publica/privada existente para site key y secret key, pero asumiendo que ahora corresponderan a llaves de v2 Invisible.

## Fuera de Alcance

- No cambiar `main`.
- No agregar hCaptcha ni doble proveedor.
- No mantener una capa hibrida v2 + v3.
- No rediseñar la UI de login/registro salvo lo minimo requerido para inicializar el captcha invisible.

## Enfoque Tecnico

### Frontend

- Reemplazar la logica actual basada en `grecaptcha.execute(siteKey, { action })` por una integracion de v2 Invisible.
- Renderizar instancias invisibles de reCAPTCHA de forma programatica para los flujos que hoy solicitan token antes del request.
- Ejecutar el captcha solo al enviar formularios sensibles.
- Si Google exige desafio, permitir que aparezca sin bloquear ni romper el estado actual del formulario.
- Si falla la emision del token, mostrar un error claro y no continuar con el request.

### Backend

- Mantener `recaptcha_token` como campo esperado en los endpoints de autenticacion.
- Verificar el token contra `https://www.google.com/recaptcha/api/siteverify`.
- Considerar valido el request cuando `success` sea verdadero.
- Eliminar dependencias de acciones o score propias de v3 si existen en la validacion actual.
- Mantener mensajes de error consistentes cuando el token falte o falle la validacion.

## Componentes Afectados

- `frontend/src/App.tsx`
- `backend/app/Http/Controllers/Api/AuthController.php`
- Configuracion publica que entrega `recaptcha_site_key`

## Manejo de Errores

- Si no hay site key configurada, el frontend no debe romperse; el backend seguira siendo la ultima linea de control segun configuracion.
- Si el script no carga o el captcha no emite token, se bloquea el submit y se informa al usuario.
- Si backend rechaza el token, se muestra el mensaje actual de error de autenticacion/CAPTCHA.

## Verificacion

- Login exitoso con token valido.
- Registro exitoso con token valido.
- Completar registro de Google con token valido.
- Intento sin token o con token invalido rechazado por backend.
- Build del frontend en verde.

## Riesgos

- La clave actual de reCAPTCHA v3 no sirve para v2 Invisible; produccion necesitara nuevas llaves en Google reCAPTCHA.
- El flujo invisible puede abrir un desafio visual en algunos casos; eso es comportamiento esperado.
- Si el backend sigue validando con reglas de v3, habra falsos rechazos hasta completar el cambio.
