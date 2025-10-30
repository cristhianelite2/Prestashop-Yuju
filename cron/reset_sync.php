<?php
/**
 * Reiniciar contador de sincronizaciones
 * Uso: Ejecutar desde navegador o CLI cuando necesites resetear el contador
 * 
 * Este script funciona SIN bootstrap de PrestaShop, usando conexión directa a MySQL
 * para actualizar ps_yuju_configuration
 */

// Detectar modo ejecución
$is_cli = php_sapi_name() === 'cli';
$is_web = !$is_cli;

// Función para cargar configuración de base de datos
function loadDbConfig() {
    // Buscar archivo config/config.php del módulo
    $module_config = dirname(__FILE__) . '/../config/config.php';
    
    if (!file_exists($module_config)) {
        die("ERROR: No se encontró config/config.php del módulo\n");
    }
    
    // Leer y parsear el archivo para extraer DB_* constants
    $content = file_get_contents($module_config);
    
    // Intentar cargar desde variables de entorno o archivo de PrestaShop
    $ps_config_paths = [
        dirname(__FILE__, 4) . '/app/config/parameters.php',  // PrestaShop 1.7+
        dirname(__FILE__, 3) . '/config/settings.inc.php',     // PrestaShop 1.6
    ];
    
    foreach ($ps_config_paths as $path) {
        if (file_exists($path)) {
            $params = include($path);
            if (isset($params['parameters'])) {
                return [
                    'host' => $params['parameters']['database_host'] ?? '127.0.0.1',
                    'user' => $params['parameters']['database_user'] ?? 'root',
                    'pass' => $params['parameters']['database_password'] ?? '',
                    'name' => $params['parameters']['database_name'] ?? 'prestashop',
                    'prefix' => $params['parameters']['database_prefix'] ?? 'ps_'
                ];
            }
        }
    }
    
    // Fallback: leer del archivo de configuración de PrestaShop antiguo
    $old_config_path = dirname(__FILE__, 3) . '/config/settings.inc.php';
    if (file_exists($old_config_path)) {
        include($old_config_path);
        if (defined('_DB_SERVER_')) {
            return [
                'host' => _DB_SERVER_,
                'user' => _DB_USER_,
                'pass' => _DB_PASSWD_,
                'name' => _DB_NAME_,
                'prefix' => _DB_PREFIX_
            ];
        }
    }
    
    die("ERROR: No se pudo cargar configuración de base de datos\n");
}

// Conectar a MySQL sin PrestaShop
$db_config = loadDbConfig();
$mysqli = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name']
);

if ($mysqli->connect_error) {
    die("ERROR de conexión: " . $mysqli->connect_error . "\n");
}

$table = $db_config['prefix'] . 'yuju_configuration';

// Función helper para actualizar configuración
function updateConfig($mysqli, $table, $key, $value, $type = 'string') {
    // Escapar valores
    $key_safe = $mysqli->real_escape_string($key);
    $value_safe = $mysqli->real_escape_string((string)$value);
    $type_safe = $mysqli->real_escape_string($type);
    $now = date('Y-m-d H:i:s');
    
    // Verificar si existe
    $check = $mysqli->query("SELECT id FROM `$table` WHERE config_key = '$key_safe'");
    
    if ($check && $check->num_rows > 0) {
        // UPDATE
        $sql = "UPDATE `$table` SET 
                config_value = '$value_safe',
                config_type = '$type_safe',
                updated_at = '$now'
                WHERE config_key = '$key_safe'";
    } else {
        // INSERT
        $sql = "INSERT INTO `$table` (config_key, config_value, config_type, created_at, updated_at)
                VALUES ('$key_safe', '$value_safe', '$type_safe', '$now', '$now')";
    }
    
    return $mysqli->query($sql);
}

// Resetear configuraciones
$success = true;
$success &= updateConfig($mysqli, $table, 'YUJU_SYNC_COUNT', '0', 'integer');
$success &= updateConfig($mysqli, $table, 'YUJU_SYNC_DATE', date('Y-m-d'), 'string');
$success &= updateConfig($mysqli, $table, 'YUJU_LAST_SYNC_TIME', '0', 'integer');

if ($success) {
    echo "✓ Contador de sincronizaciones reiniciado a 0\n";
    echo "✓ Fecha actualizada a " . date('Y-m-d') . "\n";
    echo "✓ Última sincronización borrada\n";
    echo "\nAhora puedes recargar el CRON para ver el contador en 0/5\n";
} else {
    echo "ERROR: No se pudieron actualizar las configuraciones\n";
    echo "Error MySQL: " . $mysqli->error . "\n";
}

$mysqli->close();

