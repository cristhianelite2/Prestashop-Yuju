<?php
/**
 * Script para probar la instalación de tablas del módulo Yuju
 * Simula el proceso de instalación del módulo
 */

// Configuración de la base de datos
require_once(dirname(__FILE__) . '/../../config/config.inc.php');

// Verificar si estamos en PrestaShop
if (!defined('_PS_VERSION_')) {
    die('Error: No se puede acceder a PrestaShop');
}

echo "<h2>Test de Instalación - Módulo Yuju</h2>";
echo "<p>Probando la creación de tablas desde install.sql...</p>";

// Leer el archivo install.sql
$sql_file = dirname(__FILE__) . '/sql/install.sql';

if (!file_exists($sql_file)) {
    echo "<p style='color: red;'>❌ Error: Archivo install.sql no encontrado</p>";
    exit;
}

echo "<p>✓ Archivo install.sql encontrado</p>";

// Leer y procesar el SQL
$sql = file_get_contents($sql_file);
$sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);

echo "<p>✓ SQL procesado con prefijo: " . _DB_PREFIX_ . " y engine: " . _MYSQL_ENGINE_ . "</p>";

// Dividir en consultas individuales
$queries = preg_split("/;\s*$/m", $sql);

echo "<p>✓ Total de consultas encontradas: " . count(array_filter($queries, 'trim')) . "</p>";

echo "<h3>Ejecutando consultas:</h3>";

$success_count = 0;
$error_count = 0;

foreach ($queries as $index => $query) {
    $query = trim($query);
    
    if (!empty($query)) {
        echo "<p><strong>Consulta " . ($index + 1) . ":</strong><br>";
        echo "<code>" . htmlspecialchars(substr($query, 0, 100)) . "...</code>";
        
        try {
            $result = Db::getInstance()->execute($query);
            if ($result) {
                echo " - <span style='color: green;'>✓ Ejecutada exitosamente</span></p>";
                $success_count++;
            } else {
                echo " - <span style='color: red;'>✗ Error al ejecutar</span></p>";
                $error_count++;
            }
        } catch (Exception $e) {
            echo " - <span style='color: red;'>✗ Excepción: " . htmlspecialchars($e->getMessage()) . "</span></p>";
            $error_count++;
        }
    }
}

echo "<hr>";
echo "<h3>Resumen de Instalación:</h3>";
echo "<p>Consultas exitosas: <strong style='color: green;'>$success_count</strong></p>";
echo "<p>Errores: <strong style='color: red;'>$error_count</strong></p>";

// Verificar tablas creadas
echo "<h3>Verificación de Tablas Creadas:</h3>";
$expected_tables = [
    'yuju_oauth_tokens',
    'yuju_category_mapping',
    'yuju_product_mapping',
    'yuju_attribute_mapping',
    'yuju_product_status',
    'yuju_sync_logs',
    'yuju_logs',
    'yuju_webhook_logs',
    'yuju_webhook_registrations',
    'yuju_configuration',
    'yuju_order_mapping',
    'yuju_order_status_mapping',
    'yuju_categories_cache',
    'yuju_attributes_cache',
    'yuju_attribute_values_cache',
    'yuju_attribute_value_mapping'
];

foreach ($expected_tables as $table_name) {
    $full_table_name = _DB_PREFIX_ . $table_name;
    $exists = Db::getInstance()->executeS("SHOW TABLES LIKE '$full_table_name'");
    
    if ($exists) {
        echo "<p>✓ Tabla <strong>$full_table_name</strong> creada correctamente</p>";
    } else {
        echo "<p>✗ Tabla <strong>$full_table_name</strong> NO fue creada</p>";
    }
}

echo "<hr>";
if ($error_count == 0) {
    echo "<p style='color: green; font-size: 18px;'><strong>🎉 ¡Instalación completada exitosamente!</strong></p>";
    echo "<p>Todas las tablas del módulo Yuju han sido creadas correctamente.</p>";
} else {
    echo "<p style='color: red; font-size: 18px;'><strong>⚠️ Instalación completada con errores</strong></p>";
    echo "<p>Revisa los errores anteriores para corregir los problemas.</p>";
}

echo "<p><em>Puedes eliminar este archivo después de la verificación.</em></p>";
?>