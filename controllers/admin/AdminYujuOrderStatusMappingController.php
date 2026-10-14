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

require_once _PS_MODULE_DIR_ . 'yuju/config/config.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuLogger.php';

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
        $this->ensureFieldMappingTable();
    }

    protected function ensureFieldMappingTable()
    {
        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yuju_order_field_mapping` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `target_path` varchar(190) NOT NULL,
                `template` text NOT NULL,
                `field_type` enum(\'string\',\'integer\',\'decimal\',\'boolean\') NOT NULL DEFAULT \'string\',
                `default_value` varchar(255) DEFAULT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_target_path` (`target_path`),
                KEY `idx_active` (`is_active`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    public function initContent()
    {
        $this->ensureDefaultMappings();

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuOrderStatusMapping',
            'status_mappings' => $this->getStatusMappings(),
            'field_mappings' => $this->getFieldMappings(),
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


    /** Mapeos genéricos de campos de orden. */
    protected function getFieldMappings()
    {
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_order_field_mapping ORDER BY target_path ASC, id ASC'
            );
            return is_array($rows) ? $rows : [];
        } catch (Exception $e) {
            $this->logger->log('No se pudieron leer los mapeos de campos: ' . $e->getMessage(), 'warning');
            return [];
        }
    }

    public function ajaxProcessSaveFieldMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];
        try {
            $id = (int) Tools::getValue('id_field_mapping');
            $target = trim((string) Tools::getValue('target_path'));
            $template = trim((string) Tools::getValue('template'));
            $type = strtolower(trim((string) Tools::getValue('field_type', 'string')));
            $default = trim((string) Tools::getValue('default_value'));
            $active = (int) Tools::getValue('is_active', 1) ? 1 : 0;

            if (!preg_match('/^[a-zA-Z0-9_-]+(?:\\.[a-zA-Z0-9_-]+)*$/', $target) || strlen($target) > 190) {
                throw new Exception('Ruta de destino no válida. Use rutas como customer.first_name o items.0.quantity.');
            }
            if ($template === '') {
                throw new Exception('La plantilla no puede estar vacía. Use texto o comodines como %customer.last_name%.');
            }
            if (!in_array($type, ['string', 'integer', 'decimal', 'boolean'], true)) {
                throw new Exception('Tipo de dato no válido.');
            }
            $duplicate = Db::getInstance()->getValue(
                'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_order_field_mapping
                 WHERE target_path = "' . pSQL($target) . '" AND id != ' . (int) $id
            );
            if ($duplicate) {
                throw new Exception('Ya existe una regla para ese campo destino. Edite la regla existente.');
            }
            $data = [
                'target_path' => pSQL($target),
                'template' => pSQL($template, true),
                'field_type' => pSQL($type),
                'default_value' => $default === '' ? null : pSQL($default),
                'is_active' => $active,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($id > 0) {
                $ok = Db::getInstance()->update('yuju_order_field_mapping', $data, 'id = ' . $id);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $ok = Db::getInstance()->insert('yuju_order_field_mapping', $data);
            }
            if (!$ok) {
                throw new Exception('No se pudo guardar el mapeo de campos.');
            }
            $response['success'] = true;
            $response['message'] = 'Mapeo de campo guardado.';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error saving order field mapping: ' . $e->getMessage(), 'error');
        }
        die(json_encode($response));
    }

    public function ajaxProcessDeleteFieldMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];
        try {
            $id = (int) Tools::getValue('id_field_mapping');
            if ($id <= 0) {
                throw new Exception('ID de mapeo de campo inválido.');
            }
            $ok = Db::getInstance()->delete('yuju_order_field_mapping', 'id = ' . $id);
            if (!$ok) {
                throw new Exception('No se pudo eliminar el mapeo de campo.');
            }
            $response['success'] = true;
            $response['message'] = 'Mapeo de campo eliminado.';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        die(json_encode($response));
    }

    public function ajaxProcessToggleFieldMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];
        try {
            $id = (int) Tools::getValue('id_field_mapping');
            $active = (int) Tools::getValue('is_active') ? 1 : 0;
            if ($id <= 0) {
                throw new Exception('ID de mapeo de campo inválido.');
            }
            $ok = Db::getInstance()->update('yuju_order_field_mapping', [
                'is_active' => $active,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ' . $id);
            if (!$ok) {
                throw new Exception('No se pudo cambiar el estado del mapeo.');
            }
            $response['success'] = true;
            $response['message'] = $active ? 'Mapeo activado.' : 'Mapeo desactivado.';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        die(json_encode($response));
    }

    /**
     * Carga explícitamente los recursos visuales del módulo en esta página.
     * El dashboard ya lo hace desde su controlador; las páginas de mapeo
     * no deben depender de que los hooks del módulo estén registrados.
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        if ($this->module) {
            $this->addCSS($this->module->getPathUri() . 'views/css/admin.css?v=20261013-menu-fix');
            $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
        }
    }
}
