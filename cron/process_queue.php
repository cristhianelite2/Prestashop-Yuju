<?php
/**
 * Worker dedicado de cola Yuju (create/update/delete).
 *
 * Pensado para crontab frecuente (cada 1–5 min) o disparo manual desde el BO.
 * Respeta YUJU_BATCH_SIZE (máx. envíos reales a Yuju por corrida).
 *
 * Crontab ejemplo:
 *   * /5 * * * * php /ruta/modules/prestashopyuju/cron/process_queue.php
 *
 * Opciones CLI:
 *   --loops=N     Repetir hasta N lotes si hay pendientes (default 1)
 *   --max-seconds=S  Tope de tiempo wall-clock (default 50)
 */

if (!defined('_PS_VERSION_')) {
    $candidates = [
        dirname(__FILE__) . '/../../config/config.inc.php',
        dirname(__FILE__) . '/../../../config/config.inc.php',
    ];
    $loaded = false;
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            require_once $candidate;
            $initAlt = dirname($candidate) . '/init.php';
            if (file_exists($initAlt)) {
                require_once $initAlt;
            }
            $loaded = true;
            break;
        }
    }
    if (!$loaded) {
        fwrite(STDERR, "No se pudo cargar PrestaShop\n");
        exit(1);
    }
}

require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../classes/YujuSyncQueue.php';

$isCli = (php_sapi_name() === 'cli');
$logger = new YujuLogger();

$loops = 1;
$maxSeconds = 50;
if (isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--loops=') === 0) {
            $loops = max(1, (int) substr($arg, 8));
        } elseif (strpos($arg, '--max-seconds=') === 0) {
            $maxSeconds = max(5, (int) substr($arg, 14));
        }
    }
}

$batchSize = (int) YujuConfig::get('YUJU_BATCH_SIZE', 100);
if ($batchSize < 1) {
    $batchSize = 100;
}
if ($batchSize > 500) {
    $batchSize = 500;
}

$queue = new YujuSyncQueue();
$started = microtime(true);

$line = function ($msg) use ($isCli) {
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo htmlspecialchars($msg) . "<br>\n";
    }
};

$line('==============================================');
$line('  YUJU - Worker de cola (process_queue.php)');
$line('  ' . date('Y-m-d H:i:s'));
$line('  batch=' . $batchSize . '  loops=' . $loops . '  max_seconds=' . $maxSeconds);
$line('==============================================');

$statsBefore = $queue->getQueueStats();
$line('[COLA] Pendientes al inicio: ' . (int) ($statsBefore['pending'] ?? 0)
    . ' (create=' . (int) ($statsBefore['pending_create'] ?? 0)
    . ', update=' . (int) ($statsBefore['pending_update'] ?? 0)
    . ', delete=' . (int) ($statsBefore['pending_delete'] ?? 0) . ')');

$totalProcessed = 0;
$totalSuccess = 0;
$totalFailed = 0;
$totalApi = 0;
$totalSkip = 0;
$loopsDone = 0;

for ($i = 0; $i < $loops; $i++) {
    if ((microtime(true) - $started) >= $maxSeconds) {
        $line('[COLA] Tope de tiempo alcanzado (' . $maxSeconds . 's).');
        break;
    }

    $pending = (int) ($queue->getQueueStats()['pending'] ?? 0);
    if ($pending <= 0) {
        if ($i === 0) {
            $line('[COLA] No hay productos pendientes.');
        }
        break;
    }

    $loopsDone++;
    $line('');
    $line('[COLA] Lote #' . $loopsDone . ' (hasta ' . $batchSize . ' envíos a Yuju)…');
    $batch = $queue->processBatch($batchSize);

    $totalProcessed += (int) ($batch['processed'] ?? 0);
    $totalSuccess += (int) ($batch['success'] ?? 0);
    $totalFailed += (int) ($batch['failed'] ?? 0);
    $totalApi += (int) ($batch['api_calls'] ?? 0);
    $totalSkip += (int) ($batch['skipped_no_diff'] ?? 0);

    $line('  procesados=' . (int) ($batch['processed'] ?? 0)
        . ' ok=' . (int) ($batch['success'] ?? 0)
        . ' fail=' . (int) ($batch['failed'] ?? 0)
        . ' api=' . (int) ($batch['api_calls'] ?? 0)
        . ' skip=' . (int) ($batch['skipped_no_diff'] ?? 0));

    if (empty($batch['api_calls']) && empty($batch['processed'])) {
        break;
    }
    // Si se alcanzó el cupo y aún hay pendientes, el siguiente loop sigue en otra corrida de crontab
    if (!empty($batch['stopped_at_budget']) && $loops <= 1) {
        $line('  Cupo YUJU_BATCH_SIZE alcanzado; quedan pendientes para la próxima corrida.');
        break;
    }
}

$elapsed = round(microtime(true) - $started, 2);
$statsAfter = $queue->getQueueStats();

$line('');
$line('[COLA] Resumen worker');
$line('  loops=' . $loopsDone);
$line('  procesados=' . $totalProcessed . ' ok=' . $totalSuccess . ' fail=' . $totalFailed);
$line('  api=' . $totalApi . ' skip=' . $totalSkip);
$line('  pendientes restantes=' . (int) ($statsAfter['pending'] ?? 0));
$line('  duración=' . $elapsed . 's');

$logger->info('process_queue.php completado', [
    'loops' => $loopsDone,
    'processed' => $totalProcessed,
    'success' => $totalSuccess,
    'failed' => $totalFailed,
    'api_calls' => $totalApi,
    'skipped_no_diff' => $totalSkip,
    'pending_left' => (int) ($statsAfter['pending'] ?? 0),
    'duration_s' => $elapsed,
]);

exit($totalFailed > 0 && $totalSuccess === 0 ? 1 : 0);
