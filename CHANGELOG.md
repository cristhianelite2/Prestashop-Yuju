# Changelog

## 2026-10-15 — Optimización drástica de rendimiento en Acciones por categoría (AdminYujuCategoryBulk) (v1.0.5)
- Eliminación de subconsulta correlacionada N+1 en `getYpsJoinSql()`: reemplazada por tabla derivada pre-agrupada (`MAX(id)` por `prestashop_product_id`), reduciendo de decenas de miles de ejecuciones por petición a 1 sola resolución en memoria.
- Auto-reparación e indexación en base de datos (`YujuSchemaRepair` v2): adición automática de índices faltantes en `ps_yuju_product_status` (`idx_status_product_latest`, `idx_status_product_sync`, `idx_yuju_product`), `ps_yuju_category_mapping` (`idx_ycm_ps_cat_sync`), `ps_yuju_sync_queue` (`idx_queue_status_created`) y `ps_category` (`idx_category_active_id`), evitando full table scans en catálogos grandes.
- Implementación de caché de corta duración (60s) en `getCategoryOverviewRows()` (`_PS_CACHE_DIR_ . 'yuju_cat_overview_*.json'`) con invalidación inteligente inmediata al guardar mapeos o enviar lotes de productos.
- Optimización de `getYujuCategoryOptions()` con caché en memoria estática y en disco (1h), evitando lecturas repetidas de `yuju_allowed_values.json` y consultas redundantes.
- Diagnóstico automático en log de PHP para cargas de overview que superen 0.5s.
- Bump de versión a `1.0.5` (`yuju.php`, `config.xml`, `config/config.php`).

## 2026-10-14 — Solución definitiva a visualización de canal e ID de orden Yuju (v1.0.4)
- Creación de script independiente `views/js/admin_order_view.js` con detección multinivel: extracción directa del DOM (detecta entrega "Yuju", referencia de pedido, ID de pedido y deduce canal desde correo o valores del servidor), compatibilidad total con Symfony/Twig en PrestaShop 8 y 9.
- Implementación de `hookActionAdminControllerSetMedia` para inyección de assets en controladores Symfony de pedidos, asegurando la entrega del JavaScript en todas las variantes de rutas administrativas.
- Registro redundante en ganchos de órdenes: `actionAdminControllerSetMedia`, `displayBackOfficeHeader`, `displayAdminOrderMainBottom`, `displayAdminOrderSideBottom`, `displayAdminOrderSide`, `displayAdminOrder`, `displayAdminOrderTabOrder` y `displayAdminOrderTabContentOrder`.
- Auto-registro de ganchos en `YujuUpdateManager::performUpdate()` inmediatamente tras la extracción del ZIP y en `__construct()` de `yuju.php` para tiendas ya actualizadas.
- Consulta SQL directa y robusta a `ps_orders`, `ps_customer`, `ps_carrier` y `ps_order_carrier`, evitando fallos silenciosos por falta de contexto ORM en Symfony.
- Eliminación de bloqueos tempranos en `getOrderChannelMetadata()`: garantiza que órdenes con transportista Yuju o referencia válida siempre devuelvan metadatos y nunca `null`.
- Bump de versión a `1.0.4` (`yuju.php`, `config.xml`, `config/config.php`).

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
