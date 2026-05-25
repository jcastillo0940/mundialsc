# Roadmap de Implementacion

## Fase 0 - Base tecnica

- Crear repositorio frontend React
- Crear API Laravel 13
- Configurar MySQL, `.env`, CORS y autenticacion
- Definir roles y middleware
- Crear migraciones base

## Fase 1 - Fidelidad MVP

- Registro, login y perfil cliente
- Escaneo QR con `html5-qrcode`
- Endpoint de parseo y registro de factura
- Wallet de goles y tiros
- Historial de facturas y movimientos
- Catalogo de premios directos
- Canje directo con cupon QR
- Portal de cajero para entrega
- Job de expiracion de cupones

## Fase 2 - Admin y control

- CRUD de campañas
- CRUD de premios
- Dashboard de inventario y auditoria
- Configuracion de topes diarios
- Reportes consolidados

## Fase 3 - Juegos

- Feature flag de juegos
- Endpoint de juego instant win
- Ventanas ocultas configurables
- Premios de consolacion
- UI de Mete Gol y Ruleta

## Prioridad recomendada

1. Sacar primero el flujo completo de fidelidad directa
2. Dejar el motor de juegos desacoplado y apagable por config
3. Activar azar solo cuando exista aprobacion JCJ

## Primer sprint sugerido

1. Modelado de base de datos
2. Autenticacion y roles
3. Registro de factura con `CUFE` unico
4. Wallet y movimientos
5. Catalogo y canje directo
6. Cupon QR y portal de cajero
