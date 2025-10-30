<?php
/**
 * Script para actualizar la tabla yuju_oauth_tokens agregando las columnas client_id y client_secret
 * 
 * INSTRUCCIONES DE USO:
 * 1. Copia este archivo a la raíz de tu instalación de PrestaShop
 * 2. Ejecuta desde la línea de comandos: php update_oauth_table.php
 * 3. O accede vía navegador: http://tudominio.com/update_oauth_table.php
 * 4. Elimina el archivo después de ejecutarlo
 */

// Detectar si estamos en el contexto web o CLI
$is_web = isset($_SERVER['HTTP_HOST']);

if ($is_web) {
    echo "<pre>";
}

// Buscar el archivo de configuración de PrestaShop
$config_paths = [
    './config/config.inc.php',
    '../config/config.inc.php',
    '../../config/config.inc.php',
    '../../../config/config.inc.php'
];

$config_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_found = true;
        break;
    }
}

if (!$config_found) {
    echo "ERROR: No se pudo encontrar el archivo de configuración de PrestaShop.\n";
    echo "Por favor, copia este archivo a la raíz de tu instalación de PrestaShop.\n";
    if ($is_web) echo "</pre>";
    exit(1);
}

// Verificar si las constantes están definidas
if (!defined('_DB_PREFIX_')) {
    echo "ERROR: No se pudo cargar la configuración de PrestaShop.\n";
    if ($is_web) echo "</pre>";
    exit(1);
}

try {
    // Verificar si las columnas ya existen
    $table_name = _DB_PREFIX_ . 'yuju_oauth_tokens';

    // Verificar si la tabla existe
    $table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '{$table_name}'");
    if (empty($table_exists)) {
        echo "ERROR: La tabla {$table_name} no existe.\n";
        echo "Por favor, instala primero el módulo Yuju.\n";
        if ($is_web) echo "</pre>";
        exit(1);
    }

    // Verificar si las columnas existen
    $check_client_id = Db::getInstance()->executeS("SHOW COLUMNS FROM `{$table_name}` LIKE 'client_id'");
    $check_client_secret = Db::getInstance()->executeS("SHOW COLUMNS FROM `{$table_name}` LIKE 'client_secret'");
    $check_token_expires = Db::getInstance()->executeS("SHOW COLUMNS FROM `{$table_name}` LIKE 'token_expires'");

    $updates_needed = [];

    if (empty($check_client_id)) {
        $updates_needed[] = "ADD COLUMN `client_id` varchar(255) DEFAULT NULL AFTER `id`";
        echo "- Necesita agregar columna client_id\n";
    }

    if (empty($check_client_secret)) {
        $position = empty($check_client_id) ? "AFTER `client_id`" : "AFTER `id`";
        $updates_needed[] = "ADD COLUMN `client_secret` varchar(255) DEFAULT NULL {$position}";
        echo "- Necesita agregar columna client_secret\n";
    }

    if (empty($check_token_expires)) {
        $updates_needed[] = "ADD COLUMN `token_expires` datetime DEFAULT NULL AFTER `expires_at`";
        echo "- Necesita agregar columna token_expires\n";
    }

    if (!empty($updates_needed)) {
        $sql = "ALTER TABLE `{$table_name}` " . implode(', ', $updates_needed);
        
        echo "\nEjecutando actualización...\n";
        echo "SQL: " . $sql . "\n\n";
        
        if (Db::getInstance()->execute($sql)) {
            echo "✓ Tabla actualizada correctamente\n";
        } else {
            echo "✗ Error al actualizar la tabla: " . Db::getInstance()->getMsgError() . "\n";
            if ($is_web) echo "</pre>";
            exit(1);
        }
    } else {
        echo "✓ La tabla ya tiene todas las columnas necesarias\n";
    }

    // Mostrar estructura actual de la tabla
    echo "\nEstructura actual de la tabla {$table_name}:\n";
    $columns = Db::getInstance()->executeS("SHOW COLUMNS FROM `{$table_name}`");
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})" . 
             ($column['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . 
             ($column['Default'] !== null ? " DEFAULT '{$column['Default']}'" : '') . "\n";
    }

    echo "\n✓ Proceso completado exitosamente!\n";
    echo "\nAhora puedes volver a intentar la autenticación OAuth con Yuju.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if ($is_web) echo "</pre>";
    exit(1);
}

if ($is_web) {
    echo "</pre>";
    echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo después de ejecutarlo por seguridad.</p>";
}
?>
