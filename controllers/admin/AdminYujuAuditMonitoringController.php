<?php
/**
 * Monitoreo de auditorías de ofertas + histórico/visualizador products-gral-report.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/config/config.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuOAuth.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuApiClient.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuOfferAuditor.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuProductGralReport.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuProductOfferReport.php';

class AdminYujuAuditMonitoringController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
        $this->meta_title = $this->l('Monitoreo Auditoría');
    }

    public function initContent()
    {
        if ($this->module && method_exists($this->module, 'ensureAuditTables')) {
            $this->module->ensureAuditTables();
        }
        if ($this->module && method_exists($this->module, 'ensureProductReportsTable')) {
            $this->module->ensureProductReportsTable();
        }
        if ($this->module && method_exists($this->module, 'ensureYujuAuditTabs')) {
            $this->module->ensureYujuAuditTabs();
        }

        parent::initContent();

        $stats = YujuOfferAuditor::getMonitoringStats(40);
        $runsChronological = array_reverse($stats['runs']);
        $gralStats = YujuProductGralReport::getMonitoringStats(40);
        $offerStats = YujuProductOfferReport::getMonitoringStats(40);
        $unifiedHistory = YujuProductOfferReport::getUnifiedHistory(50);

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuAuditMonitoring',
            'module_dir' => $this->module->getPathUri(),
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuAuditMonitoring', true),
            'token' => $this->token,
            'audit_stats' => $stats,
            'audit_runs' => $stats['runs'],
            'audit_runs_chrono' => $runsChronological,
            'audit_totals' => $stats['totals'],
            'audit_diff_types' => $stats['diff_types'],
            'audit_chart_json' => json_encode([
                'labels' => array_map(function ($r) {
                    return '#' . (int) $r['id'];
                }, $runsChronological),
                'matched' => array_map(function ($r) {
                    return (int) $r['matched'];
                }, $runsChronological),
                'diff' => array_map(function ($r) {
                    return (int) $r['diff_found'];
                }, $runsChronological),
                'fixed' => array_map(function ($r) {
                    return (int) $r['fixed'];
                }, $runsChronological),
                'diff_types' => $stats['diff_types'],
            ]),
            'gral_stats' => $gralStats,
            'gral_history' => $unifiedHistory,
            'gral_totals' => [
                'reports_count' => count($unifiedHistory),
                'completed' => (int) ($gralStats['totals']['completed'] ?? 0) + (int) ($offerStats['totals']['completed'] ?? 0),
                'processing' => (int) ($gralStats['totals']['processing'] ?? 0) + (int) ($offerStats['totals']['processing'] ?? 0),
                'failed' => (int) ($gralStats['totals']['failed'] ?? 0) + (int) ($offerStats['totals']['failed'] ?? 0),
            ],
            'gral_quota' => $gralStats['quota'],
            'offer_quota' => $offerStats['quota'],
            'audit_link' => $this->context->link->getAdminLink('AdminYujuAudit'),
            'config_link' => $this->context->link->getAdminLink('AdminYujuConfiguration'),
        ]);

        $this->setTemplate('audit_monitoring.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('ajax') && Tools::getValue('ajax')) {
            $action = (string) Tools::getValue('action');
            switch ($action) {
                case 'getAuditStats':
                    $this->ajaxProcessGetAuditStats();
                    break;
                case 'getAuditRunDetail':
                    $this->ajaxProcessGetAuditRunDetail();
                    break;
                case 'getGralHistory':
                    $this->ajaxProcessGetGralHistory();
                    break;
                case 'pollGralReport':
                    $this->ajaxProcessPollGralReport();
                    break;
                case 'browseGralProducts':
                    $this->ajaxProcessBrowseGralProducts();
                    break;
                case 'getGralProductDetail':
                    $this->ajaxProcessGetGralProductDetail();
                    break;
                case 'getSyncStatus':
                    $this->ajaxDie(json_encode([
                        'success' => true,
                        'ignored' => true,
                        'data' => ['is_syncing' => false, 'progress' => 0, 'status' => 'n/a'],
                    ]));
                    break;
            }
            exit;
        }

        return parent::postProcess();
    }

    public function ajaxProcessGetAuditStats()
    {
        try {
            $limit = (int) Tools::getValue('limit', 40);
            $stats = YujuOfferAuditor::getMonitoringStats($limit);
            $this->ajaxDie(json_encode([
                'success' => true,
                'data' => $stats,
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessGetAuditRunDetail()
    {
        try {
            $runId = (int) Tools::getValue('run_id');
            $resultFilter = Tools::getValue('result', null);
            if ($resultFilter === '') {
                $resultFilter = null;
            }
            $limit = (int) Tools::getValue('limit', 200);
            $offset = (int) Tools::getValue('offset', 0);

            $auditor = new YujuOfferAuditor();
            $run = $auditor->getRun($runId);
            if (!$run) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Auditoría no encontrada',
                ]));
            }

            $details = YujuOfferAuditor::getRunDetails($runId, $resultFilter, $limit, $offset);
            $this->ajaxDie(json_encode([
                'success' => true,
                'run' => $run,
                'details' => $details,
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessGetGralHistory()
    {
        try {
            $gralStats = YujuProductGralReport::getMonitoringStats(50);
            $offerStats = YujuProductOfferReport::getMonitoringStats(50);
            $history = YujuProductOfferReport::getUnifiedHistory(50);
            $this->ajaxDie(json_encode([
                'success' => true,
                'data' => [
                    'history' => $history,
                    'totals' => [
                        'reports_count' => count($history),
                        'completed' => (int) ($gralStats['totals']['completed'] ?? 0)
                            + (int) ($offerStats['totals']['completed'] ?? 0),
                        'processing' => (int) ($gralStats['totals']['processing'] ?? 0)
                            + (int) ($offerStats['totals']['processing'] ?? 0),
                        'failed' => (int) ($gralStats['totals']['failed'] ?? 0)
                            + (int) ($offerStats['totals']['failed'] ?? 0),
                    ],
                    'quota' => $gralStats['quota'],
                    'offer_quota' => $offerStats['quota'],
                ],
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessPollGralReport()
    {
        try {
            $reportId = (int) Tools::getValue('report_id');
            $service = new YujuProductGralReport();
            $result = $service->pollAndDownload($reportId);
            $result['quota'] = YujuProductGralReport::getQuotaStatus();
            $this->ajaxDie(json_encode($result));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessBrowseGralProducts()
    {
        try {
            $reportId = (int) Tools::getValue('report_id');
            $offset = (int) Tools::getValue('offset', 0);
            $limit = (int) Tools::getValue('limit', 50);
            $search = (string) Tools::getValue('search', '');
            $service = new YujuProductGralReport();
            $this->ajaxDie(json_encode($service->browseProducts($reportId, $offset, $limit, $search)));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessGetGralProductDetail()
    {
        try {
            $reportId = (int) Tools::getValue('report_id');
            $line = (int) Tools::getValue('line', 1);
            $service = new YujuProductGralReport();
            $this->ajaxDie(json_encode($service->getProductByLine($reportId, $line)));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }
}
