<?php
/**
 * Script para limpiar la caché de traducciones de PrestaShop
 * Ejecutar este script después de actualizar las traducciones
 */

// Definir la ruta base del módulo
$moduleDir = __DIR__;
$prestashopRoot = dirname(dirname($moduleDir));

// Buscar archivos de caché de traducciones
$cachePatterns = [
    $prestashopRoot . '/cache/class_index.php',
    $prestashopRoot . '/var/cache/*/translations/*',
    $prestashopRoot . '/cache/smarty/compile/*',
    $prestashopRoot . '/var/cache/*/smarty/*'
];

echo "🧹 Limpiando caché de traducciones...\n";

foreach ($cachePatterns as $pattern) {
    $files = glob($pattern, GLOB_BRACE);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                echo "✅ Eliminado: $file\n";
            } elseif (is_dir($file)) {
                // Eliminar contenido del directorio
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDir()) {
                        rmdir($fileInfo->getRealPath());
                    } else {
                        unlink($fileInfo->getRealPath());
                    }
                }
                echo "✅ Limpiado directorio: $file\n";
            }
        }
    }
}

echo "\n🎉 Caché de traducciones limpiada exitosamente!\n";
echo "\n📝 Instrucciones adicionales:\n";
echo "1. Ve al back office de PrestaShop\n";
echo "2. Navega a Parámetros Avanzados > Rendimiento\n";
echo "3. Haz clic en 'Limpiar caché'\n";
echo "4. Recarga la página del módulo\n";