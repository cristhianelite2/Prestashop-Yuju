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

class AdminYujuProductMappingController extends ModuleAdminController
{
    protected $logger;

    public function __construct()
    {
        $this->table = 'yuju_product_mapping';
        $this->className = 'YujuProductMapping';
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
        'prestashop_field' => [
        'title' => $this->l('PrestaShop Field'),
        'width' => 200,
        ],
        'yuju_field' => [
        'title' => $this->l('Yuju Field'),
        'width' => 200,
        ],
        'field_type' => [
        'title' => $this->l('Field Type'),
        'align' => 'center',
        'width' => 120,
        ],
        'is_required' => [
        'title' => $this->l('Required'),
        'align' => 'center',
        'type' => 'bool',
        'class' => 'fixed-width-sm',
        ],
        'sync_direction' => [
        'title' => $this->l('Sync Direction'),
        'align' => 'center',
        'width' => 120,
        ],
        'transformation_rule' => [
        'title' => $this->l('Transformation'),
        'width' => 150,
        ],
        'is_active' => [
        'title' => $this->l('Active'),
        'align' => 'center',
        'active' => 'status',
        'type' => 'bool',
        'class' => 'fixed-width-sm',
        ],
        ];

        $this->actions = ['edit', 'delete'];
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

        $this->toolbar_btn['load_defaults'] = [
        'href' => self::$currentIndex . '&loadDefaults&token=' . $this->token,
        'desc' => $this->l('Load Default Mappings'),
        'class' => 'process-icon-download',
        ];
    }

    public function renderForm()
    {
        // PrestaShop product fields
        $prestashop_fields = [
        ['id' => 'name', 'name' => $this->l('Product Name')],
        ['id' => 'description', 'name' => $this->l('Description')],
        ['id' => 'description_short', 'name' => $this->l('Short Description')],
        ['id' => 'price', 'name' => $this->l('Price')],
        ['id' => 'wholesale_price', 'name' => $this->l('Wholesale Price')],
        ['id' => 'reference', 'name' => $this->l('Reference')],
        ['id' => 'ean13', 'name' => $this->l('EAN13')],
        ['id' => 'upc', 'name' => $this->l('UPC')],
        ['id' => 'weight', 'name' => $this->l('Weight')],
        ['id' => 'width', 'name' => $this->l('Width')],
        ['id' => 'height', 'name' => $this->l('Height')],
        ['id' => 'depth', 'name' => $this->l('Depth')],
        ['id' => 'quantity', 'name' => $this->l('Quantity')],
        ['id' => 'minimal_quantity', 'name' => $this->l('Minimal Quantity')],
        ['id' => 'active', 'name' => $this->l('Active')],
        ['id' => 'available_for_order', 'name' => $this->l('Available for Order')],
        ['id' => 'show_price', 'name' => $this->l('Show Price')],
        ['id' => 'online_only', 'name' => $this->l('Online Only')],
        ['id' => 'meta_title', 'name' => $this->l('Meta Title')],
        ['id' => 'meta_description', 'name' => $this->l('Meta Description')],
        ['id' => 'meta_keywords', 'name' => $this->l('Meta Keywords')],
        ['id' => 'link_rewrite', 'name' => $this->l('Friendly URL')],
        ['id' => 'available_now', 'name' => $this->l('Available Now Text')],
        ['id' => 'available_later', 'name' => $this->l('Available Later Text')],
        ];

        // Yuju product fields (these would come from API documentation)
        $yuju_fields = [
        ['id' => 'title', 'name' => $this->l('Title')],
        ['id' => 'description', 'name' => $this->l('Description')],
        ['id' => 'short_description', 'name' => $this->l('Short Description')],
        ['id' => 'price', 'name' => $this->l('Price')],
        ['id' => 'cost_price', 'name' => $this->l('Cost Price')],
        ['id' => 'sku', 'name' => $this->l('SKU')],
        ['id' => 'barcode', 'name' => $this->l('Barcode')],
        ['id' => 'weight', 'name' => $this->l('Weight')],
        ['id' => 'dimensions', 'name' => $this->l('Dimensions')],
        ['id' => 'stock_quantity', 'name' => $this->l('Stock Quantity')],
        ['id' => 'min_order_quantity', 'name' => $this->l('Min Order Quantity')],
        ['id' => 'status', 'name' => $this->l('Status')],
        ['id' => 'visibility', 'name' => $this->l('Visibility')],
        ['id' => 'seo_title', 'name' => $this->l('SEO Title')],
        ['id' => 'seo_description', 'name' => $this->l('SEO Description')],
        ['id' => 'seo_keywords', 'name' => $this->l('SEO Keywords')],
        ['id' => 'slug', 'name' => $this->l('Slug')],
        ['id' => 'brand', 'name' => $this->l('Brand')],
        ['id' => 'category_id', 'name' => $this->l('Category ID')],
        ['id' => 'tags', 'name' => $this->l('Tags')],
        ];

        $field_types = [
        ['id' => 'string', 'name' => $this->l('String')],
        ['id' => 'integer', 'name' => $this->l('Integer')],
        ['id' => 'decimal', 'name' => $this->l('Decimal')],
        ['id' => 'boolean', 'name' => $this->l('Boolean')],
        ['id' => 'date', 'name' => $this->l('Date')],
        ['id' => 'datetime', 'name' => $this->l('DateTime')],
        ['id' => 'array', 'name' => $this->l('Array')],
        ['id' => 'object', 'name' => $this->l('Object')],
        ];

        $sync_directions = [
        ['id' => 'ps_to_yuju', 'name' => $this->l('PrestaShop → Yuju')],
        ['id' => 'yuju_to_ps', 'name' => $this->l('Yuju → PrestaShop')],
        ['id' => 'bidirectional', 'name' => $this->l('Bidirectional')],
        ];

        $transformation_rules = [
        ['id' => 'none', 'name' => $this->l('None')],
        ['id' => 'uppercase', 'name' => $this->l('Uppercase')],
        ['id' => 'lowercase', 'name' => $this->l('Lowercase')],
        ['id' => 'capitalize', 'name' => $this->l('Capitalize')],
        ['id' => 'strip_html', 'name' => $this->l('Strip HTML')],
        ['id' => 'currency_convert', 'name' => $this->l('Currency Convert')],
        ['id' => 'date_format', 'name' => $this->l('Date Format')],
        ['id' => 'custom', 'name' => $this->l('Custom Function')],
        ];

        $this->fields_form = [
        'legend' => [
        'title' => $this->l('Product Field Mapping'),
        'icon' => 'icon-cogs',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->l('PrestaShop Field'),
        'name' => 'prestashop_field',
        'required' => true,
        'options' => [
        'query' => $prestashop_fields,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'select',
        'label' => $this->l('Yuju Field'),
        'name' => 'yuju_field',
        'required' => true,
        'options' => [
        'query' => $yuju_fields,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'select',
        'label' => $this->l('Field Type'),
        'name' => 'field_type',
        'required' => true,
        'options' => [
        'query' => $field_types,
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
        'type' => 'select',
        'label' => $this->l('Transformation Rule'),
        'name' => 'transformation_rule',
        'options' => [
        'query' => $transformation_rules,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'textarea',
        'label' => $this->l('Custom Transformation'),
        'name' => 'custom_transformation',
        'desc' => $this->l('PHP code for custom transformation (only if transformation rule is \'custom\')'),
        ],
        [
        'type' => 'text',
        'label' => $this->l('Default Value'),
        'name' => 'default_value',
        'desc' => $this->l('Default value if source field is empty'),
        ],
        [
        'type' => 'switch',
        'label' => $this->l('Required Field'),
        'name' => 'is_required',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'is_required_on',
        'value' => 1,
        'label' => $this->l('Yes'),
        ],
        [
        'id' => 'is_required_off',
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
        if (Tools::isSubmit('loadDefaults')) {
            $this->loadDefaultMappings();
        } elseif (Tools::isSubmit('submitBulkenableMapping')) {
            $this->processBulkEnableMapping();
        } elseif (Tools::isSubmit('submitBulkdisableMapping')) {
            $this->processBulkDisableMapping();
        }

        return parent::postProcess();
    }

    public function processSave(): bool
    {
        $prestashop_field = Tools::getValue('prestashop_field');
        $yuju_field = Tools::getValue('yuju_field');

        // Check if mapping already exists
        $existing = Db::getInstance()->getRow(
            '
        SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
        WHERE prestashop_field = "' . pSQL($prestashop_field) . '"
        AND id_mapping != ' . (int) Tools::getValue('id_mapping')
        );

        if ($existing) {
            $this->errors[] = $this->l('This PrestaShop field is already mapped.');

            return false;
        }

        $data = [
        'prestashop_field' => pSQL($prestashop_field),
        'yuju_field' => pSQL($yuju_field),
        'field_type' => pSQL(Tools::getValue('field_type')),
        'sync_direction' => pSQL(Tools::getValue('sync_direction')),
        'transformation_rule' => pSQL(Tools::getValue('transformation_rule')),
        'custom_transformation' => pSQL(Tools::getValue('custom_transformation')),
        'default_value' => pSQL(Tools::getValue('default_value')),
        'is_required' => (int) Tools::getValue('is_required'),
        'is_active' => (int) Tools::getValue('is_active'),
        'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (Tools::getValue('id_mapping')) {
            // Update
            $result = Db::getInstance()->update(
                'yuju_product_mapping',
                $data,
                'id_mapping = ' . (int) Tools::getValue('id_mapping')
            );
        } else {
            // Insert
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert('yuju_product_mapping', $data);
        }

        if ($result) {
            $this->confirmations[] = $this->l('Product mapping saved successfully.');
            $this->logger->log('Product mapping saved: ' . $prestashop_field . ' -> ' . $yuju_field, 'info');

            return true;
        } else {
            $this->errors[] = $this->l('Error saving product mapping.');

            return false;
        }
    }

    protected function loadDefaultMappings()
    {
        $default_mappings = [
        [
        'prestashop_field' => 'name',
        'yuju_field' => 'title',
        'field_type' => 'string',
        'sync_direction' => 'bidirectional',
        'is_required' => 1,
        ],
        [
        'prestashop_field' => 'description',
        'yuju_field' => 'description',
        'field_type' => 'string',
        'sync_direction' => 'bidirectional',
        'is_required' => 0,
        ],
        [
        'prestashop_field' => 'description_short',
        'yuju_field' => 'short_description',
        'field_type' => 'string',
        'sync_direction' => 'bidirectional',
        'is_required' => 0,
        ],
        [
        'prestashop_field' => 'price',
        'yuju_field' => 'price',
        'field_type' => 'decimal',
        'sync_direction' => 'bidirectional',
        'is_required' => 1,
        ],
        [
        'prestashop_field' => 'reference',
        'yuju_field' => 'sku',
        'field_type' => 'string',
        'sync_direction' => 'bidirectional',
        'is_required' => 1,
        ],
        [
        'prestashop_field' => 'ean13',
        'yuju_field' => 'barcode',
        'field_type' => 'string',
        'sync_direction' => 'bidirectional',
        'is_required' => 0,
        ],
        [
        'prestashop_field' => 'quantity',
        'yuju_field' => 'stock_quantity',
        'field_type' => 'integer',
        'sync_direction' => 'bidirectional',
        'is_required' => 0,
        ],
        [
        'prestashop_field' => 'weight',
        'yuju_field' => 'weight',
        'field_type' => 'decimal',
        'sync_direction' => 'bidirectional',
        'is_required' => 0,
        ],
        [
        'prestashop_field' => 'active',
        'yuju_field' => 'status',
        'field_type' => 'boolean',
        'sync_direction' => 'bidirectional',
        'is_required' => 0,
        ],
        ];

        $inserted = 0;

        foreach ($default_mappings as $mapping) {
            // Check if mapping already exists
            $existing = Db::getInstance()->getRow('
            SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
            WHERE prestashop_field = "' . pSQL($mapping['prestashop_field']) . '"
            ');

            if (!$existing) {
                $mapping['transformation_rule'] = 'none';
                $mapping['is_active'] = 1;
                $mapping['created_at'] = date('Y-m-d H:i:s');
                $mapping['updated_at'] = date('Y-m-d H:i:s');

                if (Db::getInstance()->insert('yuju_product_mapping', $mapping)) {
                    ++$inserted;
                }
            }
        }

        if ($inserted > 0) {
            $this->confirmations[] = sprintf($this->l('%d default mappings loaded successfully.'), $inserted);
            $this->logger->log('Default product mappings loaded: ' . $inserted . ' mappings', 'info');
        } else {
            $this->warnings[] = $this->l('No new default mappings to load.');
        }
    }

    protected function processBulkEnableMapping()
    {
        $ids = Tools::getValue($this->table . 'Box');

        if (is_array($ids) && count($ids)) {
            $result = Db::getInstance()->update(
                'yuju_product_mapping',
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
                'yuju_product_mapping',
                ['is_active' => 0],
                'id_mapping IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->l('Selected mappings disabled.');
            }
        }
    }
}
