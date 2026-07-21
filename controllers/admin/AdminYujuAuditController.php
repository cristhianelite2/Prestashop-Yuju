<?php
/**
 * Ejecución visual de auditoría:
 * - Sin imágenes → products-offer-report (stock/precio, cada 12h)
 * - Con imágenes → products-gral-report (general, máx. 2/día UTC)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$yujuAuditModuleBase = _PS_MODULE_DIR_ . 'prestashopyuju/';
$yujuAuditRequired = [
    'config/config.php',
    'classes/YujuLogger.php',
    'classes/YujuOAuth.php',
    'classes/YujuApiClient.php',
    'classes/YujuProductGralReport.php',
    'classes/YujuProductOfferReport.php',
    'classes/YujuOfferAuditor.php',
];
foreach ($yujuAuditRequired as $rel) {
    $path = $yujuAuditModuleBase . $rel;
    if (!is_readable($path)) {
        // Evita fatal opaco: el controller sigue cargando y muestra aviso
        continue;
    }
    require_once $path;
}

class AdminYujuAuditController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Auditoría de Ofertas');
    }

    protected function ensureAuditInfra()
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
    }

    protected function missingAuditClasses()
    {
        $missing = [];
        if (!class_exists('YujuConfig', false) && !class_exists('YujuConfig')) {
            $missing[] = 'YujuConfig';
        }
        if (!class_exists('YujuProductGralReport')) {
            $missing[] = 'YujuProductGralReport.php';
        }
        if (!class_exists('YujuOfferAuditor')) {
            $missing[] = 'YujuOfferAuditor.php';
        }

        return $missing;
    }

    public function initContent()
    {
        $loadError = null;
        $missing = $this->missingAuditClasses();
        if (!empty($missing)) {
            $loadError = 'Faltan clases/archivos en el servidor: ' . implode(', ', $missing)
                . '. Suba esos archivos del módulo prestashopyuju.';
        }

        try {
            $this->ensureAuditInfra();
        } catch (Exception $e) {
            $loadError = ($loadError ? $loadError . ' | ' : '') . $e->getMessage();
        } catch (Throwable $e) {
            $loadError = ($loadError ? $loadError . ' | ' : '') . $e->getMessage();
        }

        parent::initContent();

        $uiEnabled = true;
        try {
            if (class_exists('YujuConfig')) {
                $uiEnabled = YujuConfig::get('YUJU_AUDIT_UI_ENABLED', 1) !== '0'
                    && YujuConfig::get('YUJU_AUDIT_UI_ENABLED', 1) !== 0
                    && YujuConfig::get('YUJU_AUDIT_UI_ENABLED', 1) !== 'false';
            }
        } catch (Exception $e) {
            $loadError = ($loadError ? $loadError . ' | ' : '') . $e->getMessage();
        }

        $ajaxUrl = $this->context->link->getAdminLink('AdminYujuAudit', true);
        $monitoringLink = $this->context->link->getAdminLink('AdminYujuAuditMonitoring', true);

        $completedReports = [];
        $quota = [
            'allowed' => true,
            'used' => 0,
            'max' => 2,
            'remaining' => 2,
            'utc_day' => gmdate('Y-m-d'),
            'message' => null,
        ];
        $offerQuota = [
            'allowed' => true,
            'interval_hours' => 12,
            'seconds_left' => 0,
            'unlock_at' => null,
            'message' => null,
        ];

        try {
            if (class_exists('YujuProductOfferReport')) {
                $completedReports = YujuProductOfferReport::getUnifiedHistory(40);
                if (!is_array($completedReports)) {
                    $completedReports = [];
                }
                $completedReports = array_values(array_filter($completedReports, function ($r) {
                    return is_array($r) && isset($r['status']) && $r['status'] === 'completed';
                }));
                $offerQuota = YujuProductOfferReport::getQuotaStatus();
            } elseif (class_exists('YujuProductGralReport')) {
                $completedReports = YujuProductGralReport::getHistory(30);
                $completedReports = array_values(array_filter($completedReports ?: [], function ($r) {
                    return is_array($r) && isset($r['status']) && $r['status'] === 'completed';
                }));
            }
            if (class_exists('YujuProductGralReport')) {
                $quota = YujuProductGralReport::getQuotaStatus();
            }
        } catch (Exception $e) {
            $loadError = ($loadError ? $loadError . ' | ' : '') . $e->getMessage();
        } catch (Throwable $e) {
            $loadError = ($loadError ? $loadError . ' | ' : '') . $e->getMessage();
        }

        $moduleDir = '';
        if ($this->module && method_exists($this->module, 'getPathUri')) {
            $moduleDir = $this->module->getPathUri();
        }

        $this->context->smarty->assign([
            'link' => $this->context->link,
            'current_controller' => 'AdminYujuAudit',
            'module_dir' => $moduleDir,
            'ajax_url' => $ajaxUrl,
            'token' => $this->token,
            'audit_ui_enabled' => $uiEnabled,
            'audit_load_error' => $loadError,
            'audit_config' => [
                'stock' => class_exists('YujuConfig') ? (int) YujuConfig::get('YUJU_AUDIT_STOCK', 1) : 1,
                'price' => class_exists('YujuConfig') ? (int) YujuConfig::get('YUJU_AUDIT_PRICE', 1) : 1,
                'images' => class_exists('YujuConfig') ? (int) YujuConfig::get('YUJU_AUDIT_IMAGES', 0) : 0,
                'enabled' => class_exists('YujuConfig') ? (int) YujuConfig::get('YUJU_AUDIT_ENABLED', 0) : 0,
                'last_run' => class_exists('YujuConfig') ? YujuConfig::get('YUJU_AUDIT_LAST_RUN_AT', '') : '',
            ],
            'gral_reports' => $completedReports,
            'saved_reports' => $completedReports,
            'gral_quota' => $quota,
            'offer_quota' => $offerQuota,
            'config_link' => $this->context->link->getAdminLink('AdminYujuConfiguration'),
            'monitoring_link' => $monitoringLink,
            'audit_js_config' => json_encode([
                'ajax_url' => $ajaxUrl,
                'token' => (string) $this->token,
                'monitoring_link' => $monitoringLink,
                'gral_quota' => $quota,
                'offer_quota' => $offerQuota,
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS),
        ]);

        $this->setTemplate('audit_run.tpl');
    }

    public function postProcess()
    {
        if (Tools::getValue('ajax')) {
            $this->ensureAuditInfra();
            $action = (string) Tools::getValue('action');
            switch ($action) {
                case 'startAudit':
                    $this->ajaxProcessStartAudit();
                    break;
                case 'requestGralForAudit':
                case 'requestReportForAudit':
                    $this->ajaxProcessRequestReportForAudit();
                    break;
                case 'pollGralForAudit':
                    $this->ajaxProcessPollGralForAudit();
                    break;
                case 'reanalyzeReport':
                    $this->ajaxProcessReanalyzeReport();
                    break;
                case 'auditChunk':
                    $this->ajaxProcessAuditChunk();
                    break;
                case 'pauseAudit':
                    $this->ajaxProcessPauseAudit();
                    break;
                case 'resumeAudit':
                    $this->ajaxProcessResumeAudit();
                    break;
                case 'exportAuditCsv':
                    $this->ajaxProcessExportAuditCsv();
                    break;
                case 'getAuditRows':
                    $this->ajaxProcessGetAuditRows();
                    break;
                case 'getProgress':
                    $this->ajaxProcessGetProgress();
                    break;
                case 'getSyncStatus':
                    $this->ajaxDie(json_encode([
                        'success' => true,
                        'ignored' => true,
                        'data' => [
                            'is_syncing' => false,
                            'progress' => 0,
                            'status' => 'n/a',
                        ],
                    ]));
                    break;
                default:
                    $this->ajaxDie(json_encode([
                        'success' => false,
                        'message' => 'Acción AJAX no reconocida: ' . $action,
                    ]));
            }
            exit;
        }

        return parent::postProcess();
    }

    protected function getAuditFieldsFromRequest()
    {
        return [
            'stock' => (int) Tools::getValue('audit_stock', YujuConfig::get('YUJU_AUDIT_STOCK', 1)),
            'price' => (int) Tools::getValue('audit_price', YujuConfig::get('YUJU_AUDIT_PRICE', 1)),
            'images' => (int) Tools::getValue('audit_images', YujuConfig::get('YUJU_AUDIT_IMAGES', 0)),
        ];
    }

    protected function getApplyFixesFromRequest()
    {
        // Por defecto NO corrige (reanalizar JSON local). apply_fixes=1 → empujar a Yuju
        $v = Tools::getValue('apply_fixes', 0);

        return (int) $v === 1;
    }

    public function ajaxProcessStartAudit()
    {
        // Compat: si mandan report_id, reanaliza; si no, pide flujo nuevo
        $reportId = (int) Tools::getValue('report_id', 0);
        if ($reportId > 0) {
            $this->ajaxProcessReanalyzeReport();

            return;
        }
        $this->ajaxProcessRequestGralForAudit();
    }

    /**
     * Compat: alias del selector de reporte según campos.
     */
    public function ajaxProcessRequestGralForAudit()
    {
        $this->ajaxProcessRequestReportForAudit();
    }

    /**
     * Solicita el reporte correcto según campos:
     * - images=1 → products-gral-report (async, 2/día UTC)
     * - images=0 → products-offer-report (sync, cada 12h)
     */
    public function ajaxProcessRequestReportForAudit()
    {
        try {
            if (YujuConfig::get('YUJU_AUDIT_UI_ENABLED', 1) === '0') {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'La interfaz gráfica de auditoría está deshabilitada en Configuración.',
                ]));
            }

            $fields = $this->getAuditFieldsFromRequest();
            if (empty($fields['stock']) && empty($fields['price']) && empty($fields['images'])) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Seleccione al menos stock, precio o imágenes.',
                ]));
            }

            // Con imágenes → reporte general; sin imágenes → solo ofertas
            if (!empty($fields['images'])) {
                $this->ajaxDie(json_encode($this->requestGralAndMaybeStartCompare($fields)));
            }

            $this->ajaxDie(json_encode($this->requestOfferAndStartCompare($fields)));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    protected function requestOfferAndStartCompare(array $fields)
    {
        $service = new YujuProductOfferReport();
        $result = $service->requestAndDownload('manual', false);
        if (empty($result['success'])) {
            $result['report_type'] = 'offer';
            $result['offer_quota'] = YujuProductOfferReport::getQuotaStatus();

            return $result;
        }

        $auditor = new YujuOfferAuditor();
        $started = $auditor->startRunFromSavedReport(
            (int) $result['report_id'],
            'manual',
            'visual',
            $fields,
            [
                'apply_fixes' => $this->getApplyFixesFromRequest(),
                'reanalyze' => 0,
            ]
        );
        $started['phase'] = 'compare';
        $started['report_type'] = 'offer';
        $started['id_task'] = $result['id_task'] ?? ($started['id_task'] ?? null);
        $started['report_id'] = (int) $result['report_id'];
        $started['reused'] = !empty($result['reused']);
        $started['offer_quota'] = YujuProductOfferReport::getQuotaStatus();
        $started['message'] = $result['message'] ?? null;
        $started['progress'] = $result['progress'] ?? null;
        $started['got_url'] = !empty($result['got_url']);
        $started['json_downloaded'] = !empty($result['json_downloaded']);

        return $started;
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    protected function requestGralAndMaybeStartCompare(array $fields)
    {
        $service = new YujuProductGralReport();
        $result = $service->requestReport('manual', false);
        if (empty($result['success'])) {
            $result['report_type'] = 'gral';
            $result['quota'] = YujuProductGralReport::getQuotaStatus();

            return $result;
        }

        if (!empty($result['report_id'])) {
            sleep(2);
            $poll = $service->pollAndDownload((int) $result['report_id']);
            if (!empty($poll['finished']) && !empty($poll['success']) && !empty($poll['report'])) {
                $auditor = new YujuOfferAuditor();
                $started = $auditor->startRunFromSavedReport(
                    (int) $result['report_id'],
                    'manual',
                    'visual',
                    $fields,
                    [
                        'apply_fixes' => $this->getApplyFixesFromRequest(),
                        'reanalyze' => 0,
                    ]
                );
                $started['phase'] = 'compare';
                $started['report_type'] = 'gral';
                $started['id_task'] = $result['id_task'] ?? ($started['id_task'] ?? null);
                $started['report_id'] = (int) $result['report_id'];
                $started['quota'] = YujuProductGralReport::getQuotaStatus();

                return $started;
            }
        }

        return [
            'success' => true,
            'phase' => 'waiting',
            'report_type' => 'gral',
            'message' => $result['message'] ?? 'Reporte general solicitado. Esperando generación…',
            'report_id' => (int) ($result['report_id'] ?? 0),
            'id_task' => $result['id_task'] ?? null,
            'quota' => YujuProductGralReport::getQuotaStatus(),
            'already_running' => !empty($result['already_running']),
        ];
    }

    /**
     * GET products-gral-report/{id_task} → cuando COMPLETED inicia comparación.
     */
    public function ajaxProcessPollGralForAudit()
    {
        try {
            $reportId = (int) Tools::getValue('report_id');
            if ($reportId <= 0) {
                $this->ajaxDie(json_encode(['success' => false, 'message' => 'report_id requerido']));
            }

            $service = new YujuProductGralReport();
            $poll = $service->pollAndDownload($reportId);

            if (empty($poll['finished']) || empty($poll['success'])) {
                $report = $service->getReport($reportId);
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'phase' => 'waiting',
                    'message' => $poll['message'] ?? 'Aún generando el JSONL…',
                    'report_id' => $reportId,
                    'id_task' => $report['id_task'] ?? null,
                    'api_status' => $poll['status'] ?? ($report['status'] ?? 'PROCESSING'),
                    'report' => $report,
                ]));
            }

            $fields = $this->getAuditFieldsFromRequest();
            $auditor = new YujuOfferAuditor();
            $started = $auditor->startRunFromGralReport(
                $reportId,
                'manual',
                'visual',
                $fields,
                [
                    'apply_fixes' => $this->getApplyFixesFromRequest(),
                    'reanalyze' => 0,
                ]
            );
            $started['phase'] = 'compare';
            $this->ajaxDie(json_encode($started));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    /**
     * Reanaliza un JSONL ya guardado vs PrestaShop actual (sin nueva API).
     */
    public function ajaxProcessReanalyzeReport()
    {
        try {
            if (YujuConfig::get('YUJU_AUDIT_UI_ENABLED', 1) === '0') {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'La interfaz gráfica de auditoría está deshabilitada en Configuración.',
                ]));
            }

            $reportId = (int) Tools::getValue('report_id');
            if ($reportId <= 0) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Seleccione un reporte completado para reanalizar.',
                ]));
            }

            $fields = $this->getAuditFieldsFromRequest();
            $auditor = new YujuOfferAuditor();
            $started = $auditor->startRunFromGralReport(
                $reportId,
                'manual',
                'visual',
                $fields,
                [
                    'apply_fixes' => $this->getApplyFixesFromRequest(),
                    'reanalyze' => 1,
                ]
            );
            $started['phase'] = 'compare';
            $this->ajaxDie(json_encode($started));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function ajaxProcessAuditChunk()
    {
        try {
            $this->ensureAuditInfra();
            $runId = (int) Tools::getValue('run_id');
            $limit = (int) Tools::getValue('limit', YujuConfig::get('YUJU_AUDIT_CHUNK_SIZE', 75));
            $auditor = new YujuOfferAuditor();
            $result = $auditor->processChunk($runId, $limit);
            $this->ajaxDie(json_encode($result));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function ajaxProcessPauseAudit()
    {
        try {
            $auditor = new YujuOfferAuditor();
            $this->ajaxDie(json_encode($auditor->pauseRun((int) Tools::getValue('run_id'))));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function ajaxProcessResumeAudit()
    {
        try {
            $auditor = new YujuOfferAuditor();
            $this->ajaxDie(json_encode($auditor->resumeRun((int) Tools::getValue('run_id'))));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function ajaxProcessExportAuditCsv()
    {
        try {
            $auditor = new YujuOfferAuditor();
            $result = $auditor->exportRunCsv(
                (int) Tools::getValue('run_id'),
                Tools::getValue('result', null),
                (string) Tools::getValue('search', '')
            );
            if (empty($result['success'])) {
                $this->ajaxDie(json_encode($result));
            }
            // Descarga directa
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . ($result['filename'] ?? 'auditoria.csv') . '"');
            echo $result['csv'];
            exit;
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function ajaxProcessGetAuditRows()
    {
        try {
            $runId = (int) Tools::getValue('run_id');
            $result = Tools::getValue('result', '');
            if ($result === '' || $result === 'all' || $result === 'total') {
                $result = null;
            }
            $search = (string) Tools::getValue('search', '');
            $limit = (int) Tools::getValue('limit', 500);
            $offset = (int) Tools::getValue('offset', 0);

            $rows = YujuOfferAuditor::getRunDetails($runId, $result, $limit, $offset, $search);
            $ui = [];
            $auditor = new YujuOfferAuditor();
            foreach ($rows as $row) {
                $diffs = [];
                $errorDetail = null;
                if (!empty($row['diffs_json'])) {
                    $decoded = json_decode($row['diffs_json'], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $d) {
                            if (isset($d['_error'])) {
                                $errorDetail = $d['_error'];
                                continue;
                            }
                            $diffs[] = $d;
                        }
                    }
                }
                $ui[] = [
                    'sku' => $row['sku'],
                    'sku_simple' => null,
                    'parent_id' => null,
                    'product_name' => $row['product_name'] ?? '',
                    'prestashop_product_id' => $row['prestashop_product_id'],
                    'yuju_product_id' => $row['yuju_product_id'],
                    'group_key' => !empty($row['prestashop_product_id'])
                        ? ('ps:' . (int) $row['prestashop_product_id'])
                        : ('sku:' . (string) ($row['sku'] ?: ('id' . ($row['id'] ?? '')))),
                    'is_variation' => false,
                    'result' => $row['result'],
                    'message' => $row['message'],
                    'ps_stock' => $row['ps_stock'],
                    'yuju_stock' => $row['yuju_stock'],
                    'ps_price' => $row['ps_price'],
                    'yuju_price' => $row['yuju_price'],
                    'ps_images_count' => $row['ps_images_count'],
                    'yuju_images_count' => $row['yuju_images_count'],
                    'diffs' => $diffs,
                    'error_detail' => $errorDetail,
                ];
            }

            $this->ajaxDie(json_encode([
                'success' => true,
                'rows' => $ui,
                'progress' => $auditor->getProgress($runId),
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function ajaxProcessGetProgress()
    {
        try {
            $this->ensureAuditInfra();
            $runId = (int) Tools::getValue('run_id');
            $auditor = new YujuOfferAuditor();
            $this->ajaxDie(json_encode([
                'success' => true,
                'progress' => $auditor->getProgress($runId),
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        } catch (Throwable $e) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
}
