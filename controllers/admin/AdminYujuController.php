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
        $accountInfo = $this->getCachedAccountInfo();

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
            'yuju_account_info' => $accountInfo,
            'ajax_url' => $this->context->link->getAdminLink('AdminYuju'),
            'token' => $this->token,
        ]);

        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        if (Tools::getValue('ajax')) {
            $action = (string) Tools::getValue('action');
            if ($action === 'refreshAccountInfo') {
                $this->ajaxProcessRefreshAccountInfo();
            } else {
                die(json_encode(['success' => false, 'message' => 'Acción no reconocida: ' . $action]));
            }
            exit;
        }

        return parent::postProcess();
    }

    /**
     * AJAX: refresca GET /account y guarda en cache.
     */
    public function ajaxProcessRefreshAccountInfo()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $result = $this->fetchAndStoreAccountInfo();
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Lee cache local de cuenta/tienda/canales.
     *
     * @return array
     */
    protected function getCachedAccountInfo()
    {
        require_once dirname(__FILE__) . '/../../config/config.php';

        $raw = YujuConfig::get('YUJU_ACCOUNT_INFO_CACHE', '');
        $updatedAt = (string) YujuConfig::get('YUJU_ACCOUNT_INFO_UPDATED_AT', '');
        $data = null;

        if (is_array($raw)) {
            $data = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return [
            'has_data' => is_array($data) && !empty($data),
            'updated_at' => $updatedAt !== '' ? $updatedAt : null,
            'id_account' => $data['id_account'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'id_shop' => $data['id_shop'] ?? null,
            'shop_name' => $data['shop_name'] ?? null,
            'channels' => (isset($data['channels']) && is_array($data['channels'])) ? $data['channels'] : [],
            'raw' => $data,
        ];
    }

    /**
     * Llama a GET /account, normaliza, cachea y actualiza mapa de marketplaces.
     *
     * @return array
     */
    protected function fetchAndStoreAccountInfo()
    {
        require_once dirname(__FILE__) . '/../../config/config.php';
        require_once dirname(__FILE__) . '/../../classes/YujuApiClient.php';

        $api = new YujuApiClient();
        $response = $api->getAccount();

        if (empty($response['success']) && !isset($response['id_account']) && !isset($response['data']['id_account'])) {
            $msg = $response['message'] ?? 'No se pudo obtener la información de la cuenta Yuju.';

            return [
                'success' => false,
                'message' => $msg,
                'account' => $this->getCachedAccountInfo(),
            ];
        }

        $payload = $response;
        if (isset($response['data']) && is_array($response['data'])) {
            $payload = $response['data'];
        }

        $normalized = [
            'id_account' => $payload['id_account'] ?? null,
            'account_name' => $payload['account_name'] ?? null,
            'id_shop' => $payload['id_shop'] ?? null,
            'shop_name' => $payload['shop_name'] ?? null,
            'channels' => [],
        ];

        if (!empty($payload['channels']) && is_array($payload['channels'])) {
            foreach ($payload['channels'] as $ch) {
                if (!is_array($ch)) {
                    continue;
                }
                $normalized['channels'][] = [
                    'id_channel' => $ch['id_channel'] ?? null,
                    'name' => $ch['name'] ?? '',
                    'generic_name' => $ch['generic_name'] ?? '',
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        YujuConfig::set('YUJU_ACCOUNT_INFO_CACHE', $normalized, 'json');
        YujuConfig::set('YUJU_ACCOUNT_INFO_UPDATED_AT', $now, 'string');

        $this->syncChannelMarketplaceMap($normalized['channels']);

        $account = $this->getCachedAccountInfo();

        return [
            'success' => true,
            'message' => 'Información de Yuju actualizada (' . count($normalized['channels']) . ' canales).',
            'account' => $account,
        ];
    }

    /**
     * Completa YUJU_CHANNEL_MARKETPLACE_MAP con generic_name de cada id_channel.
     *
     * @param array $channels
     */
    protected function syncChannelMarketplaceMap(array $channels)
    {
        if (empty($channels)) {
            return;
        }

        $map = YujuConfig::get('YUJU_CHANNEL_MARKETPLACE_MAP', []);
        if (is_string($map)) {
            $decoded = json_decode($map, true);
            $map = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($map)) {
            $map = [];
        }

        foreach ($channels as $ch) {
            $id = isset($ch['id_channel']) ? (string) $ch['id_channel'] : '';
            if ($id === '') {
                continue;
            }
            $generic = isset($ch['generic_name']) ? (string) $ch['generic_name'] : '';
            $slug = preg_replace('/[^a-z0-9]/', '', strtolower($generic));
            if ($slug === '') {
                continue;
            }
            // No pisar overrides manuales existentes
            if (!isset($map[$id]) || $map[$id] === '' || strpos((string) $map[$id], 'channel') === 0) {
                $map[$id] = $slug;
            }
        }

        YujuConfig::set('YUJU_CHANNEL_MARKETPLACE_MAP', $map, 'json');
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
