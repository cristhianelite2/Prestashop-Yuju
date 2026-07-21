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
$exitCode = 0;
$start = microtime(true);
@exec($command, $outputLines, $exitCode);
$durationMs = (int) round((microtime(true) - $start) * 1000);
$output = trim(implode("\n", $outputLines));
if ($output === '') {
    $output = '(sin salida)';
}

$runner->markFinished($jobId, (int) $exitCode, $output, $durationMs);
exit((int) $exitCode);
