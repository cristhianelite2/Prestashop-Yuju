# Changelog

## 2026-10-14 — Visualización de canal e ID de orden Yuju en Información básica (v1.0.3)
- Visualización de "Nombre del canal" e "ID de la orden de Yuju" dentro de `#orderBasicInfo` en la vista de pedidos del back office (`/sell/orders/{id}/view`).
- Mecanismo redundante de inyección: gancho global `displayBackOfficeHeader` más `displayAdminOrderMainBottom`, `displayAdminOrderSideBottom` y `displayAdminOrder`, con registro automático de hooks en tiendas actualizadas vía panel.
- Búsqueda resiliente de metadatos: localización por ID o referencia PrestaShop, auto-reparación de `prestashop_order_id` en `yuju_order_mapping`, respaldo en `yuju_webhook_logs`, resolución vía API Yuju y mapeo de nombres amigables (Mercado Libre, Amazon, Liverpool, etc.).
- Reducción de throttles de consulta a 2 minutos en lugar de 24 horas para evitar bloqueos prolongados durante revisiones.
- Versión bump a `1.0.3` (`yuju.php`, `config.xml`, `config/config.php`).

## 2026-10-13 — Renombrado del módulo: prestashopyuju → yuju
- Identidad de módulo cambiada a `yuju` (carpeta + clase `Yuju` + `$this->name = 'yuju'`): el ZIP instalable ahora es `yuju/yuju.php` y PrestaShop lo instala como módulo `yuju`.
- Archivo principal `prestashopyuju.php` → `yuju.php`; dominios de traducción `Modules.Prestashopyuju.*` → `Modules.Yuju.*` (XLF renombrados a `ModulesYujuAdmin` / `ModulesYujuShop`).
- `config.xml`, `YUJU_MODULE_NAME`, rutas `_PS_MODULE_DIR_` y todas las referencias internas (controladores, cron, plantillas, webhook, mails, SQL, composer.json) apuntan a `yuju`.
- Monitor: el empaquetador genera la raíz `yuju/`, la descarga pasa a `yuju_module.zip` y la validación lee `yuju.php` en vez de `prestashopyuju.php`.
- Versión: 1.0.2 → 1.1.0.
- Nota: es un cambio de identidad — en tiendas existentes equivale a un módulo nuevo (`modules/yuju`); la línea anterior (`v1`/1.1.x) conserva `prestashopyuju`.

## 2026-10-13

### Telemetría (monitor Yuju)
- Se reintegra el puente de telemetría nativo en `main`: `classes/YujuMonitor.php` y su `require` en `yuju.php`.
- Envío best-effort al monitor: `api.request` en `YujuApiClient::makeRequest` (metadatos de la petición) y agregados `sync.product` / `sync.error` en `YujuProductManager` (`syncProductsFromYuju` / `syncProductsToYuju`).
- Vinculación autorizada en `AdminYujuConfigurationController`: `YujuMonitor::ensureConnected()` en `initContent()` y `YujuMonitor::attemptRegistration()` tras una prueba de conectividad correcta.
- `YujuMonitor.php` se carga también en contextos CLI/cron (`require` en `YujuApiClient` y `YujuProductManager`), así la telemetría funciona fuera del back office.
- Versión alineada a `1.0.2` (`yuju.php`, `config/config.php`, `config.xml`).

## 2026-09-24

### SQL
- Unificación del esquema en un solo `sql/install.sql` (sin scripts incrementales).
- `sql/uninstall.sql` actualizado (incluye `yuju_logs` y `yuju_configuration`).
- Eliminados: `add_*.sql`, `update_add_queue_system.sql`, `fix_order_mapping_claim.sql`, `relax_category_mapping_yuju_unique.sql`, `install_temp.sql`.
- El módulo (`ensureSyncHistoryTable` / auditoría / reportes) lee tablas desde `install.sql`.
