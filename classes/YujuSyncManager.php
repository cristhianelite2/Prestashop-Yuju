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

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuCategoryManager.php';
require_once dirname(__FILE__) . '/YujuProductManager.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuSyncManager
{
    private $api_client;
    private $logger;
    private $category_manager;
    private $product_manager;
    private $config;

    public function __construct()
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->category_manager = new YujuCategoryManager();
        $this->product_manager = new YujuProductManager();
        $this->loadConfig();
    }

    /**
     * Load synchronization configuration.
     */
    protected function loadConfig()
    {
        $this->config = [
            'batch_size' => (int) (YujuConfig::get('YUJU_SYNC_BATCH_SIZE', null) ?: 50),
            'max_execution_time' => (int) (YujuConfig::get('YUJU_SYNC_MAX_EXECUTION_TIME', null) ?: 300),
            'auto_sync_enabled' => (bool) (YujuConfig::get('YUJU_AUTO_SYNC_ENABLED', null) ?: false),
            'sync_frequency' => YujuConfig::get('YUJU_SYNC_FREQUENCY', null) ?: 'hourly',
            'email_notifications' => (bool) (YujuConfig::get('YUJU_EMAIL_NOTIFICATIONS', null) ?: false),
            'notification_email' => YujuConfig::get('YUJU_NOTIFICATION_EMAIL', null) ?: '',
            'sync_categories' => (bool) (YujuConfig::get('YUJU_SYNC_CATEGORIES', null) ?: true),
            'sync_products' => (bool) (YujuConfig::get('YUJU_SYNC_PRODUCTS', null) ?: true),
            'sync_stock' => (bool) (YujuConfig::get('YUJU_SYNC_STOCK', null) ?: true),
            'sync_prices' => (bool) (YujuConfig::get('YUJU_SYNC_PRICES', null) ?: true),
            'sync_images' => (bool) (YujuConfig::get('YUJU_SYNC_IMAGES', null) ?: false),
        ];
    }

    /**
     * Execute full synchronization.
     */
    public function executeFullSync($direction = 'ps_to_yuju', $force_update = false)
    {
        // La fuente de verdad del catálogo siempre es PrestaShop; ignorar direcciones heredadas.
        $direction = 'ps_to_yuju';
        $start_time = time();
        $this->logger->log('Starting full synchronization (direction: PrestaShop -> Yuju)', 'info');

        $results = [
            'categories' => [],
            'products' => [],
            'total_time' => 0,
            'success' => true,
            'errors' => [],
        ];

        try {
            // Set execution time limit
            if ($this->config['max_execution_time'] > 0) {
                set_time_limit($this->config['max_execution_time']);
            }

            // Synchronize categories first
            if ($this->config['sync_categories']) {
                $results['categories'] = $this->syncCategories($direction, $force_update);

                if (!$results['categories']['success']) {
                    $results['success'] = false;
                    $results['errors'][] = 'Category synchronization failed';
                }
            }

            // Synchronize products
            if ($this->config['sync_products']) {
                $results['products'] = $this->syncProducts($direction, $force_update);

                if (!$results['products']['success']) {
                    $results['success'] = false;
                    $results['errors'][] = 'Product synchronization failed';
                }
            }

            $results['total_time'] = time() - $start_time;

            // Log sync completion
            $this->logSyncCompletion($results);

            // Send notification if enabled
            if ($this->config['email_notifications']) {
                $this->sendSyncNotification($results);
            }

            $this->logger->log('Full synchronization completed in ' . $results['total_time'] . ' seconds', 'info');
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $results['total_time'] = time() - $start_time;

            $this->logger->log('Full synchronization failed: ' . $e->getMessage(), 'error');
        }

        return $results;
    }

    /**
     * Execute incremental synchronization.
     */
    public function executeIncrementalSync($since_date = null)
    {
        if (!$since_date) {
            $since_date = $this->getLastSyncDate();
        }

        $this->logger->log('Starting incremental synchronization since ' . $since_date, 'info');

        $results = [
            'categories' => ['synced_count' => 0, 'errors' => []],
            'products' => ['synced_count' => 0, 'errors' => []],
            'success' => true,
            'errors' => [],
        ];

        try {
            // No importar cambios de catálogo desde Yuju. Volver a enviar el catálogo local.
            $results['categories'] = $this->category_manager->syncCategoriesToYuju();
            $results['products'] = $this->product_manager->syncProductsToYuju();

            // Update last sync date
            $this->updateLastSyncDate();
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->log('Incremental synchronization failed: ' . $e->getMessage(), 'error');
        }

        return $results;
    }

    /**
     * Synchronize categories.
     */
    protected function syncCategories($direction = 'ps_to_yuju', $force_update = false)
    {
        // Las categorías también se envían únicamente desde PrestaShop a Yuju.
        try {
            return $this->category_manager->syncCategoriesToYuju();
        } catch (Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Synchronize products.
     */
    protected function syncProducts($direction = 'ps_to_yuju', $force_update = false)
    {
        // Ignorar cualquier dirección heredada: Yuju nunca actualiza el catálogo local.
        try {
            return $this->product_manager->syncProductsToYuju();
        } catch (Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Synchronize specific products by category.
     */
    public function syncProductsByCategory($category_ids, $direction = 'ps_to_yuju')
    {
        $this->logger->log('Starting PrestaShop-to-Yuju product synchronization for categories: ' . implode(',', (array) $category_ids), 'info');
        try {
            return $this->product_manager->syncProductsToYuju(null, (array) $category_ids);
        } catch (Exception $e) {
            $this->logger->log('Category-specific product synchronization failed: ' . $e->getMessage(), 'error');
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Synchronize stock levels.
     */
    public function syncStock($product_ids = null)
    {
        if (!$this->config['sync_stock']) {
            return ['success' => false, 'error' => 'Stock synchronization is disabled'];
        }
        // El stock se toma de PrestaShop y se envía a Yuju con el payload de producto.
        return $this->product_manager->syncProductsToYuju($product_ids);
    }

    /**
     * Synchronize prices.
     */
    public function syncPrices($product_ids = null)
    {
        if (!$this->config['sync_prices']) {
            return ['success' => false, 'error' => 'Price synchronization is disabled'];
        }
        // El precio se toma de PrestaShop y se envía a Yuju con el payload de producto.
        return $this->product_manager->syncProductsToYuju($product_ids);
    }

    /**
     * Get products for stock synchronization.
     */
    protected function getProductsForStockSync($product_ids = null)
    {
        $sql = '
            SELECT p.id_product, ps.yuju_product_id
            FROM ' . _DB_PREFIX_ . 'product p
            JOIN ' . _DB_PREFIX_ . 'yuju_product_status ps ON p.id_product = ps.prestashop_product_id
            WHERE p.active = 1 AND ps.yuju_product_id IS NOT NULL
              AND (ps.prestashop_product_attribute_id IS NULL OR ps.prestashop_product_attribute_id = 0)
        ';

        if ($product_ids && is_array($product_ids)) {
            $sql .= ' AND p.id_product IN (' . implode(',', array_map('intval', $product_ids)) . ')';
        }

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get products for price synchronization.
     */
    protected function getProductsForPriceSync($product_ids = null)
    {
        return $this->getProductsForStockSync($product_ids);
    }

    /**
     * Synchronize single product stock.
     */
    protected function syncSingleProductStock($product_data)
    {
        // Método legado retenido por compatibilidad: nunca importar stock desde Yuju.
        $this->logger->log('Se bloqueó una operación de stock Yuju → PrestaShop por política unidireccional.', 'warning');
        return false;
    }

    /**
     * Synchronize single product price.
     */
    protected function syncSingleProductPrice($product_data)
    {
        // Método legado retenido por compatibilidad: nunca importar precios desde Yuju.
        $this->logger->log('Se bloqueó una operación de precio Yuju → PrestaShop por política unidireccional.', 'warning');
        return false;
    }

    /**
     * Get mapped categories by PrestaShop IDs.
     */
    protected function getMappedCategoriesByPrestashopIds($category_ids)
    {
        return Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE prestashop_category_id IN (' . implode(',', array_map('intval', $category_ids)) . ')
            AND sync_enabled = 1
        ');
    }

    /**
     * Get last synchronization date.
     */
    protected function getLastSyncDate()
    {
        $last_sync = YujuConfig::get('YUJU_LAST_SYNC_DATE');

        return $last_sync ? $last_sync : date('Y-m-d H:i:s', strtotime('-1 hour'));
    }

    /**
     * Update last synchronization date.
     */
    protected function updateLastSyncDate()
    {
        YujuConfig::set('YUJU_LAST_SYNC_DATE', date('Y-m-d H:i:s'));
    }

    /**
     * Log synchronization completion.
     */
    protected function logSyncCompletion($results)
    {
        $log_data = [
            'sync_type' => 'full',
            'entity_type' => 'products',
            'sync_direction' => 'prestashop_to_yuju',
            'status' => $results['success'] ? 'completed' : 'failed',
            'total_items' => isset($results['total_items']) ? $results['total_items'] : 0,
            'processed_items' => isset($results['processed_items']) ? $results['processed_items'] : 0,
            'success_items' => isset($results['success_items']) ? $results['success_items'] : 0,
            'error_items' => count($results['errors']),
            'start_time' => date('Y-m-d H:i:s'),
            'duration' => isset($results['total_time']) ? $results['total_time'] : 0,
        ];

        Db::getInstance()->insert('yuju_sync_logs', $log_data);
    }

    /**
     * Send synchronization notification.
     */
    protected function sendSyncNotification($results)
    {
        if (empty($this->config['notification_email'])) {
            return;
        }

        $subject = 'Yuju Synchronization ' . ($results['success'] ? 'Completed' : 'Failed');

        $message = 'Synchronization Results:\n\n';
        $message .= 'Status: ' . ($results['success'] ? 'Success' : 'Failed') . '\n';
        $message .= 'Execution Time: ' . $results['total_time'] . ' seconds\n\n';

        if (isset($results['categories'])) {
            $message .= 'Categories Synced: ' . (isset($results['categories']['synced_count']) ? $results['categories']['synced_count'] : 0) . '\n';
        }

        if (isset($results['products'])) {
            $message .= 'Products Synced: ' . (isset($results['products']['synced_count']) ? $results['products']['synced_count'] : 0) . '\n';
        }

        if (!empty($results['errors'])) {
            $message .= '\nErrors:\n' . implode('\n', $results['errors']);
        }

        Mail::Send(
            (int) Configuration::get('PS_LANG_DEFAULT'),
            'yuju_sync_notification',
            $subject,
            ['{message}' => $message],
            $this->config['notification_email'],
            null,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'yuju/mails/'
        );
    }

    /**
     * Get synchronization statistics.
     */
    public function getSyncStats()
    {
        $stats = [];

        // Get category stats
        $stats['categories'] = $this->category_manager->getCategoryMappingStats();

        // Get product stats
        $stats['products'] = $this->product_manager->getProductSyncStats();

        // Get recent sync logs
        $stats['recent_syncs'] = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_sync_logs
            ORDER BY `start_time` DESC
            LIMIT 10
        ');

        // Get sync configuration
        $stats['config'] = $this->config;

        return $stats;
    }

    /**
     * Check if synchronization is currently running.
     */
    public function isSyncRunning()
    {
        $lock_file = _PS_MODULE_DIR_ . 'yuju/logs/sync.lock';

        if (file_exists($lock_file)) {
            $lock_time = filemtime($lock_file);
            // Consider sync stuck if lock is older than max execution time + 60 seconds
            if (time() - $lock_time > $this->config['max_execution_time'] + 60) {
                unlink($lock_file);

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Create synchronization lock.
     */
    public function createSyncLock()
    {
        $lock_file = _PS_MODULE_DIR_ . 'yuju/logs/sync.lock';
        file_put_contents($lock_file, time());
    }

    /**
     * Remove synchronization lock.
     */
    public function removeSyncLock()
    {
        $lock_file = _PS_MODULE_DIR_ . 'yuju/logs/sync.lock';

        if (file_exists($lock_file)) {
            unlink($lock_file);
        }
    }
}
