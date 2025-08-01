<?php
/**
 * Script para forzar la recarga de traducciones del módulo Yuju
 * Útil cuando las traducciones no se actualizan correctamente
 */

echo "🔄 Forzando recarga de traducciones del módulo Yuju...\n\n";

// Definir rutas
$module_dir = dirname(__FILE__);
$prestashop_root = realpath($module_dir . '/../../../');

echo "📁 Directorio del módulo: $module_dir\n";
echo "📁 Directorio raíz de PrestaShop: $prestashop_root\n\n";

// Limpiar archivos de caché específicos del módulo
echo "🧹 Limpiando caché específico del módulo...\n";

$module_cache_patterns = [
    $prestashop_root . '/cache/*prestashopyuju*',
    $prestashop_root . '/var/cache/*/translations/*prestashopyuju*',
    $prestashop_root . '/app/cache/*/translations/*prestashopyuju*',
    $prestashop_root . '/cache/smarty/cache/*prestashopyuju*',
    $prestashop_root . '/cache/smarty/compile/*prestashopyuju*',
];

foreach ($module_cache_patterns as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                echo "🗑️ Eliminando: $file\n";
                unlink($file);
            } elseif (is_dir($file)) {
                echo "📁 Eliminando directorio: $file\n";
                rmdir($file);
            }
        }
    }
}

// Verificar archivos XLIFF
echo "\n📋 Verificando archivos XLIFF...\n";

$xliff_files = [
    $module_dir . '/translations/es-ES/ModulesPrestashopyujuAdmin.es-ES.xlf',
    $module_dir . '/translations/es-ES/ModulesPrestashopyujuShop.es-ES.xlf'
];

foreach ($xliff_files as $xliff_file) {
    if (file_exists($xliff_file)) {
        echo "✅ Encontrado: " . basename($xliff_file) . "\n";
        
        // Verificar que el archivo sea XML válido
        $xml = @simplexml_load_file($xliff_file);
        if ($xml !== false) {
            echo "   ✅ XML válido\n";
            
            // Contar traducciones
            $trans_units = $xml->xpath('//trans-unit');
            echo "   📊 Traducciones encontradas: " . count($trans_units) . "\n";
        } else {
            echo "   ❌ XML inválido\n";
        }
    } else {
        echo "❌ No encontrado: " . basename($xliff_file) . "\n";
    }
}

// Verificar permisos de archivos
echo "\n🔐 Verificando permisos...\n";

foreach ($xliff_files as $xliff_file) {
    if (file_exists($xliff_file)) {
        $perms = fileperms($xliff_file);
        $readable = is_readable($xliff_file) ? '✅' : '❌';
        echo "$readable " . basename($xliff_file) . " - Permisos: " . substr(sprintf('%o', $perms), -4) . "\n";
    }
}

// Verificar directorio de traducciones
$translations_dir = $module_dir . '/translations/es-ES';
if (is_dir($translations_dir)) {
    $dir_perms = fileperms($translations_dir);
    $writable = is_writable($translations_dir) ? '✅' : '❌';
    echo "$writable Directorio translations/es-ES - Permisos: " . substr(sprintf('%o', $dir_perms), -4) . "\n";
} else {
    echo "❌ Directorio translations/es-ES no existe\n";
}

// Crear archivo de prueba para verificar escritura
echo "\n🧪 Probando escritura en directorio de traducciones...\n";

$test_file = $translations_dir . '/test_write.tmp';
if (@file_put_contents($test_file, 'test')) {
    echo "✅ Escritura exitosa\n";
    unlink($test_file);
} else {
    echo "❌ Error de escritura\n";
}

// Mostrar contenido de muestra del archivo XLIFF
echo "\n📄 Muestra del archivo XLIFF principal...\n";

$main_xliff = $module_dir . '/translations/es-ES/ModulesPrestashopyujuAdmin.es-ES.xlf';
if (file_exists($main_xliff)) {
    $content = file_get_contents($main_xliff);
    $lines = explode("\n", $content);
    
    echo "Primeras 10 líneas:\n";
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo "  " . ($i + 1) . ": " . trim($lines[$i]) . "\n";
    }
    
    // Buscar algunas traducciones específicas
    echo "\n🔍 Buscando traducciones específicas...\n";
    
    $search_terms = ['Yuju Integration', 'Configuration', 'Synchronization'];
    foreach ($search_terms as $term) {
        if (strpos($content, $term) !== false) {
            echo "✅ Encontrado: '$term'\n";
        } else {
            echo "❌ No encontrado: '$term'\n";
        }
    }
}

echo "\n🎉 Verificación completada!\n\n";
echo "📝 Próximos pasos recomendados:\n";
echo "1. Reinicia el servidor web (Apache/Nginx)\n";
echo "2. Ve al back office de PrestaShop\n";
echo "3. Navega a Internacional > Traducciones\n";
echo "4. Selecciona 'Modificar traducciones'\n";
echo "5. Elige 'Módulos' y busca 'prestashopyuju'\n";
echo "6. Haz clic en 'Modificar' y luego 'Guardar'\n";
echo "7. Limpia la caché desde Parámetros avanzados > Rendimiento\n";
echo "8. Recarga la página del módulo\n\n";
echo "💡 Si las traducciones siguen sin funcionar:\n";
echo "   - Verifica que PrestaShop sea versión 1.7.6 o superior\n";
echo "   - Asegúrate de que el idioma español esté instalado\n";
echo "   - Revisa los logs de errores de PHP\n";
echo "   - Considera reinstalar el módulo\n";