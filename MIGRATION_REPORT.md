# Migración Completada: ps_configuration → ps_yuju_configuration

## 📋 Resumen de Cambios

Se ha completado la migración de TODO el sistema de configuración del módulo Yuju para usar **EXCLUSIVAMENTE** la tabla `ps_yuju_configuration` en lugar de la tabla de PrestaShop `ps_configuration`.

### ✅ Objetivo Logrado
**"NUNCA USES TABLAS DE PRESTASHOP, PARA ESO TIENES ps_yuju_configuration"**

El módulo ahora es completamente autónomo y no contamina la tabla de configuración de PrestaShop.

---

## 🔧 Archivos Modificados

### 1. **config/config.php** - Clase YujuConfig actualizada
**Cambios:**
- ❌ Antes: `Configuration::get()` y `Configuration::updateValue()`
- ✅ Ahora: Consultas directas a `ps_yuju_configuration` usando `Db::getInstance()`

**Métodos actualizados:**
```php
YujuConfig::get($key, $default)     // Lee de ps_yuju_configuration
YujuConfig::set($key, $value, $type) // Escribe en ps_yuju_configuration
YujuConfig::resetToDefaults()       // Usa YujuConfig::set()
YujuConfig::import($config)         // Usa YujuConfig::set()
```

**Soporte de tipos:**
- `string` (por defecto)
- `integer` (convertido automáticamente)
- `boolean` (convertido a 1/0)
- `json` (serializado/deserializado)

---

### 2. **cron/cron.php** - Sistema de sincronización
**Líneas cambiadas: 233-315**

**Antes:**
```php
$sync_count_today = (int) Configuration::get('YUJU_SYNC_COUNT');
Configuration::updateValue('YUJU_SYNC_DATE', $today);
```

**Después:**
```php
$sync_count_today = (int) YujuConfig::get('YUJU_SYNC_COUNT');
YujuConfig::set('YUJU_SYNC_DATE', $today, 'string');
```

**Variables afectadas:**
- `YUJU_SYNC_DATE`
- `YUJU_SYNC_COUNT`
- `YUJU_LAST_SYNC_TIME`
- `YUJU_MAX_DAILY_SYNCS`

---

### 3. **cron/sync_products.php** - Sincronización de productos
**Líneas cambiadas: 167-168**

**Antes:**
```php
$batch_size = (int) Configuration::get('YUJU_BATCH_SIZE') ?: 100;
$batch_frequency = (int) Configuration::get('YUJU_BATCH_FREQUENCY') ?: 60;
```

**Después:**
```php
$batch_size = (int) YujuConfig::get('YUJU_BATCH_SIZE') ?: 100;
$batch_frequency = (int) YujuConfig::get('YUJU_BATCH_FREQUENCY') ?: 60;
```

---

### 4. **cron/reset_sync.php** - REESCRITO COMPLETAMENTE ⚡
**Cambio crítico:** Ahora funciona **SIN bootstrap de PrestaShop**

**Antes:**
```php
require_once 'config/config.inc.php';  // ❌ Requería PrestaShop
Configuration::updateValue('YUJU_SYNC_COUNT', 0);
```

**Después:**
```php
// ✅ Conexión directa a MySQL sin PrestaShop
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
$mysqli->query("UPDATE ps_yuju_configuration SET config_value = '0' WHERE config_key = 'YUJU_SYNC_COUNT'");
```

**Ventajas:**
- ✅ Funciona en Docker sin problemas de rutas
- ✅ No requiere bootstrap pesado de PrestaShop
- ✅ Ejecución más rápida
- ✅ Lee configuración de `app/config/parameters.php` (PS 1.7+)

---

### 5. **controllers/admin/AdminYujuConfigurationController.php**
**Líneas cambiadas: 78-103, 525-527, 586, 734-736, 822-827, 928**

**Cambios masivos:**
- ❌ ~30+ llamadas a `Configuration::get()`
- ✅ Todas reemplazadas por `YujuConfig::get()`

**Métodos afectados:**
```php
renderContent()              // Cargar configuración
processOAuthConfiguration()  // Guardar OAuth
processGeneralConfiguration() // Guardar config general
processMainConfiguration()   // Guardar config principal
renderOAuthForm()           // Formulario OAuth
renderGeneralConfigForm()   // Formulario general
```

**NOTA IMPORTANTE:** Las llamadas a `Configuration::get('PS_*')` se mantuvieron intactas porque son configuraciones de PrestaShop, no del módulo.

---

### 6. **Reemplazo Global en TODO el Módulo** 🌐

Se ejecutó un reemplazo masivo con PowerShell:
```powershell
Configuration::get('YUJU_    → YujuConfig::get('YUJU_
Configuration::updateValue('YUJU_ → YujuConfig::set('YUJU_
```

**Archivos afectados (100+ reemplazos):**
- `classes/YujuApiClient.php`
- `classes/YujuOAuth.php`
- `classes/YujuSyncManager.php`
- `classes/YujuWebhookManager.php`
- `classes/YujuOrderManager.php`
- `controllers/admin/AdminYujuSyncController.php`
- `controllers/front/oauth.php`
- `cron/sync.php`
- Y muchos más...

---

## 📊 Tabla ps_yuju_configuration

### Estructura (ya existía en install.sql):
```sql
CREATE TABLE IF NOT EXISTS `PREFIX_yuju_configuration` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `config_key` varchar(100) NOT NULL,
    `config_value` longtext,
    `config_type` enum('string', 'integer', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    `is_sensitive` tinyint(1) DEFAULT 0,
    `description` text,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Claves migradas (40+ configuraciones):
```
YUJU_ENVIRONMENT
YUJU_CLIENT_ID
YUJU_CLIENT_SECRET
YUJU_BATCH_SIZE
YUJU_BATCH_FREQUENCY
YUJU_MAX_DAILY_SYNCS
YUJU_SYNC_DATE
YUJU_SYNC_COUNT
YUJU_LAST_SYNC_TIME
... y 30 más
```

---

## 🚀 Instrucciones de Migración

### Paso 1: Migrar datos existentes
Ejecutar **UNA VEZ** desde navegador:
```
http://tu-tienda.com/modules/prestashopyuju/cron/migrate_config.php
```

Este script:
1. ✅ Lee todas las configuraciones `YUJU_*` de `ps_configuration`
2. ✅ Las copia a `ps_yuju_configuration` con el tipo correcto
3. ✅ Genera reporte HTML con el resultado
4. ⚠️ NO elimina los valores antiguos (por seguridad)

**Ejemplo de salida:**
```
Migrados exitosamente: 32
Saltados (vacíos): 8
Errores: 0
```

### Paso 2: Verificar funcionamiento
1. Ir a **Admin → Módulos → Yuju Configuration**
2. Verificar que los valores se muestren correctamente
3. Cambiar algún valor y guardar
4. Verificar en phpMyAdmin que `ps_yuju_configuration` se actualice

### Paso 3: Probar CRON
```bash
# Desde navegador
http://tu-tienda.com/modules/prestashopyuju/cron/cron.php

# O desde Docker
docker exec nombre_contenedor php /var/www/html/modules/prestashopyuju/cron/cron.php
```

### Paso 4: Probar reset_sync.php (opcional)
```bash
# Desde Docker (ahora funciona sin problemas)
docker exec nombre_contenedor php /var/www/html/modules/prestashopyuju/cron/reset_sync.php
```

---

## 🔍 Verificación de Migración

### SQL para verificar datos migrados:
```sql
-- Ver todas las configuraciones YUJU
SELECT config_key, config_value, config_type, updated_at 
FROM ps_yuju_configuration 
ORDER BY config_key;

-- Comparar con tabla antigua (deben ser iguales)
SELECT name, value 
FROM ps_configuration 
WHERE name LIKE 'YUJU_%'
ORDER BY name;
```

### Verificar desde PHP:
```php
// Cargar configuración
require_once 'config/config.php';

echo YujuConfig::get('YUJU_BATCH_SIZE') . "\n";      // Debe devolver 100
echo YujuConfig::get('YUJU_MAX_DAILY_SYNCS') . "\n"; // Debe devolver 5

// Guardar nueva configuración
YujuConfig::set('YUJU_TEST_KEY', 'test_value', 'string');
```

---

## ⚠️ Problemas Conocidos y Soluciones

### 1. "Undefined type Configuration" en IDE
**Causa:** El IDE no reconoce clases de PrestaShop.
**Solución:** Es normal, funcionará en ejecución. Puedes ignorar estos warnings.

### 2. Valores no se guardan
**Verificar:**
```sql
-- ¿Existe la tabla?
SHOW TABLES LIKE '%yuju_configuration%';

-- ¿Tiene permisos?
SHOW GRANTS FOR CURRENT_USER;
```

### 3. reset_sync.php falla en Docker
**Solución:** Ya está arreglado. Ahora busca `parameters.php` automáticamente.

---

## 📈 Métricas de Cambios

| Métrica | Cantidad |
|---------|----------|
| **Archivos modificados** | 15+ |
| **Líneas de código cambiadas** | ~200+ |
| **Configuration::get() reemplazados** | ~100+ |
| **Configuration::updateValue() reemplazados** | ~30+ |
| **Claves migradas** | 40+ |
| **Tiempo de ejecución estimado** | <1 segundo |

---

## ✅ Checklist de Migración

- [x] Tabla `ps_yuju_configuration` existe en install.sql
- [x] Clase `YujuConfig` actualizada con métodos get/set
- [x] cron/cron.php usa YujuConfig
- [x] cron/sync_products.php usa YujuConfig
- [x] cron/reset_sync.php reescrito sin PrestaShop bootstrap
- [x] AdminYujuConfigurationController usa YujuConfig
- [x] Reemplazo global en todos los archivos .php
- [x] Script de migración creado (migrate_config.php)
- [ ] **PENDIENTE:** Ejecutar migrate_config.php
- [ ] **PENDIENTE:** Verificar funcionamiento en producción

---

## 🎯 Resultado Final

### Antes (❌ MAL):
```php
// Módulo mezclaba su config con PrestaShop
Configuration::get('YUJU_BATCH_SIZE');  // ps_configuration (tabla compartida)
Configuration::get('PS_LANG_DEFAULT');  // ps_configuration (tabla compartida)
```

### Después (✅ CORRECTO):
```php
// Módulo usa su propia tabla
YujuConfig::get('YUJU_BATCH_SIZE');     // ps_yuju_configuration (tabla dedicada)
Configuration::get('PS_LANG_DEFAULT');  // ps_configuration (PrestaShop)
```

**Separación limpia:** 
- 🟢 Configuración del módulo → `ps_yuju_configuration`
- 🔵 Configuración de PrestaShop → `ps_configuration`

---

## 📞 Soporte

Si encuentras algún problema:
1. Verifica los logs en `logs/error_logs/`
2. Ejecuta `migrate_config.php` de nuevo
3. Compara valores entre ambas tablas con SQL
4. Verifica permisos de la tabla `ps_yuju_configuration`

**Fecha de migración:** 2024
**Versión del módulo:** 2.0+
