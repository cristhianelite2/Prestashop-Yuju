<?php
/**
 * Script para corregir el error SQL en AdminYujuAttributeMappingController
 * Aplica la corrección de 'ag.name' a 'agl.name' directamente en el servidor
 */

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

echo "🔧 Corrigiendo error SQL en AdminYujuAttributeMappingController...\n\n";

try {
    $controller_file = dirname(__FILE__) . '/controllers/admin/AdminYujuAttributeMappingController.php';
    
    if (!file_exists($controller_file)) {
        echo "❌ Archivo del controlador no encontrado: $controller_file\n";
        exit(1);
    }
    
    echo "📁 Leyendo archivo del controlador...\n";
    $content = file_get_contents($controller_file);
    
    if ($content === false) {
        echo "❌ No se pudo leer el archivo del controlador\n";
        exit(1);
    }
    
    // Buscar y corregir el error SQL
    $old_pattern = '/ag\.name as prestashop_attribute_name/';
    $new_replacement = 'agl.name as prestashop_attribute_name';
    
    if (preg_match($old_pattern, $content)) {
        echo "🔍 Error encontrado: 'ag.name' en lugar de 'agl.name'\n";
        echo "🔧 Aplicando corrección...\n";
        
        $corrected_content = preg_replace($old_pattern, $new_replacement, $content);
        
        // Crear backup del archivo original
        $backup_file = $controller_file . '.backup.' . date('Y-m-d_H-i-s');
        if (copy($controller_file, $backup_file)) {
            echo "💾 Backup creado: $backup_file\n";
        }
        
        // Escribir el archivo corregido
        if (file_put_contents($controller_file, $corrected_content) !== false) {
            echo "✅ Corrección aplicada exitosamente\n";
            echo "\n📝 Cambio realizado:\n";
            echo "   Antes: ag.name as prestashop_attribute_name\n";
            echo "   Después: agl.name as prestashop_attribute_name\n";
        } else {
            echo "❌ Error al escribir el archivo corregido\n";
            exit(1);
        }
    } else {
        echo "✅ No se encontró el error 'ag.name' - el archivo ya está corregido\n";
    }
    
    // Verificar que el JOIN esté correcto
    if (strpos($content, 'LEFT JOIN') !== false && strpos($content, 'attribute_group_lang agl') !== false) {
        echo "✅ JOIN con attribute_group_lang está correcto\n";
    } else {
        echo "⚠️ Verificar que el JOIN con attribute_group_lang esté configurado correctamente\n";
    }
    
    echo "\n🎉 Corrección del controlador completada!\n";
    echo "\n📋 Próximos pasos:\n";
    echo "   1. Ejecutar fix_server_errors.php para crear las tablas faltantes\n";
    echo "   2. Probar el módulo en el admin de PrestaShop\n";
    echo "   3. Verificar que no hay más errores SQL\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la corrección: " . $e->getMessage() . "\n";
    echo "\n🔍 Detalles del error:\n";
    echo "   Archivo: " . $e->getFile() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Mensaje: " . $e->getMessage() . "\n";
}

echo "\n🏁 Script completado.\n";