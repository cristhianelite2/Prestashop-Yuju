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

class AdminYujuController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->meta_title = $this->trans('Yuju Integration', array(), 'Modules.Prestashopyuju.Admin');
    }

    public function initContent()
    {
        parent::initContent();

        // Get dashboard statistics
        $stats = $this->getDashboardStats();
        $recentSyncs = $this->getRecentSyncItems();
        $lastSyncDate = $this->getLastSyncDate();
        $oauthStatus = $this->getOAuthStatus();
        $productStats = $this->getProductStats();
        $webhookStats = $this->getWebhookStats();

        $this->context->smarty->assign([
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->displayName,
            'module_version' => $this->module->version,
            'current_controller' => 'AdminYuju',
            'dashboard_stats' => $stats,
            'recent_syncs' => $recentSyncs,
            'last_sync_date' => $lastSyncDate,
            'oauth_status' => $oauthStatus,
            'product_stats' => $productStats,
            'webhook_stats' => $webhookStats,
        ]);

        $this->setTemplate('dashboard.tpl');
    }

    /**
     * Get dashboard statistics for the last week
     */
    private function getDashboardStats()
    {
        $sql = 'SELECT 
                    COUNT(CASE WHEN status = "failed" THEN 1 END) as errors_count,
                    COUNT(CASE WHEN status = "cancelled" THEN 1 END) as warnings_count
                FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        
        $result = Db::getInstance()->getRow($sql);
        
        return [
            'errors_last_week' => (int)($result['errors_count'] ?? 0),
            'warnings_last_week' => (int)($result['warnings_count'] ?? 0)
        ];
    }

    /**
     * Get recent synchronized items (last 10)
     */
    private function getRecentSyncItems()
    {
        $sql = 'SELECT 
                    entity_type,
                    COUNT(*) as count,
                    MAX(start_time) as last_sync
                FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                WHERE status = "completed" 
                    AND start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND entity_type IN ("products", "categories", "attributes")
                GROUP BY entity_type
                ORDER BY last_sync DESC
                LIMIT 10';
        
        $results = Db::getInstance()->executeS($sql);
        
        $syncs = [
            'products' => 0,
            'categories' => 0,
            'attributes' => 0
        ];
        
        if ($results) {
            foreach ($results as $result) {
                $syncs[$result['entity_type']] = (int)$result['count'];
            }
        }
        
        return $syncs;
    }

    /**
     * Get last synchronization date
     */
    private function getLastSyncDate()
    {
        $sql = 'SELECT MAX(start_time) as last_sync 
                FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                WHERE status = "completed"';
        
        $result = Db::getInstance()->getRow($sql);
        
        return $result['last_sync'] ?? null;
    }

    /**
     * Get OAuth connection status
     */
    private function getOAuthStatus()
    {
        require_once dirname(__FILE__) . '/../../classes/YujuOAuth.php';
        
        try {
            $oauth = new YujuOAuth();
            $status = $oauth->getOAuthStatus();
            $tokenData = $oauth->getStoredTokenData();
            
            return [
                'is_connected' => $status['is_connected'] ?? false,
                'has_token' => !empty($tokenData['access_token']),
                'client_id' => $tokenData['client_id'] ?? null,
                'token_expires' => $tokenData['token_expires'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'is_connected' => false,
                'has_token' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get product synchronization statistics
     */
    private function getProductStats()
    {
        // Total productos en PrestaShop
        $totalProducts = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` WHERE active = 1'
        );
        
        // Productos sincronizados según yuju_product_status
        $syncedProducts = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_status` 
            WHERE sync_status = "synced"'
        );
        
        // Productos pendientes de sincronización
        $pendingProducts = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_status` 
            WHERE sync_status = "pending"'
        );
        
        // Si no hay registros en product_status, considerar todos como pendientes
        if ($syncedProducts == 0 && $pendingProducts == 0) {
            $pendingProducts = $totalProducts;
        }
        
        // Productos con errores
        $errorProducts = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_status` 
            WHERE sync_status = "error"'
        );
        
        return [
            'total' => $totalProducts,
            'synced' => $syncedProducts,
            'pending' => $pendingProducts,
            'errors' => $errorProducts,
        ];
    }

    /**
     * Get webhook statistics
     */
    private function getWebhookStats()
    {
        // Webhooks recibidos en las últimas 24 horas
        $recentWebhooks = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_webhook_logs` 
            WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        
        // Órdenes recibidas vía webhook
        $webhookOrders = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_webhook_logs` 
            WHERE event_type = "order" AND received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        
        // Webhooks procesados exitosamente
        $successWebhooks = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_webhook_logs` 
            WHERE status = "processed" AND received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        
        // Webhooks con error
        $errorWebhooks = (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_webhook_logs` 
            WHERE status = "error" AND received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        
        return [
            'recent_24h' => $recentWebhooks,
            'orders_7d' => $webhookOrders,
            'success_7d' => $successWebhooks,
            'errors_7d' => $errorWebhooks,
        ];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }
}
