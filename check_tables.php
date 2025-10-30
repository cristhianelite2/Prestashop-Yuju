<?php
/**
 * Script para verificar y crear las tablas del módulo Yuju
 */

// Buscar configuración de PrestaShop
$config_paths = [
    '../../../config/config.inc.php',
    '../../config/config.inc.php', 
    '../config/config.inc.php',
    './config/config.inc.php'
];

$config_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        echo "Configuración encontrada en: $path\n";
        $config_found = true;
        break;
    }
}

if (!$config_found) {
    echo "Error: No se pudo encontrar la configuración de PrestaShop\n";
    echo "Ejecuta este script desde la instalación de PrestaShop\n";
    exit(1);
}

if (!defined('_DB_PREFIX_')) {
    echo "Error: No se pudo cargar la configuración de PrestaShop\n";
    exit(1);
}

echo "Prefijo de BD: " . _DB_PREFIX_ . "\n";
echo "Nombre de BD: " . _DB_NAME_ . "\n\n";

// Verificar si las tablas existen
$tables = [
    'yuju_oauth_tokens',
    'yuju_category_mapping', 
    'yuju_product_mapping',
    'yuju_attribute_mapping'
];

$missing_tables = [];

echo "Verificando tablas del módulo Yuju:\n";
foreach ($tables as $table) {
    $full_table = _DB_PREFIX_ . $table;
    $exists = Db::getInstance()->executeS("SHOW TABLES LIKE '$full_table'");
    $status = empty($exists) ? 'NO EXISTE' : 'EXISTE';
    echo "- $full_table: $status\n";
    
    if (empty($exists)) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    echo "\n¿Crear las tablas faltantes? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $answer = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($answer) === 's' || strtolower($answer) === 'si') {
        echo "\nCreando tablas...\n";
        
        // Leer y ejecutar el archivo install.sql
        $install_sql = file_get_contents('./sql/install.sql');
        if ($install_sql === false) {
            echo "Error: No se pudo leer el archivo sql/install.sql\n";
            exit(1);
        }
        
        // Reemplazar el prefijo en el SQL
        $install_sql = str_replace('PREFIX_', _DB_PREFIX_, $install_sql);
        $install_sql = str_replace("' . _DB_PREFIX_ . '", _DB_PREFIX_, $install_sql);
        
        // Dividir en consultas individuales
        $queries = array_filter(array_map('trim', explode(';', $install_sql)));
        
        foreach ($queries as $query) {
            if (!empty($query)) {
                echo "Ejecutando: " . substr($query, 0, 100) . "...\n";
                if (!Db::getInstance()->execute($query)) {
                    echo "Error ejecutando consulta: " . Db::getInstance()->getMsgError() . "\n";
                    echo "Consulta: $query\n";
                } else {
                    echo "✓ Consulta ejecutada correctamente\n";
                }
            }
        }
        
        echo "\nVerificación final:\n";
        foreach ($tables as $table) {
            $full_table = _DB_PREFIX_ . $table;
            $exists = Db::getInstance()->executeS("SHOW TABLES LIKE '$full_table'");
            $status = empty($exists) ? 'NO EXISTE' : 'EXISTE';
            echo "- $full_table: $status\n";
        }
    }
} else {
    echo "\n✓ Todas las tablas existen correctamente\n";
}
?>
