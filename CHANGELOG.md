# Changelog

## 2026-10-13

### Telemetría (monitor Yuju)
- Se reintegra el puente de telemetría nativo en `main`: `classes/YujuMonitor.php` y su `require` en `prestashopyuju.php`.
- Envío best-effort al monitor: `api.request` en `YujuApiClient::makeRequest` (metadatos de la petición) y agregados `sync.product` / `sync.error` en `YujuProductManager` (`syncProductsFromYuju` / `syncProductsToYuju`).
- Vinculación autorizada en `AdminYujuConfigurationController`: `YujuMonitor::ensureConnected()` en `initContent()` y `YujuMonitor::attemptRegistration()` tras una prueba de conectividad correcta.
- `YujuMonitor.php` se carga también en contextos CLI/cron (`require` en `YujuApiClient` y `YujuProductManager`), así la telemetría funciona fuera del back office.
- Versión alineada a `1.0.2` (`prestashopyuju.php`, `config/config.php`, `config.xml`).

## 2026-09-24

### SQL
- Unificación del esquema en un solo `sql/install.sql` (sin scripts incrementales).
- `sql/uninstall.sql` actualizado (incluye `yuju_logs` y `yuju_configuration`).
- Eliminados: `add_*.sql`, `update_add_queue_system.sql`, `fix_order_mapping_claim.sql`, `relax_category_mapping_yuju_unique.sql`, `install_temp.sql`.
- El módulo (`ensureSyncHistoryTable` / auditoría / reportes) lee tablas desde `install.sql`.
