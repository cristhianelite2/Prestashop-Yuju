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
        $this->meta_title = $this->trans('Integración Yuju', array(), 'Modules.Prestashopyuju.Admin');
    }

    public function initContent()
    {
        parent::initContent();

        // Use the already-registered AdminYuju route for the order-status mapping
        // screen. This avoids relying on a separately registered legacy Tab.
        if (Tools::getValue('yuju_view') === 'order_status_mapping') {
            $this->renderOrderStatusMapping();
            return;
        }

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
            'terms_url' => $this->context->link->getModuleLink('prestashopyuju', 'terms', [], true),
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

    public function postProcess()
    {
        if (Tools::isSubmit('submitImportMadktingStatusMappings')
            && Tools::getValue('yuju_view') === 'order_status_mapping') {
            $this->processImportMadktingStatusMappings();
        }

        return parent::postProcess();
    }

    /**
     * Render the mapping screen through the registered AdminYuju controller.
     */
    protected function renderOrderStatusMapping()
    {
        $table = _DB_PREFIX_ . 'yuju_order_status_mapping';
        $mappings = Db::getInstance()->executeS('
            SELECT m.id, m.prestashop_status_id, m.yuju_status_name, m.is_active,
                   osl.name AS prestashop_status_name
            FROM ' . bqSQL($table) . ' m
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl
              ON osl.id_order_state = m.prestashop_status_id
             AND osl.id_lang = ' . (int) $this->context->language->id . '
            ORDER BY m.prestashop_status_id ASC, m.id ASC
        ');

        $source = $this->getMadktingSourceStatus();
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuOrderStatusMapping',
            'status_mappings' => is_array($mappings) ? $mappings : [],
            'madkting_status_available' => $source['available'],
            'madkting_status_count' => $source['count'],
            'import_status_url' => $this->context->link->getAdminLink(
                'AdminYuju',
                true,
                [],
                ['yuju_view' => 'order_status_mapping']
            ),
            'token' => $this->token,
            'yuju_status_docs_url' => 'https://api-docs.yuju.io/docs/estados-de-un-pedido',
        ]);

        $this->setTemplate('order_status_mapping.tpl');
    }

    /**
     * The legacy Madkting table is strictly read-only.
     */
    protected function getMadktingSourceStatus()
    {
        $table = _DB_PREFIX_ . 'mad_mapping_status_orders';
        $exists = Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = "' . pSQL($table) . '"'
        );

        if (!$exists) {
            return ['available' => false, 'count' => 0];
        }

        $count = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . bqSQL($table)
        );

        return ['available' => $count > 0, 'count' => $count];
    }

    protected function processImportMadktingStatusMappings()
    {
        $result = ['imported' => 0, 'existing' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $source = $this->getMadktingSourceStatus();
            if (!$source['available']) {
                throw new Exception('No se encontró la tabla de estados de Madkting o no contiene registros.');
            }

            $table = _DB_PREFIX_ . 'mad_mapping_status_orders';
            $rows = Db::getInstance()->executeS('
                SELECT id_mapping_status_order, id_status_order_prestashop, id_status_order_madkting
                FROM ' . bqSQL($table) . '
                ORDER BY id_mapping_status_order ASC
            ');
            if (!is_array($rows)) {
                throw new Exception('No fue posible leer los estados heredados de Madkting.');
            }

            $statusAliases = [
                'payment_required' => 'pending',
                'pending' => 'pending',
                'paid' => 'paid',
                'ready_to_ship' => 'ready_to_ship',
                'shipped' => 'shipped',
                'delivered' => 'delivered',
                'canceled' => 'canceled',
                'cancelled' => 'canceled',
                'refunded' => 'refunded',
                'with_mediation' => 'with_mediation',
            ];
            $seenPrestashopIds = [];
            $now = date('Y-m-d H:i:s');

            foreach ($rows as $row) {
                $psStatusId = (int) $row['id_status_order_prestashop'];
                $legacyStatus = strtolower(trim((string) $row['id_status_order_madkting']));
                if ($psStatusId <= 0 || !isset($statusAliases[$legacyStatus])) {
                    ++$result['skipped'];
                    continue;
                }

                $yujuStatus = $statusAliases[$legacyStatus];
                if (isset($seenPrestashopIds[$psStatusId])) {
                    ++$result['skipped'];
                    continue;
                }
                $seenPrestashopIds[$psStatusId] = true;

                $orderState = new OrderState($psStatusId, (int) $this->context->language->id);
                if (!Validate::isLoadedObject($orderState)) {
                    ++$result['skipped'];
                    continue;
                }

                $existing = Db::getInstance()->getValue('
                    SELECT id FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping
                    WHERE prestashop_status_id = ' . (int) $psStatusId . '
                      AND yuju_status_name = "' . pSQL($yujuStatus) . '"
                ');
                if ($existing) {
                    ++$result['existing'];
                    continue;
                }

                $inserted = Db::getInstance()->insert('yuju_order_status_mapping', [
                    'prestashop_status_id' => $psStatusId,
                    'yuju_status_name' => pSQL($yujuStatus),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($inserted) {
                    ++$result['imported'];
                } else {
                    ++$result['errors'];
                }
            }

            $message = sprintf(
                'Importación finalizada. Importados: %d; ya existentes: %d; omitidos: %d; errores: %d.',
                $result['imported'],
                $result['existing'],
                $result['skipped'],
                $result['errors']
            );
            if ($result['errors'] > 0) {
                $this->errors[] = $message;
            } else {
                $this->confirmations[] = $message;
            }
        } catch (Exception $e) {
            $this->errors[] = 'No se pudo importar el mapeo de estados: ' . $e->getMessage();
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
