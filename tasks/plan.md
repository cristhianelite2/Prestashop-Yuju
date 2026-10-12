# Implementation Plan: Conciliar catálogo PrestaShop → Yuju

## Overview

Reemplazar el `GET /products` no documentado por el reporte general asíncrono. Usar el reporte solo como referencia para completar IDs y calcular diferencias; los valores escritos siempre salen de PrestaShop. El dueño revisa el reporte y pulsa «Sincronizar» para enviar únicamente productos existentes y vinculados con diferencias.

## Decisiones

- Ejecutar todos los requests y descargas desde la instalación PrestaShop del cliente; no se requiere acceso remoto del desarrollador ni entregar URLs firmadas.
- Persistir estado/task y límite diario en `Configuration`; crear cada reporte con una sola tentativa, respetar máximo 2 por día UTC y reusar tarea/reporte activo o vigente.
- Polling AJAX reanudable, peticiones cortas. Descargar JSONL en temp privado con TTL; validación HTTPS, DNS/IP pública, redirecciones, timeout y tamaño; no registrar Authorization, URL firmada ni datos del reporte.
- Matching por ID Yuju existente; si falta, enlazar solo por SKU único. SKU ambiguo o ausente queda pendiente y visible. Nunca crear un producto padre en Yuju si no está ya enlazado.
- Comparar únicamente mapeos outbound activos, con transformaciones existentes; estados `synced`/`pending`/`error` de acuerdo con el enum instalado.
- Variaciones: match por SKU; actualizar existentes vía `PUT /products/{id_product}/variations/{id_variation}`, crear solo las ausentes de padres vinculados vía `POST /products/{id_product}/variations`. Si no hay ID para actualizar una variante existente, no duplicarla: mostrarla pendiente.

## Tareas

### Fase 1: Cliente y reporte reanudable

#### Task 1: API report y registro de cuota
- **Descripción:** agregar creación one-shot y consulta de estado del reporte; persistir `id_task`, UTC day/count, estado y timestamp en configuración.
- **Aceptación:** nunca se reintenta el POST; máximo dos solicitudes por día UTC; una petición repetida reanuda una tarea activa o reutiliza cache.
- **Verificación:** tests de transición/cooldown/timeout con cliente fake; `php -l`, PHP-CS-Fixer `--dry-run`.
- **Archivos probables:** `classes/YujuApiClient.php`, `classes/YujuSyncManager.php`, `config/config.php`, tests.

#### Task 2: Descarga segura y parser JSONL
- **Descripción:** consultar estado, descargar el archivo al completarse y validar filas/IDs/variaciones antes de exponerlo al reconciliador.
- **Aceptación:** no se filtra URL firmada ni contenido a logs/AJAX; límites de host/DNS/redirect/tamaño; archivo privado con TTL; JSONL incompleto no deja datos parciales.
- **Verificación:** fixture de JSONL; tests de URL externa/privada/redirect, tamaño, archivo truncado, variantes e IDs faltantes.
- **Archivos probables:** nueva clase de reporte, tests.

### Checkpoint: Reporte
- [ ] Report POST único y reanudable.
- [ ] Estado de tarea y parser de archivo cubiertos por tests offline.

### Fase 2: Reconciliación y envío

#### Task 3: Matching por ID/SKU y diferencias
- **Descripción:** comparar filas con productos/combinaciones existentes; completar IDs en `yuju_product_status`; calcular campos diferentes y estados.
- **Aceptación:** ID guardado tiene prioridad; SKU se usa solo con coincidencia única; ambiguos y productos sin pareja no se enlazan ni crean; status solo se declara `synced` si todos los campos aplicables pudieron compararse.
- **Verificación:** tests de match por ID/SKU, duplicados, mapping fields, campos ausentes y persistencia idempotente.
- **Archivos probables:** `classes/YujuProductManager.php`, tests.

#### Task 4: Enviar solo diferencias PrestaShop→Yuju
- **Descripción:** para padres ya vinculados, actualizar campos outbound; actualizar variantes existentes por `id_variation`, crear variantes faltantes bajo ese padre; persistir estado solo según respuesta API.
- **Aceptación:** ninguna escritura Yuju→PrestaShop; no crear padres sin ID; no duplicar variantes; fallos parciales quedan visibles/no `synced`.
- **Verificación:** stubs para PUT parent, PUT variation y POST missing variations; pruebas de retries/errores y no-create de padres.
- **Archivos probables:** `classes/YujuProductManager.php`, `classes/YujuApiClient.php`, tests.

### Fase 3: Back-office y cierre

#### Task 5: Progreso, comparación y botón explícito
- **Descripción:** iniciar/reanudar reporte, mostrar progreso, totales, diferencias y avisos; sincronizar solo cuando el dueño pulsa el segundo botón.
- **Aceptación:** reload reanuda; loading/processing/completed/cooldown/error claros; detalle seguro; productos sin pareja se avisan; ninguna petición GET `/products`.
- **Verificación:** Node tests, `node --check`, prueba Smarty, PHP lint/fixer, `git diff --check`; validación final manual en tienda autorizada (consume cuota real).
- **Archivos probables:** `controllers/admin/AdminYujuSyncController.php`, `views/templates/admin/sync_info.tpl`, tests, `CHANGELOG.md`.

## Riesgos y límites

| Riesgo | Mitigación |
|---|---|
| No hay ID de variación en la fila de reporte | No hacer PUT ni POST duplicado; dejar la variante pendiente con motivo visible. |
| POST crea reporte pero cliente recibe timeout | Registrar intento antes del POST y no reintentar automáticamente; evitar gastar cuota duplicada. |
| URL del archivo cambia de host/redirige | Resolver/validar cada host/IP en runtime; bloquear destinos privados y límites; no requerir intervención del dueño salvo que el host no pase validación. |
| Reporte omite campos de mapeo o SKU es duplicado | Comparación incompleta/ambigua queda pending; nunca declarar `synced` por defecto. |
| Doble click/concurrencia | Lock/estado persistente; una tarea/reconciliación activa por tienda. |

## Open questions técnicas (no requieren acceso del desarrollador a la tienda)

- Verificar con fixtures si el JSONL incluye `id_variation`; el envío se implementa fail-closed para no duplicar variantes.
- Revisar APIs de `Product`/`Combination` compatibles con PrestaShop 1.7–9 para extraer combinaciones y stock.
