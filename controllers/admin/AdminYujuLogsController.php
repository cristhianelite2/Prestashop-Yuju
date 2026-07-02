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

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';

class AdminYujuLogsController extends ModuleAdminController
{
    protected $logger;

    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->meta_title = $this->l('Yuju Logs');
        $this->logger = new YujuLogger();
    }

    public function initContent()
    {
        if ($this->module && method_exists($this->module, 'ensureSyncHistoryTable')) {
            $this->module->ensureSyncHistoryTable();
        }

        $this->context->smarty->assign('current_controller', 'AdminYujuLogs');
        parent::initContent();

        $logs = $this->getLogs();
        $queue_stats = $this->getQueueStats();
        $sync_history_errors = $this->getRecentSyncHistoryErrors(40);

        $this->context->smarty->assign([
            'logs' => $logs,
            'queue_stats' => $queue_stats,
            'sync_history_errors' => $sync_history_errors,
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
     * Últimos errores registrados en BD (historial de envíos a Yuju).
     *
     * @param int $limit
     *
     * @return array
     */
    protected function getRecentSyncHistoryErrors($limit = 40)
    {
        try {
            $table_check = Db::getInstance()->executeS(
                'SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_product_sync_history"'
            );
            if (empty($table_check)) {
                return [];
            }

            $limit = (int) $limit;
            if ($limit < 1) {
                $limit = 40;
            }

            return Db::getInstance()->executeS(
                '
                SELECT prestashop_product_id, yuju_product_id, action, http_status_code,
                       error_message, created_at, created_by
                FROM ' . _DB_PREFIX_ . 'yuju_product_sync_history
                WHERE status = "error"
                ORDER BY created_at DESC
                LIMIT ' . $limit
            );
        } catch (Exception $e) {
            return [];
        }
    }

    protected function getLogs()
    {
        $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/';
        $logs = [];

        if (is_dir($log_dir)) {
            $files = scandir($log_dir);

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $logs[] = [
                        'filename' => $file,
                        'size' => filesize($log_dir . $file),
                        'modified' => filemtime($log_dir . $file),
                    ];
                }
            }
        }

        return $logs;
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
