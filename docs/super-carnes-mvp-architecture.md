# Super Carnes MVP

## Objetivo

Construir una plataforma web unificada para 15 sucursales que combine:

- fidelizacion por puntos ("Goles")
- intentos de juego ("Tiros")
- validacion de facturas electronicas por QR de Panama
- canje de premios con control estricto de inventario
- trazabilidad operativa, antifraude y auditoria

El MVP debe operar con `React` en frontend, `Laravel 13` en backend y `MySQL InnoDB` como base de datos.

## Principios del MVP

- Mobile-first para clientes que escanean desde pasillos o cajas
- Toda decision economica se resuelve en backend
- El frontend solo representa resultados ya confirmados por el servidor
- La base de datos es la fuente unica de verdad
- Toda operacion sensible usa transacciones atomicas y bloqueos de fila
- El modulo de azar debe poder desactivarse por configuracion sin romper la app

## Modulos funcionales

### Cliente

- Registro e inicio de sesion
- Escaneo de factura QR
- Consulta de balance de goles y tiros
- Historial de facturas y movimientos
- Tienda de fidelidad para canje directo
- Arena de juegos para consumo de tiros
- Visualizacion de cupones activos, entregados y expirados

### Cajero / Customer Service

- Inicio de sesion con rol interno
- Escaneo de cupon QR
- Validacion de estado del cupon
- Confirmacion de entrega
- Registro del cajero, sucursal y fecha de entrega

### SuperAdmin

- CRUD de catalogo de premios
- Configuracion de campañas
- Configuracion de ventanas de premio mayor
- Activacion o desactivacion del modulo de juegos
- Analitica y auditoria
- Exportacion de reportes

## Reglas de negocio

### Facturas

- Cada factura se identifica por `CUFE`
- `CUFE` debe ser unico globalmente
- Si una factura ya existe, la transaccion se rechaza
- Maximo 2 facturas por usuario por dia
- Las facturas fuera del tope diario se registran solo si negocio lo requiere; no otorgan beneficios adicionales
- Límite maximo de generacion diaria: 50 goles por cuenta
- Cada factura elegible otorga:
  - `1 gol` por cada `15.00 USD` de compra
  - `1 tiro` por factura que cumpla el minimo de campaña

### Fidelidad directa

- El cliente canjea premios con goles acumulados
- El backend valida stock y balance
- Si el canje procede:
  - descuenta goles
  - reserva stock
  - genera cupon QR
  - fija vencimiento a 72 horas

### Juegos

- El cliente consume `1 tiro` por intento
- El backend decide el resultado antes de responder
- La animacion no define el premio
- El motor usa ventanas de tiempo ocultas para premios mayores
- Si no hay premio mayor:
  - puede devolver premio de consolacion
  - o `siga_participando`

### Restricciones globales

- Maximo `1 televisor` por usuario durante toda la campaña
- Maximo `1 peticion de juego o canje por segundo por cuenta`
- Cupones vencen a las `72 horas`
- Al expirar un cupon no entregado:
  - cambia a `expired`
  - el stock reservado vuelve al inventario

## Flags operativos

Estas banderas deben existir en configuracion:

- `games_enabled`
- `invoice_scan_enabled`
- `redemption_enabled`
- `major_prizes_enabled`

Con esto se puede lanzar solo fidelidad directa mientras se completa la aprobacion JCJ.

## Arquitectura propuesta

### Frontend React

- SPA con rutas protegidas por rol
- `html5-qrcode` para captura de QR
- Cliente HTTP con manejo centralizado de token y errores
- Estado de autenticacion y balances por contexto o store liviano
- UI mobile-first
- Paginas:
  - login / registro
  - dashboard cliente
  - escaneo de factura
  - tienda de fidelidad
  - arena de juegos
  - mis cupones
  - portal de cajero
  - panel admin

### Backend Laravel 13

- API REST autenticada
- `Sanctum` o equivalente para autenticacion segura
- `Form Requests` para validacion
- `Policies` y `Middleware` por rol
- `DB transactions` para operaciones economicas
- `Rate Limiter` por usuario y endpoint
- `Queues` para:
  - expirar cupones
  - reprocesos
  - reportes
  - auditoria asincrona

### MySQL

- InnoDB
- indices unicos y compuestos
- bloqueo pesimista en redenciones y ventanas
- tablas de movimientos para trazabilidad

## Modelo de datos MVP

### `users`

- `id`
- `role` enum: `client`, `cashier`, `admin`
- `full_name`
- `cedula` unique
- `email` unique
- `phone`
- `password`
- `branch_id` nullable para cajeros
- timestamps

### `branches`

- `id`
- `name`
- `code` unique
- `address`
- `is_active`
- timestamps

### `campaigns`

- `id`
- `name`
- `status` enum: `draft`, `active`, `paused`, `closed`
- `starts_at`
- `ends_at`
- `invoice_min_amount_for_shot`
- `points_per_amount` default `1`
- `amount_per_point` default `15.00`
- `daily_max_points` default `50`
- `daily_max_invoices` default `2`
- `coupon_ttl_hours` default `72`
- `games_enabled`
- `major_prizes_enabled`
- timestamps

### `registered_invoices`

- `id`
- `user_id`
- `campaign_id`
- `branch_id` nullable
- `cufe` unique
- `qr_raw_text` longtext
- `invoice_number` nullable
- `fiscal_document_type` nullable
- `issued_at` nullable
- `purchase_amount`
- `points_awarded`
- `shots_awarded`
- `status` enum: `accepted`, `duplicate`, `accepted_no_rewards`
- timestamps

Indices:

- unique `cufe`
- index `(user_id, created_at)`
- index `(campaign_id, created_at)`

### `wallets`

- `id`
- `user_id` unique
- `goals_balance`
- `shots_balance`
- timestamps

### `wallet_movements`

- `id`
- `user_id`
- `wallet_id`
- `campaign_id` nullable
- `type` enum:
  - `invoice_points_credit`
  - `invoice_shots_credit`
  - `redeem_points_debit`
  - `game_shot_debit`
  - `coupon_expire_restock`
  - `manual_adjustment`
- `resource_type` nullable
- `resource_id` nullable
- `goals_delta`
- `shots_delta`
- `meta` json
- timestamps

### `prizes`

- `id`
- `campaign_id`
- `name`
- `slug`
- `category` enum: `major`, `consolation`
- `redemption_type` enum: `direct`, `instant_win`
- `points_cost` nullable
- `shots_cost` nullable
- `total_stock`
- `reserved_stock`
- `delivered_stock`
- `is_active`
- `image_url` nullable
- timestamps

Stock disponible:

- `available_stock = total_stock - reserved_stock - delivered_stock`

### `instant_win_windows`

- `id`
- `campaign_id`
- `prize_id`
- `opens_at`
- `closes_at`
- `is_consumed`
- `consumed_by_user_id` nullable
- `consumed_at` nullable
- timestamps

Indices:

- `(campaign_id, opens_at, closes_at)`
- `(prize_id, is_consumed)`

### `game_plays`

- `id`
- `user_id`
- `campaign_id`
- `game_type` enum: `mete_gol`, `ruleta`
- `client_choice` varchar
- `result_type` enum: `major_prize`, `consolation_prize`, `no_win`
- `prize_id` nullable
- `window_id` nullable
- `shots_spent`
- `played_at`
- `meta` json
- timestamps

### `coupons`

- `id`
- `user_id`
- `campaign_id`
- `prize_id`
- `source_type` enum: `direct_redemption`, `instant_win`
- `code` uuid unique
- `qr_payload` text
- `status` enum: `generated`, `delivered`, `expired`, `cancelled`
- `expires_at`
- `delivered_at` nullable
- `delivered_by_user_id` nullable
- `delivered_branch_id` nullable
- timestamps

### `audit_logs`

- `id`
- `user_id` nullable
- `actor_role` nullable
- `event_type`
- `entity_type`
- `entity_id` nullable
- `ip_address`
- `user_agent`
- `payload` json
- `created_at`

## Flujo de escaneo de factura

1. React escanea con `html5-qrcode`
2. Frontend envia `qr_raw_text`
3. Laravel extrae `CUFE` por parser dedicado
4. Laravel valida campaña activa y tope diario de facturas
5. Laravel intenta insertar la factura con `cufe` unico
6. Si hay duplicidad, responde error controlado
7. Si es nueva:
   - calcula goles
   - calcula tiros
   - respeta topes diarios
   - actualiza wallet en transaccion
   - registra movimientos
8. Devuelve balance actualizado e historial del registro

## Flujo de canje directo

1. Cliente solicita canje
2. Backend valida:
   - campaña activa
   - premio activo
   - saldo suficiente
   - limite de televisor por usuario
   - stock disponible
3. Inicia transaccion
4. Bloquea fila del premio `FOR UPDATE`
5. Revalida stock
6. Debita goles
7. Reserva stock
8. Crea cupon con expiracion a 72 horas
9. Confirma transaccion

## Flujo de juego instant win

1. Cliente envía tipo de juego y opcion visual
2. Backend aplica rate limit
3. Backend valida saldo de tiros
4. Inicia transaccion
5. Debita `1 tiro`
6. Busca ventana activa no consumida para premio mayor
7. Si existe:
   - bloquea ventana
   - marca consumida
   - reserva stock del premio
   - genera cupon
   - registra jugada ganadora
8. Si no existe:
   - evalua premio de consolacion o no premio
   - registra jugada
9. Frontend solo anima el resultado recibido

## Antifraude y consistencia

### Duplicidad

- Unicidad global por `CUFE`
- Idempotencia recomendada en endpoints sensibles con `Idempotency-Key`

### Rate limiting

- `/scan-invoice`: por usuario y por IP
- `/games/play`: `1 req/segundo`
- `/redemptions`: `1 req/segundo`
- `/coupons/redeem`: por cajero y por IP

### Controles de campaña

- Bloqueo por usuario si ya gano televisor
- Ocultar premios no elegibles desde API
- Validar ventana temporal de campaña en todo endpoint economico

### Trazabilidad

- Toda alteracion de saldo genera `wallet_movements`
- Toda entrega fisica genera `audit_logs`
- Todo rechazo relevante genera `audit_logs`

## API MVP sugerida

### Auth

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

### Cliente

- `GET /api/dashboard`
- `POST /api/invoices/scan`
- `GET /api/invoices`
- `GET /api/wallet`
- `GET /api/prizes/store`
- `POST /api/redemptions`
- `POST /api/games/play`
- `GET /api/coupons`
- `GET /api/coupons/{code}`

### Cajero

- `POST /api/cashier/coupons/scan`
- `POST /api/cashier/coupons/{code}/deliver`

### Admin

- `GET /api/admin/campaigns`
- `POST /api/admin/campaigns`
- `PUT /api/admin/campaigns/{id}`
- `GET /api/admin/prizes`
- `POST /api/admin/prizes`
- `PUT /api/admin/prizes/{id}`
- `GET /api/admin/windows`
- `POST /api/admin/windows`
- `PUT /api/admin/windows/{id}`
- `GET /api/admin/reports/summary`
- `GET /api/admin/audit-logs`

## Validaciones criticas backend

### Escaneo

- `qr_raw_text` requerido
- `CUFE` requerido y valido
- `purchase_amount > 0`
- campaña activa obligatoria

### Canje

- premio existe y activo
- saldo suficiente
- stock disponible
- no excede premio mayor por usuario

### Juego

- modulo habilitado
- usuario con tiros disponibles
- tipo de juego permitido

### Entrega de cupon

- cupon existe
- estado `generated`
- no expirado
- cajero autenticado

## Jobs programados

### `ExpireCouponsJob`

- Ejecuta cada minuto
- Busca cupones `generated` vencidos
- Marca `expired`
- libera stock reservado
- registra auditoria

### `CampaignWindowMaintenanceJob`

- opcional en MVP
- monitorea ventanas mal configuradas o superpuestas

### `MonthlyReportJob`

- genera consolidado PDF para admin

## Consideraciones legales y de lanzamiento

- El modulo de juegos debe quedar desacoplado por `feature flag`
- Si no existe aprobacion JCJ:
  - `games_enabled = false`
  - se oculta la Arena de Juegos
  - el sistema opera solo con Tienda de Fidelidad
- Debe existir evidencia administrativa de inventario y premios mayores

## No funcionales

- Soporte movil prioritario
- Tiempos de respuesta bajos en operaciones de wallet y canje
- Logs estructurados
- Backups diarios
- Monitoreo de errores

## Riesgos tecnicos a vigilar

- Parser QR incompleto para variantes reales de DGI
- Doble procesamiento por reintentos de red
- Sobre-reserva de stock si faltan bloqueos de fila
- Desfase entre stock reservado y cupones expirados
- Abuso por cuentas duplicadas si no se valida cedula correctamente

## Definicion de listo para MVP

- Registro y login por roles
- Escaneo funcional de facturas DGI
- Wallet de goles y tiros
- Canje directo con cupon QR
- Entrega por cajero
- Expiracion automatica de cupones
- Juegos instant win activables por flag
- Auditoria basica
- Reporte administrativo basico
