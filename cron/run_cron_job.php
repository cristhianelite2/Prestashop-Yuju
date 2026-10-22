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
    . ' -d date.timezone=America/Bogota -d display_errors=1 -d log_errors=0 '
    . escapeshellarg($target)
    . ' 2>&1';

$outputLines = [];
$exitCode = 1;
$start = microtime(true);
$startedAt = date('Y-m-d H:i:s');
$launchMethod = '';

if (function_exists('exec')) {
    $launchMethod = 'exec';
    @exec($command, $outputLines, $exitCode);
} elseif (function_exists('proc_open')) {
    $launchMethod = 'proc_open';
    // Un único pipe para stdout+stderr evita bloqueos al leer dos pipes por separado.
    $combinedCommand = $command;
    $pipes = [];
    $process = @proc_open($combinedCommand, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['redirect', 1],
    ], $pipes);
    if (is_resource($process)) {
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $captured = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        $exitCode = (int) @proc_close($process);
        $outputLines = preg_split('/\r?\n/', trim((string) $captured));
    } else {
        $exitCode = 127;
        $outputLines[] = 'ERROR: no se pudo iniciar el proceso PHP: proc_open() falló.';
    }
} elseif (function_exists('popen')) {
    $launchMethod = 'popen';
    $handle = @popen($command, 'r');
    if (is_resource($handle)) {
        $captured = stream_get_contents($handle);
        $exitCode = (int) @pclose($handle);
        $outputLines = preg_split('/\r?\n/', trim((string) $captured));
    } else {
        $exitCode = 127;
        $outputLines[] = 'ERROR: no se pudo iniciar el proceso PHP mediante popen().';
    }
} else {
    $launchMethod = 'ninguno';
    $exitCode = 127;
    $outputLines[] = 'ERROR: el hosting deshabilitó exec(), proc_open() y popen(); no es posible ejecutar el cron desde el modal. Configure una tarea cron desde cPanel o solicite al hosting habilitar una función de ejecución de procesos.';
}

$durationMs = (int) round((microtime(true) - $start) * 1000);
$scriptOutput = trim(implode("\n", $outputLines));
$summary = [
    '=== DIAGNÓSTICO DE EJECUCIÓN ===',
    'Job: ' . $jobId,
    'Script: ' . $script,
    'Archivo: ' . $target,
    'PHP CLI: ' . $phpBin,
    'Método de ejecución: ' . $launchMethod,
    'Inicio: ' . $startedAt,
    'Fin: ' . date('Y-m-d H:i:s'),
    'Duración: ' . $durationMs . ' ms',
    'Código de salida: ' . (int) $exitCode . ($exitCode === 0 ? ' (correcto)' : ' (ERROR)'),
    '',
    '=== SALIDA ESTÁNDAR / ERRORES DEL SCRIPT ===',
];
if ($scriptOutput === '') {
    $summary[] = 'El script no escribió nada en stdout ni stderr.';
    $summary[] = 'Esto NO demuestra por sí solo que la sincronización haya realizado cambios.';
} else {
    $summary[] = $scriptOutput;
}
$output = implode("\n", $summary);

$runner->markFinished($jobId, (int) $exitCode, $output, $durationMs);
exit((int) $exitCode);
