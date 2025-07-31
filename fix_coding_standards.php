<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class SimpleCodingStandardsFixer
{
    private $processedFiles = 0;

    private $correctedFiles = 0;

    public function fixAllFiles()
    {
        echo "Iniciando corrección de estándares de codificación...\n\n";

        $directories = [
            __DIR__ . '/classes',
            __DIR__ . '/controllers',
            __DIR__ . '/cron',
        ];

        // Archivos específicos adicionales
        $specificFiles = [
            __DIR__ . '/webhook.php',
        ];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                echo "Procesando directorio: $dir\n";
                $this->processDirectory($dir);
            }
        }

        // Procesar archivos específicos
        echo "\nProcesando archivos específicos...\n";
        foreach ($specificFiles as $file) {
            if (file_exists($file)) {
                $this->processFile($file);
            }
        }

        // Procesar archivo principal
        $mainFile = __DIR__ . '/prestashopyuju.php';
        if (file_exists($mainFile)) {
            echo "\nProcesando archivo principal...\n";
            $this->processFile($mainFile);
        }

        echo "\n¡Corrección completada!\n";
        echo "- Archivos procesados: {$this->processedFiles}\n";
        echo "- Archivos corregidos: {$this->correctedFiles}\n";
    }

    private function processDirectory($dir)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $this->processFile($file->getPathname());
            }
        }
    }

    private function processFile($filePath)
    {
        // Excluir verify_standards.php para evitar modificaciones incorrectas
        if (basename($filePath) === 'verify_standards.php') {
            return;
        }

        ++$this->processedFiles;
        $originalContent = file_get_contents($filePath);
        $content = $this->applyRules($originalContent);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            ++$this->correctedFiles;
            echo 'Corregido: ' . basename($filePath) . "\n";
        }
    }

    private function applyRules($content)
    {
        // Solo aplicar correcciones específicas y seguras
        $content = $this->fixLiteralNewlines($content);
        $content = $this->fixTrailingWhitespace($content);
        $content = $this->fixSingleBlankLineAtEof($content);

        return $content;
    }

    /**
     * Corrige caracteres literales \n que causan errores de sintaxis.
     */
    private function fixLiteralNewlines($content)
    {
        // Corregir solo patrones específicos que causan errores de sintaxis
        $patterns = [
            '*/\\nif (!defined(\'_PS_VERSION_\')) {' => "*/\n\nif (!defined('_PS_VERSION_')) {",
            '*/\\nclass' => "*/\n\nclass",
            '*/\\nfunction' => "*/\n\nfunction",
            '*/\\n\\nif' => "*/\n\nif",
        ];

        foreach ($patterns as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    /**
     * Elimina espacios en blanco al final de líneas.
     */
    private function fixTrailingWhitespace($content)
    {
        // Eliminar espacios y tabs al final de líneas
        $content = preg_replace('/[ \t]+$/m', '', $content);

        return $content;
    }

    /**
     * Asegura una sola línea en blanco al final del archivo.
     */
    private function fixSingleBlankLineAtEof($content)
    {
        // Eliminar todas las líneas en blanco al final y agregar una sola
        $content = rtrim($content) . "\n";

        return $content;
    }
}

// Ejecutar el fixer
$fixer = new SimpleCodingStandardsFixer();
$fixer->fixAllFiles();
