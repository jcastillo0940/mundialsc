# Base de datos WampServer

La base del MVP esta lista en:

- [super_carnes_mvp.sql](E:\fureact\database\super_carnes_mvp.sql)

## Incluye

- base `super_carnes_mvp`
- tablas del negocio
- relaciones e indices
- tabla `personal_access_tokens` para Sanctum
- vistas utiles
- semillas iniciales
- triggers para wallet y cupones

## Importacion

1. Abrir `phpMyAdmin` o consola MySQL de WampServer.
2. Ejecutar:

```sql
SOURCE E:/fureact/database/super_carnes_mvp.sql;
```

## Credenciales semilla

- Admin: `admin@supercarnes.local` / `SuperCarnes123!`
- Cajero: `caja.central@supercarnes.local` / `SuperCarnes123!`

## Estado inicial

- campana base activa
- modulo de juegos apagado por defecto
- premios directos y de consolacion cargados

## Nota

La expiracion automatica de cupones por evento MySQL queda comentada en el SQL. Ya quedo implementado en Laravel con el comando:

```powershell
php artisan supercarnes:expire-coupons
```

Para dejarlo automatico:

```powershell
php artisan schedule:work
```
