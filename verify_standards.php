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

/**
 * Comentado para permitir ejecución independiente
 * if (!defined('_PS_VERSION_')) {
 *     exit;
 * }
 */
class StandardsVerifier
{
    private $errors = [];
    private $checkedFiles = 0;

    public function verifyAllFiles()
    {
        echo 'Iniciando verificación de estándares de codificación...\n\n';

        $directories = [
            __DIR__ . '/classes',
            __DIR__ . '/controllers',
            __DIR__ . '/cron',
        ];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                echo "Verificando directorio: $dir\n";
                $this->verifyDirectory($dir);
            }
        }

        // Verificar archivo principal
        $mainFile = __DIR__ . '/prestashopyuju.php';

        if (file_exists($mainFile)) {
            echo '\nVerificando archivo principal...\n';
            $this->verifyFile($mainFile);
        }

        $this->showResults();
    }

    private function verifyDirectory($dir)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && $file->getFilename() !== 'index.php') {
                $this->verifyFile($file->getPathname());
            }
        }
    }

    private function verifyFile($filePath)
    {
        ++$this->checkedFiles;
        $content = file_get_contents($filePath);
        $filename = basename($filePath);

        // Verificar regla: no_blank_lines_after_phpdoc
        if (preg_match('/(\*\*[\s\S]*?\*\/)\n\s*\n+\s*(class|function|public|private|protected|static|const|var|\$)/m', $content)) {
            $this->errors[] = "$filename: Líneas en blanco después de docblock";
        }

        // Verificar regla: no_extra_blank_lines (más de 2 líneas consecutivas)
        if (preg_match('/\n\s*\n\s*\n\s*\n/', $content)) {
            $this->errors[] = "$filename: Múltiples líneas en blanco consecutivas";
        }

        // Verificar regla: no_whitespace_in_blank_line
        if (preg_match('/^[ \t]+$/m', $content)) {
            $this->errors[] = "$filename: Espacios en blanco en líneas vacías";
        }

        // Verificar que el archivo termine con una sola línea nueva
        if (!preg_match('/\n$/', $content) || preg_match('/\n\n+$/', $content)) {
            $this->errors[] = "$filename: Archivo no termina correctamente";
        }
    }

    private function showResults()
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "RESULTADOS DE VERIFICACIÓN\n";
        echo str_repeat('=', 50) . "\n";
        echo "Archivos verificados: {$this->checkedFiles}\n";
        echo 'Errores encontrados: ' . count($this->errors) . "\n\n";

        if (empty($this->errors)) {
            echo "✅ ¡Todos los archivos cumplen con los estándares de codificación!\n";
        } else {
            echo "❌ Se encontraron los siguientes errores:\n\n";

            foreach ($this->errors as $error) {
                echo "- $error\n";
            }
        }
        echo str_repeat('=', 50) . "\n";
    }
}

// Ejecutar verificación
$verifier = new StandardsVerifier();
$verifier->verifyAllFiles();
