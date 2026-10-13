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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuSyncManager.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuProductReportReconciler.php';

class AdminYujuSyncController extends ModuleAdminController
{
    protected $sync_manager;

    protected $logger;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'yuju_sync_logs';
        $this->className = 'YujuSyncLog';
        $this->identifier = 'id';
        $this->lang = false;
        $this->addRowAction('view');
        $this->addRowAction('delete');

        $this->sync_manager = new YujuSyncManager();
        $this->logger = new YujuLogger();

        parent::__construct();

        $this->meta_title = $this->trans('Yuju Synchronization', array(), 'Modules.Prestashopyuju.Admin');

        $this->fields_list = [
        'id' => [
        'title' => $this->trans('ID', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'sync_type' => [
        'title' => $this->trans('Type', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'sync_direction' => [
        'title' => $this->trans('Direction', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'entity_type' => [
        'title' => $this->trans('Entity', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'total_items' => [
        'title' => $this->trans('Total', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'success_items' => [
        'title' => $this->trans('Success', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'duration' => [
        'title' => $this->trans('Time (s)', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'status' => [
        'title' => $this->trans('Status', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'error_items' => [
        'title' => $this->trans('Errors', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'start_time' => [
        'title' => $this->trans('Date', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'type' => 'datetime',
        ],
        ];

        
        
        

        $this->_join = '';
        $this->_where = '';
        $this->_group = '';
        $this->_orderBy = 'a.start_time';
        $this->_orderWay = 'DESC';
    }

    /**
     * Render configuration form for the synchronization panel.
     */
    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Yuju Synchronization', array(), 'Modules.Prestashopyuju.Admin'),
                'icon' => 'icon-refresh',
            ],
            'input' => [],
            'submit' => [
                'title' => $this->trans('Save', array(), 'Modules.Prestashopyuju.Admin'),
            ],
        ];

        return parent::renderForm();
    }

    /**
     * Render the list with custom panel.
     */
    public function renderList()
    {
        $this->addRowAction('view');
        $this->addRowAction('delete');

        // Add help for the panel
        $this->toolbar_title = [
            'title' => $this->trans('Yuju Synchronization', array(), 'Modules.Prestashopyuju.Admin'),
            'icon' => 'icon-refresh',
        ];

        $this->toolbar_btn['new'] = [
            'href' => self::$currentIndex . '&configure=' . $this->module->name . '&token=' . $this->token,
            'desc' => $this->trans('Configuration', array(), 'Modules.Prestashopyuju.Admin'),
        ];

        return parent::renderList();
    }

    /**
     * Initialize content (panel template).
     */
    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuSync',
            'is_sync_running' => $this->sync_manager->isSyncRunning(),
            'last_sync_date' => Configuration::get('YUJU_LAST_SYNC_DATE'),
            'auto_sync_enabled' => (bool) Configuration::get('YUJU_AUTO_SYNC'),
            'yuju_module_dir' => _PS_MODULE_DIR_ . 'prestashopyuju/',
            'yuju_module_name' => 'prestashopyuju',
            'yuju_current_index' => self::$currentIndex,
            'yuju_token' => $this->token,
            'yuju_ps_version' => _PS_VERSION_,
        ]);

        // Include the sync panel template
        $this->setTemplate('sync_info.tpl');
    }

    /**
     * Get sync error guidance for user-friendly messages.
     */
    protected function getYujuSyncErrorGuidance($message, $endpoint = '')
    {
        $guidance = [];

        // Check for specific error patterns
        if (stripos($message, 'IncompleteSignatureException') !== false ||
            stripos($message, 'Authorization header requires') !== false ||
            stripos($message, 'Credential') !== false ||
            stripos($message, 'Signature') !== false ||
            stripos($message, 'SignedHeaders') !== false) {
            // AWS SigV4 auth errors - likely using the undocumented GET /products endpoint
            if ($endpoint === 'products' || stripos($message, '/products') !== false) {
                $guidance[] = $this->trans(
                    'El endpoint GET /products no estÃ¡ documentado pÃºblicamente y requiere autenticaciÃ³n AWS SigV4. '
                    . 'El mÃ³dulo ahora usa el reporte general oficial (POST /products-gral-report) en su lugar.',
                    array(),
                    'Modules.Prestashopyuju.Admin'
                );
            } else {
                $guidance[] = $this->trans(
                    'Error de firma de autenticaciÃ³n AWS. Verifica que el token de Yuju sea vÃ¡lido y que la cuenta tenga permisos para el endpoint solicitado.',
                    array(),
                    'Modules.Prestashopyuju.Admin'
                );
            }
        }

        if (stripos($message, 'quota') !== false || stripos($message, 'rate limit') !== false || stripos($message, 'too many') !== false) {
            $guidance[] = $this->trans(
                'Se ha alcanzado el lÃ­mite de solicitudes del reporte general (mÃ¡ximo 2 por dÃ­a UTC). '
                . 'Intenta nuevamente maÃ±ana o reutiliza el reporte anterior si ya estÃ¡ completado.',
                array(),
                'Modules.Prestashopyuju.Admin'
            );
        }

        if (stripos($message, '401') !== false || stripos($message, 'Unauthorized') !== false) {
            $guidance[] = $this->trans(
                'El token de Yuju no es vÃ¡lido o ha expirado. Ve a la pestaÃ±a "Conectividad" y pulsa "Conectar" para reautorizar.',
                array(),
                'Modules.Prestashopyuju.Admin'
            );
        }

        if (stripos($message, '403') !== false || stripos($message, 'Forbidden') !== false) {
            $guidance[] = $this->trans(
                'La API de Yuju rechazÃ³ la solicitud (HTTP 403). Verifica los permisos de la cuenta Yuju o contacta a soporte de Yuju.',
                array(),
                'Modules.Prestashopyuju.Admin'
            );
        }

        if (stripos($message, '404') !== false || stripos($message, 'Not Found') !== false) {
            $guidance[] = $this->trans(
                'El recurso solicitado no existe en la API de Yuju. Es posible que el endpoint haya cambiado.',
                array(),
                'Modules.Prestashopyuju.Admin'
            );
        }

        if (stripos($message, '500') !== false || stripos($message, '502') !== false || stripos($message, '503') !== false || stripos($message, '504') !== false) {
            $guidance[] = $this->trans(
                'Error interno del servidor de Yuju. El problema es temporal; intenta nuevamente en unos minutos.',
                array(),
                'Modules.Prestashopyuju.Admin'
            );
        }

        // Generic fallback
        if (empty($guidance)) {
            $guidance[] = $this->trans(
                'Error inesperado. Revisa los logs del mÃ³dulo (PrestaShop â†’ ParÃ¡metros avanzados â†’ Logs) para mÃ¡s detalles.',
                array(),
                'Modules.Prestashopyuju.Admin'
            );
        }

        return $guidance;
    }

    public function ajaxProcessGetSyncStatus()
    {
        $is_running = $this->sync_manager->isSyncRunning();
        $stats = $this->sync_manager->getSyncStats();

        $response = [
        'is_running' => $is_running,
        'last_sync' => Configuration::get('YUJU_LAST_SYNC_DATE'),
        'stats' => $stats,
        ];

        exit(json_encode($response));
    }

    public function ajaxProcessStartYujuProductReport()
    {
        try {
            $result = $this->sync_manager->startYujuProductReport();
            $this->respondWithJson($result);
        } catch (Exception $e) {
            $this->respondWithJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessDownloadYujuOfferReport()
    {
        // Offer reports may require an API request plus a large file download.
        // Avoid PHP's short default execution limit killing the AJAX response mid-request.
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $this->respondWithJson($this->sync_manager->downloadYujuOfferReport());
        } catch (Exception $e) {
            $this->respondWithJson(['success' => false, 'status' => 'failed', 'message' => 'Error al guardar el reporte de ofertas: ' . $e->getMessage()]);
        }
    }

    public function ajaxProcessPollYujuProductReport()
    {
        $retry_download = (bool) Tools::getValue('retry_download', false);

        try {
            $result = $this->sync_manager->pollYujuProductReport($retry_download);
            $this->respondWithJson($result);
        } catch (Exception $e) {
            $this->respondWithJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Show a mapped payload and its cached Yuju report row for read-only debugging.
     */
    public function ajaxProcessGetYujuProductPayload()
    {
        try {
            $product_id = (int) Tools::getValue('product_id');
            if ($product_id <= 0) {
                throw new InvalidArgumentException('El ID de producto no es válido.');
            }

            $debug = $this->sync_manager->getMappedProductPayloadForDebug($product_id);
            $debug['yuju_report_product'] = null;
            $debug['yuju_report_note'] = 'No se encontró el producto vinculado en el reporte Yuju cacheado.';

            $report_manager = $this->sync_manager->getProductReportManager();
            $state = $report_manager->getStoredState();
            if ($state['status'] === 'COMPLETED' && !empty($state['cache_id']) && !empty($debug['yuju_product_id'])) {
                $report_products = $report_manager->getCachedReportProducts();
                if ($report_products instanceof Traversable || is_array($report_products)) {
                    foreach ($report_products as $report_product) {
                        if (isset($report_product['id_product'])
                            && (string) $report_product['id_product'] === (string) $debug['yuju_product_id']
                        ) {
                            $debug['yuju_report_product'] = $report_product;
                            $debug['yuju_report_note'] = 'Registro original del reporte general cacheado de Yuju, sin transformar.';
                            break;
                        }
                    }
                }
            }

            $this->respondWithJson(['success' => true, 'debug' => $debug]);
        } catch (Exception $e) {
            $this->respondWithJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessGetYujuProductSyncReport()
    {
        try {
            // Get the cached report from the report manager
            $report_manager = $this->sync_manager->getProductReportManager();
            if (!method_exists($report_manager, 'getMergedCachedReportProducts')) {
                throw new Exception('Report manager does not support cached report access.');
            }

            $report_index = null;
            $report_products = null;

            // Recover the existing task's download when its local cache is missing.
            // Check the result explicitly so a failed recovery cannot be reported as ready.
            $state = $report_manager->getStoredState();
            if (!empty($state['task_id']) && in_array($state['status'], ['COMPLETED', 'DOWNLOAD_ERROR', 'DOWNLOAD_EXPIRED'], true)) {
                $recovery_result = $report_manager->pollReport(true);
                $state = $report_manager->getStoredState();

                if (empty($recovery_result['success']) || $state['status'] !== 'COMPLETED' || empty($state['cache_id'])) {
                    $this->respondWithJson([
                        'success' => false,
                        'status' => isset($recovery_result['status']) ? $recovery_result['status'] : $state['status'],
                        'report_cached' => false,
                        'message' => !empty($recovery_result['message'])
                            ? $recovery_result['message']
                            : 'Yuju terminó el informe, pero no se pudo recuperar su archivo local. Reintenta la descarga del informe existente.',
                    ]);
                    return;
                }
            }

            // Read the cached rows and build the index.
            if ($state['status'] === 'COMPLETED' && !empty($state['cache_id'])) {
                // A discovered local report is valid even when its original task ID
                // was lost from Configuration. The private JSONL is the source of truth.
                $report_products = $report_manager->getMergedCachedReportProducts();

                if ($report_products instanceof Traversable) {
                    $report_products = iterator_to_array($report_products);
                }

                // A previous module version could have accepted an empty file as a valid cache.
                // Force a fresh download for the same task instead of returning a misleading cache error.
                if (empty($report_products)
                    && empty($state['local_report_recovered'])
                    && !empty($state['task_id'])
                    && method_exists($report_manager, 'recoverReportDownload')
                ) {
                    $recovery_result = $report_manager->recoverReportDownload();
                    $state = $report_manager->getStoredState();

                    if (!empty($recovery_result['success']) && $state['status'] === 'COMPLETED' && !empty($state['cache_id'])) {
                        $report_products = $report_manager->getMergedCachedReportProducts();
                        if ($report_products instanceof Traversable) {
                            $report_products = iterator_to_array($report_products);
                        }
                    } else {
                        $this->respondWithJson([
                            'success' => false,
                            'status' => isset($recovery_result['status']) ? $recovery_result['status'] : $state['status'],
                            'report_cached' => false,
                            'message' => !empty($recovery_result['message'])
                                ? $recovery_result['message']
                                : 'El informe guardado no contiene productos y no se pudo recuperar la descarga de la tarea existente.',
                        ]);
                        return;
                    }
                }

                if (!empty($report_products)) {
                    $report_index = YujuProductReportReconciler::indexReportRows($report_products);
                }
            }

            if (empty($report_products) || !$report_index || empty($report_index['product_count'])) {
                $this->respondWithJson([
                    'success' => false,
                    'status' => isset($state['status']) ? $state['status'] : 'UNKNOWN',
                    'report_cached' => false,
                    'message' => 'El informe de Yuju se descargó, pero contiene 0 productos válidos. No se puede reconciliar el catálogo de PrestaShop hasta recuperar un archivo con productos.',
                ]);
                return;
            }

            $report = $this->sync_manager->getYujuProductSyncReport($report_index, $report_products);
            $this->respondWithJson(['success' => true, 'report' => $report]);
        } catch (Exception $e) {
            $this->respondWithJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessPushDifferencesToYuju()
    {
        if ($this->sync_manager->isSyncRunning()) {
            $response = [
                'success' => false,
                'message' => $this->trans('A synchronization is already running. Please wait for it to finish.', array(), 'Modules.Prestashopyuju.Admin'),
            ];
            $this->respondWithJson($response);
        }

        $selected_product_ids_raw = Tools::getValue('selected_product_ids', '[]');
        $selected_product_ids = is_array($selected_product_ids_raw)
            ? $selected_product_ids_raw
            : json_decode((string) $selected_product_ids_raw, true);
        if (!is_array($selected_product_ids)) {
            $this->respondWithJson(['success' => false, 'message' => 'La selección de productos no es válida.']);
            return;
        }
        $selected_product_ids = array_values(array_unique(array_filter(array_map('intval', $selected_product_ids), function($id) { return $id > 0; })));
        if (empty($selected_product_ids)) {
            $this->respondWithJson(['success' => false, 'message' => 'Selecciona al menos un producto para actualizar.']);
            return;
        }

        $lock_created = false;
        $response = [];

        try {
            // First get the reconciliation report
            $report_manager = $this->sync_manager->getProductReportManager();
            $state = $report_manager->getStoredState();

            if ($state['status'] === 'COMPLETED' && !empty($state['cache_id'])) {
                $report_products = $report_manager->getMergedCachedReportProducts();

                if ($report_products instanceof Traversable) {
                    $report_products = iterator_to_array($report_products);
                }

                if (!empty($report_products)) {
                    $report_index = YujuProductReportReconciler::indexReportRows($report_products);

                    $reconciliation_report = $this->sync_manager->getYujuProductSyncReport($report_index, $report_products);

                    $this->sync_manager->createSyncLock();
                    $lock_created = true;

                    $results = $this->sync_manager->pushDifferencesToYuju($reconciliation_report, $selected_product_ids, true);

                    // Do not immediately reconcile against the same stale cached report:
                    // that report predates the successful API write and would overwrite
                    // the just-saved "synced" state back to "pending".
                    $updated_reconciliation_report = $reconciliation_report;
                    $successfully_synced_ids = [];
                    $sync_results = isset($results['products']) && is_array($results['products']) ? $results['products'] : [];
                    foreach ($sync_results as $sync_result) {
                        if (isset($sync_result['status'], $sync_result['prestashop_product_id'])
                            && $sync_result['status'] === 'synced'
                        ) {
                            $successfully_synced_ids[(int) $sync_result['prestashop_product_id']] = true;
                        }
                    }
                    foreach (['all_products', 'unsynced_products'] as $list_key) {
                        if (empty($updated_reconciliation_report[$list_key]) || !is_array($updated_reconciliation_report[$list_key])) {
                            continue;
                        }
                        foreach ($updated_reconciliation_report[$list_key] as &$report_row) {
                            $row_id = isset($report_row['prestashop_product_id']) ? (int) $report_row['prestashop_product_id'] : 0;
                            if ($row_id > 0 && isset($successfully_synced_ids[$row_id])) {
                                $report_row['status'] = 'synced';
                                $report_row['sync_status'] = 'synced';
                            }
                        }
                        unset($report_row);
                    }

                    $response = [
                        'success' => true,
                        'message' => sprintf(
                            $this->trans('SincronizaciÃ³n finalizada. Productos actualizados: %d. Errores: %d.', array(), 'Modules.Prestashopyuju.Admin'),
                            isset($results['synced']) ? (int) $results['synced'] : 0,
                            isset($results['failed']) ? (int) $results['failed'] : 0
                        ),
                        'synced_count' => isset($results['synced']) ? (int) $results['synced'] : 0,
                        'errors' => isset($results['products']) ? array_filter($results['products'], function($p) { return $p['status'] === 'error'; }) : [],
                        'details' => isset($results['products']) ? $results['products'] : [],
                        'report' => $updated_reconciliation_report,
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => $this->trans('El reporte cacheado estÃ¡ vacÃ­o.', array(), 'Modules.Prestashopyuju.Admin'),
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => $this->trans('No hay un reporte general completado y cacheado. Inicia un reporte y espera a que termine.', array(), 'Modules.Prestashopyuju.Admin'),
                ];
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($lock_created) {
            $this->sync_manager->removeSyncLock();
        }

        $this->respondWithJson($response);
    }

    public function ajaxProcessInspectYujuProducts()
    {
        try {
            $report = $this->sync_manager->getYujuProductSyncReport();
            $this->respondWithJson(['success' => true, 'report' => $report]);
        } catch (Exception $e) {
            $this->respondWithJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessSyncUnsyncedProducts()
    {
        if ($this->sync_manager->isSyncRunning()) {
            $response = [
                'success' => false,
                'message' => $this->trans('A synchronization is already running. Please wait for it to finish.', array(), 'Modules.Prestashopyuju.Admin'),
            ];
            $this->respondWithJson($response);
        }

        $lock_created = false;
        $response = [];

        try {
            $report = $this->sync_manager->getYujuProductSyncReport();

            if (empty($report['unsynced_products'])) {
                $response = [
                    'success' => true,
                    'message' => $this->trans('All Yuju products are already synchronized.', array(), 'Modules.Prestashopyuju.Admin'),
                    'synced_count' => 0,
                    'errors' => [],
                    'report' => $report,
                ];
            } else {
                $product_ids = array_column($report['unsynced_products'], 'id');
                $this->sync_manager->createSyncLock();
                $lock_created = true;
                $results = $this->sync_manager->syncYujuProductsById($product_ids);

                if (empty($results['success'])) {
                    $response = [
                        'success' => false,
                        'message' => isset($results['error']) ? $results['error'] : $this->trans('Product synchronization failed.', array(), 'Modules.Prestashopyuju.Admin'),
                        'report' => $report,
                    ];
                } else {
                    try {
                        $updated_report = $this->sync_manager->getYujuProductSyncReport();
                    } catch (Exception $e) {
                        $updated_report = $report;
                    }

                    $response = [
                        'success' => true,
                        'message' => sprintf(
                            $this->trans('Synchronization finished. Products imported: %d. Errors: %d.', array(), 'Modules.Prestashopyuju.Admin'),
                            isset($results['synced_count']) ? (int) $results['synced_count'] : 0,
                            isset($results['errors']) ? count($results['errors']) : 0
                        ),
                        'synced_count' => isset($results['synced_count']) ? (int) $results['synced_count'] : 0,
                        'errors' => isset($results['errors']) ? $results['errors'] : [],
                        'report' => $updated_report,
                    ];
                }
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($lock_created) {
            $this->sync_manager->removeSyncLock();
        }

        $this->respondWithJson($response);
    }

    protected function respondWithJson($response)
    {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode($response));
    }

    public function ajaxProcessCleanLogs()
    {
        try {
            $days = (int) Tools::getValue('days', 30);
            $deleted_count = $this->logger->cleanOldLogs($days);

            $response = [
            'success' => true,
            'message' => sprintf($this->trans('Deleted %d old log entries', array(), 'Modules.Prestashopyuju.Admin'), $deleted_count),
            ];
        } catch (Exception $e) {
            $response = [
            'success' => false,
            'message' => $e->getMessage(),
            ];
        }

        exit(json_encode($response));
    }

    public function ajaxProcessExportLogs()
    {
        try {
            $format = Tools::getValue('format', 'csv');
            $date_from = Tools::getValue('date_from');
            $date_to = Tools::getValue('date_to');

            $export_data = $this->logger->exportLogs($format, $date_from, $date_to);

            $response = [
            'success' => true,
            'download_url' => $export_data['url'],
            ];
        } catch (Exception $e) {
            $response = [
            'success' => false,
            'message' => $e->getMessage(),
            ];
        }

        exit(json_encode($response));
    }
}