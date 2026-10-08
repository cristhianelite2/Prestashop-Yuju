# Auditoría del módulo `prestashopyuju`

**Fecha:** 2026-10-08
**Alcance:** código PHP, SQL, plantillas Smarty y configuración del módulo (52 archivos PHP, ~13.600 líneas).

**Método de verificación**

| Comprobación | Herramienta | Resultado |
|---|---|---|
| Sintaxis PHP | `php -l` (PHP 8.5, Docker) sobre los 52 archivos | ✅ sin errores |
| SQL de instalación | MySQL 8.4 real, `install.sql` con `PREFIX_`/`ENGINE_TYPE` sustituidos | ✅ 21 tablas creadas |
| SQL de desinstalación | MySQL 8.4 real, `uninstall.sql` | ⚠️ deja 2 tablas |
| Plantillas `.tpl` | Balance `{if}/{/if}`, `{foreach}/{foreach}`… en las 23 plantillas | ✅ balanceadas |
| Estándares del proyecto | `verify_standards.php` | ⚠️ 9 incidencias |
| Formato de código | `php-cs-fixer-v3.phar --dry-run` | ✅ limpio |
| Análisis estático propio | clases/métodos/claves/tablas/templates referenciados vs. definidos | ❌ 32 llamadas a métodos inexistentes + 29 sobre propiedades `null` |
| Segunda opinión | PHPStan nivel 2 | ruido (sin core de PrestaShop); ver "Falsos positivos descartados" |

---

## P0 — Rompen instalación o producción

### 1. Los componentes del módulo nunca se inicializan (`prestashopyuju.php`)

El constructor tiene las 5 inicializaciones **comentadas** (`prestashopyuju.php:68-72`):

```php
// $this->logger = new YujuLogger();
// $this->api_client = new YujuApiClient();
// $this->oauth = new YujuOAuth();
// $this->sync_manager = new YujuSyncManager();
// $this->webhook_manager = new YujuWebhookManager();
```

No se inicializan en ningún otro punto, pero se usan en:

| Propiedad | Uso | Líneas |
|---|---|---|
| `$this->logger` | rutas de **instalación/desinstalación**: `installTabs()`, `uninstallTabs()`, `registerHooks()`, `installConfiguration()`, `createDirectories()` | 246, 277, 318, 336, 373 |
| `$this->sync_manager` | 20 hooks (`hookActionProductAdd`, `hookActionCategoryUpdate`, …) y `hookDisplayAdminProductsExtra` | 410–600, 656, 661 |
| `$this->oauth` | `isConfigured()`, `getModuleStatus()` | 689, 699 |

**Impacto:**
- Instalación: si cualquier paso falla (un hook, una clave, un directorio), PHP lanza `Error: Call to a member function error() on null` y la instalación muere sin mensaje útil.
- Producción: `YUJU_ENABLE_AUTO_SYNC` y `YUJU_ENABLE_PRODUCT_SYNC` se instalan en `true` (`config/config.php:97,101`), así que los hooks **están armados por defecto**: crear o editar un producto en el Back Office ejecuta `$this->sync_manager->queueProductSync(...)` sobre `null` → **Error fatal PHP 8** que interrumpe el guardado.

### 2. 32 llamadas a métodos que no existen

| Método inexistente | Clase real | Invocaciones |
|---|---|---|
| `queueProductSync`, `queueStockSync`, `queueCategorySync`, `queueOrderSync`, `queueAttributeSync`, `queueCarrierSync`, `queueCustomerSync`, `queueManufacturerSync` | `YujuSyncManager` | 20 (`prestashopyuju.php:410-600`) |
| `getProductSyncStatus`, `getYujuProductId` | `YujuSyncManager` | 2 (`prestashopyuju.php:656,661`) |
| `getAttributeValues` | `YujuApiClient` | 1 (`classes/YujuAttributeManager.php:357`) |
| `updateCategory`, `createCategory` | `YujuApiClient` | 2 (`classes/YujuCategoryManager.php:189,192`) |
| `createOrder` | `YujuApiClient` | 1 (`classes/YujuOrderManager.php:307`) |
| `getUpdatedItems`, `getProductsByCategories` | `YujuApiClient` | 2 (`classes/YujuSyncManager.php:149,238`) |
| `registerWebhook`, `unregisterWebhook` | `YujuApiClient` | 2 (`classes/YujuWebhookManager.php:468,504`) — el método real es `createWebhook`/`deleteWebhook` |
| `syncSpecificProducts` | `YujuProductManager` | 2 (`controllers/admin/AdminYujuProductStatusController.php:288,373`) |

No hay cabecera mágica `__call` en ninguna de las clases afectadas: cada llamada es un `Error` fatal en PHP 8.

Nota: los 20 primeros son **doble fallo** — ni el objeto existe (P0.1) ni el método (P0.2).

### 3. Versión desincronizada

- `config.xml` → `1.0.7`
- `CHANGELOG.md` → `[1.0.7] - 2026-10-08`
- `prestashopyuju.php:51` → `$this->version = '1.0.6';`

PrestaShop toma la versión de la clase del módulo: el gestor de módulos mostrará 1.0.6 mientras `config.xml` dice 1.0.7, y las actualizaciones por XML/actualizador se comportarán de forma inconsistente.

---

## P1 — Funcionalidad rota sin fatal

### 4. Sistema de claves de configuración partido en dos (28 claves usadas y nunca definidas)

`installConfiguration()` sólo persiste las 38 claves de `YujuConfig::getDefaults()`. Hay **28 claves más** que el código lee/escribe pero que nunca se crean, y varios pares duplicados que se pisan:

| El panel escribe | Otro componente lee | Efecto |
|---|---|---|
| `YUJU_AUTO_SYNC` (form y `oauth_setup.tpl:167`) | hooks leen `YUJU_ENABLE_AUTO_SYNC` | **el interruptor de auto-sincronización no controla los hooks** |
| `YUJU_ENVIRONMENT` (`AdminYujuConfigurationController:620`) | `getApiBaseUrl()` lee `YUJU_API_ENVIRONMENT` | selector sandbox/producción inconsistente según la ruta |
| `YUJU_BATCH_SIZE` (:297) | `YujuSyncManager:51` lee `YUJU_SYNC_BATCH_SIZE` | el tamaño de lote del panel no llega al sincronizador |
| `YUJU_LOG_RETENTION` (:630) | `YUJU_LOG_RETENTION_DAYS` | igual |
| `YUJU_EMAIL_NOTIFICATIONS` (:300) | `YUJU_ENABLE_EMAIL_NOTIFICATIONS` | igual |
| — | `YUJU_LAST_SYNC_DATE` vs `YUJU_LAST_SYNC_TIME` | fecha de última sincronización según quién la mire |

Otras claves usadas sin definirse: `YUJU_OAUTH_STATE`, `YUJU_API_CLIENT_ID`, `YUJU_API_CLIENT_SECRET`, `YUJU_LAST_CRON_SYNC`, `YUJU_ENABLE_{ATTRIBUTE,CARRIER,CUSTOMER,MANUFACTURER}_SYNC`, `YUJU_AUTO_SYNC_ENABLED`, `YUJU_SYNC_{CATEGORIES,PRODUCTS,STOCK,PRICES,IMAGES}`, `YUJU_ORDER_PREFIX`, `YUJU_MONITOR_{URL,TOKEN}`, `YUJU_{BATCH,FREQUENCY,AUDIT_FREQUENCY,ERROR_THRESHOLD}`, `YUJU_SYNC_MAX_EXECUTION_TIME`.

### 5. `Configuration::get()` mal llamado (~20 sitios)

La firma real es `Configuration::get($idKey, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)`. El segundo parámetro **no es el valor por defecto**. Ejemplos:

```php
Configuration::get('YUJU_ENVIRONMENT', 'sandbox');   // YujuApiClient.php:40, YujuOAuth.php:38
Configuration::get('YUJU_SYNC_FREQUENCY', 3600);     // cron/sync.php:61
Configuration::get('YUJU_BATCH_SIZE', 50);           // AdminYujuConfigurationController.php:70
```

Cuando la clave no existe, el "default" nunca se aplica (se devuelve `false`) y, además, se pasa `'sandbox'`/`3600` como `$idLang`. Todas las claves listadas en el punto 4 caen en este caso. El wrapper propio `YujuConfig::get($key, $default)` sí está bien implementado; el problema es el uso directo de `Configuration::get`.

### 6. Unidades incompatibles en `YUJU_SYNC_FREQUENCY`

- `config/config.php:94` → `3600` (segundos).
- UI (`views/templates/admin/configuration/oauth_setup.tpl:183-188`) → valores en **minutos** (`5`, `15`, `60`, `360`, `1440`).
- `cron/sync.php:61-65` → compara `time() - strtotime($last_sync) < $sync_frequency` (**segundos**).

Tras guardar "Cada hora" (60), el cron corre cada 60 segundos; el default 3600 instalado implica 1 hora… hasta que alguien guarda el formulario y lo convierte en minutos.

### 7. `sql/uninstall.sql` no borra 2 tablas

`install.sql` crea **21** tablas; `uninstall.sql` dropea **19**. Quedan huérfanas:

- `ps_yuju_configuration`
- `ps_yuju_logs`

---

## P2 — Seguridad y calidad

### 8. Webhook: sin secreto no se verifica la firma

`classes/YujuWebhookManager.php:99` — si `YUJU_WEBHOOK_SECRET` está vacío, `verifySignature()` hace `return true` con un warning de log. Así que **una instalación recién instalada acepta webhooks sin autenticar**, y con el front controller público (`controllers/front/webhook.php` + `webhook.php`) cualquiera puede inyectar eventos de pedido/producto. La comparación cuando sí hay secreto es correcta (`hash_hmac` + `hash_equals`). El default instalado es `''` (`config/config.php:118`).

### 9. `verify_standards.php`: 9 incidencias

```
YujuAttributeMapping.php, YujuCategoryMapping.php, YujuProductMapping.php,
oauth.php, terms.php          → archivo no termina con una sola línea nueva
AdminYujuAttributeMappingController.php, AdminYujuProductMappingController.php,
prestashopopyuju.php          → múltiples líneas en blanco consecutivas
```

(`php-cs-fixer --dry-run` pasa limpio: las reglas del verificador propio son más estrictas.)

---

## Falsos positivos descartados (no corregir)

- **`self::TYPE_STRING/INT/BOOL/DATE`** en las clases `*Mapping`: PHPStan las marca como no definidas, pero **existen en `ObjectModel`** (del core), que sí es su clase padre. Correcto tal cual.
- **`$this->module` en los controladores admin**: el core lo inicializa porque todos extienden `ModuleAdminController` (no `AdminController`) y `installTabs()` registra los Tab con `module => prestashopyuju`.
- **`require '../../config/config.inc.php'`** en `webhook.php` y `../../../…` en `cron/sync.php`: rutas correctas cuando el módulo está en `modules/prestashopyuju/`; el repo no está anidado dentro de una tienda.
- **Clases del core** (`Product`, `Order`, `HelperForm`, `Db`, `Configuration`, `pSQL()`, `_DB_PREFIX_`, `trans()`, `l()`, `update()`): ~1.900 avisos de PHPStan por ausencia del core de PrestaShop en el entorno.

---

## Verificado sin incidencias

- Sintaxis PHP 8.5 en los 52 archivos.
- `install.sql` válido en MySQL 8.4: 21/21 tablas con sus claves, índices e `ENGINE_TYPE`.
- Las 23 plantillas `.tpl` tienen bloques balanceados.
- Los 23 hooks registrados tienen su método `hookXxx()` correspondiente en `Prestashopyuju`.
- Los 9 controladores admin y los 3 front tienen nombre de clase coherente con el archivo (`AdminXxxController` / `PrestashopyujuXxxModuleFrontController`). Verificado contra `Dispatcher::dispatch()` del core: para los front calcula `$module_name . $controller . 'ModuleFrontController'`, y como PHP resuelve los nombres de clase sin distinguir mayúsculas, `PrestashopyujuOAuthModuleFrontController` (fichero `oauth.php`) se instancia sin problema.
- Assets referenciados (`views/css/admin.css`, `views/js/admin.js`) presentes.
- `getContent()` redirige correctamente vía `Tools::redirectAdmin()`.

---

## Limitaciones de esta auditoría

No había una instalación de PrestaShop disponible en el entorno, por lo que **no se pudo ejecutar**:

1. La instalación real del módulo en Back Office (el P0.1 se dedujo por lectura estática: la inicialización está comentada y las llamadas existen).
2. La validación de que los 23 hooks registrados existan en PS 8/9 (algunos como `actionOrderReturn` o `actionProductAttributeUpdate` deberían verificarse contra el listado de hooks de la versión objetivo).
3. El flujo OAuth completo (autorización, callback, refresh) y la recepción de un webhook real.
4. `php-cs-fixer` y PHPStan sin stubs del core: útiles sólo como señal débil.

## Orden de reparación sugerido

1. P0.1 + P0.2 juntos (inicializar componentes **y** implementar/mapear los métodos `queue*`): sin esto la instalación y el Back Office no son utilizables.
2. P0.3 (versión).
3. P1.4 + P1.5 (unificar claves de configuración y corregir `Configuration::get`).
4. P1.6 (unidades de frecuencia) y P1.7 (`uninstall.sql`).
5. P2.8 (exigir secreto de webhook) y P2.9 (estándares).
