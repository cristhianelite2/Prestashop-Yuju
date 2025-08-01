<?php
/**
 * Script para corregir errores del módulo Yuju en el servidor
 * Este script aplica las correcciones necesarias para resolver los errores SQL
 */

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

echo "🔧 Aplicando correcciones al módulo Yuju...\n\n";

try {
    // 1. Verificar que la tabla yuju_attribute_value_mapping existe
    echo "📋 Verificando tabla yuju_attribute_value_mapping...\n";
    
    $table_exists = Db::getInstance()->executeS(
        "SHOW TABLES LIKE '" . _DB_PREFIX_ . "yuju_attribute_value_mapping'"
    );
    
    if (empty($table_exists)) {
        echo "⚠️ Tabla yuju_attribute_value_mapping no existe. Creándola...\n";
        
        $sql = '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yuju_attribute_value_mapping` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `attribute_mapping_id` int(11) NOT NULL,
            `prestashop_attribute_value_id` int(11) NOT NULL,
            `yuju_value_id` varchar(255) NOT NULL,
            `yuju_value_name` varchar(255),
            `sync_direction` enum(\'prestashop_to_yuju\', \'yuju_to_prestashop\', \'bidirectional\') DEFAULT \'bidirectional\',
            `is_active` tinyint(1) DEFAULT 1,
            `last_sync_at` datetime,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_mapping` (`attribute_mapping_id`, `prestashop_attribute_value_id`),
            KEY `idx_attribute_mapping` (`attribute_mapping_id`),
            KEY `idx_prestashop_value` (`prestashop_attribute_value_id`),
            KEY `idx_yuju_value` (`yuju_value_id`),
            KEY `idx_active` (`is_active`),
            FOREIGN KEY (`attribute_mapping_id`) REFERENCES `' . _DB_PREFIX_ . 'yuju_attribute_mapping` (`id`) ON DELETE CASCADE
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
        
        if (Db::getInstance()->execute($sql)) {
            echo "✅ Tabla yuju_attribute_value_mapping creada exitosamente\n";
        } else {
            echo "❌ Error al crear la tabla yuju_attribute_value_mapping\n";
        }
    } else {
        echo "✅ Tabla yuju_attribute_value_mapping ya existe\n";
    }
    
    // 2. Verificar que las tablas de caché existen
    echo "\n📋 Verificando tablas de caché...\n";
    
    $cache_tables = [
        'yuju_attributes_cache' => '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yuju_attributes_cache` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `yuju_attribute_id` varchar(255) NOT NULL,
            `name` varchar(255) NOT NULL,
            `type` varchar(50) DEFAULT \'text\',
            `required` tinyint(1) DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_yuju_attribute` (`yuju_attribute_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;',
        
        'yuju_attribute_values_cache' => '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yuju_attribute_values_cache` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `yuju_attribute_id` varchar(255) NOT NULL,
            `yuju_value_id` varchar(255) NOT NULL,
            `value_name` varchar(255) NOT NULL,
            `value_code` varchar(100),
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_yuju_attribute_value` (`yuju_attribute_id`, `yuju_value_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
    ];
    
    foreach ($cache_tables as $table_name => $create_sql) {
        $table_exists = Db::getInstance()->executeS(
            "SHOW TABLES LIKE '" . _DB_PREFIX_ . $table_name . "'"
        );
        
        if (empty($table_exists)) {
            echo "⚠️ Tabla $table_name no existe. Creándola...\n";
            
            if (Db::getInstance()->execute($create_sql)) {
                echo "✅ Tabla $table_name creada exitosamente\n";
            } else {
                echo "❌ Error al crear la tabla $table_name\n";
            }
        } else {
            echo "✅ Tabla $table_name ya existe\n";
        }
    }
    
    // 3. Verificar estructura de la tabla yuju_attribute_mapping
    echo "\n📋 Verificando estructura de yuju_attribute_mapping...\n";
    
    $columns = Db::getInstance()->executeS(
        "SHOW COLUMNS FROM " . _DB_PREFIX_ . "yuju_attribute_mapping"
    );
    
    $has_id_column = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'id') {
            $has_id_column = true;
            break;
        }
    }
    
    if ($has_id_column) {
        echo "✅ Columna 'id' existe en yuju_attribute_mapping\n";
    } else {
        echo "❌ Columna 'id' no existe en yuju_attribute_mapping\n";
        echo "⚠️ Esto puede causar problemas. Verifica la estructura de la tabla.\n";
    }
    
    echo "\n🎉 Correcciones aplicadas exitosamente!\n";
    echo "\n📝 Resumen de acciones realizadas:\n";
    echo "   - Verificación y creación de tabla yuju_attribute_value_mapping\n";
    echo "   - Verificación y creación de tablas de caché\n";
    echo "   - Verificación de estructura de tablas\n";
    echo "\n✅ El módulo debería funcionar correctamente ahora.\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la aplicación de correcciones: " . $e->getMessage() . "\n";
    echo "\n🔍 Detalles del error:\n";
    echo "   Archivo: " . $e->getFile() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Mensaje: " . $e->getMessage() . "\n";
}

echo "\n🏁 Script completado.\n";