<?php
/**
 * Worker CLI: ejecuta un cron concreto y actualiza el estado del job (background desde el BO).
 *
 * Uso:
 *   php run_cron_job.php --job=JOB_ID --script=cron.php
 */

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo "CLI only\n";
    exit(1);
}

$jobId = '';
$script = '';
foreach ($argv as $arg) {
    if (strpos($arg, '--job=') === 0) {
        $jobId = substr($arg, 6);
    } elseif (strpos($arg, '--script=') === 0) {
        $script = substr($arg, 9);
    }
}

$jobId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $jobId);
$script = basename((string) $script);

if ($jobId === '' || $script === '') {
    fwrite(STDERR, "Uso: php run_cron_job.php --job=ID --script=archivo.php\n");
    exit(1);
}

require_once dirname(__FILE__) . '/../classes/YujuCronJobRunner.php';

$runner = new YujuCronJobRunner();
if (!$runner->isAllowedScript($script)) {
    $runner->markFinished($jobId, 1, 'Script no permitido: ' . $script, 0);
    exit(1);
}

$runner->markRunning($jobId);

$target = dirname(__FILE__) . '/' . $script;
if (!is_file($target)) {
    $runner->markFinished($jobId, 1, 'Archivo no encontrado: ' . $script, 0);
    exit(1);
}

$phpBin = PHP_BINARY;
if (!$phpBin || stripos((string) $phpBin, 'php-fpm') !== false) {
    $phpBin = 'php';
}

$command = escapeshellarg($phpBin)
    . ' -d date.timezone=America/Bogota '
    . escapeshellarg($target)
    . ' 2>&1';

$outputLines = [];
$exitCode = 1;
$start = microtime(true);

if (function_exists('exec')) {
    @exec($command, $outputLines, $exitCode);
} elseif (function_exists('proc_open')) {
    $pipes = [];
    $process = @proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (is_resource($process)) {
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
        $exitCode = (int) @proc_close($process);
        $combined = trim((string) $stdout . "\n" . (string) $stderr);
        if ($combined !== '') {
            $outputLines = preg_split('/\r?\n/', $combined);
        }
    } else {
        $outputLines[] = 'No se pudo iniciar el proceso PHP: proc_open() falló.';
    }
} elseif (function_exists('popen')) {
    $handle = @popen($command, 'r');
    if (is_resource($handle)) {
        $captured = stream_get_contents($handle);
        $exitCode = (int) @pclose($handle);
        $outputLines = preg_split('/\r?\n/', trim((string) $captured));
    } else {
        $outputLines[] = 'No se pudo iniciar el proceso PHP mediante popen().';
    }
} else {
    $outputLines[] = 'El hosting deshabilitó exec(), proc_open() y popen(); no es posible lanzar el cron desde el modal. Configure el cron desde cPanel o solicite habilitar una función de ejecución de procesos.';
}

$durationMs = (int) round((microtime(true) - $start) * 1000);
$output = trim(implode("\n", $outputLines));
if ($output === '') {
    $output = '(sin salida)';
}

$runner->markFinished($jobId, (int) $exitCode, $output, $durationMs);
exit((int) $exitCode);
