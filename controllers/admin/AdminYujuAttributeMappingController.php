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

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuApiClient.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuAttributeMapping.php';

class AdminYujuAttributeMappingController extends ModuleAdminController
{
    protected $logger;

    public function __construct()
    {
        $this->table = 'yuju_attribute_mapping';
        $this->className = 'YujuAttributeMapping';
        $this->identifier = 'id';
        $this->bootstrap = true;
        $this->lang = false;

        parent::__construct();

        $this->logger = new YujuLogger();

        $this->fields_list = [
            'id' => [
                'title' => $this->trans('ID', array(), 'Modules.Prestashopyuju.Admin'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'prestashop_attribute_name' => [
                'title' => $this->trans('PrestaShop Attribute', array(), 'Modules.Prestashopyuju.Admin'),
                'width' => 200,
            ],
            'yuju_attribute_id' => [
                'title' => $this->trans('Yuju Attribute ID', array(), 'Modules.Prestashopyuju.Admin'),
                'align' => 'center',
                'width' => 150,
            ],
            'yuju_attribute_name' => [
                'title' => $this->trans('Yuju Attribute Name', array(), 'Modules.Prestashopyuju.Admin'),
                'width' => 200,
            ],
            'attribute_type' => [
                'title' => $this->trans('Type', array(), 'Modules.Prestashopyuju.Admin'),
                'align' => 'center',
                'width' => 100,
            ],

            'value_mapping_count' => [
                'title' => $this->trans('Value Mappings', array(), 'Modules.Prestashopyuju.Admin'),
                'align' => 'center',
                'width' => 100,
            ],
            'is_active' => [
                'title' => $this->trans('Active', array(), 'Modules.Prestashopyuju.Admin'),
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'class' => 'fixed-width-sm',
            ],
        ];

        $this->actions = ['edit', 'delete', 'manage_values'];
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Delete selected', array(), 'Modules.Prestashopyuju.Admin'),
                'confirm' => $this->trans('Delete selected items?', array(), 'Modules.Prestashopyuju.Admin'),
            ],
            'enableMapping' => [
                'text' => $this->trans('Enable mapping', array(), 'Modules.Prestashopyuju.Admin'),
                'icon' => 'icon-check'
            ],
            'disableMapping' => [
                'text' => $this->trans('Disable mapping', array(), 'Modules.Prestashopyuju.Admin'),
                'icon' => 'icon-remove'
            ],
        ];

        $this->toolbar_btn['new'] = [
            'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
            'desc' => $this->trans('Add new mapping', array(), 'Modules.Prestashopyuju.Admin'),
        ];

        $this->toolbar_btn['sync_attributes'] = [
            'href' => self::$currentIndex . '&syncYujuAttributes&token=' . $this->token,
            'desc' => $this->trans('Sync Yuju Attributes', array(), 'Modules.Prestashopyuju.Admin'),
            'class' => 'process-icon-refresh',
        ];
    }

    public function initContent()
    {
        if (Tools::isSubmit('manageValues')) {
            $this->manageAttributeValues();
            return;
        }
        
        $yuju_attributes_count = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache'
        );
        if ($yuju_attributes_count === 0) {
            $this->loadFallbackAttributesFromJson();
            $yuju_attributes_count = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache'
            );
        }

        $yuju_attributes_for_modal = $this->getYujuAllowedValueOptionsForModal();
        if (empty($yuju_attributes_for_modal)) {
            $yuju_attributes_for_modal = Db::getInstance()->executeS('
                SELECT yuju_attribute_id AS id, name, type
                FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
                ORDER BY type ASC, name ASC
            ') ?: [];
        }

        // Assign data for the template
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuAttributeMapping',
            'attribute_mappings' => $this->getAttributeMappings(),
            'prestashop_attributes' => $this->getPrestashopAttributes(),
            'prestashop_attribute_groups' => $this->getPrestashopAttributeGroups(),
            'yuju_attributes' => $this->getYujuAttributes(),
            'yuju_attributes_for_modal' => $yuju_attributes_for_modal,
            'yuju_attribute_groups' => $this->getYujuAttributeGroups(),
            'yuju_attributes_count' => $yuju_attributes_count,

            'ajax_url' => $this->context->link->getAdminLink('AdminYujuAttributeMapping'),
            'token' => $this->token,
        ]);
        
        parent::initContent();
        
        $this->setTemplate('attribute_mapping.tpl');
    }

    public function renderList()
    {
        $this->_select = '
            al.name as prestashop_attribute_name,
            (
                SELECT COUNT(*)
                FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping avm
                WHERE avm.attribute_mapping_id = a.id
            ) as value_mapping_count
        ';

        $id_lang = (int) $this->context->language->id;
        $this->_join = '
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute attr_ps ON (a.prestashop_attribute_id = attr_ps.id_attribute)
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON (attr_ps.id_attribute = al.id_attribute AND al.id_lang = ' . $id_lang . ')
        ';

        $this->_orderBy = 'a.id';
        $this->_orderWay = 'DESC';

        return parent::renderList();
    }

    public function renderForm()
    {
        if (Tools::isSubmit('syncYujuAttributes')) {
            $this->syncYujuAttributes();
            Tools::redirectAdmin(self::$currentIndex . '&token=' . $this->token);
        }

        // Get PrestaShop attributes
        $ps_attributes = [];
        $attribute_groups = AttributeGroup::getAttributesGroups($this->context->language->id);
        foreach ($attribute_groups as $group) {
            $ps_attributes[] = [
                'id' => $group['id_attribute_group'],
                'name' => $group['name'],
            ];
        }

        // Get Yuju attributes
        $yuju_attributes = $this->getYujuAttributes();

        // Attribute types
        $attribute_types = [
            ['id' => 'select', 'name' => $this->trans('Select', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'text', 'name' => $this->trans('Text', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'number', 'name' => $this->trans('Number', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'boolean', 'name' => $this->trans('Boolean', array(), 'Modules.Prestashopyuju.Admin')],
        ];



        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Mapeo de Atributos', array(), 'Modules.Prestashopyuju.Admin'),
                'icon' => 'icon-list-alt',
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->trans('Atributo PrestaShop', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'prestashop_attribute_id',
                    'required' => true,
                    'options' => [
                        'query' => $ps_attributes,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->trans('Atributo Yuju', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'yuju_attribute_id',
                    'required' => true,
                    'options' => [
                        'query' => $yuju_attributes,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->trans('Tipo de Atributo', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'attribute_type',
                    'required' => true,
                    'options' => [
                        'query' => $attribute_types,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],

                [
                    'type' => 'switch',
                    'label' => $this->trans('Crear Valores Automáticamente', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'auto_create_values',
                    'desc' => $this->trans('Crear automáticamente valores de atributos faltantes durante la sincronización', array(), 'Modules.Prestashopyuju.Admin'),
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'auto_create_values_on',
                            'value' => 1,
                            'label' => $this->trans('Habilitado', array(), 'Admin.Global'),
                        ],
                        [
                            'id' => 'auto_create_values_off',
                            'value' => 0,
                            'label' => $this->trans('Deshabilitado', array(), 'Admin.Global'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Activo', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'is_active',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Habilitado', array(), 'Admin.Global'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Deshabilitado', array(), 'Admin.Global'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->trans('Guardar', array(), 'Admin.Actions'),
            ],
        ];

        return parent::renderForm();
    }

    public function processSave()
    {
        $prestashop_attribute_id = (int) Tools::getValue('prestashop_attribute_id');
        $yuju_attribute_id = Tools::getValue('yuju_attribute_id');

        // Check if mapping already exists
        $existing = Db::getInstance()->getRow('
            SELECT id FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE prestashop_attribute_id = ' . (int) $prestashop_attribute_id . '
            AND id != ' . (int) Tools::getValue('id') . '
        ');

        if ($existing) {
            $this->errors[] = $this->trans('This PrestaShop attribute is already mapped.', array(), 'Modules.Prestashopyuju.Admin');

            return false;
        }

        // Get Yuju attribute name
        $yuju_attribute_name = $this->getYujuAttributeName($yuju_attribute_id);

        if (!$yuju_attribute_name) {
            $this->errors[] = $this->trans('Invalid Yuju attribute selected.', array(), 'Modules.Prestashopyuju.Admin');

            return false;
        }

        $data = [
            'prestashop_attribute_id' => $prestashop_attribute_id,
            'yuju_attribute_id' => pSQL($yuju_attribute_id),
            'yuju_attribute_name' => pSQL($yuju_attribute_name),
            'attribute_type' => pSQL(Tools::getValue('attribute_type')),
            'sync_direction' => 'prestashop_to_yuju',
            'auto_create_values' => (int) Tools::getValue('auto_create_values'),
            'is_active' => (int) Tools::getValue('is_active'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (Tools::getValue('id')) {
            // Update
            $result = Db::getInstance()->update(
                'yuju_attribute_mapping',
                $data,
                'id = ' . (int) Tools::getValue('id')
            );
        } else {
            // Insert
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert('yuju_attribute_mapping', $data);
        }

        if ($result) {
            $this->confirmations[] = $this->trans('Attribute mapping saved successfully.', array(), 'Modules.Prestashopyuju.Admin');
            $this->logger->log('Attribute mapping saved: PS Attribute ' . $prestashop_attribute_id . ' -> Yuju Attribute ' . $yuju_attribute_id, 'info');
        } else {
            $this->errors[] = $this->trans('Error saving attribute mapping.', array(), 'Modules.Prestashopyuju.Admin');

            return false;
        }
    }

    public function displayManageValuesLink($token, $id)
    {
        return '<a class="btn btn-default" href="' . self::$currentIndex . '&manageValues&id=' . $id . '&token=' . $token . '">
            <i class="icon-cogs"></i> ' . $this->trans('Manage Values', array(), 'Modules.Prestashopyuju.Admin') . '
        </a>';
    }

    /**
     * La API devuelve el JSON en $apiResult['data']; los ítems suelen ir en ['data'] anidado.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractYujuAttributeItemsFromApiResult($apiResult)
    {
        if (!is_array($apiResult) || empty($apiResult['success']) || !isset($apiResult['data']) || !is_array($apiResult['data'])) {
            return [];
        }

        $body = $apiResult['data'];

        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        if (isset($body['attributes']) && is_array($body['attributes'])) {
            return $body['attributes'];
        }

        $keys = array_keys($body);
        if ($keys === range(0, count($body) - 1)) {
            return $body;
        }

        if (isset($body['id'])) {
            return [$body];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $attribute
     *
     * @return array{id: string, name: string, type: string, required: int, values: array<int, array<string, mixed>>}
     */
    protected function normalizeYujuAttributeRow($attribute)
    {
        $id = '';
        if (isset($attribute['id'])) {
            $id = (string) $attribute['id'];
        } elseif (isset($attribute['attribute_id'])) {
            $id = (string) $attribute['attribute_id'];
        }

        $name = (string) ($attribute['name'] ?? $attribute['label'] ?? $attribute['title'] ?? $id);
        $typeRaw = $attribute['type'] ?? $attribute['data_type'] ?? $attribute['field_type'] ?? 'select';
        $type = is_string($typeRaw) ? $typeRaw : 'select';

        $values = [];
        if (isset($attribute['values']) && is_array($attribute['values'])) {
            $values = $attribute['values'];
        } elseif (isset($attribute['options']) && is_array($attribute['options'])) {
            $values = $attribute['options'];
        }

        return [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'required' => (int) ($attribute['required'] ?? 0),
            'values' => $values,
        ];
    }

    protected function syncYujuAttributes()
    {
        try {
            $api_client = new YujuApiClient();
            $apiResult = $api_client->getAttributes();
            $items = $this->extractYujuAttributeItemsFromApiResult($apiResult);

            if (!empty($items)) {
                $this->persistYujuAttributesInCache($items);

                $this->confirmations[] = $this->trans('Yuju attributes synchronized successfully.', array(), 'Modules.Prestashopyuju.Admin');
                $this->logger->log('Yuju attributes synchronized: ' . count($items) . ' attributes', 'info');
            } else {
                $loaded_from_json = $this->loadFallbackAttributesFromJson();
                if (!$loaded_from_json) {
                    $this->errors[] = $this->trans('No attributes found in Yuju.', array(), 'Modules.Prestashopyuju.Admin');
                    if (is_array($apiResult) && !empty($apiResult['message'])) {
                        $this->errors[] = $apiResult['message'];
                    }
                }
            }
        } catch (Exception $e) {
            $loaded_from_json = $this->loadFallbackAttributesFromJson();
            if (!$loaded_from_json) {
                $this->errors[] = $this->trans('Error synchronizing Yuju attributes: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
                $this->logger->log('Error synchronizing Yuju attributes: ' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function persistYujuAttributesInCache($items)
    {
        Db::getInstance()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'yuju_attributes_cache');
        Db::getInstance()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'yuju_attribute_values_cache');

        foreach ($items as $attribute) {
            $row = $this->normalizeYujuAttributeRow($attribute);
            if ($row['id'] === '') {
                continue;
            }

            Db::getInstance()->insert('yuju_attributes_cache', [
                'yuju_attribute_id' => pSQL($row['id']),
                'name' => pSQL($row['name']),
                'type' => pSQL($row['type']),
                'required' => $row['required'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!empty($row['values'])) {
                foreach ($row['values'] as $value) {
                    if (!is_array($value)) {
                        continue;
                    }
                    $vid = isset($value['id']) ? (string) $value['id'] : (isset($value['value_id']) ? (string) $value['value_id'] : '');
                    if ($vid === '') {
                        continue;
                    }
                    $vname = (string) ($value['name'] ?? $value['label'] ?? $value['value'] ?? $vid);
                    Db::getInstance()->insert('yuju_attribute_values_cache', [
                        'yuju_attribute_id' => pSQL($row['id']),
                        'yuju_value_id' => pSQL($vid),
                        'value_name' => pSQL($vname),
                        'value_code' => pSQL($value['code'] ?? ''),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    protected function getAllowedValuesJsonPath()
    {
        return _PS_MODULE_DIR_ . 'prestashopyuju/config/yuju_allowed_values.json';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getFallbackAttributesFromJson()
    {
        $path = $this->getAllowedValuesJsonPath();
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['attributes']) || !is_array($decoded['attributes'])) {
            return [];
        }

        return $decoded['attributes'];
    }

    /**
     * Opciones del modal: IDs de valor Yuju (p. ej. agua, rojo) según yuju_allowed_values.json.
     *
     * @return array<int, array{id: string, name: string, type: string}>
     */
    protected function getYujuAllowedValueOptionsForModal()
    {
        $attrs = $this->getFallbackAttributesFromJson();
        if (empty($attrs)) {
            return [];
        }

        $seen = [];
        $out = [];
        foreach ($attrs as $attr) {
            if (!is_array($attr) || empty($attr['values']) || !is_array($attr['values'])) {
                continue;
            }
            $ptype = isset($attr['type']) ? (string) $attr['type'] : 'custom';
            foreach ($attr['values'] as $v) {
                if (!is_array($v)) {
                    continue;
                }
                $vid = isset($v['id']) ? (string) $v['id'] : '';
                if ($vid === '' || isset($seen[$vid])) {
                    continue;
                }
                $seen[$vid] = true;
                $vname = (string) ($v['name'] ?? $vid);
                $out[] = [
                    'id' => $vid,
                    'name' => $vname,
                    'type' => $ptype,
                ];
            }
        }

        usort($out, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    /**
     * @param string $value_id
     *
     * @return string|false
     */
    protected function getYujuValueLabelFromJson($value_id)
    {
        $value_id = (string) $value_id;
        if ($value_id === '') {
            return false;
        }
        foreach ($this->getFallbackAttributesFromJson() as $attr) {
            if (empty($attr['values']) || !is_array($attr['values'])) {
                continue;
            }
            foreach ($attr['values'] as $v) {
                if (!is_array($v)) {
                    continue;
                }
                if ((string) ($v['id'] ?? '') === $value_id) {
                    return (string) ($v['name'] ?? $value_id);
                }
            }
        }

        return false;
    }

    /**
     * @param string $value_id
     *
     * @return string Tipo API (color, category, …) o cadena vacía
     */
    protected function getYujuApiTypeFromJsonForValueId($value_id)
    {
        $value_id = (string) $value_id;
        if ($value_id === '') {
            return '';
        }
        foreach ($this->getFallbackAttributesFromJson() as $attr) {
            if (empty($attr['values']) || !is_array($attr['values']) || !isset($attr['type'])) {
                continue;
            }
            foreach ($attr['values'] as $v) {
                if (!is_array($v)) {
                    continue;
                }
                if ((string) ($v['id'] ?? '') === $value_id) {
                    return (string) $attr['type'];
                }
            }
        }

        return '';
    }

    /**
     * Tipo API para filtros del modal (legacy: id de atributo padre; nuevo: id de valor).
     *
     * @param string $id
     *
     * @return string
     */
    protected function getYujuApiTypeForMappingValue($id)
    {
        $id = trim((string) $id);
        if ($id === '') {
            return '';
        }
        $row = Db::getInstance()->getRow('
            SELECT type FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
            WHERE yuju_attribute_id = "' . pSQL($id) . '"
        ');
        if ($row && !empty($row['type'])) {
            return (string) $row['type'];
        }
        $row = Db::getInstance()->getRow('
            SELECT yac.type
            FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache yavc
            INNER JOIN ' . _DB_PREFIX_ . 'yuju_attributes_cache yac
                ON (yavc.yuju_attribute_id = yac.yuju_attribute_id)
            WHERE yavc.yuju_value_id = "' . pSQL($id) . '"
        ');
        if ($row && !empty($row['type'])) {
            return (string) $row['type'];
        }
        $t = $this->getYujuApiTypeFromJsonForValueId($id);

        return $t !== '' ? $t : '';
    }

    /**
     * @param string $apiType
     *
     * @return string
     */
    protected function mapYujuApiTypeToAttributeType($apiType)
    {
        $apiType = (string) $apiType;
        if (in_array($apiType, ['color', 'size', 'material'], true)) {
            return $apiType;
        }

        return 'custom';
    }

    /**
     * @param string $value_id
     *
     * @return string|false yuju_attribute_id padre o false
     */
    protected function resolveYujuParentAttributeIdFromValueId($value_id)
    {
        $value_id = trim((string) $value_id);
        if ($value_id === '') {
            return false;
        }
        $pid = Db::getInstance()->getValue('
            SELECT yuju_attribute_id FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache
            WHERE yuju_value_id = "' . pSQL($value_id) . '"
        ');
        if ($pid) {
            return (string) $pid;
        }
        foreach ($this->getFallbackAttributesFromJson() as $attr) {
            if (empty($attr['id']) || empty($attr['values']) || !is_array($attr['values'])) {
                continue;
            }
            foreach ($attr['values'] as $v) {
                if (!is_array($v)) {
                    continue;
                }
                if ((string) ($v['id'] ?? '') === $value_id) {
                    return (string) $attr['id'];
                }
            }
        }

        return false;
    }

    protected function loadFallbackAttributesFromJson()
    {
        $fallback_items = $this->getFallbackAttributesFromJson();
        if (empty($fallback_items)) {
            return false;
        }

        $this->persistYujuAttributesInCache($fallback_items);
        $this->confirmations[] = $this->trans('Yuju values loaded from local JSON fallback.', array(), 'Modules.Prestashopyuju.Admin');
        $this->logger->log('Fallback JSON loaded for Yuju attributes. Count: ' . count($fallback_items), 'warning');

        return true;
    }

    protected function getYujuAttributes()
    {
        $attributes = [];

        $result = Db::getInstance()->executeS('
            SELECT yuju_attribute_id as id, name, type
            FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
            ORDER BY name
        ');

        if ($result) {
            foreach ($result as $attribute) {
                $attributes[] = [
                    'id' => $attribute['id'],
                    'name' => $attribute['name'] . ' (' . $attribute['type'] . ')',
                ];
            }
        }

        return $attributes;
    }

    protected function getYujuAttributeName($attribute_id)
    {
        $attribute_id = trim((string) $attribute_id);
        if ($attribute_id === '') {
            return false;
        }
        $result = Db::getInstance()->getRow('
            SELECT name FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
            WHERE yuju_attribute_id = "' . pSQL($attribute_id) . '"
        ');
        if ($result) {
            return $result['name'];
        }
        $result = Db::getInstance()->getRow('
            SELECT value_name AS name FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache
            WHERE yuju_value_id = "' . pSQL($attribute_id) . '"
        ');
        if ($result) {
            return $result['name'];
        }

        return $this->getYujuValueLabelFromJson($attribute_id);
    }

    /**
     * Tipo almacenado en BD (enum) a partir del id (atributo padre o valor permitido).
     */
    protected function inferAttributeTypeForMapping($yuju_attribute_id)
    {
        $yuju_attribute_id = trim((string) $yuju_attribute_id);
        if ($yuju_attribute_id === '') {
            return 'custom';
        }
        $row = Db::getInstance()->getRow('
            SELECT type FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
            WHERE yuju_attribute_id = "' . pSQL($yuju_attribute_id) . '"
        ');
        if ($row && $row['type'] !== '' && $row['type'] !== null) {
            return $this->mapYujuApiTypeToAttributeType((string) $row['type']);
        }
        $row = Db::getInstance()->getRow('
            SELECT yac.type
            FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache yavc
            INNER JOIN ' . _DB_PREFIX_ . 'yuju_attributes_cache yac
                ON (yavc.yuju_attribute_id = yac.yuju_attribute_id)
            WHERE yavc.yuju_value_id = "' . pSQL($yuju_attribute_id) . '"
        ');
        if ($row && $row['type'] !== '' && $row['type'] !== null) {
            return $this->mapYujuApiTypeToAttributeType((string) $row['type']);
        }
        $apiType = $this->getYujuApiTypeFromJsonForValueId($yuju_attribute_id);
        if ($apiType !== '') {
            return $this->mapYujuApiTypeToAttributeType($apiType);
        }

        return 'custom';
    }

    protected function processBulkEnableMapping()
    {
        $ids = Tools::getValue($this->table . 'Box');

        if (is_array($ids) && count($ids)) {
            $result = Db::getInstance()->update(
                'yuju_attribute_mapping',
                ['is_active' => 1],
                'id IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->trans('Selected mappings enabled.', array(), 'Modules.Prestashopyuju.Admin');
            }
        }
    }

    protected function processBulkDisableMapping()
    {
        $ids = Tools::getValue($this->table . 'Box');

        if (is_array($ids) && count($ids)) {
            $result = Db::getInstance()->update(
                'yuju_attribute_mapping',
                ['is_active' => 0],
                'id IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->trans('Selected mappings disabled.', array(), 'Modules.Prestashopyuju.Admin');
            }
        }
    }

    protected function manageAttributeValues()
    {
        $id = (int) Tools::getValue('id');

        if (!$id) {
            $this->errors[] = $this->trans('Invalid mapping ID.', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        // Get mapping details
        $mapping = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE id = ' . (int) $id
        );

        if (!$mapping) {
            $this->errors[] = $this->trans('Mapping not found.', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        $mapping['id_mapping'] = (int) $mapping['id'];

        $id_attribute_group = (int) Db::getInstance()->getValue('
            SELECT id_attribute_group FROM ' . _DB_PREFIX_ . 'attribute
            WHERE id_attribute = ' . (int) $mapping['prestashop_attribute_id'] . '
        ');

        $ps_values = AttributeGroup::getAttributes($this->context->language->id, $id_attribute_group);

        $yuju_parent_id = $this->resolveYujuParentAttributeIdFromValueId($mapping['yuju_attribute_id']);
        if (!$yuju_parent_id) {
            $yuju_parent_id = $mapping['yuju_attribute_id'];
        }

        $yuju_values = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache
            WHERE yuju_attribute_id = "' . pSQL($yuju_parent_id) . '"
        ');

        $existing_mappings = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping
            WHERE attribute_mapping_id = ' . (int) $id . '
        ');

        $this->context->smarty->assign([
            'mapping' => $mapping,
            'ps_values' => $ps_values,
            'yuju_values' => $yuju_values,
            'existing_mappings' => $existing_mappings,
            'current_index' => self::$currentIndex,
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/attribute_value_mapping.tpl');
    }

    // AJAX Methods
    public function ajaxProcessSaveMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            $prestashop_attribute_id = (int) Tools::getValue('prestashop_attribute_id');
            $yuju_attribute_id = trim((string) Tools::getValue('yuju_attribute_id'));

            $attribute_type = $this->inferAttributeTypeForMapping($yuju_attribute_id);
            $sync_direction = 'prestashop_to_yuju';
            $auto_create_values = 1;
            $is_active = 1;

            if ($id) {
                $prev = Db::getInstance()->getRow('
                    SELECT is_active, auto_create_values FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
                    WHERE id = ' . (int) $id . '
                ');
                if ($prev) {
                    $is_active = (int) $prev['is_active'];
                    $auto_create_values = (int) $prev['auto_create_values'];
                }
            }

            if (!$prestashop_attribute_id || $yuju_attribute_id === '') {
                throw new Exception($this->trans('All required fields must be filled.', array(), 'Modules.Prestashopyuju.Admin'));
            }

            // Check if mapping already exists
            $existing = Db::getInstance()->getRow('
                SELECT id FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
                WHERE prestashop_attribute_id = ' . (int) $prestashop_attribute_id . '
                AND id != ' . (int) $id . '
            ');

            if ($existing) {
                throw new Exception($this->trans('This PrestaShop attribute is already mapped.', array(), 'Modules.Prestashopyuju.Admin'));
            }

            $yuju_attribute_name = $this->getYujuAttributeName($yuju_attribute_id);
            if (!$yuju_attribute_name) {
                throw new Exception($this->trans('Invalid Yuju attribute selected.', array(), 'Modules.Prestashopyuju.Admin'));
            }

            $id_lang = (int) $this->context->language->id;
            $prestashop_attribute_name = (string) Db::getInstance()->getValue('
                SELECT al.name
                FROM ' . _DB_PREFIX_ . 'attribute_lang al
                WHERE al.id_attribute = ' . (int) $prestashop_attribute_id . '
                AND al.id_lang = ' . $id_lang . '
            ');

            $data = [
                'prestashop_attribute_id' => $prestashop_attribute_id,
                'prestashop_attribute_name' => pSQL($prestashop_attribute_name),
                'yuju_attribute_id' => pSQL($yuju_attribute_id),
                'yuju_attribute_name' => pSQL($yuju_attribute_name),
                'attribute_type' => pSQL($attribute_type),
                'sync_direction' => pSQL($sync_direction),
                'auto_create_values' => $auto_create_values,
                'is_active' => $is_active,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($id) {
                $result = Db::getInstance()->update(
                    'yuju_attribute_mapping',
                    $data,
                    'id = ' . (int) $id
                );
                $message = $this->trans('Attribute mapping updated successfully.', array(), 'Modules.Prestashopyuju.Admin');
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = Db::getInstance()->insert('yuju_attribute_mapping', $data);
                $message = $this->trans('Attribute mapping created successfully.', array(), 'Modules.Prestashopyuju.Admin');
            }

            if ($result) {
                $response['success'] = true;
                $response['message'] = $message;
                $this->logger->log('Attribute mapping saved: PS Attribute ' . $prestashop_attribute_id . ' -> Yuju Attribute ' . $yuju_attribute_id, 'info');
            } else {
                throw new Exception($this->trans('Error saving attribute mapping.', array(), 'Modules.Prestashopyuju.Admin'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error saving attribute mapping: ' . $e->getMessage(), 'error');
        }

        die(json_encode($response));
    }

    public function ajaxProcessGetMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'data' => null];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            if (!$id) {
                throw new Exception($this->trans('Invalid mapping ID.', array(), 'Modules.Prestashopyuju.Admin'));
            }

            $mapping = Db::getInstance()->getRow('
                SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
                WHERE id = ' . (int) $id . '
            ');

            if ($mapping) {
                $prestashop_attribute_group_id = (int) Db::getInstance()->getValue('
                    SELECT id_attribute_group FROM ' . _DB_PREFIX_ . 'attribute
                    WHERE id_attribute = ' . (int) $mapping['prestashop_attribute_id'] . '
                ');
                $yuju_attribute_group_id = $this->getYujuApiTypeForMappingValue($mapping['yuju_attribute_id']);

                $mapping['id_mapping'] = (int) $mapping['id'];
                $mapping['prestashop_attribute_group_id'] = $prestashop_attribute_group_id;
                $mapping['yuju_attribute_group_id'] = $yuju_attribute_group_id;

                $response['success'] = true;
                $response['data'] = $mapping;
            } else {
                throw new Exception($this->trans('Mapping not found.', array(), 'Modules.Prestashopyuju.Admin'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    public function ajaxProcessDeleteMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            if (!$id) {
                throw new Exception($this->trans('Invalid mapping ID.', array(), 'Modules.Prestashopyuju.Admin'));
            }

            $result = Db::getInstance()->delete(
                'yuju_attribute_mapping',
                'id = ' . (int) $id
            );

            if ($result) {
                $response['success'] = true;
                $response['message'] = $this->trans('Attribute mapping deleted successfully.', array(), 'Modules.Prestashopyuju.Admin');
                $this->logger->log('Attribute mapping deleted: ID ' . $id, 'info');
            } else {
                throw new Exception($this->trans('Error deleting attribute mapping.', array(), 'Modules.Prestashopyuju.Admin'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error deleting attribute mapping: ' . $e->getMessage(), 'error');
        }

        die(json_encode($response));
    }

    public function ajaxProcessToggleMapping()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => ''];

        try {
            $id = (int) Tools::getValue('id_mapping', Tools::getValue('id'));
            $status = (int) Tools::getValue('status', Tools::getValue('is_active'));

            if (!$id) {
                throw new Exception($this->trans('Invalid mapping ID.', array(), 'Modules.Prestashopyuju.Admin'));
            }

            $result = Db::getInstance()->update(
                'yuju_attribute_mapping',
                ['is_active' => $status],
                'id = ' . (int) $id
            );

            if ($result) {
                $response['success'] = true;
                $response['message'] = $status ? 
                    $this->trans('Attribute mapping enabled.', array(), 'Modules.Prestashopyuju.Admin') :
                    $this->trans('Attribute mapping disabled.', array(), 'Modules.Prestashopyuju.Admin');
                $this->logger->log('Attribute mapping status changed: ID ' . $id . ' -> ' . ($status ? 'enabled' : 'disabled'), 'info');
            } else {
                throw new Exception($this->trans('Error updating attribute mapping status.', array(), 'Modules.Prestashopyuju.Admin'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    public function ajaxProcessRefreshYujuAttributes()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => '', 'data' => []];

        try {
            $this->syncYujuAttributes();
            if (!empty($this->errors)) {
                $response['message'] = implode(' ', $this->errors);
                die(json_encode($response));
            }
            $attributes = $this->getYujuAttributes();

            $response['success'] = true;
            $response['message'] = $this->trans('Yuju attributes refreshed successfully.', array(), 'Modules.Prestashopyuju.Admin');
            $response['data'] = $attributes;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    public function ajaxProcessGetPrestashopAttributes()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'data' => [], 'message' => ''];

        try {
            $group_id = (int) Tools::getValue('group_id');
            $id_lang = (int) $this->context->language->id;

            if ($group_id <= 0) {
                throw new Exception('Grupo de PrestaShop inválido.');
            }

            $rows = Db::getInstance()->executeS('
                SELECT 
                    a.id_attribute AS id,
                    al.name
                FROM ' . _DB_PREFIX_ . 'attribute a
                INNER JOIN ' . _DB_PREFIX_ . 'attribute_lang al 
                    ON (
                        al.id_attribute = a.id_attribute
                        AND al.id_lang = ' . $id_lang . '
                    )
                WHERE a.id_attribute_group = ' . $group_id . '
                ORDER BY al.name ASC
            ');

            $response['success'] = true;
            $response['data'] = $rows ?: [];
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    public function ajaxProcessGetYujuAttributes()
    {
        header('Content-Type: application/json');
        $response = ['success' => false, 'data' => [], 'message' => ''];

        try {
            $group_id = trim((string) Tools::getValue('group_id', ''));

            $q = trim((string) Tools::getValue('q'));
            $qLower = $q !== '' ? mb_strtolower($q, 'UTF-8') : '';

            $fromJson = $this->getYujuAllowedValueOptionsForModal();
            if (!empty($fromJson)) {
                if ($group_id === '' || $group_id === 'all') {
                    $response['success'] = true;
                    $response['data'] = [];
                    die(json_encode($response));
                }
                $filtered = [];
                foreach ($fromJson as $opt) {
                    if ((string) $opt['type'] !== $group_id) {
                        continue;
                    }
                    if ($qLower !== '') {
                        $nameLower = mb_strtolower((string) $opt['name'], 'UTF-8');
                        $idLower = mb_strtolower((string) $opt['id'], 'UTF-8');
                        if (strpos($nameLower, $qLower) === false && strpos($idLower, $qLower) === false) {
                            continue;
                        }
                    }
                    $filtered[] = $opt;
                }
                $response['success'] = true;
                $response['data'] = $filtered;

                die(json_encode($response));
            }

            if ($group_id === '' || $group_id === 'all') {
                $response['success'] = true;
                $response['data'] = [];
                die(json_encode($response));
            }

            $whereParts = [];
            $whereParts[] = 'type = "' . pSQL($group_id) . '"';
            if ($q !== '') {
                $like = '%' . pSQL($q) . '%';
                $whereParts[] = '(name LIKE "' . $like . '" OR yuju_attribute_id LIKE "' . $like . '")';
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);

            $rows = Db::getInstance()->executeS('
                SELECT 
                    yuju_attribute_id AS id,
                    name,
                    type
                FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
                ' . $where . '
                ORDER BY name ASC
            ');

            $response['success'] = true;
            $response['data'] = $rows ?: [];
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    // Helper Methods
    protected function getAttributeMappings()
    {
        $mappings = [];

        $id_lang = (int) $this->context->language->id;

        $result = Db::getInstance()->executeS('
            SELECT 
                am.*,
                am.id AS id_mapping,
                al.name AS prestashop_attribute_name,
                agl.name AS prestashop_attribute_group_name,
                COALESCE(yac_direct.type, yac_val.type) AS yuju_attribute_type,
                (
                    SELECT COUNT(*)
                    FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping avm
                    WHERE avm.attribute_mapping_id = am.id
                ) as value_mapping_count
            FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping am
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON (am.prestashop_attribute_id = a.id_attribute)
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON (a.id_attribute = al.id_attribute AND al.id_lang = ' . $id_lang . ')
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON (a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . $id_lang . ')
            LEFT JOIN ' . _DB_PREFIX_ . 'yuju_attributes_cache yac_direct ON (am.yuju_attribute_id = yac_direct.yuju_attribute_id)
            LEFT JOIN (
                SELECT yuju_value_id, MIN(yuju_attribute_id) AS parent_attr_id
                FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache
                GROUP BY yuju_value_id
            ) yavc_one ON (am.yuju_attribute_id = yavc_one.yuju_value_id)
            LEFT JOIN ' . _DB_PREFIX_ . 'yuju_attributes_cache yac_val ON (yavc_one.parent_attr_id = yac_val.yuju_attribute_id)
            ORDER BY am.id DESC
        ');

        if ($result) {
            $mappings = $result;
        }

        return $mappings;
    }

    protected function getPrestashopAttributes()
    {
        $attributes = [];
        $attribute_groups = AttributeGroup::getAttributesGroups($this->context->language->id);
        
        foreach ($attribute_groups as $group) {
            $attributes[] = [
                'id' => $group['id_attribute_group'],
                'name' => $group['name'],
            ];
        }
        
        return $attributes;
    }

    protected function getAttributeTypes()
    {
        return [
            ['id' => 'select', 'name' => $this->trans('Seleccionar', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'text', 'name' => $this->trans('Texto', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'number', 'name' => $this->trans('Número', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'boolean', 'name' => $this->trans('Verdadero/Falso', array(), 'Modules.Prestashopyuju.Admin')],
        ];
    }

    protected function getPrestashopAttributeGroups()
    {
        $attribute_groups = AttributeGroup::getAttributesGroups($this->context->language->id);
        $groups = [];
        
        foreach ($attribute_groups as $group) {
            $groups[] = [
                'id' => $group['id_attribute_group'],
                'name' => $group['name'],
            ];
        }
        
        return $groups;
    }

    protected function getYujuAttributeGroups()
    {
        $typesSeen = [];
        foreach ($this->getFallbackAttributesFromJson() as $attr) {
            if (!is_array($attr) || empty($attr['type'])) {
                continue;
            }
            $t = (string) $attr['type'];
            if ($t !== '') {
                $typesSeen[$t] = true;
            }
        }

        $groups = [];
        if (!empty($typesSeen)) {
            $typeList = array_keys($typesSeen);
            sort($typeList, SORT_STRING);
            foreach ($typeList as $type) {
                $groups[] = [
                    'id' => $type,
                    'name' => sprintf($this->trans('Tipo de valor: %s', array(), 'Modules.Prestashopyuju.Admin'), $type),
                ];
            }

            return $groups;
        }

        $result = Db::getInstance()->executeS('
            SELECT DISTINCT type
            FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
            ORDER BY type
        ');

        if ($result) {
            foreach ($result as $row) {
                $type = (string) $row['type'];
                if ($type === '') {
                    continue;
                }
                $groups[] = [
                    'id' => $type,
                    'name' => sprintf($this->trans('Tipo de valor: %s', array(), 'Modules.Prestashopyuju.Admin'), $type),
                ];
            }
        }

        return $groups;
    }

    protected function getFieldTypes()
    {
        return [
            ['id' => 'string', 'name' => $this->trans('Cadena de texto', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'integer', 'name' => $this->trans('Número entero', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'decimal', 'name' => $this->trans('Número decimal', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'boolean', 'name' => $this->trans('Verdadero/Falso', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'date', 'name' => $this->trans('Fecha', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'datetime', 'name' => $this->trans('Fecha y Hora', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'text', 'name' => $this->trans('Texto', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'html', 'name' => $this->trans('HTML', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'json', 'name' => $this->trans('JSON', array(), 'Modules.Prestashopyuju.Admin')],
        ];
    }

    protected function getTransformationRules()
    {
        return [
            ['id' => 'none', 'name' => $this->trans('Ninguna', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'uppercase', 'name' => $this->trans('Mayúsculas', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'lowercase', 'name' => $this->trans('Minúsculas', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'capitalize', 'name' => $this->trans('Capitalizar', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'strip_html', 'name' => $this->trans('Eliminar HTML', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'strip_tags', 'name' => $this->trans('Eliminar Etiquetas', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'trim', 'name' => $this->trans('Recortar Espacios', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'number_format', 'name' => $this->trans('Formato de Número', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'date_format', 'name' => $this->trans('Formato de Fecha', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'boolean_convert', 'name' => $this->trans('Convertir a Booleano', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'custom', 'name' => $this->trans('Código PHP Personalizado', array(), 'Modules.Prestashopyuju.Admin')],
        ];
    }


}