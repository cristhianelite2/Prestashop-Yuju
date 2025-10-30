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

        $this->context->smarty->assign([
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->displayName,
            'module_version' => $this->module->version,
            'current_controller' => 'AdminYuju',
            'dashboard_stats' => $stats,
            'recent_syncs' => $recentSyncs,
            'last_sync_date' => $lastSyncDate,
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

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }
}
