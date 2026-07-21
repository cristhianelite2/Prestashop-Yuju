<?php
/**
 * Cron silencioso de auditoría de ofertas (PS → Yuju únicamente).
 */

if (!defined('_PS_VERSION_')) {
    $root_path = dirname(__FILE__);
    $candidates = [
        $root_path . '/../../config/config.inc.php',
        $root_path . '/../../../config/config.inc.php',
    ];
    $loaded = false;
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            require_once $candidate;
            $init = dirname($candidate) . '/../init.php';
            // config.inc.php is already in /config; init.php is sibling of config
            $initAlt = dirname($candidate) . '/init.php';
            if (file_exists($initAlt)) {
                require_once $initAlt;
            } elseif (file_exists($init)) {
                require_once $init;
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

require_once dirname(__FILE__) . '/../autoload.php';
require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/../classes/YujuApiClient.php';
require_once dirname(__FILE__) . '/../classes/YujuProductGralReport.php';
require_once dirname(__FILE__) . '/../classes/YujuProductOfferReport.php';
require_once dirname(__FILE__) . '/../classes/YujuOfferAuditor.php';

$is_web = (php_sapi_name() !== 'cli');
$logger = new YujuLogger();

function yuju_audit_log($message, $type = 'info', $is_web = false)
{
    if ($is_web) {
        echo '<p>' . htmlspecialchars('[' . strtoupper($type) . '] ' . $message) . '</p>';
    } else {
        echo '[' . strtoupper($type) . '] ' . $message . "\n";
    }
}

try {
    if (class_exists('Module')) {
        $module = Module::getInstanceByName('prestashopyuju');
        if ($module && method_exists($module, 'ensureAuditTables')) {
            $module->ensureAuditTables();
        }
        if ($module && method_exists($module, 'ensureProductReportsTable')) {
            $module->ensureProductReportsTable();
        }
    }

    $force = false;
    if (class_exists('Tools') && Tools::getValue('force')) {
        $force = true;
    }
    if (isset($argv) && is_array($argv) && in_array('--force', $argv, true)) {
        $force = true;
    }

    $auditor = new YujuOfferAuditor();

    // Reanudar run programado incompleto
    $runningId = (int) Db::getInstance()->getValue(
        'SELECT `id` FROM `' . _DB_PREFIX_ . 'yuju_audit_runs`
         WHERE `status` = \'running\' AND `trigger` = \'scheduled\'
         ORDER BY `id` ASC LIMIT 1'
    );

    if ($runningId > 0) {
        yuju_audit_log('Reanudando auditoría programada run #' . $runningId, 'info', $is_web);
        $result = $auditor->runUntilComplete($runningId, 300);
        $progress = $auditor->getProgress($runningId);
        if (($progress['status'] ?? '') === 'completed') {
            YujuOfferAuditor::markScheduledExecuted();
            yuju_audit_log(
                sprintf(
                    'Completada (reanudada). matched=%d diff=%d fixed=%d errors=%d not_found=%d',
                    $progress['matched'],
                    $progress['diff_found'],
                    $progress['fixed'],
                    $progress['errors'],
                    $progress['not_found']
                ),
                'success',
                $is_web
            );
        } else {
            yuju_audit_log(
                'Parcial: ' . $progress['processed'] . '/' . $progress['total'],
                'warning',
                $is_web
            );
        }
        $logger->info('audit_offers.php reanudación', $progress);
        return;
    }

    if (!$force && !YujuOfferAuditor::isDue()) {
        yuju_audit_log('Auditoría no programada para este momento (isDue=false). Use --force para forzar.', 'info', $is_web);
        return;
    }

    if (!(int) YujuConfig::get('YUJU_AUDIT_ENABLED', 0) && !$force) {
        yuju_audit_log('Auditoría programada deshabilitada en configuración.', 'warning', $is_web);
        return;
    }

    $auditImages = (int) YujuConfig::get('YUJU_AUDIT_IMAGES', 0);
    $reportType = $auditImages ? 'gral' : 'offer';
    yuju_audit_log(
        'Iniciando auditoría desde products-' . ($auditImages ? 'gral' : 'offer') . '-report...',
        'info',
        $is_web
    );

    // Preferir reporte completado reciente del tipo adecuado
    $reportId = (int) Db::getInstance()->getValue(
        'SELECT id FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
         WHERE report_type = \'' . pSQL($reportType) . '\' AND status = \'completed\'
         ORDER BY id DESC LIMIT 1'
    );

    if ($reportId <= 0 || $force) {
        if ($auditImages) {
            $service = new YujuProductGralReport();
            $service->processPending(3);
            $req = $service->requestReport('scheduled', $force);
            if (empty($req['success'])) {
                if ($reportId > 0) {
                    yuju_audit_log('No se pudo solicitar nuevo gral; usando histórico #' . $reportId . ' — ' . ($req['message'] ?? ''), 'warning', $is_web);
                } else {
                    throw new Exception($req['message'] ?? 'Sin products-gral-report disponible');
                }
            } else {
                $reportId = (int) ($req['report_id'] ?? 0);
                yuju_audit_log('Tarea gral id_task=' . ($req['id_task'] ?? '?') . ' report_id=' . $reportId, 'info', $is_web);
                $deadline = time() + 120;
                while (time() < $deadline) {
                    $poll = $service->pollAndDownload($reportId);
                    if (!empty($poll['finished']) && !empty($poll['success'])) {
                        break;
                    }
                    if (!empty($poll['finished']) && empty($poll['success'])) {
                        throw new Exception($poll['message'] ?? 'Fallo al descargar gral-report');
                    }
                    sleep(5);
                }
                $rep = $service->getReport($reportId);
                if (!$rep || $rep['status'] !== 'completed') {
                    throw new Exception('El products-gral-report no terminó a tiempo (report_id=' . $reportId . ').');
                }
            }
        } else {
            $offerService = new YujuProductOfferReport();
            $req = $offerService->requestAndDownload('scheduled', $force);
            if (empty($req['success'])) {
                if ($reportId > 0) {
                    yuju_audit_log('No se pudo solicitar offer; usando histórico #' . $reportId . ' — ' . ($req['message'] ?? ''), 'warning', $is_web);
                } else {
                    throw new Exception($req['message'] ?? 'Sin products-offer-report disponible');
                }
            } else {
                $reportId = (int) ($req['report_id'] ?? 0);
                yuju_audit_log('Offer report_id=' . $reportId . (empty($req['reused']) ? '' : ' (reutilizado)'), 'info', $is_web);
            }
        }
    }

    $start = $auditor->startRunFromSavedReport($reportId, 'scheduled', 'silent', null, [
        'apply_fixes' => true,
        'reanalyze' => 0,
    ]);
    if (empty($start['success'])) {
        throw new Exception($start['message'] ?? 'Error al iniciar auditoría');
    }

    $runId = (int) $start['run_id'];
    yuju_audit_log('Run #' . $runId . ' desde reporte #' . $reportId . ' — total: ' . (int) $start['total'], 'info', $is_web);

    $result = $auditor->runUntilComplete($runId, 300);
    $progress = $auditor->getProgress($runId);

    if (!empty($result['finished']) || ($progress['status'] ?? '') === 'completed') {
        YujuOfferAuditor::markScheduledExecuted();
        yuju_audit_log(
            sprintf(
                'Completada. matched=%d diff=%d fixed=%d errors=%d not_found=%d',
                $progress['matched'],
                $progress['diff_found'],
                $progress['fixed'],
                $progress['errors'],
                $progress['not_found']
            ),
            'success',
            $is_web
        );
    } else {
        yuju_audit_log(
            'Auditoría parcialmente procesada (límite de tiempo). Procesados: '
            . $progress['processed'] . '/' . $progress['total'],
            'warning',
            $is_web
        );
    }

    $logger->info('audit_offers.php finalizado', $progress);
} catch (Exception $e) {
    yuju_audit_log('Error: ' . $e->getMessage(), 'error', $is_web);
    $logger->error('audit_offers.php: ' . $e->getMessage());
}
