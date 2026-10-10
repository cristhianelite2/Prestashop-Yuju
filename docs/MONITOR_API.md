# API del monitor: integración del módulo

Referencia rápida de cómo el módulo `prestashopyuju` se integra con el monitor
(`https://yuju.ceballosleon.com`). El contrato completo de endpoints, campos,
fechas e idempotencia vive en el repositorio del monitor:
`yuju-module-monitor/docs/api-contract.md`.

## Principios

- **Registro solo tras autorización**: la tienda solo se registra cuando la
  validación existente de credenciales (`YujuApiClient::testConnection()`)
  devuelve `state === 'connected'` sin `needs_auth`. Un mero formato correcto de
  credenciales no registra nada.
- **No viajan credenciales**: hacia el monitor solo van datos mínimos de
  identificación (dominio canónico, nombre, versiones y fecha UTC). Nada de
  `client_id`, `secret`, códigos de autorización ni datos de pago.
- **API key persistente e idempotente**: la primera instalación recibe su clave
  (`yjm_…`); recuperarla o re-registrar devuelve la **misma** clave. No se rota
  por re-validación. La clave se guarda en `Configuration` (`YUJU_MONITOR_TOKEN`)
  y no se muestra de nuevo en el panel.
- **El monitor nunca interrumpe**: timeouts cortos (1 s conexión / 2 s total),
  envío best-effort al cierre de la petición y fallos solo a log local sin secretos.

## Flujos

### 1. Registro (`POST /api/v1/modules/register`)

Se ejecuta desde `AdminYujuConfigurationController::ajaxProcessTestConnectivity`
después de que la prueba de conectividad resulte satisfactoria
(`YujuMonitor::registerIfAuthorized($connectionTest, true)`).

Request:

```json
{
  "key": "<sha256 de yuju + fecha/hora, ventana ±2 min>",
  "url": "https://tienda-ejemplo.com",
  "name": "Mi Tienda",
  "module_version": "1.0.8",
  "prestashop_version": "8.2.0",
  "registered_at": "2026-10-10 09:30:00"
}
```

Cabecera `X-API-Key` opcional: si la tienda ya tiene clave, se presenta para que
el monitor devuelva la misma (recuperación, sin rotación).

Respuestas: `201` (creada) o `200` (ya existente) con `api_key`,
`already_registered` e `installation`. El módulo guarda `api_key` en
`YUJU_MONITOR_TOKEN`.

### 2. Actividad (`POST /api/v1/modules/activity`)

Al cierre de cada petición PHP el módulo envía un único lote (máx. 50 eventos)
autenticado con la cabecera `X-API-Key: <api key>`.

Tipos de evento:

| Tipo           | Origen                                                        |
| -------------- | ------------------------------------------------------------- |
| `api.request`  | Cada llamada a la API de Yuju (`YujuApiClient`)               |
| `sync.product` | Ejecución de sincronización de productos (agregado)           |
| `sync.order`   | Webhooks de pedido y envío de pedidos a Yuju (agregado)       |
| `sync.error`   | Fallo de ejecución (productos y pedidos)                      |

Los contadores de sincronización se agregan por ejecución con
`YujuMonitor::tally()` (un evento por tipo con `count`/`failedCount` y un
`correlationId` único por lote), en lugar de un evento por producto/pedido. Los
reintentos con el mismo `correlationId` no duplican contadores en el monitor.

Cada evento usa `occurredAt` en UTC (`gmdate('Y-m-d H:i:s')`).

## Configuración manual

1. El monitor debe estar desplegado y accesible (por defecto
   `https://yuju.ceballosleon.com`).
2. En el panel del módulo, rellenar las credenciales de Yuju y pulsar
   **Probar conectividad** en la sección de conexión: si la validación es
   satisfactoria, la tienda queda registrada en el monitor y su API key se
   guarda automáticamente (`YUJU_MONITOR_URL` / `YUJU_MONITOR_TOKEN`).
3. Ver la tienda, sus métricas y su historial en el dashboard del monitor
   (panel de administración → Instalaciones).

## Configuración interna (Configuration)

- `YUJU_MONITOR_URL`: base del monitor (sin barra final).
- `YUJU_MONITOR_TOKEN`: API key de la instalación (nunca se expone en el panel).
- `YUJU_MONITOR_LAST_CONNECT`: marca de tiempo del último intento automático
  (throttle de 6 h para llamadas no explícitas).