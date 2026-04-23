# Sistema de Cola por Lotes - Implementación Completa

## 📋 Resumen

Se ha implementado un sistema de **sincronización por lotes** que:

✅ Agrupa actualizaciones de productos (crear/actualizar) en una cola  
✅ Procesa lotes configurables cada ejecución del cron (ej: 100 productos cada 5 minutos)  
✅ **EXCEPCIÓN:** Cambios de precio y stock se sincronizan **inmediatamente** (sin cola)  
✅ Evita saturar PrestaShop y la API de Yuju con miles de peticiones simultáneas  

---

## 🗂️ Archivos Modificados

### 1. **Nueva Clase: `YujuSyncQueue.php`**
📁 `classes/YujuSyncQueue.php`

**Funcionalidades:**
- `addToQueue($product_id, $action, $priority, $data)` - Agregar producto a la cola
- `processBatch($batch_size)` - Procesar lote de N productos
- `getQueueStats()` - Estadísticas (pendientes, completados, fallidos)
- `processImmediately()` - Sincronización inmediata para prioridad alta (precio/stock)
- `cleanOldCompleted()` - Limpieza automática de registros antiguos

---

### 2. **Hook Actualizado: `hookActionProductUpdate`**
📁 `prestashopyuju.php` (líneas ~928-1180)

**Cambios:**
```php
// ANTES: Sincronizaba todo inmediatamente
$api_client->updateProduct($yuju_product_id, $yuju_data);

// AHORA: Verifica prioridad
if ($priority === 'high') {
    // PRECIO/STOCK: Inmediato
    $api_client->updateProduct($yuju_product_id, $yuju_data);
} else {
    // OTROS CAMPOS: A la cola
    $sync_queue->addToQueue($product_id, 'update', 'normal', $yuju_data);
    // Estado -> 'queued'
}
```

**Prioridades:**
- **HIGH** (inmediato): `price`, `quantity` (stock)
- **NORMAL** (cola): `name`, `reference`, `weight`, etc.

---

### 3. **Cron Actualizado: Procesamiento de Lotes**
📁 `cron/cron.php` (líneas ~3715-3850)

**Nueva sección agregada:**
```php
// PROCESAR COLA DE SINCRONIZACIÓN POR LOTES
$sync_queue = new YujuSyncQueue();
$batch_size = YujuConfig::get('YUJU_BATCH_SIZE', 100);
$batch_stats = $sync_queue->processBatch($batch_size);
```

**Ejecución del cron (cada 5 minutos):**
1. Descarga catálogo (si corresponde)
2. Procesa webhooks de órdenes
3. **NUEVO:** Procesa cola de sincronización (100 productos por defecto)
4. Limpia registros antiguos (completados hace +7 días)

**Vista web del cron:**
- Muestra estadísticas: Total, Pendientes, Procesando, Completados, Fallidos
- Resultados del lote: Procesados, Exitosos, Fallidos, Duración

---

### 4. **Base de Datos**

#### Nueva Tabla: `ps_yuju_sync_queue`
📁 `sql/install.sql`

```sql
CREATE TABLE `ps_yuju_sync_queue` (
    id int(11) PRIMARY KEY AUTO_INCREMENT,
    prestashop_product_id int(11) NOT NULL,
    action enum('create','update') NOT NULL,
    priority enum('high','normal') DEFAULT 'normal',
    status enum('pending','processing','completed','failed') DEFAULT 'pending',
    data TEXT NOT NULL,                    -- JSON con los datos a sincronizar
    attempts int(11) DEFAULT 0,
    max_attempts int(11) DEFAULT 3,
    error_message TEXT,
    created_at datetime NOT NULL,
    last_attempt_at datetime,
    processed_at datetime
);
```

#### Actualización: `ps_yuju_product_status`
📁 `sql/install.sql`

```sql
-- Nuevo estado agregado: 'queued'
sync_status enum('pending', 'syncing', 'synced', ..., 'queued')
```

---

### 5. **Scripts SQL**

#### Para instalaciones nuevas:
📁 `sql/install.sql` - Ya incluye tabla `yuju_sync_queue`

#### Para bases de datos existentes (PRUEBAS):
📁 `sql/update_add_queue_system.sql`

```bash
# Ejecutar en MySQL:
mysql -u root -p prestashop_yuju < sql/update_add_queue_system.sql
```

**Este script hace:**
1. Modifica `sync_status` para agregar 'queued'
2. Crea tabla `ps_yuju_sync_queue`
3. Agrega configuraciones `YUJU_BATCH_SIZE` y `YUJU_BATCH_FREQUENCY`
4. Muestra verificación de cambios

#### Para desinstalación:
📁 `sql/uninstall.sql` - Incluye `DROP TABLE ps_yuju_sync_queue`

---

## ⚙️ Configuración

### Panel de Administración
🔗 `AdminYujuConfiguration` → Pestaña "Sincronización"

**Configuraciones existentes:**
- **YUJU_BATCH_SIZE**: Tamaño del lote (default: 100)
  - Productos a procesar en cada ejecución del cron
  - Rango: 1-500
  
- **YUJU_BATCH_FREQUENCY**: Frecuencia en segundos (default: 60)
  - Tiempo mínimo entre lotes
  - Rango: 30-3600 segundos

---

## 🚀 Flujo de Trabajo

### Escenario: Actualizar 5,000 productos

**Configuración:**
- `YUJU_BATCH_SIZE` = 100
- Cron cada 5 minutos

**Proceso:**
1. **Usuario edita campos normales** (nombre, referencia, peso):
   ```
   Hook detecta cambio → Prioridad NORMAL → Agrega a cola → Estado: 'queued'
   ```

2. **Usuario edita precio/stock**:
   ```
   Hook detecta cambio → Prioridad ALTA → Sincroniza inmediatamente → Estado: 'synced'
   ```

3. **Cron ejecuta cada 5 minutos**:
   ```
   Minuto 0:  Procesa lote 1-100    (100 productos)
   Minuto 5:  Procesa lote 101-200  (100 productos)
   Minuto 10: Procesa lote 201-300  (100 productos)
   ...
   Minuto 245: Procesa lote 4901-5000 (100 productos)
   ```

**Tiempo total:** ~4 horas para 5,000 productos (sin saturar el sistema)

---

## 📊 Estados de Sincronización

| Estado | Descripción |
|--------|-------------|
| `pending` | Producto no sincronizado aún |
| `queued` | **NUEVO** - En cola, esperando procesamiento |
| `syncing` | Sincronización en progreso |
| `synced` | Sincronizado correctamente |
| `synced_with_warnings` | Sincronizado con advertencias |
| `synced_with_errors` | Sincronizado con errores |
| `error` | Error en sincronización |
| `disabled` | Sincronización deshabilitada |

---

## 🔍 Monitoreo

### Vista Web del Cron
🔗 `https://yuju.ceballosleon.com/modules/prestashopyuju/cron/cron.php`

**Sección "Cola de Sincronización por Lotes":**
- Total de elementos en cola
- Pendientes, Procesando, Completados, Fallidos
- Resultados del último lote procesado

### Logs
📁 `logs/info.log` y `logs/error.log`

```
[Cola] Estadísticas de sincronización
  Total: 5000
  Pendientes: 4900
  Completados: 100
  
[Cola] Procesando lote de 100 productos...
[Cola] Producto procesado exitosamente - queue_id: 123, product_id: 456
[Cola] Lote procesado - processed: 100, success: 98, failed: 2
```

### Base de Datos
```sql
-- Ver cola actual
SELECT * FROM ps_yuju_sync_queue WHERE status = 'pending' ORDER BY created_at ASC;

-- Estadísticas
SELECT status, COUNT(*) as total FROM ps_yuju_sync_queue GROUP BY status;

-- Productos en cola
SELECT COUNT(*) FROM ps_yuju_product_status WHERE sync_status = 'queued';
```

---

## 🧪 Pruebas

### 1. Ejecutar Script de Actualización
```bash
cd f:\xampp82\htdocs\html\yuju\prestashopyuju
Get-Content sql\update_add_queue_system.sql | & "f:\xampp82\mysql\bin\mysql.exe" -u root prestashop_yuju
```

### 2. Verificar Tabla Creada
```sql
SHOW TABLES LIKE 'ps_yuju_sync_queue';
DESCRIBE ps_yuju_sync_queue;
```

### 3. Probar Hook (editar producto)
1. Editar **nombre** de un producto → Verificar estado `queued`
2. Editar **precio** de un producto → Verificar sincronización inmediata
3. Revisar `ps_yuju_sync_queue` → Debe tener 1 registro pendiente

### 4. Ejecutar Cron Manualmente
```bash
# Por URL (navegador)
https://yuju.ceballosleon.com/modules/prestashopyuju/cron/cron.php

# Por CLI
php f:\xampp82\htdocs\html\yuju\prestashopyuju\cron\cron.php
```

### 5. Verificar Procesamiento
```sql
-- Debe aparecer como 'completed'
SELECT * FROM ps_yuju_sync_queue WHERE prestashop_product_id = [ID_PRODUCTO];

-- Estado del producto debe cambiar a 'synced'
SELECT sync_status FROM ps_yuju_product_status WHERE prestashop_product_id = [ID_PRODUCTO];
```

---

## 🛠️ Reintentos y Manejo de Errores

**Sistema de reintentos automáticos:**
- `max_attempts` = 3 por defecto
- Si falla 3 veces → Estado: `failed`
- Registra `error_message` en cada intento
- Productos fallidos NO bloquean el lote

**Limpieza automática:**
- Registros `completed` > 7 días → Eliminados automáticamente
- Evita acumulación infinita en la base de datos

---

## ✅ Ventajas del Sistema

1. **Escalabilidad**: Procesa miles de productos sin saturar el servidor
2. **Priorización**: Precio/stock inmediatos, otros campos en lotes
3. **Tolerancia a fallos**: Reintentos automáticos, registros de errores
4. **Monitoreo**: Estadísticas en tiempo real, logs detallados
5. **Mantenimiento**: Limpieza automática de registros antiguos
6. **Flexible**: Tamaño de lote y frecuencia configurables

---

## 📌 Notas Importantes

⚠️ **Cron debe ejecutarse cada 5 minutos** para procesar lotes regularmente:
```bash
*/5 * * * * /usr/bin/php /ruta/a/prestashop/modules/prestashopyuju/cron/cron.php
```

⚠️ **Precio y stock NUNCA van a la cola** - Se sincronizan inmediatamente

⚠️ **Estados intermedios**: Un producto puede estar `queued` temporalmente antes de pasar a `synced`

⚠️ **Compatibilidad**: Sistema compatible con sincronización automática existente (webhooks)

---

## 🎯 Próximos Pasos

1. Ejecutar `update_add_queue_system.sql` en base de datos de pruebas
2. Verificar creación de tabla `ps_yuju_sync_queue`
3. Editar producto de prueba (cambiar nombre)
4. Verificar estado `queued` en base de datos
5. Ejecutar cron manualmente
6. Verificar que el producto se sincronizó correctamente
7. Configurar cron automático cada 5 minutos en servidor

---

**Implementación completada** ✅  
Sistema de cola por lotes operativo y listo para pruebas.
