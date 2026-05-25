# Super Carnes MVP

Monorepo MVP con:

- [backend Laravel](E:\fureact\backend)
- [frontend React + Vite](E:\fureact\frontend)
- [base de datos MySQL/WampServer](E:\fureact\database\super_carnes_mvp.sql)
- [documentacion funcional](E:\fureact\docs\super-carnes-mvp-architecture.md)

## Estado actual

- base de datos creada en MySQL local `super_carnes_mvp`
- API Laravel conectada a WampServer
- autenticacion por tokens con Sanctum
- registro, login, dashboard, escaneo de facturas, canje directo, cupones, cashier y resumen admin
- frontend React mobile-first consumiendo la API

## Credenciales

- Admin: `admin@supercarnes.local` / `SuperCarnes123!`
- Cajero: `caja.central@supercarnes.local` / `SuperCarnes123!`

## Live Score API

Configura estas variables en [backend/.env](E:\fureact\backend\.env:1):

- `LIVE_SCORE_API_KEY`
- `LIVE_SCORE_API_SECRET`
- `LIVE_SCORE_API_COMPETITION_ID` o `LIVE_SCORE_API_COMPETITION_IDS`
- `LIVE_SCORE_API_LANG`

Backoffice:

- `http://127.0.0.1:8000/admin/integrations`

Comandos:

```powershell
cd E:\fureact\backend
php artisan livescore:sync-fixtures
php artisan livescore:sync-live
php artisan livescore:sync-commentary
```

## Arranque rapido

### Backend

```powershell
cd E:\fureact\backend
php artisan serve --host=127.0.0.1 --port=8000
```

### Scheduler recomendado

```powershell
cd E:\fureact\backend
php artisan schedule:work
```

### Frontend

```powershell
cd E:\fureact\frontend
npm install
npm run dev
```

## URL esperadas

- API Laravel: `http://127.0.0.1:8000/api`
- Frontend Vite: `http://127.0.0.1:5173`

## Verificacion hecha

- `php artisan route:list`
- `php artisan test`
- `php artisan supercarnes:expire-coupons`
- `npm run build`
- login real admin y cliente
- registro real de cliente
- escaneo real de factura de prueba contra MySQL
- creacion real de campaña admin por API
