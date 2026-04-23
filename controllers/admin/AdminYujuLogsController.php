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
        $this->context->smarty->assign('current_controller', 'AdminYujuLogs');
        parent::initContent();

        $logs = $this->getLogs();
        $queue_stats = $this->getQueueStats();

        $this->context->smarty->assign([
            'logs' => $logs,
            'queue_stats' => $queue_stats,
            'module_dir' => $this->module->getPathUri(),
        ]);

        $this->setTemplate('logs.tpl');
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
