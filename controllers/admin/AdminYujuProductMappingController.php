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
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuProductMapping.php';

class AdminYujuProductMappingController extends ModuleAdminController
{
    protected $logger;

    /**
     * Translate legacy controller labels without relying on the removed l() method.
     * Keeps the controller compatible with PrestaShop versions exposing the translator
     * through Context, while avoiding a fatal error when translation services differ.
     */
    protected function translate($string)
    {
        if (isset($this->context) && is_object($this->context)
            && method_exists($this->context, 'getTranslator')) {
            $translator = $this->context->getTranslator();
            if (is_object($translator) && method_exists($translator, 'trans')) {
                return $translator->trans($string, [], 'Modules.Prestashopyuju.Admin');
            }
        }

        if (class_exists('Context')) {
            $context = Context::getContext();
            if (is_object($context) && method_exists($context, 'getTranslator')) {
                $translator = $context->getTranslator();
                if (is_object($translator) && method_exists($translator, 'trans')) {
                    return $translator->trans($string, [], 'Modules.Prestashopyuju.Admin');
                }
            }
        }

        // Last-resort fallback: labels remain visible instead of crashing the page.
        return $string;
    }

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
        'title' => $this->translate('ID'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'prestashop_field' => [
        'title' => $this->translate('PrestaShop Field'),
        'width' => 200,
        ],
        'yuju_field' => [
        'title' => $this->translate('Yuju Field'),
        'width' => 200,
        ],
        'field_type' => [
        'title' => $this->translate('Field Type'),
        'align' => 'center',
        'width' => 120,
        ],
        'is_required' => [
        'title' => $this->translate('Required'),
        'align' => 'center',
        'type' => 'bool',
        'class' => 'fixed-width-sm',
        ],

        'transformation_rule' => [
        'title' => $this->translate('Transformation'),
        'width' => 150,
        ],
        'is_active' => [
        'title' => $this->translate('Active'),
        'align' => 'center',
        'active' => 'status',
        'type' => 'bool',
        'class' => 'fixed-width-sm',
        ],
        ];

        $this->actions = ['edit', 'delete'];
        $this->bulk_actions = [
        'delete' => [
        'text' => $this->translate('Delete selected'),
        'confirm' => $this->translate('Delete selected items?'),
        'icon' => 'icon-trash'
        ],
        'enableMapping' => [
        'text' => $this->translate('Enable mapping'),
        'icon' => 'icon-check'
        ],
        'disableMapping' => [
        'text' => $this->translate('Disable mapping'),
        'icon' => 'icon-remove'
        ],
        ];

        $this->toolbar_btn['new'] = [
        'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
        'desc' => $this->translate('Add new mapping'),
        ];

        $this->toolbar_btn['load_defaults'] = [
        'href' => self::$currentIndex . '&loadDefaults&token=' . $this->token,
        'desc' => $this->translate('Load Default Mappings'),
        'class' => 'process-icon-download',
        ];
    }

    public function initContent()
    {
        // Keep required Yuju mappings and fallback values available on existing installations.
        $this->loadDefaultMappings(true);

        // Assign data to Smarty
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuProductMapping',
            'product_mappings' => $this->getProductMappings(),
            'prestashop_fields' => $this->getPrestashopFields(),
            'yuju_fields' => $this->getYujuFields(),
            'field_types' => $this->getFieldTypes(),

            'transformation_rules' => $this->getTransformationRules(),
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuProductMapping'),
        ]);
        
        parent::initContent();
        
        $this->setTemplate('product_mapping.tpl');
    }

    public function renderForm()
    {
        // PrestaShop product fields
        $prestashop_fields = [
        ['id' => 'name', 'name' => $this->translate('Product Name')],
        ['id' => 'description', 'name' => $this->translate('Description')],
        ['id' => 'description_short', 'name' => $this->translate('Short Description')],
        ['id' => 'price', 'name' => $this->translate('Price')],
        ['id' => 'wholesale_price', 'name' => $this->translate('Wholesale Price')],
        ['id' => 'reference', 'name' => $this->translate('Reference')],
        ['id' => 'ean13', 'name' => $this->translate('EAN13')],
        ['id' => 'upc', 'name' => $this->translate('UPC')],
        ['id' => 'weight', 'name' => $this->translate('Weight')],
        ['id' => 'width', 'name' => $this->translate('Width')],
        ['id' => 'height', 'name' => $this->translate('Height')],
        ['id' => 'depth', 'name' => $this->translate('Depth')],
        ['id' => 'manufacturer', 'name' => $this->translate('Manufacturer')],
        ['id' => 'quantity', 'name' => $this->translate('Quantity')],
        ['id' => 'minimal_quantity', 'name' => $this->translate('Minimal Quantity')],
        ['id' => 'active', 'name' => $this->translate('Active')],
        ['id' => 'available_for_order', 'name' => $this->translate('Available for Order')],
        ['id' => 'show_price', 'name' => $this->translate('Show Price')],
        ['id' => 'online_only', 'name' => $this->translate('Online Only')],
        ['id' => 'meta_title', 'name' => $this->translate('Meta Title')],
        ['id' => 'meta_description', 'name' => $this->translate('Meta Description')],
        ['id' => 'meta_keywords', 'name' => $this->translate('Meta Keywords')],
        ['id' => 'link_rewrite', 'name' => $this->translate('Friendly URL')],
        ['id' => 'available_now', 'name' => $this->translate('Available Now Text')],
        ['id' => 'available_later', 'name' => $this->translate('Available Later Text')],
        ];

        // Yuju fields from the official product model.
        $yuju_fields = [
        ['id' => 'sku_simple', 'name' => $this->translate('Simple SKU')],
        ['id' => 'sku', 'name' => $this->translate('SKU')],
        ['id' => 'name', 'name' => $this->translate('Product Name')],
        ['id' => 'description', 'name' => $this->translate('Description')],
        ['id' => 'id_category', 'name' => $this->translate('Category ID')],
        ['id' => 'stock', 'name' => $this->translate('Stock')],
        ['id' => 'price', 'name' => $this->translate('Price')],
        ['id' => 'brand', 'name' => $this->translate('Brand')],
        ['id' => 'shipping', 'name' => $this->translate('Shipping')],
        ['id' => 'dimensions_unit', 'name' => $this->translate('Dimensions Unit')],
        ['id' => 'shipping_width', 'name' => $this->translate('Shipping Width')],
        ['id' => 'shipping_depth', 'name' => $this->translate('Shipping Depth')],
        ['id' => 'shipping_height', 'name' => $this->translate('Shipping Height')],
        ['id' => 'weight_unit', 'name' => $this->translate('Weight Unit')],
        ['id' => 'weight', 'name' => $this->translate('Weight')],
        ['id' => 'images', 'name' => $this->translate('Images')],
        ['id' => 'characteristics', 'name' => $this->translate('Characteristics')],
        ['id' => 'listing_type', 'name' => $this->translate('Listing Type')],
        ['id' => 'ean', 'name' => $this->translate('EAN')],
        ['id' => 'upc', 'name' => $this->translate('UPC')],
        ['id' => 'isbn_10', 'name' => $this->translate('ISBN 10')],
        ['id' => 'isbn_13', 'name' => $this->translate('ISBN 13')],
        ['id' => 'mpn', 'name' => $this->translate('MPN')],
        ['id' => 'product_weight', 'name' => $this->translate('Product Weight')],
        ['id' => 'net_content', 'name' => $this->translate('Net Content')],
        ['id' => 'variations', 'name' => $this->translate('Variations')],
        ['id' => 'channel_fields', 'name' => $this->translate('Channel Fields')],
        ['id' => 'channel_categories', 'name' => $this->translate('Channel Categories')],
        ];

        $field_types = [
        ['id' => 'string', 'name' => $this->translate('Cadena de texto')],
        ['id' => 'integer', 'name' => $this->translate('Número entero')],
        ['id' => 'decimal', 'name' => $this->translate('Número decimal')],
        ['id' => 'boolean', 'name' => $this->translate('Verdadero/Falso')],
        ['id' => 'date', 'name' => $this->translate('Fecha')],
        ['id' => 'datetime', 'name' => $this->translate('Fecha y Hora')],
        ['id' => 'array', 'name' => $this->translate('Lista')],
        ['id' => 'object', 'name' => $this->translate('Objeto')],
        ];



        $transformation_rules = [
        ['id' => 'none', 'name' => $this->translate('Ninguna')],
        ['id' => 'uppercase', 'name' => $this->translate('Mayúsculas')],
        ['id' => 'lowercase', 'name' => $this->translate('Minúsculas')],
        ['id' => 'capitalize', 'name' => $this->translate('Capitalizar')],
        ['id' => 'strip_html', 'name' => $this->translate('Eliminar HTML')],
        ['id' => 'currency_convert', 'name' => $this->translate('Convertir Moneda')],
        ['id' => 'date_format', 'name' => $this->translate('Formato de Fecha')],
        ['id' => 'custom', 'name' => $this->translate('Función Personalizada')],
        ];

        $this->fields_form = [
        'legend' => [
        'title' => $this->translate('Mapeo de Campos de Producto'),
        'icon' => 'icon-cogs',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->translate('Campo PrestaShop'),
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
        'label' => $this->translate('Campo Yuju'),
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
        'label' => $this->translate('Tipo de Campo'),
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
        'label' => $this->translate('Regla de Transformación'),
        'name' => 'transformation_rule',
        'options' => [
        'query' => $transformation_rules,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'textarea',
        'label' => $this->translate('Transformación Personalizada'),
        'name' => 'custom_transformation',
        'desc' => $this->translate('Código PHP para transformación personalizada (solo si la regla de transformación es \'custom\')'),
        ],
        [
        'type' => 'text',
        'label' => $this->translate('Valor por Defecto'),
        'name' => 'default_value',
        'desc' => $this->translate('Valor por defecto si el campo origen está vacío'),
        ],
        [
        'type' => 'switch',
        'label' => $this->translate('Campo Requerido'),
        'name' => 'is_required',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'is_required_on',
        'value' => 1,
        'label' => $this->translate('Sí'),
        ],
        [
        'id' => 'is_required_off',
        'value' => 0,
        'label' => $this->translate('No'),
        ],
        ],
        ],
        [
        'type' => 'switch',
        'label' => $this->translate('Activo'),
        'name' => 'is_active',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'is_active_on',
        'value' => 1,
        'label' => $this->translate('Habilitado'),
        ],
        [
        'id' => 'is_active_off',
        'value' => 0,
        'label' => $this->translate('Deshabilitado'),
        ],
        ],
        ],
        ],
        'submit' => [
        'title' => $this->translate('Guardar'),
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
            $this->errors[] = $this->translate('This PrestaShop field is already mapped.');

            return false;
        }

        $data = [
        'prestashop_field' => pSQL($prestashop_field),
        'yuju_field' => pSQL($yuju_field),
        'field_type' => pSQL(Tools::getValue('field_type')),

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
            $this->confirmations[] = $this->translate('Product mapping saved successfully.');
            $this->logger->log('Product mapping saved: ' . $prestashop_field . ' -> ' . $yuju_field, 'info');

            return true;
        } else {
            $this->errors[] = $this->translate('Error saving product mapping.');

            return false;
        }
    }

    protected function loadDefaultMappings($silent = false)
    {
        // Correct only known legacy defaults; custom mappings are preserved.
        $legacy_mappings = [
            ['name', 'title', 'name'],
            ['ean13', 'barcode', 'ean'],
            ['quantity', 'stock_quantity', 'stock'],
            ['width', 'width', 'shipping_width'],
            ['height', 'height', 'shipping_height'],
            ['depth', 'length', 'shipping_depth'],
            ['depth', 'depth', 'shipping_depth'],
        ];
        $all_mappings = Db::getInstance()->executeS('
            SELECT id_mapping, prestashop_field, yuju_field
            FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
        ');
        $mapping_targets = [];
        if (is_array($all_mappings)) {
            foreach ($all_mappings as $row) {
                $mapping_targets[$row['prestashop_field'] . '|' . $row['yuju_field']] = (int) $row['id_mapping'];
            }
            foreach ($all_mappings as $row) {
                foreach ($legacy_mappings as $legacy) {
                    list($ps_field, $old_yuju_field, $new_yuju_field) = $legacy;
                    if ($row['prestashop_field'] !== $ps_field || $row['yuju_field'] !== $old_yuju_field) {
                        continue;
                    }
                    $target_key = $ps_field . '|' . $new_yuju_field;
                    if (!isset($mapping_targets[$target_key]) || $mapping_targets[$target_key] === (int) $row['id_mapping']) {
                        Db::getInstance()->update(
                            'yuju_product_mapping',
                            ['yuju_field' => pSQL($new_yuju_field), 'updated_at' => date('Y-m-d H:i:s')],
                            'id_mapping = ' . (int) $row['id_mapping']
                        );
                        $mapping_targets[$target_key] = (int) $row['id_mapping'];
                    }
                }
            }
        }
        Db::getInstance()->execute('
            UPDATE ' . _DB_PREFIX_ . 'yuju_product_mapping
            SET sync_direction = "prestashop_to_yuju", updated_at = NOW()
            WHERE sync_direction = "ps_to_yuju"
        ');

        $default_mappings = [
            ['prestashop_field' => 'name', 'yuju_field' => 'name', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_required' => 1],
            ['prestashop_field' => 'description', 'yuju_field' => 'description', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_required' => 1, 'default_value' => 'No disponible'],
            ['prestashop_field' => 'price', 'yuju_field' => 'price', 'field_type' => 'decimal', 'sync_direction' => 'bidirectional', 'is_required' => 1],
            ['prestashop_field' => 'reference', 'yuju_field' => 'sku', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_required' => 1],
            ['prestashop_field' => 'ean13', 'yuju_field' => 'ean', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_required' => 0],
            ['prestashop_field' => 'upc', 'yuju_field' => 'upc', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_required' => 0],
            ['prestashop_field' => 'quantity', 'yuju_field' => 'stock', 'field_type' => 'integer', 'sync_direction' => 'bidirectional', 'is_required' => 1],
            ['prestashop_field' => 'weight', 'yuju_field' => 'weight', 'field_type' => 'decimal', 'sync_direction' => 'bidirectional', 'is_required' => 1, 'default_value' => '1'],
            ['prestashop_field' => 'manufacturer', 'yuju_field' => 'brand', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_required' => 1, 'default_value' => 'No disponible'],
            ['prestashop_field' => 'width', 'yuju_field' => 'shipping_width', 'field_type' => 'decimal', 'sync_direction' => 'bidirectional', 'is_required' => 1, 'default_value' => '20'],
            ['prestashop_field' => 'height', 'yuju_field' => 'shipping_height', 'field_type' => 'decimal', 'sync_direction' => 'bidirectional', 'is_required' => 1, 'default_value' => '20'],
            ['prestashop_field' => 'depth', 'yuju_field' => 'shipping_depth', 'field_type' => 'decimal', 'sync_direction' => 'bidirectional', 'is_required' => 1, 'default_value' => '20'],
        ];

        $inserted = 0;
        foreach ($default_mappings as $mapping) {
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
            if (isset($mapping['default_value']) && $mapping['default_value'] !== '') {
                Db::getInstance()->update(
                    'yuju_product_mapping',
                    [
                        'default_value' => pSQL($mapping['default_value']),
                        'is_required' => 1,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'yuju_field = "' . pSQL($mapping['yuju_field']) . '"'
                );
            }
        }

        if (!$silent && $inserted > 0) {
            $this->confirmations[] = sprintf($this->translate('%d default mappings loaded successfully.'), $inserted);
            $this->logger->log('Default product mappings loaded: ' . $inserted . ' mappings', 'info');
        } elseif (!$silent) {
            $this->warnings[] = $this->translate('No new default mappings to load. Known legacy defaults were checked.');
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
                $this->confirmations[] = $this->translate('Selected mappings enabled.');
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
                $this->confirmations[] = $this->translate('Selected mappings disabled.');
            }
        }
    }

    // AJAX Methods
    public function ajaxProcessSaveMapping()
    {
        $response = ['success' => false, 'message' => ''];

        try {
            $id_mapping = (int) Tools::getValue('id_mapping');
            $prestashop_field = Tools::getValue('prestashop_field');
            $yuju_field = Tools::getValue('yuju_field');
            $field_type = Tools::getValue('field_type');

            $transformation_rule = Tools::getValue('transformation_rule');
            $custom_transformation = Tools::getValue('custom_transformation');
            $default_value = Tools::getValue('default_value');
            $is_required = (int) Tools::getValue('is_required');
            $is_active = (int) Tools::getValue('is_active');

            // Validation
            if (empty($prestashop_field) || empty($yuju_field) || empty($field_type)) {
                throw new Exception($this->translate('Required fields are missing.'));
            }

            // Check if mapping already exists (for different mapping)
            $existing_query = '
                SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
                WHERE prestashop_field = "' . pSQL($prestashop_field) . '"
            ';
            if ($id_mapping > 0) {
                $existing_query .= ' AND id_mapping != ' . $id_mapping;
            }

            $existing = Db::getInstance()->getRow($existing_query);
            if ($existing) {
                throw new Exception($this->translate('This PrestaShop field is already mapped.'));
            }

            $data = [
                'prestashop_field' => pSQL($prestashop_field),
                'yuju_field' => pSQL($yuju_field),
                'field_type' => pSQL($field_type),
                'sync_direction' => 'ps_to_yuju',
                'transformation_rule' => pSQL($transformation_rule),
                'custom_transformation' => pSQL($custom_transformation),
                'default_value' => pSQL($default_value),
                'is_required' => $is_required,
                'is_active' => $is_active,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($id_mapping > 0) {
                // Update existing mapping
                $result = Db::getInstance()->update(
                    'yuju_product_mapping',
                    $data,
                    'id_mapping = ' . $id_mapping
                );
                $message = $this->translate('Product mapping updated successfully.');
            } else {
                // Create new mapping
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = Db::getInstance()->insert('yuju_product_mapping', $data);
                $message = $this->translate('Product mapping created successfully.');
            }

            if ($result) {
                $response['success'] = true;
                $response['message'] = $message;
                $this->logger->log('Product mapping saved: ' . $prestashop_field . ' -> ' . $yuju_field, 'info');
            } else {
                throw new Exception($this->translate('Error saving product mapping.'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error saving product mapping: ' . $e->getMessage(), 'error');
        }

        $this->ajaxRender(json_encode($response));
    }

    public function ajaxProcessGetMapping()
    {
        $response = ['success' => false, 'data' => null];

        try {
            $id_mapping = (int) Tools::getValue('id_mapping');
            if ($id_mapping <= 0) {
                throw new Exception($this->translate('Invalid mapping ID.'));
            }

            $mapping = Db::getInstance()->getRow('
                SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
                WHERE id_mapping = ' . $id_mapping
            );

            if ($mapping) {
                $response['success'] = true;
                $response['data'] = $mapping;
            } else {
                throw new Exception($this->translate('Mapping not found.'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        $this->ajaxRender(json_encode($response));
    }

    public function ajaxProcessDeleteMapping()
    {
        $response = ['success' => false, 'message' => ''];

        try {
            $id_mapping = (int) Tools::getValue('id_mapping');
            if ($id_mapping <= 0) {
                throw new Exception($this->translate('Invalid mapping ID.'));
            }

            $result = Db::getInstance()->delete(
                'yuju_product_mapping',
                'id_mapping = ' . $id_mapping
            );

            if ($result) {
                $response['success'] = true;
                $response['message'] = $this->translate('Product mapping deleted successfully.');
                $this->logger->log('Product mapping deleted: ID ' . $id_mapping, 'info');
            } else {
                throw new Exception($this->translate('Error deleting product mapping.'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error deleting product mapping: ' . $e->getMessage(), 'error');
        }

        $this->ajaxRender(json_encode($response));
    }

    public function ajaxProcessToggleMapping()
    {
        $response = ['success' => false, 'message' => ''];

        try {
            $id_mapping = (int) Tools::getValue('id_mapping');
            $is_active = (int) Tools::getValue('is_active');

            if ($id_mapping <= 0) {
                throw new Exception($this->translate('Invalid mapping ID.'));
            }

            $result = Db::getInstance()->update(
                'yuju_product_mapping',
                ['is_active' => $is_active],
                'id_mapping = ' . $id_mapping
            );

            if ($result) {
                $response['success'] = true;
                $response['message'] = $is_active ? 
                    $this->translate('Product mapping enabled successfully.') : 
                    $this->translate('Product mapping disabled successfully.');
                $this->logger->log('Product mapping toggled: ID ' . $id_mapping . ' -> ' . ($is_active ? 'enabled' : 'disabled'), 'info');
            } else {
                throw new Exception($this->translate('Error updating product mapping status.'));
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error toggling product mapping: ' . $e->getMessage(), 'error');
        }

        $this->ajaxRender(json_encode($response));
    }

    public function ajaxProcessRefreshYujuFields()
    {
        $response = ['success' => false, 'message' => '', 'data' => []];

        try {
            // This would typically fetch from Yuju API
            // For now, return the static list
            $yuju_fields = $this->getYujuFields();
            
            $response['success'] = true;
            $response['data'] = $yuju_fields;
            $response['message'] = $this->translate('Yuju fields refreshed successfully.');
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error refreshing Yuju fields: ' . $e->getMessage(), 'error');
        }

        $this->ajaxRender(json_encode($response));
    }

    // Helper Methods
    protected function getProductMappings()
    {
        return Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
            ORDER BY FIELD(yuju_field, 'sku_simple', 'sku', 'name', 'description', 'id_category', 'stock', 'price', 'brand', 'shipping', 'dimensions_unit', 'shipping_width', 'shipping_depth', 'shipping_height', 'weight_unit', 'weight', 'images', 'characteristics', 'listing_type', 'ean', 'upc', 'isbn_10', 'isbn_13', 'mpn', 'product_weight', 'net_content', 'variations', 'channel_fields', 'channel_categories'), prestashop_field ASC
        ');
    }

    protected function getPrestashopFields()
    {
        return [
            ['id' => 'name', 'name' => $this->translate('Product Name')],
            ['id' => 'description', 'name' => $this->translate('Description')],
            ['id' => 'description_short', 'name' => $this->translate('Short Description')],
            ['id' => 'price', 'name' => $this->translate('Price')],
            ['id' => 'wholesale_price', 'name' => $this->translate('Wholesale Price')],
            ['id' => 'reference', 'name' => $this->translate('Reference/SKU')],
            ['id' => 'ean13', 'name' => $this->translate('EAN13')],
            ['id' => 'upc', 'name' => $this->translate('UPC')],
            ['id' => 'isbn', 'name' => $this->translate('ISBN')],
            ['id' => 'mpn', 'name' => $this->translate('MPN')],
            ['id' => 'quantity', 'name' => $this->translate('Quantity')],
            ['id' => 'minimal_quantity', 'name' => $this->translate('Minimal Quantity')],
            ['id' => 'weight', 'name' => $this->translate('Weight')],
            ['id' => 'width', 'name' => $this->translate('Width')],
            ['id' => 'height', 'name' => $this->translate('Height')],
            ['id' => 'depth', 'name' => $this->translate('Depth')],
            ['id' => 'manufacturer', 'name' => $this->translate('Manufacturer')],
            ['id' => 'active', 'name' => $this->translate('Active')],
            ['id' => 'available_for_order', 'name' => $this->translate('Available for Order')],
            ['id' => 'show_price', 'name' => $this->translate('Show Price')],
            ['id' => 'online_only', 'name' => $this->translate('Online Only')],
            ['id' => 'condition', 'name' => $this->translate('Condition')],
            ['id' => 'visibility', 'name' => $this->translate('Visibility')],
            ['id' => 'meta_title', 'name' => $this->translate('Meta Title')],
            ['id' => 'meta_description', 'name' => $this->translate('Meta Description')],
            ['id' => 'meta_keywords', 'name' => $this->translate('Meta Keywords')],
            ['id' => 'link_rewrite', 'name' => $this->translate('Friendly URL')],
            ['id' => 'available_now', 'name' => $this->translate('Available Now Text')],
            ['id' => 'available_later', 'name' => $this->translate('Available Later Text')],
        ];
    }

    protected function getYujuFields()
    {
        return [
            ['id' => 'sku_simple', 'name' => $this->translate('Simple SKU')],
            ['id' => 'sku', 'name' => $this->translate('SKU')],
            ['id' => 'name', 'name' => $this->translate('Product Name')],
            ['id' => 'description', 'name' => $this->translate('Description')],
            ['id' => 'id_category', 'name' => $this->translate('Category ID')],
            ['id' => 'stock', 'name' => $this->translate('Stock')],
            ['id' => 'price', 'name' => $this->translate('Price')],
            ['id' => 'brand', 'name' => $this->translate('Brand')],
            ['id' => 'shipping', 'name' => $this->translate('Shipping')],
            ['id' => 'dimensions_unit', 'name' => $this->translate('Dimensions Unit')],
            ['id' => 'shipping_width', 'name' => $this->translate('Shipping Width')],
            ['id' => 'shipping_depth', 'name' => $this->translate('Shipping Depth')],
            ['id' => 'shipping_height', 'name' => $this->translate('Shipping Height')],
            ['id' => 'weight_unit', 'name' => $this->translate('Weight Unit')],
            ['id' => 'weight', 'name' => $this->translate('Weight')],
            ['id' => 'images', 'name' => $this->translate('Images')],
            ['id' => 'characteristics', 'name' => $this->translate('Characteristics')],
            ['id' => 'listing_type', 'name' => $this->translate('Listing Type')],
            ['id' => 'ean', 'name' => $this->translate('EAN')],
            ['id' => 'upc', 'name' => $this->translate('UPC')],
            ['id' => 'isbn_10', 'name' => $this->translate('ISBN 10')],
            ['id' => 'isbn_13', 'name' => $this->translate('ISBN 13')],
            ['id' => 'mpn', 'name' => $this->translate('MPN')],
            ['id' => 'product_weight', 'name' => $this->translate('Product Weight')],
            ['id' => 'net_content', 'name' => $this->translate('Net Content')],
            ['id' => 'variations', 'name' => $this->translate('Variations')],
            ['id' => 'channel_fields', 'name' => $this->translate('Channel Fields')],
            ['id' => 'channel_categories', 'name' => $this->translate('Channel Categories')],
        ];
    }

    protected function getFieldTypes()
    {
        return [
            ['id' => 'string', 'name' => $this->translate('Cadena de texto')],
            ['id' => 'integer', 'name' => $this->translate('Número entero')],
            ['id' => 'decimal', 'name' => $this->translate('Número decimal')],
            ['id' => 'boolean', 'name' => $this->translate('Verdadero/Falso')],
            ['id' => 'date', 'name' => $this->translate('Fecha')],
            ['id' => 'datetime', 'name' => $this->translate('Fecha y Hora')],
            ['id' => 'text', 'name' => $this->translate('Texto')],
            ['id' => 'html', 'name' => $this->translate('HTML')],
            ['id' => 'json', 'name' => $this->translate('JSON')],
        ];
    }



    protected function getTransformationRules()
    {
        return [
            ['id' => 'none', 'name' => $this->translate('Ninguna')],
            ['id' => 'uppercase', 'name' => $this->translate('Mayúsculas')],
            ['id' => 'lowercase', 'name' => $this->translate('Minúsculas')],
            ['id' => 'capitalize', 'name' => $this->translate('Capitalizar')],
            ['id' => 'strip_html', 'name' => $this->translate('Eliminar HTML')],
            ['id' => 'strip_tags', 'name' => $this->translate('Eliminar Etiquetas')],
            ['id' => 'trim', 'name' => $this->translate('Recortar Espacios')],
            ['id' => 'number_format', 'name' => $this->translate('Formato de Número')],
            ['id' => 'date_format', 'name' => $this->translate('Formato de Fecha')],
            ['id' => 'boolean_convert', 'name' => $this->translate('Convertir a Booleano')],
            ['id' => 'custom', 'name' => $this->translate('Código PHP Personalizado')],
        ];
    }
}
