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

require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuLogger.php';

class AdminYujuLogsController extends ModuleAdminController
{
    protected $logger;
    protected $bulkSyncLogsError = '';

    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->meta_title = 'Yuju Logs';
        $this->logger = new YujuLogger();
    }

    public function initContent()
    {
        if ($this->module && method_exists($this->module, 'ensureSyncHistoryTable')) {
            $this->module->ensureSyncHistoryTable();
        }
        if ($this->module && method_exists($this->module, 'ensureQueueAndSyncLogsTables')) {
            $this->module->ensureQueueAndSyncLogsTables();
        }

        $this->context->smarty->assign('current_controller', 'AdminYujuLogs');
        parent::initContent();

        $logs = $this->getLogs();
        $queue_stats = $this->getQueueStats();
        $sync_history = $this->getRecentSyncHistory(10);
        $recent_queue_items = $this->getRecentQueueItems(10);
        require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuOfferAuditor.php';
        $bulk_offer_tasks = array_slice(YujuOfferAuditor::getBulkOfferTaskHistory(false), -20);
        $bulk_sync_logs = $this->getRecentBulkSyncLogs(10);

        $this->context->smarty->assign([
            'bulk_offer_tasks' => $bulk_offer_tasks,
            'logs' => $logs,
            'queue_stats' => $queue_stats,
            'sync_history' => $sync_history,
            'bulk_sync_logs' => $bulk_sync_logs,
            'bulk_sync_logs_error' => $this->bulkSyncLogsError,
            'recent_queue_items' => $recent_queue_items,
            'module_dir' => $this->module->getPathUri(),
            'db_prefix' => _DB_PREFIX_,
            'cleanup_ajax_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
            'cleanup_token' => $this->token,
        ]);

        $this->setTemplate('logs.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('ajax') && Tools::getValue('ajax')) {
            $action = (string) Tools::getValue('action');
            switch ($action) {
                case 'processQueueNow':
                    $this->ajaxProcessQueueNow();
                    break;
                case 'refreshBulkOfferTasks':
                    $this->ajaxProcessRefreshBulkOfferTasks();
                    break;
                case 'recoverBulkOfferTask':
                    $this->ajaxProcessRecoverBulkOfferTask();
                    break;
                case 'getCleanupStats':
                    $this->ajaxProcessGetCleanupStats();
                    break;
                case 'purgeCleanupData':
                    $this->ajaxProcessPurgeCleanupData();
                    break;
            }
            exit;
        }

        return parent::postProcess();
    }

    /**
     * Ejecuta manualmente un lote pequeño de la cola para diagnóstico y recuperación.
     * Nunca declara confirmado un ID Yuju si todavía no está guardado en el estado local.
     */
    protected function ajaxProcessQueueNow()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuSyncQueue.php';
            $queue = new YujuSyncQueue();
            $stats = $queue->processBatch(5);
            $items = [];

            foreach ((array) ($stats['items'] ?? []) as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $status = Db::getInstance()->getRow(
                    'SELECT yuju_product_id, sync_status, last_error
                     FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                     WHERE prestashop_product_id = ' . $productId . '
                       AND (prestashop_product_attribute_id IS NULL OR prestashop_product_attribute_id = 0)
                     ORDER BY id ASC'
                );
                $yujuId = trim((string) ($status['yuju_product_id'] ?? ''));
                if ($yujuId === '' || $yujuId === '0' || strtolower($yujuId) === 'null') {
                    $yujuId = null;
                }
                $items[] = [
                    'queue_id' => (int) ($item['queue_id'] ?? 0),
                    'prestashop_product_id' => $productId,
                    'yuju_product_id' => $yujuId,
                    'sync_status' => (string) ($status['sync_status'] ?? 'unknown'),
                    'ok' => !empty($item['ok']),
                    'error' => (string) ($item['error'] ?? ($status['last_error'] ?? '')),
                    'awaiting_id' => !empty($item['ok']) && $yujuId === null
                        && (string) ($status['sync_status'] ?? '') === 'creating_in_yuju',
                ];
            }

            die(json_encode([
                'success' => true,
                'message' => 'Se ejecutó un lote de hasta 5 productos. Un producto solo se considera confirmado cuando aparece su ID de Yuju.',
                'stats' => [
                    'processed' => (int) ($stats['processed'] ?? 0),
                    'success' => (int) ($stats['success'] ?? 0),
                    'failed' => (int) ($stats['failed'] ?? 0),
                    'api_calls' => (int) ($stats['api_calls'] ?? 0),
                    'pending' => (int) (($queue->getQueueStats()['pending'] ?? 0)),
                ],
                'items' => $items,
            ], JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'No se pudo procesar la cola: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    protected function ajaxProcessRefreshBulkOfferTasks()
    {
        header('Content-Type: application/json; charset=utf-8');
        require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuOfferAuditor.php';
        $tasks = YujuOfferAuditor::refreshBulkOfferTaskHistory();
        die(json_encode(['success' => true, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE));
    }

    protected function ajaxProcessRecoverBulkOfferTask()
    {
        header('Content-Type: application/json; charset=utf-8');
        require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuOfferAuditor.php';

        $taskId = trim((string) Tools::getValue('task_id', ''));
        $result = YujuOfferAuditor::recoverBulkOfferTaskById($taskId);
        if (!empty($result['success'])) {
            $result['tasks'] = YujuOfferAuditor::getBulkOfferTaskHistory(false);
        }
        die(json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Tablas de solo histórico / logs (no mapeos, tokens ni configuración operativa).
     *
     * @return array<string, array<string, string>>
     */
    protected function getCleanupTargetsDefinition()
    {
        return [
            'product_sync_history' => [
                'label' => 'Historial de envíos y respuestas (productos)',
                'description' => 'Tabla de auditoría por producto; no borra estados en yuju_product_status.',
                'table' => 'yuju_product_sync_history',
                'date_column' => 'created_at',
                'extra_where_sql' => '',
            ],
            'sync_logs' => [
                'label' => 'Logs de sincronización masiva',
                'description' => 'Sesiones de sync (incremental, manual, etc.).',
                'table' => 'yuju_sync_logs',
                'date_column' => 'start_time',
                'extra_where_sql' => '',
            ],
            'module_logs' => [
                'label' => 'Logs internos (tabla yuju_logs)',
                'description' => 'Mensajes guardados en base de datos por el módulo.',
                'table' => 'yuju_logs',
                'date_column' => 'created_at',
                'extra_where_sql' => '',
            ],
            'webhook_logs' => [
                'label' => 'Historial de webhooks',
                'description' => 'Recepción de eventos Yuju; no afecta registros de webhooks activos.',
                'table' => 'yuju_webhook_logs',
                'date_column' => 'received_at',
                'extra_where_sql' => '',
            ],
            'sync_queue_finished' => [
                'label' => 'Cola: solo trabajos completados o fallidos',
                'description' => 'Elimina entradas antiguas ya terminadas; no borra pendientes ni en proceso.',
                'table' => 'yuju_sync_queue',
                'date_column' => 'created_at',
                'extra_where_sql' => '`status` IN ("completed", "failed")',
            ],
        ];
    }

    protected function cleanupTableExists($tableSuffix)
    {
        $name = _DB_PREFIX_ . $tableSuffix;
        try {
            $r = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($name) . '"');

            return !empty($r);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $tableSuffix
     *
     * @return int bytes (best effort)
     */
    protected function getTableApproximateSizeBytes($tableSuffix)
    {
        $fullName = _DB_PREFIX_ . $tableSuffix;
        try {
            $row = Db::getInstance()->getRow(
                '
                SELECT (DATA_LENGTH + INDEX_LENGTH) AS sz
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = "' . pSQL($fullName) . '"
                '
            );

            return (int) ($row['sz'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    protected function ajaxProcessGetCleanupStats()
    {
        header('Content-Type: application/json; charset=utf-8');

        $days = (int) Tools::getValue('days', 30);
        $days = max(1, min(3650, $days));
        $cutoffTs = strtotime('-' . $days . ' days');
        $cutoff = date('Y-m-d H:i:s', $cutoffTs);

        $targets = [];
        foreach ($this->getCleanupTargetsDefinition() as $id => $def) {
            $exists = $this->cleanupTableExists($def['table']);
            $entry = [
                'id' => $id,
                'label' => $def['label'],
                'description' => isset($def['description']) ? $def['description'] : '',
                'table_full' => _DB_PREFIX_ . $def['table'],
                'exists' => $exists,
                'row_count' => 0,
                'size_bytes' => 0,
                'last_record_at' => null,
                'older_than_cutoff_count' => 0,
                'cutoff' => $cutoff,
                'days' => $days,
            ];

            if (!$exists) {
                $targets[$id] = $entry;
                continue;
            }

            $entry['size_bytes'] = $this->getTableApproximateSizeBytes($def['table']);
            $tbl = '`' . _DB_PREFIX_ . $def['table'] . '`';
            $col = '`' . str_replace('`', '', $def['date_column']) . '`';
            $extra = trim((string) $def['extra_where_sql']);

            $whereScope = $extra !== '' ? '(' . $extra . ')' : '1';
            $countSql = 'SELECT COUNT(*) FROM ' . $tbl . ' WHERE ' . $whereScope;
            $entry['row_count'] = (int) Db::getInstance()->getValue($countSql);

            $maxSql = 'SELECT MAX(' . $col . ') FROM ' . $tbl . ' WHERE ' . $whereScope;
            $entry['last_record_at'] = Db::getInstance()->getValue($maxSql);

            $oldWhere = $whereScope . ' AND ' . $col . ' < "' . pSQL($cutoff) . '"';
            $entry['older_than_cutoff_count'] = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM ' . $tbl . ' WHERE ' . $oldWhere
            );

            $targets[$id] = $entry;
        }

        echo json_encode([
            'success' => true,
            'cutoff' => $cutoff,
            'days' => $days,
            'targets' => array_values($targets),
        ], JSON_UNESCAPED_UNICODE);
    }

    protected function ajaxProcessPurgeCleanupData()
    {
        header('Content-Type: application/json; charset=utf-8');

        $days = (int) Tools::getValue('days', 30);
        $days = max(1, min(3650, $days));
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $selected = Tools::getValue('targets');
        if (!is_array($selected) || empty($selected)) {
            $selected = isset($_POST['targets']) && is_array($_POST['targets']) ? $_POST['targets'] : [];
        }

        if (empty($selected)) {
            echo json_encode([
                'success' => false,
                'errors' => ['Seleccione al menos una tabla.'],
                'deleted' => [],
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $allowed = array_keys($this->getCleanupTargetsDefinition());
        $deleted = [];
        $errors = [];

        foreach ($selected as $key) {
            $key = (string) $key;
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $def = $this->getCleanupTargetsDefinition()[$key];
            if (!$this->cleanupTableExists($def['table'])) {
                $deleted[$key] = 0;
                continue;
            }

            $tbl = '`' . _DB_PREFIX_ . $def['table'] . '`';
            $col = '`' . str_replace('`', '', $def['date_column']) . '`';
            $extra = trim((string) $def['extra_where_sql']);
            $whereScope = $extra !== '' ? '(' . $extra . ')' : '1';

            $countOldSql = 'SELECT COUNT(*) FROM ' . $tbl . ' WHERE ' . $whereScope . ' AND ' . $col . ' < "' . pSQL($cutoff) . '"';
            $nOld = (int) Db::getInstance()->getValue($countOldSql);
            $countAllSql = 'SELECT COUNT(*) FROM ' . $tbl . ' WHERE ' . $whereScope;
            $nAll = (int) Db::getInstance()->getValue($countAllSql);

            // Si no hay registros antiguos por umbral, permitir limpieza completa de la tabla seleccionada.
            $deleteSql = $nOld > 0
                ? ('DELETE FROM ' . $tbl . ' WHERE ' . $whereScope . ' AND ' . $col . ' < "' . pSQL($cutoff) . '"')
                : ('DELETE FROM ' . $tbl . ' WHERE ' . $whereScope);
            $n = $nOld > 0 ? $nOld : $nAll;
            try {
                if (Db::getInstance()->execute($deleteSql)) {
                    $deleted[$key] = $n;
                } else {
                    $errors[] = $key . ': ' . Db::getInstance()->getMsgError();
                    $deleted[$key] = 0;
                }
            } catch (Exception $e) {
                $errors[] = $key . ': ' . $e->getMessage();
                $deleted[$key] = 0;
            }
        }

        $defs = $this->getCleanupTargetsDefinition();
        $lines = [];
        foreach ($deleted as $k => $n) {
            $lab = isset($defs[$k]['label']) ? $defs[$k]['label'] : $k;
            $lines[] = $lab . ': ' . (int) $n . ' fila(s)';
        }

        echo json_encode([
            'success' => empty($errors),
            'deleted' => $deleted,
            'deleted_summary' => $lines,
            'errors' => $errors,
            'cutoff' => $cutoff,
            'days' => $days,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Últimos intentos de sincronización de productos (éxitos, advertencias y errores).
     *
     * @param int $limit
     *
     * @return array
     */
    protected function getRecentSyncHistory($limit = 100)
    {
        try {
            $table_check = Db::getInstance()->executeS(
                'SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_product_sync_history"'
            );
            if (empty($table_check)) {
                return [];
            }

            $limit = max(1, min(500, (int) $limit));

            return Db::getInstance()->executeS(
                '
                SELECT prestashop_product_id, yuju_product_id, action, status,
                       http_status_code, error_message, created_at, created_by
                FROM ' . _DB_PREFIX_ . 'yuju_product_sync_history
                ORDER BY created_at DESC
                LIMIT ' . $limit
            ) ?: [];
        } catch (Exception $e) {
            error_log('[Yuju Logs] No se pudo leer historial de sincronización: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Últimos trabajos de cola, incluidos los recién enviados que aún no han llegado a Yuju.
     *
     * @param int $limit
     *
     * @return array
     */
    protected function getRecentQueueItems($limit = 100)
    {
        try {
            $table = _DB_PREFIX_ . 'yuju_sync_queue';
            $table_check = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($table) . '"');
            if (empty($table_check)) {
                return [];
            }

            $limit = max(1, min(500, (int) $limit));

            return Db::getInstance()->executeS(
                '
                SELECT id, prestashop_product_id, prestashop_product_attribute_id,
                       action, status, attempts, error_message, created_at,
                       last_attempt_at, processed_at
                FROM `' . bqSQL($table) . '`
                ORDER BY created_at DESC, id DESC
                LIMIT ' . $limit
            ) ?: [];
        } catch (Exception $e) {
            error_log('[Yuju Logs] No se pudo leer la cola de sincronización: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Historial de solicitudes masivas iniciadas desde el listado de productos.
     *
     * @param int $limit
     * @return array
     */
    protected function getRecentBulkSyncLogs($limit = 100)
    {
        $this->bulkSyncLogsError = '';
        $table = _DB_PREFIX_ . 'yuju_sync_logs';
        try {
            $db = Db::getInstance();
            $exists = $db->executeS('SHOW TABLES LIKE "' . pSQL($table) . '"');
            if ($exists === false) {
                $dbError = (string) $db->getMsgError();
                $this->bulkSyncLogsError = 'No se pudo comprobar la tabla ' . $table . ': '
                    . ($dbError !== '' ? $dbError : 'error SQL sin detalle');
                error_log('[Yuju Logs] ' . $this->bulkSyncLogsError);
                return [];
            }
            if (empty($exists)) {
                $this->bulkSyncLogsError = 'No existe la tabla ' . $table . '. La inicialización de tablas del módulo no la creó.';
                error_log('[Yuju Logs] ' . $this->bulkSyncLogsError);
                return [];
            }

            $limit = max(1, min(500, (int) $limit));
            $rows = $db->executeS(
                'SELECT id, sync_type, entity_type, sync_direction, status, total_items,
                        processed_items, success_items, error_items, start_time, end_time,
                        error_message, details, created_by
                 FROM ' . bqSQL($table) . '
                 ORDER BY start_time DESC, id DESC
                 LIMIT ' . $limit
            );
            if ($rows === false) {
                $dbError = (string) $db->getMsgError();
                $this->bulkSyncLogsError = 'No se pudo consultar ' . $table . ': '
                    . ($dbError !== '' ? $dbError : 'error SQL sin detalle');
                error_log('[Yuju Logs] ' . $this->bulkSyncLogsError);
                return [];
            }

            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            $this->bulkSyncLogsError = 'Excepción al consultar lotes: ' . $e->getMessage();
            error_log('[Yuju Logs] ' . $this->bulkSyncLogsError);
            return [];
        }
    }


    protected function getLogs()
    {
        $logDir = _PS_MODULE_DIR_ . 'yuju/logs/';
        $logs = [];

        if (!is_dir($logDir)) {
            return [];
        }

        foreach (scandir($logDir) as $file) {
            // El histórico JSON de tareas ya se muestra arriba; no duplicarlo como archivo.
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'log') {
                continue;
            }
            $path = $logDir . $file;
            if (!is_file($path)) {
                continue;
            }
            $logs[] = [
                'filename' => $file,
                'size' => (int) @filesize($path),
                'modified' => (int) @filemtime($path),
            ];
        }

        usort($logs, static function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });

        return array_slice($logs, 0, 5);
    }

    protected function getQueueStats()
    {
        try {
            // Verificar si la tabla existe
            $table_exists = Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_sync_queue"');
            
            if (!$table_exists) {
                // Tabla no existe, retornar estadísticas en cero
                return [
                    'total' => 0,
                    'pending' => 0,
                    'processing' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'queued_products' => 0,
                ];
            }
            
            // Estadísticas de la cola de sincronización
            $queue_stats = Db::getInstance()->getRow('
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
                FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
            ');
            
            // Productos con estado queued
            $queued_products = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) 
                FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                WHERE sync_status = "queued"
            ');
            
            return [
                'total' => (int)($queue_stats['total'] ?? 0),
                'pending' => (int)($queue_stats['pending'] ?? 0),
                'processing' => (int)($queue_stats['processing'] ?? 0),
                'completed' => (int)($queue_stats['completed'] ?? 0),
                'failed' => (int)($queue_stats['failed'] ?? 0),
                'queued_products' => $queued_products,
            ];
        } catch (Exception $e) {
            // En caso de error, retornar estadísticas en cero
            return [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'queued_products' => 0,
            ];
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }
}
