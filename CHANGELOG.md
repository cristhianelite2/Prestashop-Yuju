## [1.1.18] - 2026-10-13
### Corregido
- El actualizador registra y activa la pestaña administrativa `AdminYujuProductCategoryStatus` después de copiar los archivos. Evita que la sección de estado de productos por categoría muestre «Página no encontrada» en instalaciones existentes.
- Se incrementa la versión para que las tiendas puedan detectar e instalar esta reparación.

## [1.1.17] - 2026-10-13
### Corregido
- Se completa la traducción al español de las acciones, mensajes y etiquetas visibles en la administración de webhooks.
- Se traducen textos restantes de las vistas de OAuth y detalles de webhooks.
- Se traducen etiquetas y mensajes de los mapeos de productos y atributos, además de los mensajes de sincronización y conexión.
- Se corrigen textos con codificación incorrecta en los mensajes de sincronización.
- Se traducen al español los metadatos del módulo en `config.xml`.
- Se mantiene separada la vista de estado individual de productos del resumen por categoría.

## [1.1.16] - 2026-10-13
### Corregido
- Se separa el listado detallado de estado de productos del resumen por categoría, con una sección de menú y vista independientes para cada uno.
- Se fuerza el español en las vistas administrativas, incluidos los mensajes y acciones de sincronización y registros.

## [1.1.15] - 2026-10-13
### Corregido
- Se restaura el panel de estadísticas de productos: contadores, actividad reciente, errores y estado agrupado por categoría. Se alinea la estructura que entrega el controlador con las variables que consume la plantilla y se cuentan productos únicos aunque tengan combinaciones.

## [1.1.14] - 2026-10-13
### Corregido
- La pantalla de mapeo de estados se sirve desde el controlador administrativo existente `AdminYuju`, evitando depender de una pestaña de administración que puede no estar registrada en instalaciones existentes.
- El menú lateral dirige a la ruta autenticada de `AdminYuju` y conserva la importación de mapeos heredados en modo de solo lectura sobre Madkting.

## [1.1.13] - 2026-10-12

### Corregido
- El actualizador registra y activa la pestaña `AdminYujuOrderStatusMapping` después de copiar los archivos, antes de marcar la actualización como instalada. Evita que el enlace del menú lateral abra «Página no encontrada» en tiendas existentes.
- Se alinea `<version>` de `config.xml` a `1.1.13` (seguía en `1.1.5`), para que una instalación o exportación limpia del módulo reporte la versión correcta.

## [1.1.12] - 2026-10-12

### Corregido
- Se agrega **Mapeo de estados de pedidos** al menú lateral interno de Integración Yuju (yuju-nav), junto a las demás secciones.
- Se marca la opción como activa al entrar al controlador correspondiente. No depende de una pestaña del menú global de PrestaShop.

## [1.1.11] - 2026-10-12

### Corregido
- La sección de mapeo de estados se crea o repara al entrar a la configuración del módulo, sin depender exclusivamente del hook de cabecera.
- Si la pestaña ya existía, se reactiva y se reasigna al menú Yuju para corregir registros inactivos o con padre incorrecto.

## [1.1.10] - 2026-10-12

### Corregido
- Se incrementa la versión para que el actualizador detecte e instale los cambios recientes de la sección de mapeo de estados de pedidos; se sincroniza la versión interna usada por el actualizador.

## [Unreleased]

## [1.1.9] - 2026-10-12

### Añadido
- Sección de administración para mapear estados de pedidos entre PrestaShop y Yuju.
- Botón de importación idempotente desde `mad_mapping_status_orders`; la tabla heredada se consulta en modo de solo lectura.
- Creación automática de la pestaña de mapeo en tiendas existentes.

### Corregido
- La carga de mapeos de estado aplica solo los activos y reconoce `canceled` como el identificador oficial de Yuju.

### Corregido
- Se corrige un error de sintaxis en `AdminYujuProductMappingController::getProductMappings()` (comillas simples sin escapar dentro de la cadena SQL del `ORDER BY FIELD(...)`) que rompía la página de mapeo de productos. El orden por campo Yuju se construye ahora desde `getYujuFields()`, así no se desincroniza ni repite la lista.

## [1.1.6] - 2026-10-13

### Corregido
- El catálogo de estados se reconcilia desde Yuju: incluye productos padre y variaciones, enlaza por SKU cuando la coincidencia es única y deja `prestashop_product_id` en `NULL` cuando no existe una relación segura.
- Se guarda el nombre de Yuju y la combinación de PrestaShop asociada a cada variación.
- Una sincronización enviada correctamente queda en estado `synced`; la reconciliación posterior puede volver a señalar diferencias detectadas por un reporte actualizado.

### Cambiado
- `ps_yuju_product_status` permite múltiples filas por producto PrestaShop, ya que cada variación de Yuju tiene su propia fila.
- Se elimina `sync_direction` de la tabla de estados y de su interfaz.
- La reconciliación elimina filas obsoletas que no están en el reporte actual de Yuju.
- Se añade migración para tiendas existentes al actualizar el módulo a 1.1.6.

# Changelog

- El botón «Iniciar sincronización» muestra el avance paso a paso (solicitar informe general, esperar y descargar, obtener stock/precio, combinar y detectar diferencias), con el estado de cada paso y el tiempo transcurrido de espera; si un paso falla se marca con su error y el botón se libera.
- El reporte de stock/precio (oferta) se reutiliza durante 12 horas; pasado ese tiempo se solicita y descarga uno nuevo. La URL temporal de descarga ya no se guarda en la tabla `yuju_product_reports` y en la configuración solo se conserva 5 minutos mientras se reintenta una descarga fallida; se elimina al terminar.
- Se corrige la estructura de `sync_info.tpl`: la reconciliación estaba dentro de `panel-heading` (se veía con estilos de encabezado); ahora el contenido va en `panel-body` y la tabla permite desplazamiento horizontal.
- Se agrega `tests/offer_report_ttl_test.php` para la reutilización de 12 horas y la URL temporal.

- Se añade un botón JSON por producto en la reconciliación para inspeccionar el payload generado por los mapeos activos de PrestaShop→Yuju, las variaciones y el registro original correspondiente del reporte Yuju cacheado, sin realizar escrituras en la API.

- Se separan diferencias urgentes (solo stock y precio) de las no urgentes; se agregan selección individual/masiva y actualización limitada a esos campos, con reconciliación automática posterior.
- El resumen de reconciliación desglosa el número de productos con diferencias urgentes y no urgentes, además del total general de productos con diferencias.
- Se añaden filtros interactivos a la reconciliación (diferencias por defecto, productos ausentes en Yuju, sincronizados, revisión manual, SKU ambiguo, deshabilitados y todos), paginación de 100 por defecto con opciones 20/50/500 y contadores de catálogo.

- Se corrige el reenvío del índice y los productos del reporte JSONL validado desde `YujuSyncManager` hacia `YujuProductManager`, para que AdminYujuSync pueda construir y mostrar la reconciliación en lugar de descartar el reporte y lanzar un error.

- La reconciliación de AdminYujuSync carga automáticamente el último reporte JSONL local validado, incluso si falta el ID de tarea guardado.
- Los metadatos del reporte local se registran o actualizan en la tabla propia `yuju_product_reports`; el archivo y sus productos permanecen en `var/reports/`.
- Se crea la tabla de metadatos del módulo si no existe, sin modificar tablas generales de PrestaShop ni almacenar URLs firmadas o rutas absolutas.


## [Unreleased]

- Recuperación de informes JSONL válidos desde `var/reports/` cuando el estado guardado ya no referencia el archivo, sin sobrescribir configuración ni solicitar un nuevo reporte a Yuju.
- La recuperación local valida cada candidato y omite archivos incompletos, vacíos, inválidos o fuera del límite de tamaño.
- Se conserva `yuju_product_reports` como candidata para metadatos de reportes; no se eliminan tablas automáticamente sin verificar datos y referencias en cada instalación.


Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2026-10-12

### Corregido
- Se escapan con `{ldelim}` y `{rdelim}` los delimitadores del objeto JavaScript `retry_download` para evitar que Smarty lo interprete como una etiqueta y falle al compilar la plantilla.
- Si Yuju devuelve `COMPLETED` desde la solicitud inicial, el módulo descarga y valida el archivo antes de marcarlo listo; si falta la caché, recupera la descarga de la misma tarea sin crear otro reporte ni consumir cuota.
- El panel solo habilita la reconciliación cuando el servidor confirma `report_cached: true`.
- Un archivo HTTP 200 vacío ya no se acepta como reporte cacheado; se registra un diagnóstico seguro y se elimina el archivo inválido.
- Al cargar la reconciliación se comprueba el resultado de la recuperación y, si se detecta una caché vacía de una versión anterior, se vuelve a descargar la tarea existente antes de mostrar un error preciso.
- El botón de comprobación ahora reintenta realmente una descarga fallida reutilizando la tarea guardada, y el panel muestra el mensaje diagnóstico del backend en lugar de ocultarlo con un error genérico.

### Añadido
- Flujo de sincronización basado en **reporte general asíncrono** de Yuju (`POST /products-gral-report`): "Iniciar sincronización" solicita el reporte, espera a que se complete (polling), descarga el JSONL en caché privada con validación SSRF, y reconcilia catálogo PrestaShop contra Yuju por ID guardado o SKU único.
- Matching determinista: ID Yuju existente tiene prioridad; SKU de padre o variación enlaza solo si es único; SKU ambiguos o duplicados quedan pendientes para resolución manual.
- Panel de reconciliación con totales (total/sincronizados/no sincronizados/listos para enviar) y tabla de diferencias por campo (padre y variaciones). Productos sin pareja en Yuju se listan, nunca se crean.
- Botón **"Sincronizar (PrestaShop → Yuju)"** que envía **solo las diferencias** de productos ya vinculados (incluye variaciones). `PUT /products/{id}` para padres, `PUT /products/{id}/variations/{id}` para variantes existentes, `POST /products/{id}/variations` para variantes nuevas bajo padres vinculados. Sin `id_variation` en el reporte, la variante queda pendiente sin duplicarse.
- Cliente API: `createProductGeneralReport()` (POST sin reintento para no gastar cuota), `getProductGeneralReportStatus()`, `updateProductVariation()`, `createProductVariations()`.
- Persistencia de estado de reporte y límite diario (2/día UTC) en `Configuration`; reanudación tras recarga; descarga con validación HTTPS/DNS público/redirects/tamaño/TTL; URL firmada y contenido nunca expuestos en logs ni UI.
- Parser JSONL streaming con validación estricta (`id_product`, `sku`/`sku_simple`, `variations[]` con `sku` obligatorio).
- Limpieza automática de caché de reportes >24h.

### Corregido
- `GET /products` ya no se usa en el nuevo flujo; se mantiene solo para compatibilidad del panel antiguo.
- Credenciales y URLs firmadas redactadas en logs de API.

## 2026-10-12

### Añadido
- En el panel de sincronización, botón para consultar productos de Yuju, ver cuáles tienen un ID vinculado en `yuju_product_status` y cuántos faltan. Se pueden importar únicamente los que no están vinculados.

### Corregido
- Se escapan en Smarty las llaves del objeto AJAX del panel de sincronización para evitar un error de compilación de la plantilla.
- La prueba de conectividad muestra `id_account` como ID de tienda y ya no expone en pantalla el resultado de telemetría ni el bloque de depuración/respuesta cruda de Yuju.
- Si el escaneo recibe SigV4 en `GET /products`, el panel limita la guía a esa operación, evita atribuir una causa definitiva y describe los reportes documentados y sus límites.

## 2026-10-11

### Reglas de coordinación (AGENTS.md)

- Se añade `AGENTS.md` con las reglas comunes de desarrollo (fuente: `_sistema/Workflow.md` del vault Obsidian) y los datos del proyecto.
- Desarrollo en `test` con push silencioso; `main` solo al pedir subir; CHANGELOG obligatorio antes de publicar a `test`.

## [1.1.5] - 2026-10-10

### Cambiado
- **Limpieza de hooks**: el panel *"Estado de los Hooks del Módulo"* pasa de 23 a **6** hooks. Se eliminan 17 hooks que ya no se usaban (habían quedado de una arquitectura inicial de auto-sync por hooks); la sincronización automática se ejecuta por **cron** (`cron/sync.php`) y **webhooks** (`webhook.php`).
- Solo quedan siempre activos `displayBackOfficeHeader` y `actionAdminControllerSetMedia` (assets del panel).

### Añadido
- **Hooks de sincronización puntual cableados y deshabilitados por defecto** (se activan con el switch del panel):
  - `actionProductUpdate` → empuja el producto a Yuju (`YujuProductManager::syncProductsToYuju`).
  - `actionUpdateQuantity` → sincroniza **stock y precio** del producto (`YujuSyncManager::syncStock` + `syncPrices`).
  - `actionValidateOrder` → crea el pedido en Yuju (`YujuOrderManager::sendOrderToYuju`).
  - `actionOrderStatusUpdate` → replica el cambio de estado (`YujuOrderManager::updateOrderStatusInYuju`).
- Todos los handlers son *best-effort*: `try/catch` + log, para no interrumpir jamás el guardado del producto/pedido si la API falla.
- `upgrade/upgrade-1.1.5.php`: desregistra los 17 hooks retirados en tiendas existentes y deja los 4 nuevos desactivados por defecto. Además, el módulo aplica una migración equivalente una sola vez desde el back-office (`ensureHookMigration`), porque el actualizador propio (GitHub) no ejecuta los scripts `upgrade/`.

### Corregido
- Las rutas de error de instalación/desinstalación usaban `$this->logger` (que es `null`); ahora usan `PrestaShopLogger::addLog`.
- Eliminado el hook `displayAdminProductsExtra`, que llamaba a métodos inexistentes (`getProductSyncStatus`) sobre `$this->sync_manager` nulo.

## [1.1.4] - 2026-10-10

### Añadido
- **Switch por hook** en el panel *"Estado de los Hooks del Módulo"*: cada hook se puede **habilitar/deshabilitar** desde la configuración. Al apagarlo se desregistra el hook de PrestaShop y la elección se guarda en `YUJU_DISABLED_HOOKS` (nueva clave en `YujuConfig`), por lo que `registerHooks()` lo omite al reinstalar/actualizar el módulo y el estado se mantiene. Nuevo endpoint AJAX `ToggleHook` en `AdminYujuConfigurationController` y métodos `getDisabledHooks()`/`setHookEnabled()` en el módulo. El resumen (`X/Y habilitados`) y el aviso se recalculan al instante, sin recargar la página.

## [1.1.3] - 2026-10-10

### Añadido
- Panel **"Estado de los Hooks del Módulo"** en *Configuración*: lista los hooks que el módulo necesita y marca cuáles están **habilitados** (registrados en la tienda) y cuáles **no**, con un resumen (`X/Y habilitados`) y aviso cuando faltan por registrar. Útil tras una actualización que no re-registró los hooks (p. ej. si los assets o algún evento dejaban de funcionar).

## [1.1.2] - 2026-10-09

### Corregido
- El registro de la tienda en el monitor fallaba **siempre** cuando la zona horaria del servidor de PrestaShop no era UTC: la llave temporal del handshake se generaba con `date()` (hora local) y el monitor la valida en UTC, así que el monitor respondía `401 invalid_handshake_key` y jamás quedaba rastro. Ahora se deriva con `gmdate()` (UTC) en el módulo y en el monitor, dentro de la ventana de ±2 minutos.
- `"Tiendas disponibles: Sin nombre (ID: N/A)"`: el listado de tiendas no se desenvolvía cuando la API de Yuju devuelve envoltorios anidados (p. ej. `{"data": {"stores": [...]}}`). `normalizeStoresList()` ahora desenvuelve los contenedores habituales recursivamente, y el panel muestra el JSON crudo de `account` en bloque de depuración cuando el esquema aún no coincide, para poder ajustarlo sin adivinar.

### Cambiado
- El botón **"Probar Conectividad"** ahora genera un intento de vinculación **visible en el monitor en cada pulsación** (también cuando la tienda ya estaba vinculada: presenta su API key, sin rotarla). El monitor registra cada intento —éxito o rechazo— y el panel muestra si se vinculó y qué respondió (`data.monitor`).
- La respuesta del test de conectividad incluye `data.monitor` (estado del registro) y `data.debug_info.stores_raw` (respuesta cruda del endpoint `account`) para diagnóstico.

### Añadido
- Panel **"Intentos de conexión"** en el dashboard del monitor (`/app`): cada intento entrante de `/api/v1/modules/register` queda registrado (nueva tabla `registration_attempts`) con dominio, nombre, versiones, IP, resultado, HTTP y hora —incluidos los rechazados por handshake, API key, tienda ya registrada, campos prohibidos o payload inválido.

## [Unreleased]

### Cambiado
- Los informes generales de productos de Yuju se guardan en `var/reports/` dentro del módulo, con permisos restrictivos, en lugar del directorio temporal del sistema.

### Seguridad
- Se bloquea el acceso HTTP directo a `var/`, donde se almacenan los informes descargados.


### Corregido
- La reconciliación devuelve todos los productos activos de PrestaShop para mostrar sincronizados sin diferencias, productos con diferencias y productos no encontrados en Yuju; la lista de envío sigue limitada a productos vinculados con diferencias.
- Al abrir la reconciliación se intenta recuperar la descarga de un informe completado si falta la caché local, reutilizando la tarea existente.


### Corregido
- Botón **"Probar Conectividad"** de la configuración sin efecto en PrestaShop 9: el JS del módulo solo se cargaba vía hooks (`displayBackOfficeHeader`/`actionAdminControllerSetMedia`), que pueden no estar registrados o ejecutarse tarde. `AdminYujuConfigurationController` ahora define `setMedia()` y carga `admin.js`/`admin.css` de forma determinista, y la plantilla inyecta `yujuAdminConfig` (URL AJAX + token) para no depender de los globales `window.currentIndex`/`window.token`.

### Cambiado
- La vinculación con el monitor usa el **flujo autorizado versionado**: la tienda solo se registra (`POST /api/v1/modules/register`) tras una prueba de conectividad satisfactoria (`state === 'connected'` sin `needs_auth`) desde `Probar conectividad`; ya no hay handshake silencioso en segundo plano. El registro es idempotente: si la instalación ya tiene API key no se rota, y al presentarla el monitor devuelve la misma clave.
- La telemetría se envía a `POST /api/v1/modules/activity` autenticada con la cabecera `X-API-Key` (antes `/api/events` con Bearer).
- Los contadores de sincronización se **agregan por ejecución** (`YujuMonitor::tally()`): un evento por tipo con `count`/`failedCount` y `correlationId` único por lote (sin doble conteo), en lugar de un evento por producto.
- **Nueva telemetría de pedidos** (`YujuOrderManager`): webhooks entrantes (creado/actualizado/estado/cancelado) y envío de pedidos a Yuju, con un contador por operación y sus errores.
- Los eventos puntuales (`api.request`) incluyen `occurredAt` en UTC (`gmdate`).

### Añadido
- `docs/MONITOR_API.md`: referencia de la integración con el monitor (flujos, protección de credenciales, configuración).

### Eliminado
- Sección *Historial de Intentos OAuth (Debug)* de la configuración (acordeón con detalle de request/response/cURL verbose), su JavaScript inline y sus estilos. Ese historial se consulta en **Yuju Logs**, donde los intentos aparecen agrupados en un único bloque con el número de falidos, el último intento y el historial paginado. Se retiran los endpoints AJAX `GetOAuthAttempts`, `GetOAuthAttemptDetail` y `CleanOAuthAttempts` que solo alimentaban ese panel.

## [1.0.7] - 2026-10-08

### Añadido
- Puente de telemetría nativo `YujuMonitor`: la tienda puede vincularse a un monitor externo y reportar únicamente metadatos
- Sección **Monitor de telemetría** en la configuración del módulo, con URL del monitor, token de instalación y botón **Probar conectividad** (handshake `POST /api/connect`)
- Telemetría de peticiones a la API de Yuju (`YujuApiClient::makeRequest`), de sincronización de productos en ambos sentidos y de errores de lote (`YujuProductManager`)

### Técnico
- Eventos con esquema cerrado, enviados en un único lote best-effort al cierre de la petición, con timeouts de 1 s (conexión) y 2 s (total): nunca interrumpen la sincronización
- No se envían cuerpos de peticiones, cabeceras, productos, pedidos, datos de clientes ni credenciales
- El token de instalación se guarda en `Configuration` y no se muestra de vuelta en el panel

## [1.0.6] - 2026-09-25

### Añadido
- Compatibilidad con PrestaShop 9 / 9.1 (`ps_versions_compliancy` hasta `9.99.99`)

### Corregido
- Error de instalación: "The version of your module is not compliant with your PrestaShop version"
- Nombres de controladores front alineados con convención PrestaShop (`webhook.php`, `oauth.php`)

### Técnico
- Rango de compatibilidad: PrestaShop 1.7.0.0 – 9.99.99 (incluye PS 8 y PS 9)
- Referencia: documentación oficial de módulos PS9 y anuncio PrestaShop 9.1.0
## [1.0.5] - 2026-09-24

### Corregido
- Logo del módulo en el listado de PrestaShop: se regenera `logo.png` válido (140x140) y se corrige `config.xml`
- El logo en el servidor estaba corrupto por transferencia en modo texto (firma PNG `89` convertida a `EF BF BD`)
## [1.0.4] - 2026-09-24

### Añadido
- Detección automática de actualizaciones desde GitHub (rama `main`) con caché de 24 horas
- Panel de actualizaciones en el Panel de Control (AdminYuju)
- Botón **Buscar actualizaciones** para forzar la comprobación
- Botón **Actualizar módulo** para descargar e instalar la última versión desde el repositorio público
- Nueva clase `YujuUpdateManager` para consultar commits/versión y aplicar el ZIP de GitHub

### Técnico
- Comparación por versión semántica y SHA de commit
- Conserva `logs/`, `cache/` y `exports/` durante la actualización
- Endpoints AJAX: `CheckUpdate` y `PerformUpdate` en AdminYujuController
## [1.0.3] - 2025-01-31

### Corregido
- 🐛 Solucionado error SQL "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'created_at' in 'where clause'"
- 🔧 Corregidas consultas SQL en tabla yuju_sync_logs para usar 'start_time' en lugar de 'created_at'
- 📊 Actualizado método getDashboardStats() para usar columnas correctas
- 🔄 Corregido método getRecentSyncItems() con columnas apropiadas
- 📅 Solucionado método getLastSyncDate() para usar start_time
- 🧹 Corregido método cleanOldLogs() en YujuLogger
- ✅ Actualizados valores de estado de 'error'/'warning' a 'failed'/'cancelled' según enum de tabla

### Técnico
- 🗃️ Alineadas consultas SQL con estructura real de tabla yuju_sync_logs
- 📋 Verificada consistencia entre install.sql y consultas en código
- 🎯 Eliminadas todas las referencias incorrectas a 'created_at' en yuju_sync_logs

## [1.0.2] - 2025-01-31

### Corregido
- Solucionado error "registerStylesheet" en AdminModulesController para PrestaShop 8
- Migrado JavaScript inline a archivo externo para mejor compatibilidad
- Corregidos métodos de registro de assets para AdminController
- Implementado hookActionAdminControllerSetMedia correctamente

### Mejorado
- JavaScript del botón "Copiar" ahora funciona correctamente
- Mejor compatibilidad con PrestaShop 8
- Código JavaScript más modular y mantenible
- Eliminado JavaScript inline del template

### Técnico
- Reemplazado registerStylesheet/registerJavascript por addCSS/addJS en AdminController
- Centralizada funcionalidad JavaScript en admin.js
- Mejorada estructura de carga de assets

## [1.0.1] - 2025-01-08

### Added
- ✨ Botón "Test Connectivity" en la configuración para verificar conexión con API de Yuju
- 🔗 Método `getStores()` en YujuApiClient para obtener tiendas disponibles
- 📋 Validación de conectividad con manejo de errores detallado

### Fixed
- 🐛 Corregido botón de copiar que no funcionaba correctamente
- 🎯 Mejorada funcionalidad de copia al portapapeles con mensajes en español
- ✅ Agregado mensaje de confirmación "¡Copiado!" al copiar URLs
- 🔧 Solucionado error de instalación por datos duplicados en install.sql
- 📊 Eliminadas inserciones duplicadas en tabla yuju_product_mapping
- 🛡️ Implementado INSERT IGNORE para evitar conflictos en reinstalaciones
- 🎨 Preservado HTML original del botón incluyendo iconos
- 🌐 Mejorado manejo de errores con fallback para navegadores antiguos

### Changed
- 🔄 Función copyToClipboard mejorada con mejor UX y manejo de errores
- 📝 Actualizado install.sql para prevenir violaciones de restricción única
- 🎯 Optimizada experiencia de usuario en botones de copia

## [1.0.0] - 2024-12-19

### Added
- ✨ Estructura inicial del módulo PrestaShop para integración con Yuju
- 🔐 Sistema de autenticación OAuth2 para conexión segura con API de Yuju
- 📦 Cliente API robusto para comunicación con plataforma Yuju
- 🔄 Sistema de sincronización de productos en tiempo real
- 🗂️ Mapeo inteligente de categorías entre PrestaShop y marketplaces
- 📋 Gestión de atributos de productos con mapeo personalizable
- 🛒 Integración completa de gestión de pedidos multicanal
- 🔗 Soporte de webhooks para actualizaciones en tiempo real
- 📊 Sistema de logging comprehensivo con auditoría detallada
- ⚙️ Controladores de administración para gestión completa del módulo
- 🎯 Gestión de sincronización con control de errores y reintentos
- 📈 Manager de webhooks para eventos críticos del sistema
- 🔧 Archivo de configuración centralizado para parámetros del módulo
- 📄 Scripts SQL para instalación y desinstalación de base de datos
- 🎨 Interfaz de administración con CSS y JavaScript personalizados
- 📋 Controladores especializados para cada funcionalidad:
  - AdminYujuConfigurationController: Configuración general
  - AdminYujuSyncController: Control de sincronización
  - AdminYujuProductMappingController: Mapeo de productos
  - AdminYujuCategoryMappingController: Mapeo de categorías
  - AdminYujuAttributeMappingController: Mapeo de atributos
  - AdminYujuWebhookController: Gestión de webhooks
  - AdminYujuLogsController: Visualización de logs
  - AdminYujuProductStatusController: Estado de productos
- 🔄 Script de sincronización por cron para automatización
- 🌐 Soporte multiidioma con sistema de traducciones
- 📁 Estructura de directorios organizada y escalable

### Fixed
- 🐛 Corregidas rutas incorrectas en archivos de clases que causaban errores "Failed to open stream"
- 🔧 Solucionada indentación incorrecta en array `ps_versions_compliancy`
- ⚡ Optimizadas rutas de includes usando `dirname(__FILE__)` para mayor compatibilidad
- 🛠️ Corregidos errores de sintaxis que impedían la instalación del módulo
- 📝 Aplicados estándares de codificación PHP-CS-Fixer en todos los archivos
- 🎯 Temporalmente comentadas inicializaciones de componentes para permitir instalación sin errores

### Changed
- 📚 README.md completamente reescrito en español con información detallada sobre Yuju
- 🔄 Rutas de includes actualizadas en todas las clases para usar rutas relativas
- 📋 Documentación mejorada con casos de uso y beneficios específicos
- 🌐 Enfoque en mercado latinoamericano y marketplaces regionales

### Technical Details
- 🏗️ Arquitectura modular con separación clara de responsabilidades
- 🔒 Implementación segura de OAuth2 con manejo de tokens
- 📊 Sistema de logging con diferentes niveles (info, warning, error)
- 🔄 Sincronización bidireccional entre PrestaShop y Yuju
- 🎯 Mapeo flexible de entidades con configuración personalizable
- ⚡ Optimización de performance con cache y procesamiento por lotes
- 🛡️ Validación robusta de datos y manejo de errores
- 🔧 Configuración centralizada con valores por defecto sensatos

### Compatibility
- ✅ Compatible con PrestaShop 1.5 y versiones superiores
- ✅ Soporte para PHP 7.0+
- ✅ Integración con +20 marketplaces de América Latina
- ✅ Compatible con múltiples ERPs y sistemas de gestión

### Security
- 🔐 Autenticación OAuth2 segura
- 🛡️ Validación de entrada en todos los endpoints
- 🔒 Encriptación de credenciales sensibles
- 📝 Logs de auditoría para trazabilidad completa
- 🚫 Protección contra inyección SQL y XSS

---

**Nota**: Esta es la primera versión estable del módulo, lista para producción con todas las funcionalidades principales implementadas y probadas.

## [Unreleased]

### Added
- Nothing yet

### Changed
- Nothing yet

### Deprecated
- Nothing yet

### Removed
- Nothing yet

### Fixed
- Nothing yet

### Security
- Nothing yet

## [1.0.0] - 2024-01-01

### Added
- **Core Module Structure**
  - Main module file `prestashopyuju.php` with complete PrestaShop integration
  - Module installation and uninstallation procedures
  - Database schema creation and management
  - Configuration management system

- **Authentication System**
  - OAuth 2.0 implementation for secure API access
  - Token management and automatic refresh
  - Secure credential storage
  - Authorization flow with Yuju platform

- **API Integration**
  - Complete Yuju API client implementation
  - Support for all major API endpoints (products, categories, orders, stock, prices)
  - Rate limiting and error handling
  - Request/response logging
  - Retry mechanisms for failed requests

- **Synchronization Engine**
  - Bidirectional data synchronization
  - Full and incremental sync modes
  - Batch processing for large datasets
  - Conflict resolution strategies
  - Sync status tracking and reporting

- **Product Management**
  - Complete product data synchronization
  - Product field mapping configuration
  - Image synchronization
  - Variant and attribute handling
  - Stock level synchronization
  - Price synchronization with currency support

- **Category Management**
  - Category hierarchy synchronization
  - Category mapping between platforms
  - Automatic category creation
  - Category tree structure preservation

- **Order Management**
  - Order creation from Yuju to PrestaShop
  - Order status synchronization
  - Customer data handling
  - Address management
  - Payment and shipping information sync

- **Attribute System**
  - Product attribute synchronization
  - Attribute group management
  - Attribute value mapping
  - Custom attribute handling

- **Webhook System**
  - Real-time webhook processing
  - Webhook signature verification
  - Event-driven synchronization
  - Webhook registration and management
  - Comprehensive webhook logging

- **Admin Interface**
  - Configuration management panel
  - Synchronization control dashboard
  - Product status monitoring
  - Webhook management interface
  - Comprehensive logging viewer
  - Field mapping configuration
  - Attribute mapping management

- **Logging System**
  - Multi-level logging (debug, info, warning, error)
  - Separate log files for different components
  - Log rotation and cleanup
  - Admin interface for log viewing
  - Export functionality for logs

- **Automation Features**
  - Cron job support for automated synchronization
  - Background processing
  - Email notifications for sync errors
  - Automatic retry mechanisms
  - Scheduled sync frequency configuration

- **Security Features**
  - HMAC signature verification for webhooks
  - Secure token storage
  - Input validation and sanitization
  - SQL injection prevention
  - XSS protection

- **Performance Optimizations**
  - Efficient database queries
  - Batch processing capabilities
  - Memory usage optimization
  - Connection pooling
  - Caching mechanisms

- **Error Handling**
  - Comprehensive error reporting
  - Graceful error recovery
  - Detailed error logging
  - User-friendly error messages
  - Automatic retry for transient errors

- **Documentation**
  - Complete README with installation and configuration guide
  - Inline code documentation
  - API endpoint documentation
  - Troubleshooting guide
  - Development guidelines

- **Development Tools**
  - Composer configuration
  - Git ignore file
  - Code style configuration
  - Testing framework setup
  - Development environment support

### Technical Details

#### Database Schema
- `ps_yuju_oauth_tokens` - OAuth token storage with automatic refresh
- `ps_yuju_category_mapping` - Category relationship mapping
- `ps_yuju_product_mapping` - Product field mapping configuration
- `ps_yuju_attribute_mapping` - Attribute synchronization mapping
- `ps_yuju_product_status` - Individual product sync status tracking
- `ps_yuju_sync_logs` - Detailed synchronization operation logs
- `ps_yuju_logs` - General module operation logs
- `ps_yuju_webhook_logs` - Webhook activity and response logs
- `ps_yuju_webhook_registrations` - Active webhook registrations
- `ps_yuju_config` - Module configuration storage

#### API Endpoints Supported
- Products: Full CRUD operations with variants and attributes
- Categories: Hierarchy management and mapping
- Orders: Creation, updates, and status synchronization
- Stock: Real-time inventory synchronization
- Prices: Multi-currency price synchronization
- Attributes: Product attribute and value management
- Webhooks: Event registration and management
- Customers: Customer data synchronization
- Manufacturers: Brand and manufacturer sync

#### Synchronization Features
- **Full Sync**: Complete data synchronization between platforms
- **Incremental Sync**: Only changed items since last sync
- **Real-time Sync**: Immediate updates via webhooks
- **Scheduled Sync**: Automated synchronization via cron jobs
- **Manual Sync**: On-demand synchronization from admin panel

#### Field Mapping System
- Flexible field mapping between PrestaShop and Yuju
- Custom transformation rules
- Data type conversion
- Default value handling
- Conditional mapping based on product attributes

#### Error Recovery
- Automatic retry with exponential backoff
- Failed item queuing for later processing
- Manual retry capabilities
- Detailed error reporting and resolution guidance

### Requirements
- PrestaShop 1.6.x or higher
- PHP 5.6 or higher
- MySQL 5.6 or higher
- cURL extension
- OpenSSL extension
- mbstring extension
- Valid Yuju API credentials

### Installation
1. Upload module files to PrestaShop modules directory
2. Install module through PrestaShop admin panel
3. Configure API credentials
4. Complete OAuth authorization
5. Configure synchronization settings
6. Set up webhooks (optional)
7. Configure cron jobs for automation (optional)

### Configuration
- API credentials setup
- OAuth authorization flow
- Synchronization preferences
- Field mapping configuration
- Webhook registration
- Logging preferences
- Performance tuning options

### Security
- OAuth 2.0 secure authentication
- HMAC-SHA256 webhook signature verification
- Encrypted credential storage
- Input validation and sanitization
- SQL injection prevention
- XSS protection measures

---

## Version History

### Version Numbering
This project follows [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

### Release Schedule
- **Major releases**: Annually or for significant architectural changes
- **Minor releases**: Quarterly for new features
- **Patch releases**: As needed for bug fixes and security updates

### Support Policy
- **Current version**: Full support with new features and bug fixes
- **Previous major version**: Security updates and critical bug fixes only
- **Older versions**: No longer supported

---

## Contributing

When contributing to this project, please:
1. Follow the existing code style
2. Add tests for new functionality
3. Update documentation as needed
4. Add entries to this changelog
5. Follow the commit message conventions

### Commit Message Format
```
type(scope): description

[optional body]

[optional footer]
```

Types: feat, fix, docs, style, refactor, test, chore

---

## Links
- [Repository](https://github.com/yuju/prestashop-integration)
- [Documentation](https://docs.yuju.com/prestashop)
- [Issue Tracker](https://github.com/yuju/prestashop-integration/issues)
- [Support](mailto:support@yuju.com)
