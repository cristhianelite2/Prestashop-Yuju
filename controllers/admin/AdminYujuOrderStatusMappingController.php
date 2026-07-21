<?php
/**
 * 2024 Yuju Integration.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/config/config.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';

class AdminYujuOrderStatusMappingController extends ModuleAdminController
{
    protected $logger;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'yuju_order_status_mapping';
        $this->identifier = 'id';
        $this->lang = false;
        $this->className = false;

        parent::__construct();

        $this->logger = new YujuLogger();
    }

    public function initContent()
    {
        $this->ensureDefaultMappings();

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuOrderStatusMapping',
            'status_mappings' => $this->getStatusMappings(),
            'prestashop_order_states' => $this->getPrestashopOrderStates(),
            'yuju_order_statuses' => YujuStatusMappings::getOfficialYujuOrderStatuses(),
            'yuju_documented_statuses' => YujuStatusMappings::getDocumentedYujuOrderStatuses(),
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuOrderStatusMapping'),
            'token' => $this->token,
        ]);

        parent::initContent();
        $this->setTemplate('order_status_mapping.tpl');
    }

    /**
     * @return array
     */
    protected function getPrestashopOrderStates()
    {
        $states = OrderState::getOrderStates((int) $this->context->language->id);
        $out = [];
        foreach ($states as $state) {
            $out[] = [
                'id' => (int) $state['id_order_state'],
                'name' => $state['name'],
                'color' => isset($state['color']) ? $state['color'] : '',
            ];
        }

        return $out;
    }

    /**
     * @return array
     */
    protected function getStatusMappings()
    {
        $rows = Db::getInstance()->executeS('
            SELECT m.*, osl.name AS prestashop_status_name, os.color AS prestashop_status_color
            FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping m
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state os ON os.id_order_state = m.prestashop_status_id
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl
                ON osl.id_order_state = m.prestashop_status_id
                AND osl.id_lang = ' . (int) $this->context->language->id . '
            ORDER BY m.yuju_status_name ASC
        ');

        return $rows ?: [];
    }

    /**
     * Asegura que existan todos los mapeos por defecto (docs Yuju + aliases).
     * No sobrescribe filas ya configuradas por el merchant.
     *
     * @return int cantidad insertada
     */
    protected function ensureDefaultMappings()
    {
        $existing = Db::getInstance()->executeS(
            'SELECT yuju_status_name FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping'
        );
        $have = [];
        if (is_array($existing)) {
            foreach ($existing as $row) {
                $key = strtolower(trim((string) ($row['yuju_status_name'] ?? '')));
                if ($key !== '') {
                    $have[$key] = true;
                }
            }
        }

        $defaults = YujuStatusMappings::getDefaultYujuToPsMappings();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;

        foreach ($defaults as $yuju_status => $ps_status_id) {
            $yuju_status = strtolower(trim((string) $yuju_status));
            $ps_status_id = (int) $ps_status_id;
            if ($yuju_status === '' || $ps_status_id <= 0) {
                continue;
            }
            if (isset($have[$yuju_status])) {
                continue;
            }
            $ok = Db::getInstance()->insert('yuju_order_status_mapping', [
                'prestashop_status_id' => $ps_status_id,
                'yuju_status_name' => pSQL($yuju_status),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($ok) {
                $have[$yuju_status] = true;
                ++$inserted;
            }
        }

        return $inserted;
    }

    public function ajaxProcessSaveMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            $prestashop_status_id = (int) Tools::getValue('prestashop_status_id');
            $yuju_status_name = trim((string) Tools::getValue('yuju_status_name'));
            $is_active = (int) Tools::getValue('is_active', 1) ? 1 : 0;

            if ($prestashop_status_id <= 0 || $yuju_status_name === '') {
                throw new Exception('Debe seleccionar un estado de Yuju y uno de PrestaShop.');
            }

            $valid = array_keys(YujuStatusMappings::getOfficialYujuOrderStatuses());
            if (!in_array($yuju_status_name, $valid, true)) {
                throw new Exception('Estado de Yuju no válido.');
            }

            $state = new OrderState($prestashop_status_id);
            if (!Validate::isLoadedObject($state)) {
                throw new Exception('Estado de PrestaShop no válido.');
            }

            $existing = Db::getInstance()->getRow('
                SELECT id FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping
                WHERE yuju_status_name = "' . pSQL($yuju_status_name) . '"
                AND id != ' . (int) $id . '
            ');
            if ($existing) {
                throw new Exception('Este estado de Yuju ya está mapeado. Edítelo o elimínelo primero.');
            }

            $data = [
                'prestashop_status_id' => $prestashop_status_id,
                'yuju_status_name' => pSQL($yuju_status_name),
                'is_active' => $is_active,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($id > 0) {
                $ok = Db::getInstance()->update(
                    'yuju_order_status_mapping',
                    $data,
                    'id = ' . (int) $id
                );
                $message = 'Mapeo de estado actualizado.';
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $ok = Db::getInstance()->insert('yuju_order_status_mapping', $data);
                $message = 'Mapeo de estado creado.';
            }

            if (!$ok) {
                throw new Exception('No se pudo guardar el mapeo.');
            }

            $response['success'] = true;
            $response['message'] = $message;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error saving order status mapping: ' . $e->getMessage(), 'error');
        }

        die(json_encode($response));
    }

    public function ajaxProcessDeleteMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            if ($id <= 0) {
                throw new Exception('ID de mapeo inválido.');
            }

            $ok = Db::getInstance()->delete('yuju_order_status_mapping', 'id = ' . (int) $id);
            if (!$ok) {
                throw new Exception('No se pudo eliminar el mapeo.');
            }

            $response['success'] = true;
            $response['message'] = 'Mapeo eliminado.';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    public function ajaxProcessToggleMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            $is_active = (int) Tools::getValue('is_active') ? 1 : 0;
            if ($id <= 0) {
                throw new Exception('ID de mapeo inválido.');
            }

            $ok = Db::getInstance()->update(
                'yuju_order_status_mapping',
                [
                    'is_active' => $is_active,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ' . (int) $id
            );
            if (!$ok) {
                throw new Exception('No se pudo actualizar el estado.');
            }

            $response['success'] = true;
            $response['message'] = $is_active ? 'Mapeo activado.' : 'Mapeo desactivado.';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }
}
