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
require_once _PS_MODULE_DIR_ . 'prestashopyuju/config/config.php';

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

        $this->bulk_actions = [
        'delete' => [
        'text' => $this->trans('Delete selected', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'icon-trash',
        'confirm' => $this->trans('Delete selected items?', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        ];
    }

    public function initContent()
    {
        $this->context->smarty->assign('current_controller', 'AdminYujuSync');
        parent::initContent();
    }

    public function renderList()
    {
        // Add toolbar buttons
        $this->toolbar_btn['sync_full'] = [
        'href' => self::$currentIndex . '&action=syncFull&token=' . $this->token,
        'desc' => $this->trans('Full Sync', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'process-icon-refresh',
        ];

        $this->toolbar_btn['sync_incremental'] = [
        'href' => self::$currentIndex . '&action=syncIncremental&token=' . $this->token,
        'desc' => $this->trans('Incremental Sync', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'process-icon-update',
        ];

        $this->toolbar_btn['sync_categories'] = [
        'href' => self::$currentIndex . '&action=syncCategories&token=' . $this->token,
        'desc' => $this->trans('Sync Categories', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'process-icon-category',
        ];

        $this->toolbar_btn['sync_products'] = [
        'href' => self::$currentIndex . '&action=syncProducts&token=' . $this->token,
        'desc' => $this->trans('Sync Products', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'process-icon-product',
        ];

        $this->toolbar_btn['sync_stock'] = [
        'href' => self::$currentIndex . '&action=syncStock&token=' . $this->token,
        'desc' => $this->trans('Sync Stock', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'process-icon-quantity',
        ];

        $this->toolbar_btn['sync_prices'] = [
        'href' => self::$currentIndex . '&action=syncPrices&token=' . $this->token,
        'desc' => $this->trans('Sync Prices', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'process-icon-dollar',
        ];

        // Add sync status information
        $sync_stats = $this->sync_manager->getSyncStats();
        $is_sync_running = $this->sync_manager->isSyncRunning();

        $this->context->smarty->assign([
        'sync_stats' => $sync_stats,
        'is_sync_running' => $is_sync_running,
        'last_sync_date' => YujuConfig::get('YUJU_LAST_SYNC_DATE'),
        'auto_sync_enabled' => YujuConfig::get('YUJU_AUTO_SYNC_ENABLED'),
        ]);

        $sync_info = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/sync_info.tpl');

        return $sync_info . parent::renderList();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('action')) {
            $action = Tools::getValue('action');

            switch ($action) {
                case 'syncFull':
                    $this->processSyncFull();
                    break;
                case 'syncIncremental':
                    $this->processSyncIncremental();
                    break;
                case 'syncCategories':
                    $this->processSyncCategories();
                    break;
                case 'syncProducts':
                    $this->processSyncProducts();
                    break;
                case 'syncStock':
                    $this->processSyncStock();
                    break;
                case 'syncPrices':
                    $this->processSyncPrices();
                    break;
                case 'stopSync':
                    $this->processStopSync();
                    break;
            }
        }

        return parent::postProcess();
    }

    protected function processSyncFull()
    {
        if ($this->sync_manager->isSyncRunning()) {
            $this->errors[] = $this->trans('Synchronization is already running. Please wait for it to complete.', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        try {
            $this->sync_manager->createSyncLock();

            $direction = Tools::getValue('direction', 'prestashop_to_yuju');
            $force_update = (bool) Tools::getValue('force_update', false);

            $results = $this->sync_manager->executeFullSync($direction, $force_update);

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    $this->trans('Full synchronization completed successfully. Categories: %d, Products: %d, Time: %d seconds', array(), 'Modules.Prestashopyuju.Admin'),
                    isset($results['categories']['synced_count']) ? $results['categories']['synced_count'] : 0,
                    isset($results['products']['synced_count']) ? $results['products']['synced_count'] : 0,
                    $results['total_time']
                );
            } else {
                $this->errors[] = $this->trans('Full synchronization failed: ', array(), 'Modules.Prestashopyuju.Admin') . implode(', ', $results['errors']);
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Synchronization error: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        } finally {
            $this->sync_manager->removeSyncLock();
        }
    }

    protected function processSyncIncremental()
    {
        if ($this->sync_manager->isSyncRunning()) {
            $this->errors[] = $this->trans('Synchronization is already running. Please wait for it to complete.', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        try {
            $this->sync_manager->createSyncLock();

            $since_date = Tools::getValue('since_date');
            $results = $this->sync_manager->executeIncrementalSync($since_date);

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    $this->trans('Incremental synchronization completed successfully. Categories: %d, Products: %d', array(), 'Modules.Prestashopyuju.Admin'),
                    $results['categories']['synced_count'],
                    $results['products']['synced_count']
                );
            } else {
                $this->errors[] = $this->trans('Incremental synchronization failed: ', array(), 'Modules.Prestashopyuju.Admin') . implode(', ', $results['errors']);
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Synchronization error: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        } finally {
            $this->sync_manager->removeSyncLock();
        }
    }

    protected function processSyncCategories()
    {
        try {
            $direction = Tools::getValue('direction', 'prestashop_to_yuju');
            $results = $this->sync_manager->syncCategories($direction);

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    $this->trans('Category synchronization completed successfully. Synced: %d', array(), 'Modules.Prestashopyuju.Admin'),
                    isset($results['synced_count']) ? $results['synced_count'] : 0
                );
            } else {
                $this->errors[] = $this->trans('Category synchronization failed: ', array(), 'Modules.Prestashopyuju.Admin') . implode(', ', $results['errors']);
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Category synchronization error: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    protected function processSyncProducts()
    {
        try {
            $direction = Tools::getValue('direction', 'bidirectional');
            $category_ids = Tools::getValue('category_ids');

            if ($category_ids) {
                $category_ids = explode(',', $category_ids);
                $results = $this->sync_manager->syncProductsByCategory($category_ids, $direction);
            } else {
                $results = $this->sync_manager->syncProducts($direction);
            }

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    $this->trans('Product synchronization completed successfully. Synced: %d', array(), 'Modules.Prestashopyuju.Admin'),
                    isset($results['synced_count']) ? $results['synced_count'] : 0
                );
            } else {
                $this->errors[] = $this->trans('Product synchronization failed: ', array(), 'Modules.Prestashopyuju.Admin') . implode(', ', $results['errors']);
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Product synchronization error: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    protected function processSyncStock()
    {
        try {
            $product_ids = Tools::getValue('product_ids');

            if ($product_ids) {
                $product_ids = explode(',', $product_ids);
            }

            $results = $this->sync_manager->syncStock($product_ids);

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    $this->trans('Stock synchronization completed successfully. Updated: %d products', array(), 'Modules.Prestashopyuju.Admin'),
                    $results['updated_count']
                );
            } else {
                $this->errors[] = $this->trans('Stock synchronization failed: ', array(), 'Modules.Prestashopyuju.Admin') . $results['error'];
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Stock synchronization error: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    protected function processSyncPrices()
    {
        try {
            $product_ids = Tools::getValue('product_ids');

            if ($product_ids) {
                $product_ids = explode(',', $product_ids);
            }

            $results = $this->sync_manager->syncPrices($product_ids);

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    $this->trans('Price synchronization completed successfully. Updated: %d products', array(), 'Modules.Prestashopyuju.Admin'),
                    $results['updated_count']
                );
            } else {
                $this->errors[] = $this->trans('Price synchronization failed: ', array(), 'Modules.Prestashopyuju.Admin') . $results['error'];
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Price synchronization error: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    protected function processStopSync()
    {
        try {
            $this->sync_manager->removeSyncLock();
            $this->confirmations[] = $this->trans('Synchronization stopped successfully.', array(), 'Modules.Prestashopyuju.Admin');
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error stopping synchronization: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    public function renderView()
    {
        $log_id = (int) Tools::getValue('id');

        if (!$log_id) {
            $this->errors[] = $this->trans('Invalid log ID', array(), 'Modules.Prestashopyuju.Admin');

            return $this->renderList();
        }

        $log = Db::getInstance()->getRow(
            '
        SELECT * FROM ' . _DB_PREFIX_ . 'yuju_sync_logs
        WHERE id = ' . (int) $log_id
        );

        if (!$log) {
            $this->errors[] = $this->trans('Log not found', array(), 'Modules.Prestashopyuju.Admin');

            return $this->renderList();
        }

        // Get detailed log entries for this sync
        $detailed_logs = $this->logger->getLogsByDateRange(
            $log['created_at'],
            date('Y-m-d H:i:s', strtotime($log['created_at'] . ' +' . $log['execution_time'] . ' seconds'))
        );

        $this->context->smarty->assign([
        'sync_log' => $log,
        'detailed_logs' => $detailed_logs,
        'back_url' => self::$currentIndex . '&token=' . $this->token,
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/sync_log_view.tpl');
    }

    public function renderForm()
    {
        // Sync configuration form
        $this->fields_form = [
        'legend' => [
        'title' => $this->trans('Synchronization Settings', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'icon-cogs',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->trans('Sync Direction', array(), 'Modules.Prestashopyuju.Admin'),
        'name' => 'sync_direction',
        'options' => [
        'query' => [
        ['id' => 'bidirectional', 'name' => $this->trans('Bidirectional', array(), 'Modules.Prestashopyuju.Admin')],
        ['id' => 'yuju_to_ps', 'name' => $this->trans('Yuju to PrestaShop', array(), 'Modules.Prestashopyuju.Admin')],
        ['id' => 'ps_to_yuju', 'name' => $this->trans('PrestaShop to Yuju', array(), 'Modules.Prestashopyuju.Admin')],
        ],
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'switch',
        'label' => $this->trans('Force Update', array(), 'Modules.Prestashopyuju.Admin'),
        'name' => 'force_update',
        'is_bool' => true,
        'values' => [
        ['id' => 'force_update_on', 'value' => 1, 'label' => $this->trans('Enabled', array(), 'Modules.Prestashopyuju.Admin')],
        ['id' => 'force_update_off', 'value' => 0, 'label' => $this->trans('Disabled', array(), 'Modules.Prestashopyuju.Admin')],
        ],
        ],
        [
        'type' => 'text',
        'label' => $this->trans('Category IDs', array(), 'Modules.Prestashopyuju.Admin'),
        'name' => 'category_ids',
        'desc' => $this->trans('Comma-separated list of category IDs (for product sync only)', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        [
        'type' => 'text',
        'label' => $this->trans('Product IDs', array(), 'Modules.Prestashopyuju.Admin'),
        'name' => 'product_ids',
        'desc' => $this->trans('Comma-separated list of product IDs (for stock/price sync only)', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        [
        'type' => 'datetime',
        'label' => $this->trans('Since Date', array(), 'Modules.Prestashopyuju.Admin'),
        'name' => 'since_date',
        'desc' => $this->trans('For incremental sync only', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        ],
        'submit' => [
        'title' => $this->trans('Execute Sync', array(), 'Modules.Prestashopyuju.Admin'),
        'class' => 'btn btn-default pull-right',
        ],
        ];

        return parent::renderForm();
    }

    public function ajaxProcessGetSyncStatus()
    {
        $is_running = $this->sync_manager->isSyncRunning();
        $stats = $this->sync_manager->getSyncStats();

        $response = [
        'is_running' => $is_running,
        'last_sync' => YujuConfig::get('YUJU_LAST_SYNC_DATE'),
        'stats' => $stats,
        ];

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
