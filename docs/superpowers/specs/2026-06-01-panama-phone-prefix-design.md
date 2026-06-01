# Diseno De Prefijo Fijo Para Telefono

## Objetivo

Actualizar el campo de telefono del registro para mostrar `+507` de forma visible y fija, mientras el usuario solo escribe los 8 digitos locales.

## Decision

- Mantener el patron visual actual del formulario.
- Usar un bloque izquierdo con apariencia de selector listo para evolucionar a dropdown real.
- Limitar el input visible a los 8 digitos locales.
- Seguir enviando al backend el valor completo en formato `+507########`.

## Alcance

- Ajustar el markup del campo de telefono en `frontend/src/App.tsx`.
- Reutilizar la normalizacion existente para guardar el telefono completo.
- Actualizar mensajes de error para reflejar que el usuario ya no escribe el prefijo.
- Agregar estilos especificos sin alterar el resto del formulario.

## Fuera De Alcance

- Soporte multi-pais.
- Cambio de patron visual general del auth.
- Refactor del formulario completo.
