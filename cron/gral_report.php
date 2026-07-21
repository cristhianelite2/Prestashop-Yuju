<?php
/**
 * Cron: products-gral-report (máx. 2/día UTC).
 * - Solicita reporte si está habilitado y corresponde
 * - Encuesta tareas pending/processing y descarga JSONL al histórico
 *
 * Uso: php cron/gral_report.php [--force]
 */

$is_web = (php_sapi_name() !== 'cli');

if (!defined('_PS_VERSION_')) {
    $configPaths = [
        dirname(__FILE__) . '/../../../config/config.inc.php',
        dirname(__FILE__) . '/../../../../config/config.inc.php',
    ];
    $loaded = false;
    foreach ($configPaths as $p) {
        if (file_exists($p)) {
            require_once $p;
            $loaded = true;
            break;
        }
    }
    if (!$loaded) {
        fwrite(STDERR, "No se pudo cargar PrestaShop config.inc.php\n");
        exit(1);
    }
}

require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/../classes/YujuProductGralReport.php';

function yuju_gral_log($msg, $level = 'info', $is_web = false)
{
    $prefix = strtoupper($level);
    if ($is_web) {
        echo '<p>[' . htmlspecialchars($prefix) . '] ' . htmlspecialchars($msg) . '</p>';
    } else {
        echo "[{$prefix}] {$msg}\n";
    }
}

$force = false;
if (php_sapi_name() === 'cli') {
    global $argv;
    $force = in_array('--force', $argv ?? [], true);
} else {
    $force = (bool) Tools::getValue('force');
}

try {
    if (class_exists('Module')) {
        $mod = Module::getInstanceByName('prestashopyuju');
        if ($mod && method_exists($mod, 'ensureProductReportsTable')) {
            $mod->ensureProductReportsTable();
        }
    }

    $service = new YujuProductGralReport();
    $quota = YujuProductGralReport::getQuotaStatus();
    yuju_gral_log(
        'Cupo UTC ' . $quota['utc_day'] . ': ' . $quota['used'] . '/' . $quota['max'] . ' usados',
        'info',
        $is_web
    );

    // 1) Procesar pendientes
    $pendingResults = $service->processPending(5);
    foreach ($pendingResults as $pr) {
        $msg = $pr['message'] ?? json_encode($pr);
        yuju_gral_log($msg, !empty($pr['success']) ? 'success' : 'warning', $is_web);
    }

    // 2) Solicitar si due o force
    $shouldRequest = $force || YujuProductGralReport::isDue();
    if ($shouldRequest) {
        if (!(int) YujuConfig::get('YUJU_GRAL_REPORT_ENABLED', 0) && !$force) {
            yuju_gral_log('Reporte general deshabilitado en configuración.', 'warning', $is_web);
        } else {
            $result = $service->requestReport($force ? 'manual' : 'scheduled', $force);
            yuju_gral_log(
                $result['message'] ?? 'Solicitud enviada',
                !empty($result['success']) ? 'success' : 'error',
                $is_web
            );

            // Poll inmediato corto
            if (!empty($result['success']) && !empty($result['report_id']) && empty($result['already_running'])) {
                sleep(3);
                $poll = $service->pollAndDownload((int) $result['report_id']);
                yuju_gral_log($poll['message'] ?? 'Poll', !empty($poll['success']) ? 'success' : 'info', $is_web);
            }
        }
    } else {
        yuju_gral_log('No corresponde solicitar nuevo reporte ahora.', 'info', $is_web);
    }
} catch (Exception $e) {
    yuju_gral_log($e->getMessage(), 'error', $is_web);
    exit(1);
}
