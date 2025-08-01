<?php
/**
 * Script para limpiar caché del nuevo sistema de traducciones de PrestaShop 1.7.6+
 * y regenerar las traducciones del módulo Yuju
 */

echo "🧹 Limpiando caché del nuevo sistema de traducciones...\n\n";

// Definir rutas base
$module_dir = dirname(__FILE__);
$prestashop_root = realpath($module_dir . '/../../../');

echo "📁 Directorio del módulo: $module_dir\n";
echo "📁 Directorio raíz de PrestaShop: $prestashop_root\n\n";

// Directorios de caché a limpiar
$cache_dirs = [
    $prestashop_root . '/cache/dev/',
    $prestashop_root . '/cache/prod/',
    $prestashop_root . '/cache/translations/',
    $prestashop_root . '/var/cache/dev/',
    $prestashop_root . '/var/cache/prod/',
    $prestashop_root . '/var/cache/translations/',
    $prestashop_root . '/app/cache/dev/',
    $prestashop_root . '/app/cache/prod/',
];

// Archivos específicos de traducciones a eliminar
$translation_patterns = [
    $prestashop_root . '/cache/catalogue_*.php',
    $prestashop_root . '/cache/translations_*.php',
    $prestashop_root . '/var/cache/*/translations/*',
    $prestashop_root . '/var/cache/*/catalogue_*',
    $prestashop_root . '/app/cache/*/translations/*',
    $prestashop_root . '/app/cache/*/catalogue_*',
];

// Limpiar directorios de caché
foreach ($cache_dirs as $dir) {
    if (is_dir($dir)) {
        echo "📁 Limpiando directorio: $dir\n";
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                // Eliminar recursivamente
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $path) {
                    $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
                }
                rmdir($file);
            }
        }
    }
}

// Limpiar archivos específicos de traducciones
foreach ($translation_patterns as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                echo "🗑️ Eliminando archivo: $file\n";
                unlink($file);
            }
        }
    }
}

// Limpiar archivos de caché adicionales
echo "\n🔄 Limpiando archivos de caché adicionales...\n";

$additional_cache_patterns = [
    $prestashop_root . '/cache/smarty/cache/*',
    $prestashop_root . '/cache/smarty/compile/*',
    $prestashop_root . '/cache/cachefs/*',
    $prestashop_root . '/img/tmp/*',
];

foreach ($additional_cache_patterns as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                echo "🗑️ Eliminando: $file\n";
                unlink($file);
            }
        }
    }
}

echo "✅ Limpieza de caché completada\n";

echo "\n🎉 Proceso de limpieza completado!\n\n";
echo "📝 Instrucciones adicionales:\n";
echo "1. Ve al back office de PrestaShop\n";
echo "2. Navega a Internacional > Traducciones\n";
echo "3. Selecciona 'Modificar traducciones'\n";
echo "4. Elige 'Módulos' y selecciona 'prestashopyuju'\n";
echo "5. Guarda las traducciones para regenerar la caché\n";
echo "6. Recarga la página del módulo\n\n";
echo "💡 Nota: El nuevo sistema de traducciones usa archivos XLIFF en lugar de PHP\n";
echo "📁 Archivos XLIFF ubicados en: translations/es-ES/\n";