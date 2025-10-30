# Configuración de CRON para Sincronización Automática

## 🔄 Flujo del Sistema

El sistema ahora funciona completamente automatizado con un solo endpoint:

```
https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php
```

### Lógica Inteligente del CRON

Cada vez que se ejecuta (cada 10 minutos), el sistema:

1. **Valida si hay trabajo pendiente**:
   - ✅ Descargas fallidas o en progreso (últimas 2 horas)
   - ✅ Sincronizaciones pendientes (descargas sin sincronizar en últimas 2 horas)
   - ✅ Necesidad de nueva descarga (cada 12 horas)

2. **Salida temprana si no hay trabajo**:
   - Si no hay trabajos pendientes Y no toca descarga → Sale inmediatamente (modo ligero)
   - Muestra cuándo será la próxima descarga programada

3. **Descarga cada 12 horas**:
   - Solicita URL a Yuju API
   - Descarga JSON de CloudFront (con auto-retry si error 403)
   - Registra en `ps_yuju_sync_logs` con `sync_direction='yuju_to_prestashop'`

4. **Auto-sincronización inmediata**:
   - Después de una descarga exitosa, automáticamente ejecuta la sincronización
   - Verifica que no exista sincronización previa para ese `download_id`
   - Actualiza stock y precio en Yuju
   - Registra en `ps_yuju_sync_logs` con `sync_direction='prestashop_to_yuju'`

5. **Ventana de 2 horas**:
   - Solo sincroniza descargas de las últimas 2 horas
   - Después de 2 horas, espera la siguiente descarga (12h)

---

## ⚙️ Configuración del CRON

### Opción 1: cPanel (Recomendado)

1. Acceder al cPanel de tu hosting
2. Ir a **"Cron Jobs"** o **"Tareas Cron"**
3. Crear nueva tarea con:

**Intervalo**: Cada 10 minutos
```
*/10 * * * *
```

**Comando**:
```bash
curl -s "https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php" > /dev/null 2>&1
```

O si tu servidor requiere PHP directo:
```bash
/usr/bin/php /home/tuusuario/public_html/modules/prestashopyuju/cron/sync_products.php > /dev/null 2>&1
```

---

### Opción 2: Linux (Consola SSH)

1. Editar crontab:
```bash
crontab -e
```

2. Agregar línea:
```bash
*/10 * * * * curl -s "https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php" > /dev/null 2>&1
```

3. Guardar y salir (Ctrl+O, Enter, Ctrl+X en nano)

4. Verificar que se guardó:
```bash
crontab -l
```

---

### Opción 3: Windows (Task Scheduler)

1. Abrir **"Programador de tareas"** (Task Scheduler)
2. Crear tarea básica
3. Configurar:
   - **Desencadenador**: Cada 10 minutos
   - **Acción**: Iniciar programa
   - **Programa**: `powershell.exe`
   - **Argumentos**: 
     ```
     -Command "Invoke-WebRequest -Uri 'https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php' -UseBasicParsing | Out-Null"
     ```

---

## 📊 Monitoreo del Sistema

### Ver logs en tiempo real

Desde SSH:
```bash
tail -f /home/tuusuario/public_html/modules/prestashopyuju/logs/sync_logs/sync_*.log
```

### Consultar última ejecución

SQL directo en phpMyAdmin:
```sql
SELECT * FROM ps_yuju_sync_logs 
ORDER BY start_time DESC 
LIMIT 10;
```

### Ver descargas y sus sincronizaciones

```sql
SELECT 
    d.id as download_id,
    d.start_time as download_time,
    d.status as download_status,
    d.total_items,
    COUNT(s.id) as sync_count,
    MAX(s.start_time) as last_sync_time
FROM ps_yuju_sync_logs d
LEFT JOIN ps_yuju_sync_logs s 
    ON s.sync_direction = 'prestashop_to_yuju'
    AND JSON_EXTRACT(s.details, '$.source_download_id') = d.id
WHERE d.sync_direction = 'yuju_to_prestashop'
GROUP BY d.id
ORDER BY d.start_time DESC
LIMIT 20;
```

---

## 🔍 Verificación del Sistema

### Test Manual (vía navegador)

```
https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php
```

Deberías ver mensajes como:
- ✅ **SISTEMA AL DÍA** → Todo bien, no hay trabajo pendiente
- ⚡ **TRABAJO PENDIENTE DETECTADO** → Procesando descargas o sincronizaciones
- ⚡ **NUEVA DESCARGA NECESARIA** → Han pasado 12 horas, descargando...

### Test CRON (vía curl)

```bash
curl -s "https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php"
```

---

## 📈 Comportamiento Esperado

### Primera ejecución (00:00)
```
⚡ PRIMERA DESCARGA DEL SISTEMA
➤ PASO 1: Solicitando URL de descarga a Yuju API...
✓ URL recibida de Yuju API
➤ PASO 2: Descargando JSON desde CloudFront...
✓ JSON guardado localmente (123.45 MB)
🔄 Iniciando auto-sincronización...
⚡ Ejecutando sincronización automática...
✓ Sincronización completada
   → Actualizados: 1234
   → Sincronizados: 4567
```

### Ejecuciones siguientes (00:10, 00:20, ..., 11:50)
```
✅ SISTEMA AL DÍA - No hay trabajos pendientes
   Última descarga: 2024-01-15 00:00:00
   Próxima descarga en: ~11.8 horas
   Ejecutando en modo monitoreo ligero (10 min)
Tiempo de ejecución: 0.02s
```

### A las 12:00 (siguiente descarga)
```
⚡ NUEVA DESCARGA NECESARIA (han pasado 12.0 horas)
➤ PASO 1: Solicitando URL de descarga a Yuju API...
[... proceso de descarga ...]
🔄 Iniciando auto-sincronización...
[... proceso de sincronización ...]
```

---

## 🛠️ Troubleshooting

### El CRON no se ejecuta

1. Verificar que el usuario tenga permisos:
```bash
ls -la /home/tuusuario/public_html/modules/prestashopyuju/cron/
```

2. Probar comando manualmente:
```bash
curl -v "https://yuju.ceballosleon.com/modules/prestashopyuju/cron/sync_products.php"
```

### Error 403 en CloudFront

El sistema tiene auto-retry. Si persiste:
1. Verificar token OAuth válido
2. Ver logs en `ps_yuju_sync_logs`
3. El sistema reintentará en la próxima ejecución (10 min)

### Sincronización no se ejecuta

Verificar en `ps_yuju_sync_logs`:
```sql
SELECT * FROM ps_yuju_sync_logs 
WHERE sync_direction = 'yuju_to_prestashop'
AND status = 'completed'
AND start_time >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
ORDER BY start_time DESC;
```

Si hay descargas completas sin sincronización, el sistema las detectará en la próxima ejecución.

### Sistema muy lento

Ajustar parámetros en `sync_products.php`:
```php
$batch_size = 100; // Reducir si hay timeouts
$batch_frequency = 5; // Aumentar espera entre lotes
```

---

## 📝 Notas Importantes

1. **No modificar la frecuencia** de 10 minutos (es la óptima para el sistema)
2. **No crear múltiples CRON** para el mismo endpoint (causará conflictos)
3. **El sistema es idempotente**: ejecutar múltiples veces no causa duplicados
4. **Logs automáticos**: todo se registra en `ps_yuju_sync_logs`
5. **Ventana de 2 horas**: después de ese tiempo, espera la siguiente descarga

---

## ✅ Checklist de Configuración

- [ ] CRON configurado con intervalo de 10 minutos
- [ ] URL correcta en el comando
- [ ] Test manual exitoso (navegador)
- [ ] Test CRON exitoso (curl)
- [ ] Verificar logs en `ps_yuju_sync_logs`
- [ ] Confirmar primera descarga + sincronización
- [ ] Monitorear durante 24 horas

---

## 🎯 Resultado Final

Con esta configuración, tu sistema:

✅ Descarga JSON de Yuju cada 12 horas  
✅ Sincroniza automáticamente después de cada descarga  
✅ Se ejecuta en modo ligero cuando no hay trabajo  
✅ Reintenta automáticamente si hay errores  
✅ Se recupera de bloqueos de CloudFront  
✅ Registra todo en base de datos  
✅ Es completamente autónomo (sin intervención manual)

**🚀 ¡Sistema listo para producción!**
