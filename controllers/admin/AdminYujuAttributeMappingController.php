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
        $this->identifier = 'id_mapping';
        $this->bootstrap = true;
        $this->lang = false;

        parent::__construct();

        $this->logger = new YujuLogger();

        $this->fields_list = [
            'id_mapping' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'prestashop_attribute_name' => [
                'title' => $this->l('PrestaShop Attribute'),
                'width' => 200,
            ],
            'yuju_attribute_id' => [
                'title' => $this->l('Yuju Attribute ID'),
                'align' => 'center',
                'width' => 150,
            ],
            'yuju_attribute_name' => [
                'title' => $this->l('Yuju Attribute Name'),
                'width' => 200,
            ],
            'attribute_type' => [
                'title' => $this->l('Type'),
                'align' => 'center',
                'width' => 100,
            ],
            'sync_direction' => [
                'title' => $this->l('Sync Direction'),
                'align' => 'center',
                'width' => 120,
            ],
            'value_mapping_count' => [
                'title' => $this->l('Value Mappings'),
                'align' => 'center',
                'width' => 100,
            ],
            'is_active' => [
                'title' => $this->l('Active'),
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'class' => 'fixed-width-sm',
            ],
        ];

        $this->actions = ['edit', 'delete', 'manage_values'];
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
            ],
            'enableMapping' => [
                'text' => $this->l('Enable mapping'),
            ],
            'disableMapping' => [
                'text' => $this->l('Disable mapping'),
            ],
        ];

        $this->toolbar_btn['new'] = [
            'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
            'desc' => $this->l('Add new mapping'),
        ];

        $this->toolbar_btn['sync_attributes'] = [
            'href' => self::$currentIndex . '&syncYujuAttributes&token=' . $this->token,
            'desc' => $this->l('Sync Yuju Attributes'),
            'class' => 'process-icon-refresh',
        ];
    }

    public function renderList()
    {
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('manage_values');

        // Add custom SQL to get attribute names and value mapping count
        $this->_select = 'agl.name as prestashop_attribute_name,
                         (SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping
                          WHERE attribute_mapping_id = a.id_mapping) as value_mapping_count';
        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON (a.prestashop_attribute_id = agl.id_attribute_group AND agl.id_lang = ' . (int) $this->context->language->id . ')';

        return parent::renderList();
    }

    public function renderForm()
    {
        // Get PrestaShop attribute groups
        $attribute_groups = AttributeGroup::getAttributesGroups($this->context->language->id);
        $ps_attributes = [];

        foreach ($attribute_groups as $group) {
            $ps_attributes[] = [
                'id' => $group['id_attribute_group'],
                'name' => $group['name'],
            ];
        }

        // Get Yuju attributes
        $yuju_attributes = $this->getYujuAttributes();

        $attribute_types = [
            ['id' => 'select', 'name' => $this->l('Select')],
            ['id' => 'radio', 'name' => $this->l('Radio')],
            ['id' => 'color', 'name' => $this->l('Color')],
            ['id' => 'text', 'name' => $this->l('Text')],
            ['id' => 'textarea', 'name' => $this->l('Textarea')],
            ['id' => 'file', 'name' => $this->l('File')],
            ['id' => 'date', 'name' => $this->l('Date')],
        ];

        $sync_directions = [
            ['id' => 'ps_to_yuju', 'name' => $this->l('PrestaShop → Yuju')],
            ['id' => 'yuju_to_ps', 'name' => $this->l('Yuju → PrestaShop')],
            ['id' => 'bidirectional', 'name' => $this->l('Bidirectional')],
        ];

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Attribute Mapping'),
                'icon' => 'icon-list-alt',
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->l('PrestaShop Attribute'),
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
                    'label' => $this->l('Yuju Attribute'),
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
                    'label' => $this->l('Attribute Type'),
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
                    'label' => $this->l('Sync Direction'),
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
                    'label' => $this->l('Auto Create Values'),
                    'name' => 'auto_create_values',
                    'desc' => $this->l('Automatically create missing attribute values during sync'),
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'auto_create_values_on',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ],
                        [
                            'id' => 'auto_create_values_off',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ],
                    ],
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'is_active',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'is_active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'is_active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('syncYujuAttributes')) {
            $this->syncYujuAttributes();
        } elseif (Tools::isSubmit('submitBulkenableMapping')) {
            $this->processBulkEnableMapping();
        } elseif (Tools::isSubmit('submitBulkdisableMapping')) {
            $this->processBulkDisableMapping();
        }

        return parent::postProcess();
    }

    public function processSave()
    {
        $prestashop_attribute_id = (int) Tools::getValue('prestashop_attribute_id');
        $yuju_attribute_id = Tools::getValue('yuju_attribute_id');

        // Check if mapping already exists
        $existing = Db::getInstance()->getRow('
            SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE prestashop_attribute_id = ' . (int) $prestashop_attribute_id . '
            AND id_mapping != ' . (int) Tools::getValue('id_mapping')
        );

        if ($existing) {
            $this->errors[] = $this->l('This PrestaShop attribute is already mapped.');

            return false;
        }

        // Get Yuju attribute name
        $yuju_attribute_name = $this->getYujuAttributeName($yuju_attribute_id);

        if (!$yuju_attribute_name) {
            $this->errors[] = $this->l('Invalid Yuju attribute selected.');

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

        if (Tools::getValue('id_mapping')) {
            // Update
            $result = Db::getInstance()->update(
                'yuju_attribute_mapping',
                $data,
                'id_mapping = ' . (int) Tools::getValue('id_mapping')
            );
        } else {
            // Insert
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert('yuju_attribute_mapping', $data);
        }

        if ($result) {
            $this->confirmations[] = $this->l('Attribute mapping saved successfully.');
            $this->logger->log('Attribute mapping saved: PS Attribute ' . $prestashop_attribute_id . ' -> Yuju Attribute ' . $yuju_attribute_id, 'info');
        } else {
            $this->errors[] = $this->l('Error saving attribute mapping.');

            return false;
        }
    }

    public function displayManage_valuesLink($token, $id, $name = null)
    {
        $tpl = $this->createTemplate('helpers/list/list_action_manage_values.tpl');
        $tpl->assign([
            'href' => self::$currentIndex . '&manageValues&id_mapping=' . $id . '&token=' . $this->token,
            'action' => $this->l('Manage Values'),
            'id' => $id,
        ]);

        return $tpl->fetch();
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
                            ]);
                        }
                    }
                }

                $this->confirmations[] = $this->l('Yuju attributes synchronized successfully.');
                $this->logger->log('Yuju attributes synchronized: ' . count($attributes['data']) . ' attributes', 'info');
            } else {
                $this->errors[] = $this->l('No attributes found in Yuju.');
            }
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error synchronizing Yuju attributes: ') . $e->getMessage();
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
                'id_mapping IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->l('Selected mappings enabled.');
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
                'id_mapping IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->l('Selected mappings disabled.');
            }
        }
    }

    public function initContent()
    {
        if (Tools::isSubmit('manageValues')) {
            $this->manageAttributeValues();

            return;
        }

        parent::initContent();
    }

    protected function manageAttributeValues()
    {
        $id_mapping = (int) Tools::getValue('id_mapping');

        if (!$id_mapping) {
            $this->errors[] = $this->l('Invalid mapping ID.');

            return;
        }

        // Get mapping details
        $mapping = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE id_mapping = ' . (int) $id_mapping
        );

        if (!$mapping) {
            $this->errors[] = $this->l('Mapping not found.');

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
            WHERE attribute_mapping_id = ' . (int) $id_mapping
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
