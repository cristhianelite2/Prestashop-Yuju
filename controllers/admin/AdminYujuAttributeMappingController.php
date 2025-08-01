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
            'sync_direction' => [
                'title' => $this->trans('Sync Direction', array(), 'Modules.Prestashopyuju.Admin'),
                'align' => 'center',
                'width' => 120,
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
            ],
            'disableMapping' => [
                'text' => $this->trans('Disable mapping', array(), 'Modules.Prestashopyuju.Admin'),
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
        
        $this->context->smarty->assign('current_controller', 'AdminYujuAttributeMapping');
        parent::initContent();
        
        $this->setTemplate('attribute_mapping.tpl');
    }

    public function renderList()
    {
        $this->_select = '
            agl.name as prestashop_attribute_name,
            (
                SELECT COUNT(*)
                FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping avm
                WHERE avm.attribute_mapping_id = a.id
            ) as value_mapping_count
        ';

        $this->_join = '
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group ag ON (a.prestashop_attribute_id = ag.id_attribute_group)
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON (ag.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . (int) $this->context->language->id . ')
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

        // Sync directions
        $sync_directions = [
            ['id' => 'ps_to_yuju', 'name' => $this->trans('PrestaShop → Yuju', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'yuju_to_ps', 'name' => $this->trans('Yuju → PrestaShop', array(), 'Modules.Prestashopyuju.Admin')],
            ['id' => 'bidirectional', 'name' => $this->trans('Bidirectional', array(), 'Modules.Prestashopyuju.Admin')],
        ];

        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Attribute Mapping', array(), 'Modules.Prestashopyuju.Admin'),
                'icon' => 'icon-list-alt',
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->trans('PrestaShop Attribute', array(), 'Modules.Prestashopyuju.Admin'),
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
                    'label' => $this->trans('Yuju Attribute', array(), 'Modules.Prestashopyuju.Admin'),
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
                    'label' => $this->trans('Attribute Type', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'attribute_type',
                    'required' => true,
                    'options' => [
                        'query' => $attribute_types,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->trans('Sync Direction', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'sync_direction',
                    'required' => true,
                    'options' => [
                        'query' => $sync_directions,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Auto Create Values', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'auto_create_values',
                    'desc' => $this->trans('Automatically create missing attribute values during sync', array(), 'Modules.Prestashopyuju.Admin'),
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'auto_create_values_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                        ],
                        [
                            'id' => 'auto_create_values_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Active', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'is_active',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', array(), 'Admin.Actions'),
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
            AND id != ' . (int) Tools::getValue('id')
        );

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
            'sync_direction' => pSQL(Tools::getValue('sync_direction')),
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

    protected function syncYujuAttributes()
    {
        try {
            $api_client = new YujuApiClient();
            $attributes = $api_client->getAttributes();

            if ($attributes && isset($attributes['data'])) {
                // Store attributes in cache table for faster access
                Db::getInstance()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'yuju_attributes_cache');

                foreach ($attributes['data'] as $attribute) {
                    Db::getInstance()->insert('yuju_attributes_cache', [
                        'yuju_attribute_id' => pSQL($attribute['id']),
                        'name' => pSQL($attribute['name']),
                        'type' => pSQL($attribute['type'] ?? 'select'),
                        'required' => (int) ($attribute['required'] ?? 0),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    // Store attribute values if available
                    if (isset($attribute['values']) && is_array($attribute['values'])) {
                        foreach ($attribute['values'] as $value) {
                            Db::getInstance()->insert('yuju_attribute_values_cache', [
                                'yuju_attribute_id' => pSQL($attribute['id']),
                                'yuju_value_id' => pSQL($value['id']),
                                'value_name' => pSQL($value['name']),
                                'value_code' => pSQL($value['code'] ?? ''),
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }

                $this->confirmations[] = $this->trans('Yuju attributes synchronized successfully.', array(), 'Modules.Prestashopyuju.Admin');
                $this->logger->log('Yuju attributes synchronized: ' . count($attributes['data']) . ' attributes', 'info');
            } else {
                $this->errors[] = $this->trans('No attributes found in Yuju.', array(), 'Modules.Prestashopyuju.Admin');
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error synchronizing Yuju attributes: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
            $this->logger->log('Error synchronizing Yuju attributes: ' . $e->getMessage(), 'error');
        }
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
        $result = Db::getInstance()->getRow('
            SELECT name FROM ' . _DB_PREFIX_ . 'yuju_attributes_cache
            WHERE yuju_attribute_id = "' . pSQL($attribute_id) . '"
        ');

        return $result ? $result['name'] : false;
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

        // Get PrestaShop attribute values
        $ps_values = AttributeGroup::getAttributes($this->context->language->id, $mapping['prestashop_attribute_id']);

        // Get Yuju attribute values
        $yuju_values = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_values_cache
            WHERE yuju_attribute_id = "' . pSQL($mapping['yuju_attribute_id']) . '"
        ');

        // Get existing value mappings
        $existing_mappings = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping
            WHERE attribute_mapping_id = ' . (int) $id
        );

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
}