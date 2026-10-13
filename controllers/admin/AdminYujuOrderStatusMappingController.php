<?php
/**
 * Order status mapping and legacy Madkting import.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminYujuOrderStatusMappingController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'yuju_order_status_mapping';
        $this->className = 'YujuOrderStatusMapping';
        $this->identifier = 'id';
        $this->lang = false;

        parent::__construct();

        $this->meta_title = 'Mapeo de estados de pedidos';
        $this->toolbar_title = 'Mapeo de estados de pedidos';
    }

    public function initContent()
    {
        parent::initContent();

        $source = $this->getMadktingSourceStatus();
        $mappings = Db::getInstance()->executeS('
            SELECT m.id, m.prestashop_status_id, m.yuju_status_name, m.is_active,
                   osl.name AS prestashop_status_name
            FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping m
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl
              ON osl.id_order_state = m.prestashop_status_id
             AND osl.id_lang = ' . (int) $this->context->language->id . '
            ORDER BY m.prestashop_status_id ASC, m.id ASC
        ');

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuOrderStatusMapping',
            'status_mappings' => is_array($mappings) ? $mappings : [],
            'madkting_status_available' => $source['available'],
            'madkting_status_count' => $source['count'],
            'import_status_url' => $this->context->link->getAdminLink('AdminYujuOrderStatusMapping'),
            'token' => $this->token,
            'yuju_status_docs_url' => 'https://api-docs.yuju.io/docs/estados-de-un-pedido',
        ]);

        $this->setTemplate('order_status_mapping.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitImportMadktingStatusMappings')) {
            $this->processImportMadktingStatusMappings();
        }

        return parent::postProcess();
    }

    /**
     * The legacy table is read-only. Never alter or mark rows in Madkting.
     */
    protected function getMadktingSourceStatus()
    {
        $table = _DB_PREFIX_ . 'mad_mapping_status_orders';
        $exists = Db::getInstance()->getValue(
            'SHOW TABLES LIKE "' . pSQL($table) . '"'
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
        $result = [
            'imported' => 0,
            'existing' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

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

            // Yuju documents these standardized order progress states. payment_required
            // is a legacy synonym for pending; unsupported values are not guessed.
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

                // A single PrestaShop status cannot map to several Yuju statuses
                // deterministically. Keep the first legacy row and report conflicts.
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
                    SELECT id
                    FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping
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
                'Importación finalizada. Importados: %d; ya existentes: %d; omitidos (estado no admitido, inexistente o conflicto): %d; errores: %d.',
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
}
