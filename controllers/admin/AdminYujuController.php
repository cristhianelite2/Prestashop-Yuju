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

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuUpdateManager.php';

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

        $stats = $this->getDashboardStats();
        $recentSyncs = $this->getRecentSyncItems();
        $lastSyncDate = $this->getLastSyncDate();
        $updateManager = new YujuUpdateManager($this->module->version);
        $updateStatus = $updateManager->getUpdateStatus(false);

        $this->context->smarty->assign([
            'module_dir' => $this->module->getPathUri(),
            'module_name' => $this->module->displayName,
            'module_version' => $this->module->version,
            'current_controller' => get_class($this),
            'dashboard_stats' => $stats,
            'recent_syncs' => $recentSyncs,
            'last_sync_date' => $lastSyncDate,
            'update_status' => $updateStatus,
            'ajax_url' => $this->context->link->getAdminLink('AdminYuju'),
            'admin_yuju_token' => Tools::getAdminTokenLite('AdminYuju'),
        ]);

        $this->setTemplate('dashboard.tpl');
    }

    /**
     * Forzar comprobación de actualizaciones en GitHub.
     */
    public function ajaxProcessCheckUpdate()
    {
        try {
            $updateManager = new YujuUpdateManager($this->module->version);
            $status = $updateManager->getUpdateStatus(true);

            if (!empty($status['check_error'])) {
                throw new Exception($status['check_error']);
            }

            $message = $status['update_available']
                ? $this->trans('Hay una actualización disponible.', array(), 'Modules.Prestashopyuju.Admin')
                : $this->trans('El módulo está actualizado.', array(), 'Modules.Prestashopyuju.Admin');

            exit(json_encode([
                'success' => true,
                'message' => $message,
                'data' => $status,
            ]));
        } catch (Exception $e) {
            exit(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Descargar e instalar la última versión desde la rama main.
     */
    public function ajaxProcessPerformUpdate()
    {
        try {
            $updateManager = new YujuUpdateManager($this->module->version);
            $result = $updateManager->performUpdate();

            exit(json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]));
        } catch (Exception $e) {
            exit(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    private function getDashboardStats()
    {
        $sql = 'SELECT 
                    COUNT(CASE WHEN status = "failed" THEN 1 END) as errors_count,
                    COUNT(CASE WHEN status = "cancelled" THEN 1 END) as warnings_count
                FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)';

        $result = Db::getInstance()->getRow($sql);

        return [
            'errors_last_week' => (int) ($result['errors_count'] ?? 0),
            'warnings_last_week' => (int) ($result['warnings_count'] ?? 0),
        ];
    }

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
            'attributes' => 0,
        ];

        if ($results) {
            foreach ($results as $result) {
                $syncs[$result['entity_type']] = (int) $result['count'];
            }
        }

        return $syncs;
    }

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
