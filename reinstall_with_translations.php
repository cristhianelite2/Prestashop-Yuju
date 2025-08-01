<?php
/**
 * Script para reinstalar el módulo Yuju y forzar la carga de traducciones
 * Este script desinstala y reinstala el módulo para asegurar que las traducciones se carguen correctamente
 */

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

echo "🔄 Reinstalando módulo Yuju con traducciones...\n\n";

try {
    // Obtener instancia del módulo
    $module = Module::getInstanceByName('prestashopyuju');
    
    if (!$module) {
        echo "❌ No se pudo encontrar el módulo prestashopyuju\n";
        exit(1);
    }
    
    echo "📦 Módulo encontrado: " . $module->displayName . "\n";
    echo "📍 Versión: " . $module->version . "\n\n";
    
    // Verificar si el módulo está instalado
    if ($module->isInstalled($module->name)) {
        echo "🗑️ Desinstalando módulo...\n";
        
        // Desinstalar el módulo
        if ($module->uninstall()) {
            echo "✅ Módulo desinstalado exitosamente\n";
        } else {
            echo "❌ Error al desinstalar el módulo\n";
            exit(1);
        }
    } else {
        echo "ℹ️ El módulo no estaba instalado\n";
    }
    
    // Limpiar caché antes de reinstalar
    echo "\n🧹 Limpiando caché...\n";
    
    if (class_exists('Tools') && method_exists('Tools', 'clearCache')) {
        Tools::clearCache();
        echo "✅ Caché general limpiada\n";
    }
    
    // Limpiar caché de Smarty
    if (class_exists('Context') && Context::getContext()->smarty) {
        Context::getContext()->smarty->clearAllCache();
        Context::getContext()->smarty->clearCompiledTemplate();
        echo "✅ Caché de Smarty limpiada\n";
    }
    
    // Esperar un momento
    sleep(2);
    
    // Reinstalar el módulo
    echo "\n📦 Instalando módulo...\n";
    
    // Crear nueva instancia del módulo
    $module = new PrestaShopYuju();
    
    if ($module->install()) {
        echo "✅ Módulo instalado exitosamente\n";
        
        // Verificar que las traducciones estén disponibles
        echo "\n🔍 Verificando traducciones...\n";
        
        // Probar algunas traducciones
        $test_translations = [
            'Yuju Integration',
            'Configuration',
            'Synchronization'
        ];
        
        foreach ($test_translations as $key) {
            $translated = $module->trans($key, array(), 'Modules.Prestashopyuju.Admin');
            echo "🔤 '$key' -> '$translated'\n";
        }
        
        echo "\n✅ Reinstalación completada exitosamente\n";
        
    } else {
        echo "❌ Error al instalar el módulo\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Error durante la reinstalación: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
    exit(1);
}

echo "\n🎉 Proceso completado!\n\n";
echo "📝 Instrucciones finales:\n";
echo "1. Ve al back office de PrestaShop\n";
echo "2. Navega a Módulos > Gestor de módulos\n";
echo "3. Busca 'Yuju Integration'\n";
echo "4. Verifica que esté instalado y activo\n";
echo "5. Ve a Internacional > Traducciones\n";
echo "6. Selecciona 'Modificar traducciones' > 'Módulos' > 'prestashopyuju'\n";
echo "7. Guarda las traducciones\n";
echo "8. Limpia la caché desde Parámetros avanzados > Rendimiento\n";
echo "9. Accede al módulo y verifica que las traducciones aparezcan en español\n\n";
echo "💡 Las traducciones deberían funcionar correctamente ahora.\n";