# Sistema de Sincronización Automática de Stock y Precios - Yuju

## 📋 Resumen de Implementación

### ✅ Cambios Realizados

#### 1. **CRON Principal Simplificado** (`cron/cron.php`)
- ❌ **ELIMINADO**: Verificación de token (check_token.php) - ya no es necesario porque el token tiene 10 años de validez
- ✅ **AGREGADO**: Sistema de sincronización automática de stock y precios
- ⏰ **Frecuencia**: Configurable desde el panel de administración

#### 2. **Nuevo Script de Sincronización** (`cron/sync_products.php`)
Funcionalidades:
- 📥 Consulta `GET https://api.tp.yuju.io/products-offer-report` para obtener productos de Yuju
- 🔍 Compara SKU con productos en PrestaShop (tabla `ps_product.reference`)
- 📊 Detecta diferencias en stock y precio
- 📤 Actualiza productos en Yuju usando `PUT https://api.tp.yuju.io/products/{id}`
- 📝 Genera reporte diario en `logs/sync_logs/sync_YYYY-MM-DD.log`

#### 3. **Panel de Administración** (`controllers/admin/AdminYujuConfigurationController.php`)
- ⚙️ Nueva configuración: `YUJU_SYNC_INTERVAL`
- ⏱️ Rango: 5 a 1440 minutos (5 min - 24 horas)
- 💾 Valor por defecto: 30 minutos

#### 4. **Interfaz de Configuración** (`views/templates/admin/configuration/oauth_setup.tpl`)
- 🎨 Campo nuevo: "Intervalo de Sincronización"
- 📝 Input numérico con validación (mín: 5, máx: 1440)
- 💡 Ayuda contextual para el usuario

---

## 🚀 Cómo Funciona

### Flujo de Sincronización

```
1. CRON Principal (cada 5 minutos)
   └─> Verifica si pasó el intervalo configurado
       └─> SI: Ejecuta sync_products.php
       └─> NO: Espera próxima ejecución

2. sync_products.php
   ├─> Obtiene token OAuth válido
   ├─> GET /products-offer-report (productos de Yuju)
   ├─> Itera cada producto:
   │   ├─> Busca en PrestaShop por SKU
   │   ├─> Compara stock y precio
   │   └─> Si difieren:
   │       └─> PUT /products/{id} (actualiza en Yuju)
   └─> Genera reporte diario
```

### Ejemplo de Reporte Diario

```
=================================================
REPORTE DE SINCRONIZACIÓN - 2025-10-22 01:30:15
=================================================

✓ SKU: PROD001 | Stock: 10 → 8 | Precio: 99.99 → 89.99
✓ SKU: PROD002 | Stock: 5 → 12
✗ SKU: PROD003 | Error HTTP 404

=================================================
ESTADÍSTICAS
=================================================
Total productos Yuju: 150
Procesados: 150
Actualizados: 2
Sin cambios: 145
No encontrados en PrestaShop: 2
Errores: 1

Tiempo de ejecución: 3.45 segundos
=================================================
```

---

## ⚙️ Configuración

### En el Panel de Administración

1. Ir a: **Módulos → Yuju Integration → Configuración**
2. Buscar: **"Intervalo de Sincronización"**
3. Establecer: Minutos entre sincronizaciones (mínimo 5)
4. Guardar

### Recomendaciones de Intervalo

| Tamaño Catálogo | Intervalo Recomendado |
|-----------------|----------------------|
| < 100 productos | 15 minutos          |
| 100-500         | 30 minutos          |
| 500-1000        | 60 minutos          |
| > 1000          | 120 minutos         |

---

## 📊 Monitoreo

### Ver Reportes de Sincronización

```bash
# Último reporte
cat modules/prestashopyuju/logs/sync_logs/sync_2025-10-22.log

# Todos los reportes de hoy
ls -lh modules/prestashopyuju/logs/sync_logs/sync_$(date +%Y-%m-%d).log

# Buscar errores en reportes
grep "✗" modules/prestashopyuju/logs/sync_logs/sync_*.log
```

### Ejecutar Manualmente

```bash
# Por CLI
php modules/prestashopyuju/cron/sync_products.php

# Por navegador
https://tutienda.com/modules/prestashopyuju/cron/sync_products.php
```

---

## 🔧 Mantenimiento

### Logs Automáticos

- **Ubicación**: `logs/sync_logs/`
- **Formato**: `sync_YYYY-MM-DD.log`
- **Rotación**: Un archivo por día
- **Contenido**: Todos los cambios realizados

### Limpieza de Logs Antiguos

```bash
# Eliminar logs de más de 30 días
find modules/prestashopyuju/logs/sync_logs/ -name "sync_*.log" -mtime +30 -delete
```

---

## 🐛 Solución de Problemas

### La sincronización no se ejecuta

1. Verificar que el CRON principal está funcionando:
   ```bash
   curl https://tutienda.com/modules/prestashopyuju/cron/cron.php
   ```

2. Revisar configuración del intervalo:
   - Panel Admin → Configuración → Intervalo de Sincronización

3. Verificar última ejecución:
   ```php
   Configuration::get('YUJU_LAST_SYNC_CHECK');
   ```

### Productos no se actualizan

1. Verificar que el SKU coincide:
   - PrestaShop: campo `reference` en tabla `ps_product`
   - Yuju: campo `sku` o `sku_simple`

2. Revisar permisos del token OAuth

3. Consultar reporte diario para ver errores específicos

---

## 📚 Referencias API

- **GET /products-offer-report**: https://api-docs.yuju.io/docs/reporte-de-productos-en-oferta
- **PUT /products/{id}**: https://api-docs.yuju.io/docs/actualizar-un-producto

---

## ✅ Checklist de Verificación

- [x] Token OAuth válido (10 años)
- [x] CRON principal ejecutándose cada 5 minutos
- [x] Intervalo de sincronización configurado
- [x] Directorio `logs/sync_logs/` creado y con permisos
- [x] Productos tienen SKU (reference) en PrestaShop
- [x] SKU coinciden entre PrestaShop y Yuju

---

**Fecha de Implementación**: 2025-10-22  
**Versión del Módulo**: 1.0.0  
**Desarrollado por**: Yuju Integration Team
