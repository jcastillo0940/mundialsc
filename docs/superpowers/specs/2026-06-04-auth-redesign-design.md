# Exact Auth Redesign Design

## Goal
Rehacer la pantalla publica de autenticacion para que replique lo mas fielmente posible el arte de referencia entregado por el usuario, manteniendo intacta la logica actual de login y registro.

## Scope
- Reemplazar la presentacion visual del `/login`.
- Conservar tabs de `Iniciar sesion` y `Registrarse`.
- Mantener los flujos actuales de submit, validaciones y registro por pasos.
- Dejar placeholders con rutas estables para assets faltantes.

## Layout Requirements
- Fondo azul futbolero full-screen.
- Logo centrado arriba.
- Hero izquierdo con titular gigante, subtitulo, beneficios y zona de personajes.
- Card blanca derecha con tabs, campos, CTA amarillo, social login y bloque de seguridad.
- Barra inferior azul con pasos numerados y texto legal.

## Asset Rules
Como el usuario no cuenta con los originales, se deben dejar rutas exactas para placeholders reemplazables:
- `frontend/public/redesign/auth-logo-super-carnes.svg`
- `frontend/public/redesign/auth-player-left.svg`
- `frontend/public/redesign/auth-mascot-center.svg`
- `frontend/public/redesign/auth-ball-center.svg`
- `frontend/public/redesign/auth-stadium-bg.svg`
- `frontend/public/redesign/auth-crowd-overlay.svg`
- `frontend/public/redesign/auth-confetti-layer.svg`

## Non-Goals
- No rediseñar aun las pantallas internas.
- No alterar endpoints, contratos API ni reglas de validacion.

