# Live Score Cron Para Servidor

Este proyecto no depende de un webhook para actualizar partidos. La sincronizacion se ejecuta desde Laravel Scheduler.

## Resumen

- `fixtures` se sincroniza 1 vez al dia por defecto.
- `live` se sincroniza cada 3 minutos por defecto.
- `commentary` se sincroniza cada 3 minutos por defecto.
- Todos esos intervalos se pueden ajustar desde el backoffice en **Integraciones**.

## Requisito minimo en servidor

El servidor debe ejecutar el scheduler de Laravel cada minuto.

### Opcion recomendada

Si tu entorno permite un proceso persistente:

```bash
php artisan schedule:work
```

### Opcion clasica con cron

Si prefieres cron del sistema, agrega una tarea que corra cada minuto:

```bash
* * * * * cd /ruta/al/proyecto/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Que hace cada tarea

- `livescore:sync-fixtures`
  - Importa o actualiza el calendario.
  - Recomendado: 1 vez al dia.

- `livescore:sync-live`
  - Actualiza marcador y estado de partidos en curso.
  - Recomendado: cada 3 minutos.

- `livescore:sync-commentary`
  - Guarda eventos como goles, tarjetas y jugadas importantes.
  - Recomendado: cada 3 minutos.

## Configuracion desde el backoffice

En `Integraciones` el admin puede definir:

- intervalo de fixtures en horas
- intervalo de live en minutos
- intervalo de commentary en minutos
- activacion o desactivacion de auto commentary

## Variables de entorno utiles

Estas variables son opcionales porque el backoffice puede controlar parte de la operacion:

- `LIVE_SCORE_API_KEY`
- `LIVE_SCORE_API_SECRET`
- `LIVE_SCORE_API_COMPETITION_ID`
- `LIVE_SCORE_API_COMPETITION_IDS`
- `LIVE_SCORE_API_LANG`
- `LIVE_SCORE_FIXTURES_SYNC_INTERVAL_HOURS`
- `LIVE_SCORE_LIVE_SYNC_INTERVAL_MINUTES`
- `LIVE_SCORE_COMMENTARY_SYNC_INTERVAL_MINUTES`

## Notas operativas

- Si no hay partidos activos, el sync de live/commentary deberia consumir pocas o ninguna request util.
- Si un partido ya quedo en `final`, el sistema deja de usarlo para pronosticos y no debe seguir sumando puntos.
- El pronostico del usuario de desempate se guarda en `users.group_stage_goal_prediction` y el ranking lo compara contra los goles reales de la fase de grupos.
