<?php
/**
 * Script para generar un ZIP del módulo PrestaShop Yuju
 * Excluye archivos de desarrollo y solo incluye lo necesario para la instalación
 */

// Configuración
$moduleDir = __DIR__;
$zipFileName = 'prestashopyuju_module.zip';
$zipPath = dirname($moduleDir) . DIRECTORY_SEPARATOR . $zipFileName;

// Archivos y directorios a excluir
$excludePatterns = [
    'vendor/',
    'vendor\\',
    'node_modules/',
    'node_modules\\',
    '.git/',
    '.git\\',
    '.gitignore',
    'php-cs-fixer-v3.phar',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    '.php-cs-fixer.php',
    'fix_coding_standards.php',
    'verify_standards.php',
    'create_module_zip.php', // Este mismo script
    'CODE_FORMATTING.md',
    'GITHUB_SETUP.md',
    'ESTRUCTURA_MODULO.md',
    'INSTALACION.md',
    'CHANGELOG.md',
    'README.md',
    '.htaccess',
    'logs/',
    'logs\\',
    'cache/',
    'cache\\',
    '.vscode/',
    '.vscode\\',
    '.idea/',
    '.idea\\',
    '*.log',
    '*.tmp',
    'Thumbs.db',
    '.DS_Store'
];

/**
 * Verifica si un archivo debe ser excluido
 */
function shouldExclude($filePath, $excludePatterns) {
    $normalizedPath = str_replace('\\', '/', $filePath);
    
    foreach ($excludePatterns as $pattern) {
        $normalizedPattern = str_replace('\\', '/', $pattern);
        
        // Verificar patrones con wildcards
        if (strpos($pattern, '*') !== false) {
            if (fnmatch($normalizedPattern, $normalizedPath)) {
                return true;
            }
        }
        // Verificar directorios
        elseif (substr($normalizedPattern, -1) === '/') {
            if (strpos($normalizedPath, $normalizedPattern) === 0) {
                return true;
            }
        }
        // Verificar archivos exactos
        else {
            if (basename($normalizedPath) === $normalizedPattern || $normalizedPath === $normalizedPattern) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Agrega archivos al ZIP recursivamente dentro de la carpeta prestashopyuju
 */
function addFilesToZip($zip, $dir, $basePath, $excludePatterns) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($basePath) + 1);
            
            // Verificar si el archivo debe ser excluido
            if (!shouldExclude($relativePath, $excludePatterns)) {
                // Normalizar la ruta para el ZIP y agregar carpeta prestashopyuju
                $zipPath = 'prestashopyuju/' . str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $zipPath);
                echo "Agregado: $zipPath\n";
            } else {
                echo "Excluido: $relativePath\n";
            }
        }
    }
}

try {
    // Eliminar ZIP anterior si existe
    if (file_exists($zipPath)) {
        unlink($zipPath);
        echo "ZIP anterior eliminado.\n";
    }
    
    // Crear nuevo ZIP
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        throw new Exception("No se pudo crear el archivo ZIP. Código de error: $result");
    }
    
    echo "Creando ZIP del módulo PrestaShop Yuju...\n";
    echo "Directorio base: $moduleDir\n";
    echo "Archivo ZIP: $zipPath\n\n";
    
    // Agregar archivos al ZIP
    addFilesToZip($zip, $moduleDir, $moduleDir, $excludePatterns);
    
    // Cerrar el ZIP
    $zip->close();
    
    // Verificar que el ZIP se creó correctamente
    if (file_exists($zipPath)) {
        $fileSize = filesize($zipPath);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        
        echo "\n✅ ZIP creado exitosamente!\n";
        echo "📁 Archivo: $zipFileName\n";
        echo "📊 Tamaño: $fileSizeMB MB\n";
        echo "📍 Ubicación: $zipPath\n\n";
        
        echo "🚀 El módulo está listo para instalar en PrestaShop.\n";
        echo "💡 Sube el archivo $zipFileName al panel de administración de PrestaShop.\n";
    } else {
        throw new Exception("El archivo ZIP no se creó correctamente.");
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Proceso completado exitosamente!\n";
?>