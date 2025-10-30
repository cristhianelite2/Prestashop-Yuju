<?php
/**
 * Migración de configuración desde ps_configuration a ps_yuju_configuration
 * 
 * Este script debe ejecutarse UNA SOLA VEZ después de actualizar el módulo
 * para mover todas las configuraciones YUJU_* de la tabla de PrestaShop
 * a la tabla dedicada del módulo.
 * 
 * Uso: Ejecutar desde navegador o CLI
 */

// Cargar PrestaShop
require_once dirname(__FILE__, 2) . '/config/config.inc.php';
require_once dirname(__FILE__, 2) . '/init.php';

// Cargar clase YujuConfig
require_once _PS_MODULE_DIR_ . 'prestashopyuju/config/config.php';

echo "<h1>Migración de Configuración Yuju</h1>\n";
echo "<p>Moviendo configuraciones de ps_configuration a ps_yuju_configuration...</p>\n";

// Lista de claves YUJU a migrar
$yuju_keys_to_migrate = [
    'YUJU_ENVIRONMENT',
    'YUJU_CLIENT_ID',
    'YUJU_CLIENT_SECRET',
    'YUJU_REDIRECT_URI',
    'YUJU_AUTO_SYNC',
    'YUJU_SYNC_FREQUENCY',
    'YUJU_BATCH_SIZE',
    'YUJU_BATCH_FREQUENCY',
    'YUJU_MAX_DAILY_SYNCS',
    'YUJU_EMAIL_NOTIFICATIONS',
    'YUJU_NOTIFICATION_EMAIL',
    'YUJU_WEBHOOK_SECRET',
    'YUJU_LOG_LEVEL',
    'YUJU_LOG_RETENTION',
    'YUJU_PRESTASHOP_STORE_ID',
    'YUJU_STORE_LANGUAGE',
    'YUJU_SYNC_ENABLED',
    'YUJU_SYNC_PRICES',
    'YUJU_SYNC_STOCK',
    'YUJU_SYNC_IMAGES',
    'YUJU_SYNC_ORDERS',
    'YUJU_CLEAN_HTML',
    'YUJU_LOGGING_ENABLED',
    'YUJU_FORCE_UPDATE',
    'YUJU_API_CLIENT_ID',
    'YUJU_API_CLIENT_SECRET',
    'YUJU_API_ENVIRONMENT',
    'YUJU_AUDIT_FREQUENCY',
    'YUJU_ERROR_THRESHOLD',
    'YUJU_OAUTH_STATE',
    'YUJU_SYNC_DATE',
    'YUJU_SYNC_COUNT',
    'YUJU_LAST_SYNC_TIME',
    'YUJU_LAST_CRON_SYNC',
    'YUJU_LAST_SYNC_DATE',
    'YUJU_AUTO_SYNC_ENABLED',
    'YUJU_SYNC_BATCH_SIZE',
    'YUJU_SYNC_MAX_EXECUTION_TIME',
    'YUJU_SYNC_CATEGORIES',
    'YUJU_SYNC_PRODUCTS',
    'YUJU_ORDER_PREFIX',
    'YUJU_ENABLE_EMAIL_NOTIFICATIONS',
];

$migrated = 0;
$skipped = 0;
$errors = 0;

echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Clave</th><th>Valor Antiguo</th><th>Estado</th></tr>\n";

foreach ($yuju_keys_to_migrate as $key) {
    // Leer valor actual de ps_configuration
    $old_value = Configuration::get($key);
    
    if ($old_value === false || $old_value === '') {
        echo "<tr><td>$key</td><td><em>(vacío)</em></td><td style='color:gray'>Saltado</td></tr>\n";
        $skipped++;
        continue;
    }
    
    // Determinar tipo de dato
    $type = 'string';
    if (is_numeric($old_value)) {
        if (strpos($old_value, '.') !== false) {
            $type = 'string'; // Decimales como string para no perder precisión
        } else {
            $type = 'integer';
        }
    } elseif ($old_value === '1' || $old_value === '0') {
        // Podría ser boolean
        if (strpos($key, 'ENABLED') !== false || 
            strpos($key, 'SYNC_') !== false || 
            strpos($key, 'LOGGING') !== false ||
            strpos($key, 'NOTIFICATIONS') !== false ||
            strpos($key, 'AUTO_') !== false) {
            $type = 'boolean';
        }
    }
    
    // Guardar en ps_yuju_configuration
    try {
        $success = YujuConfig::set($key, $old_value, $type);
        
        if ($success) {
            echo "<tr><td>$key</td><td>" . htmlspecialchars(substr($old_value, 0, 50)) . "</td><td style='color:green'>✓ Migrado ($type)</td></tr>\n";
            $migrated++;
            
            // Opcional: Eliminar de ps_configuration después de migrar
            // Configuration::deleteByName($key);
        } else {
            echo "<tr><td>$key</td><td>" . htmlspecialchars(substr($old_value, 0, 50)) . "</td><td style='color:red'>✗ Error</td></tr>\n";
            $errors++;
        }
    } catch (Exception $e) {
        echo "<tr><td>$key</td><td>" . htmlspecialchars(substr($old_value, 0, 50)) . "</td><td style='color:red'>✗ " . $e->getMessage() . "</td></tr>\n";
        $errors++;
    }
    
    flush();
}

echo "</table>\n";
echo "<hr>\n";
echo "<h2>Resumen de Migración</h2>\n";
echo "<ul>\n";
echo "<li><strong>Migrados exitosamente:</strong> $migrated</li>\n";
echo "<li><strong>Saltados (vacíos):</strong> $skipped</li>\n";
echo "<li><strong>Errores:</strong> $errors</li>\n";
echo "</ul>\n";

if ($migrated > 0) {
    echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;border-radius:5px;margin:20px 0'>\n";
    echo "<h3 style='color:#155724;margin:0 0 10px 0'>✓ Migración Completada</h3>\n";
    echo "<p>Se han migrado $migrated configuraciones a ps_yuju_configuration.</p>\n";
    echo "<p><strong>Nota:</strong> Los valores antiguos siguen en ps_configuration. ";
    echo "Si deseas eliminarlos, descomenta la línea Configuration::deleteByName() en este script y ejecútalo de nuevo.</p>\n";
    echo "</div>\n";
}

if ($errors > 0) {
    echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;border-radius:5px;margin:20px 0'>\n";
    echo "<h3 style='color:#721c24;margin:0 0 10px 0'>⚠ Errores Detectados</h3>\n";
    echo "<p>Se encontraron $errors errores durante la migración. Revisa los logs para más detalles.</p>\n";
    echo "</div>\n";
}

echo "<hr>\n";
echo "<p><a href='../admin-dev/index.php?controller=AdminYujuConfiguration'>← Volver a Configuración Yuju</a></p>\n";
