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

        // Prellena las reglas comunes sin duplicar ni sobrescribir configuraciones existentes.
        $defaults = [
            'Cliente' => [
                ['customer.first_name', '%customer.first_name%', 'string', 'Not Found'],
                ['customer.last_name', '%customer.last_name%', 'string', 'Not Found'],
                ['customer.email', '%customer.email%', 'string', 'Not Found'],
                ['customer.phone', '%customer.phone%', 'string', 'Not Found'],
                ['customer.doc_number', '%customer.doc_number%', 'string', 'Not Found'],
            ],
            'Dirección de envío' => [
                ['shipping_address.first_name', '%shipping_address.first_name%', 'string', 'Not Found'],
                ['shipping_address.last_name', '%shipping_address.last_name%', 'string', 'Not Found'],
                ['shipping_address.address_line_1', '%shipping_address.address_line_1%', 'string', 'Not Found'],
                ['shipping_address.address_line_2', '%shipping_address.address_line_2%', 'string', 'Not Found'],
                ['shipping_address.city', '%shipping_address.city%', 'string', 'Not Found'],
                ['shipping_address.postal_code', '%shipping_address.postal_code%', 'string', 'Not Found'],
                ['shipping_address.phone', '%shipping_address.phone%', 'string', 'Not Found'],
                ['shipping_address.company', '%shipping_address.company%', 'string', 'Not Found'],
                ['shipping_address.dni', '%shipping_address.dni%', 'string', 'Not Found'],
                ['shipping_address.country_code', '%shipping_address.country_code%', 'string', 'MX'],
                ['shipping_address.state_code', '%shipping_address.state_code%', 'string', 'Not Found'],
            ],
            'Dirección de facturación' => [
                ['billing_address.first_name', '%billing_address.first_name%', 'string', 'Not Found'],
                ['billing_address.last_name', '%billing_address.last_name%', 'string', 'Not Found'],
                ['billing_address.address_line_1', '%billing_address.address_line_1%', 'string', 'Not Found'],
                ['billing_address.address_line_2', '%billing_address.address_line_2%', 'string', 'Not Found'],
                ['billing_address.city', '%billing_address.city%', 'string', 'Not Found'],
                ['billing_address.postal_code', '%billing_address.postal_code%', 'string', 'Not Found'],
                ['billing_address.phone', '%billing_address.phone%', 'string', 'Not Found'],
                ['billing_address.company', '%billing_address.company%', 'string', 'Not Found'],
                ['billing_address.dni', '%billing_address.dni%', 'string', 'Not Found'],
                ['billing_address.country_code', '%billing_address.country_code%', 'string', 'MX'],
                ['billing_address.state_code', '%billing_address.state_code%', 'string', 'Not Found'],
            ],
            'Carrito y productos' => [
                ['currency', '%currency%', 'string', 'MXN'],
                ['items.0.quantity', '%items.0.quantity%', 'integer', '1'],
                ['items.0.price', '%items.0.price%', 'decimal', '0'],
                ['items.0.name', '%items.0.name%', 'string', 'Not Found'],
                ['items.0.sku', '%items.0.sku%', 'string', 'Not Found'],
                ['items.0.product_id', '%items.0.product_id%', 'string', 'Not Found'],
                ['shipping_cost', '%shipping_cost%', 'decimal', '0'],
                ['products_total', '%products_total%', 'decimal', '0'],
            ],
            'Orden' => [
                ['id', '%id%', 'string', 'Not Found'],
                ['reference', '%reference%', 'string', 'Not Found'],
                ['status', '%status%', 'string', 'Not Found'],
                ['total_amount', '%total_amount%', 'decimal', '0'],
                ['total_amount_tax_excl', '%total_amount_tax_excl%', 'decimal', '0'],
                ['payment_method', '%payment_method%', 'string', 'Not Found'],
                ['shipping_method', '%shipping_method%', 'string', 'Not Found'],
                ['created_at', '%created_at%', 'string', 'Not Found'],
            ],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $group => $rules) {
            foreach ($rules as $rule) {
                $exists = Db::getInstance()->getValue(
                    'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_order_field_mapping WHERE target_path = "' . pSQL($rule[0]) . '"'
                );
                if ($exists) {
                    continue;
                }
                Db::getInstance()->insert('yuju_order_field_mapping', [
                    'target_path' => pSQL($rule[0]),
                    'template' => pSQL($rule[1], true),
                    'field_type' => pSQL($rule[2]),
                    'default_value' => pSQL($rule[3]),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function initContent()
    {
        $this->ensureDefaultMappings();

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuOrderStatusMapping',
            'status_mappings' => $this->getStatusMappings(),
            'field_mappings' => $this->getFieldMappings(),
            'field_mapping_groups' => $this->getFieldMappingGroups(),
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


    /** Agrupa las reglas para mostrarlas en acordeones compactos. */
    protected function getFieldMappingGroups()
    {
        $groups = [
            'Cliente' => [],
            'Dirección de envío' => [],
            'Dirección de facturación' => [],
            'Carrito y productos' => [],
            'Orden' => [],
            'Otros' => [],
        ];
        foreach ($this->getFieldMappings() as $row) {
            $path = (string) ($row['target_path'] ?? '');
            if (strpos($path, 'customer.') === 0) {
                $group = 'Cliente';
            } elseif (strpos($path, 'shipping_address.') === 0 || strpos($path, 'address.shipping.') === 0) {
                $group = 'Dirección de envío';
            } elseif (strpos($path, 'billing_address.') === 0 || strpos($path, 'address.billing.') === 0) {
                $group = 'Dirección de facturación';
            } elseif (strpos($path, 'items.') === 0 || strpos($path, 'cart.') === 0 || in_array($path, ['currency', 'shipping_cost', 'products_total'], true)) {
                $group = 'Carrito y productos';
            } elseif (in_array($path, ['id', 'reference', 'status', 'total_amount', 'total_amount_tax_excl', 'payment_method', 'shipping_method', 'created_at'], true) || strpos($path, 'order.') === 0) {
                $group = 'Orden';
            } else {
                $group = 'Otros';
            }
            $groups[$group][] = $row;
        }
        return $groups;
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
