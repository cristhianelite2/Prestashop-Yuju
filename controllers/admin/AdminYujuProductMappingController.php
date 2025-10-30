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
        'icon' => 'icon-trash'
        ],
        'enableMapping' => [
        'text' => $this->l('Enable mapping'),
        'icon' => 'icon-check'
        ],
        'disableMapping' => [
        'text' => $this->l('Disable mapping'),
        'icon' => 'icon-remove'
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

    public function initContent()
    {
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
        ['id' => 'string', 'name' => $this->l('Cadena de texto')],
        ['id' => 'integer', 'name' => $this->l('Número entero')],
        ['id' => 'decimal', 'name' => $this->l('Número decimal')],
        ['id' => 'boolean', 'name' => $this->l('Verdadero/Falso')],
        ['id' => 'date', 'name' => $this->l('Fecha')],
        ['id' => 'datetime', 'name' => $this->l('Fecha y Hora')],
        ['id' => 'array', 'name' => $this->l('Lista')],
        ['id' => 'object', 'name' => $this->l('Objeto')],
        ];



        $transformation_rules = [
        ['id' => 'none', 'name' => $this->l('Ninguna')],
        ['id' => 'uppercase', 'name' => $this->l('Mayúsculas')],
        ['id' => 'lowercase', 'name' => $this->l('Minúsculas')],
        ['id' => 'capitalize', 'name' => $this->l('Capitalizar')],
        ['id' => 'strip_html', 'name' => $this->l('Eliminar HTML')],
        ['id' => 'currency_convert', 'name' => $this->l('Convertir Moneda')],
        ['id' => 'date_format', 'name' => $this->l('Formato de Fecha')],
        ['id' => 'custom', 'name' => $this->l('Función Personalizada')],
        ];

        $this->fields_form = [
        'legend' => [
        'title' => $this->l('Mapeo de Campos de Producto'),
        'icon' => 'icon-cogs',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->l('Campo PrestaShop'),
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
        'label' => $this->l('Campo Yuju'),
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
        'label' => $this->l('Tipo de Campo'),
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
        'label' => $this->l('Regla de Transformación'),
        'name' => 'transformation_rule',
        'options' => [
        'query' => $transformation_rules,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'textarea',
        'label' => $this->l('Transformación Personalizada'),
        'name' => 'custom_transformation',
        'desc' => $this->l('Código PHP para transformación personalizada (solo si la regla de transformación es \'custom\')'),
        ],
        [
        'type' => 'text',
        'label' => $this->l('Valor por Defecto'),
        'name' => 'default_value',
        'desc' => $this->l('Valor por defecto si el campo origen está vacío'),
        ],
        [
        'type' => 'switch',
        'label' => $this->l('Campo Requerido'),
        'name' => 'is_required',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'is_required_on',
        'value' => 1,
        'label' => $this->l('Sí'),
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
        'label' => $this->l('Activo'),
        'name' => 'is_active',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'is_active_on',
        'value' => 1,
        'label' => $this->l('Habilitado'),
        ],
        [
        'id' => 'is_active_off',
        'value' => 0,
        'label' => $this->l('Deshabilitado'),
        ],
        ],
        ],
        ],
        'submit' => [
        'title' => $this->l('Guardar'),
        ],
        ];

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('loadDefaults')) {
            $this->loadDefaultMappings();
            $this->updateRequiredFields();
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
            // Campos obligatorios según requerimientos
            [
                'prestashop_field' => 'name',
                'yuju_field' => 'nombre',
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'reference',
                'yuju_field' => 'sku',
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'reference',
                'yuju_field' => 'sku_simple',
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'description',
                'yuju_field' => 'descripcion',
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'images',
                'yuju_field' => 'imagenes',
                'field_type' => 'array',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'price',
                'yuju_field' => 'precio',
                'field_type' => 'decimal',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'quantity',
                'yuju_field' => 'stock',
                'field_type' => 'integer',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'manufacturer',
                'yuju_field' => 'marca',
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'condition',
                'yuju_field' => 'condicion',
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'shipping_method',
                'yuju_field' => 'metodo_envio',
                'field_type' => 'string',
                'sync_direction' => 'ps_to_yuju',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => 'El marketplace lo calcula',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'shipping_price',
                'yuju_field' => 'precio_envio',
                'field_type' => 'decimal',
                'sync_direction' => 'ps_to_yuju',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '0',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'dimension_unit',
                'yuju_field' => 'unidad_dimension',
                'field_type' => 'string',
                'sync_direction' => 'ps_to_yuju',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => 'cm',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'height',
                'yuju_field' => 'altura',
                'field_type' => 'decimal',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '0',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'width',
                'yuju_field' => 'ancho',
                'field_type' => 'decimal',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '0',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'depth',
                'yuju_field' => 'profundidad',
                'field_type' => 'decimal',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '0',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'weight_unit',
                'yuju_field' => 'unidad_peso',
                'field_type' => 'string',
                'sync_direction' => 'ps_to_yuju',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => 'kg',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'weight',
                'yuju_field' => 'peso',
                'field_type' => 'decimal',
                'sync_direction' => 'bidirectional',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => '0',
                'transformation_rule' => 'none'
            ],
            [
                'prestashop_field' => 'ml_template',
                'yuju_field' => 'plantilla_mercadolibre',
                'field_type' => 'string',
                'sync_direction' => 'ps_to_yuju',
                'is_required' => 1,
                'is_active' => 1,
                'default_value' => 'No usar plantilla',
                'transformation_rule' => 'none'
            ]
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

    protected function updateRequiredFields()
    {
        // Lista de campos que deben ser obligatorios según requerimientos
        $required_fields = [
            'name', 'reference', 'description', 'images', 'price', 
            'quantity', 'manufacturer', 'condition', 'shipping_method', 
            'shipping_price', 'dimension_unit', 'height', 'width', 
            'depth', 'weight_unit', 'weight', 'ml_template'
        ];

        $updated = 0;
        foreach ($required_fields as $field) {
            $result = Db::getInstance()->update(
                'yuju_product_mapping',
                ['is_required' => 1],
                'prestashop_field = "' . pSQL($field) . '"'
            );
            
            if ($result) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->confirmations[] = sprintf($this->l('%d fields updated as required.'), $updated);
            $this->logger->log('Required fields updated: ' . $updated . ' fields', 'info');
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
                throw new Exception($this->l('Required fields are missing.'));
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
                throw new Exception($this->l('This PrestaShop field is already mapped.'));
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
                $message = $this->l('Product mapping updated successfully.');
            } else {
                // Create new mapping
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = Db::getInstance()->insert('yuju_product_mapping', $data);
                $message = $this->l('Product mapping created successfully.');
            }

            if ($result) {
                $response['success'] = true;
                $response['message'] = $message;
                $this->logger->log('Product mapping saved: ' . $prestashop_field . ' -> ' . $yuju_field, 'info');
            } else {
                throw new Exception($this->l('Error saving product mapping.'));
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
                throw new Exception($this->l('Invalid mapping ID.'));
            }

            $mapping = Db::getInstance()->getRow('
                SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
                WHERE id_mapping = ' . $id_mapping
            );

            if ($mapping) {
                $response['success'] = true;
                $response['data'] = $mapping;
            } else {
                throw new Exception($this->l('Mapping not found.'));
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
                throw new Exception($this->l('Invalid mapping ID.'));
            }

            $result = Db::getInstance()->delete(
                'yuju_product_mapping',
                'id_mapping = ' . $id_mapping
            );

            if ($result) {
                $response['success'] = true;
                $response['message'] = $this->l('Product mapping deleted successfully.');
                $this->logger->log('Product mapping deleted: ID ' . $id_mapping, 'info');
            } else {
                throw new Exception($this->l('Error deleting product mapping.'));
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
                throw new Exception($this->l('Invalid mapping ID.'));
            }

            $result = Db::getInstance()->update(
                'yuju_product_mapping',
                ['is_active' => $is_active],
                'id_mapping = ' . $id_mapping
            );

            if ($result) {
                $response['success'] = true;
                $response['message'] = $is_active ? 
                    $this->l('Product mapping enabled successfully.') : 
                    $this->l('Product mapping disabled successfully.');
                $this->logger->log('Product mapping toggled: ID ' . $id_mapping . ' -> ' . ($is_active ? 'enabled' : 'disabled'), 'info');
            } else {
                throw new Exception($this->l('Error updating product mapping status.'));
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
            $response['message'] = $this->l('Yuju fields refreshed successfully.');
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error refreshing Yuju fields: ' . $e->getMessage(), 'error');
        }

        $this->ajaxRender(json_encode($response));
    }

    // Helper Methods
    protected function getProductMappings()
    {
        // Orden específico según requerimientos
        $order_fields = [
            'name', 'reference', 'reference', 'description', 'images', 'price', 
            'quantity', 'manufacturer', 'condition', 'shipping_method', 
            'shipping_price', 'dimension_unit', 'height', 'width', 'depth', 
            'weight_unit', 'weight', 'ml_template'
        ];
        
        $mappings = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
            ORDER BY 
                CASE prestashop_field
                    WHEN "name" THEN 1
                    WHEN "reference" THEN 2
                    WHEN "description" THEN 4
                    WHEN "images" THEN 5
                    WHEN "price" THEN 6
                    WHEN "quantity" THEN 7
                    WHEN "manufacturer" THEN 8
                    WHEN "condition" THEN 9
                    WHEN "shipping_method" THEN 10
                    WHEN "shipping_price" THEN 11
                    WHEN "dimension_unit" THEN 12
                    WHEN "height" THEN 13
                    WHEN "width" THEN 14
                    WHEN "depth" THEN 15
                    WHEN "weight_unit" THEN 16
                    WHEN "weight" THEN 17
                    WHEN "ml_template" THEN 18
                    ELSE 99
                END,
                yuju_field ASC
        ');
        
        return $mappings;
    }

    protected function getPrestashopFields()
    {
        return [
            ['id' => 'name', 'name' => $this->l('Product Name')],
            ['id' => 'description', 'name' => $this->l('Description')],
            ['id' => 'description_short', 'name' => $this->l('Short Description')],
            ['id' => 'price', 'name' => $this->l('Price')],
            ['id' => 'wholesale_price', 'name' => $this->l('Wholesale Price')],
            ['id' => 'reference', 'name' => $this->l('Reference/SKU')],
            ['id' => 'ean13', 'name' => $this->l('EAN13')],
            ['id' => 'upc', 'name' => $this->l('UPC')],
            ['id' => 'isbn', 'name' => $this->l('ISBN')],
            ['id' => 'mpn', 'name' => $this->l('MPN')],
            ['id' => 'quantity', 'name' => $this->l('Quantity')],
            ['id' => 'minimal_quantity', 'name' => $this->l('Minimal Quantity')],
            ['id' => 'weight', 'name' => $this->l('Weight')],
            ['id' => 'width', 'name' => $this->l('Width')],
            ['id' => 'height', 'name' => $this->l('Height')],
            ['id' => 'depth', 'name' => $this->l('Depth')],
            ['id' => 'active', 'name' => $this->l('Active')],
            ['id' => 'available_for_order', 'name' => $this->l('Available for Order')],
            ['id' => 'show_price', 'name' => $this->l('Show Price')],
            ['id' => 'online_only', 'name' => $this->l('Online Only')],
            ['id' => 'condition', 'name' => $this->l('Condition')],
            ['id' => 'visibility', 'name' => $this->l('Visibility')],
            ['id' => 'meta_title', 'name' => $this->l('Meta Title')],
            ['id' => 'meta_description', 'name' => $this->l('Meta Description')],
            ['id' => 'meta_keywords', 'name' => $this->l('Meta Keywords')],
            ['id' => 'link_rewrite', 'name' => $this->l('Friendly URL')],
            ['id' => 'available_now', 'name' => $this->l('Available Now Text')],
            ['id' => 'available_later', 'name' => $this->l('Available Later Text')],
        ];
    }

    protected function getYujuFields()
    {
        return [
            ['id' => 'title', 'name' => $this->l('Title')],
            ['id' => 'description', 'name' => $this->l('Description')],
            ['id' => 'short_description', 'name' => $this->l('Short Description')],
            ['id' => 'price', 'name' => $this->l('Price')],
            ['id' => 'cost_price', 'name' => $this->l('Cost Price')],
            ['id' => 'sku', 'name' => $this->l('SKU')],
            ['id' => 'barcode', 'name' => $this->l('Barcode')],
            ['id' => 'gtin', 'name' => $this->l('GTIN')],
            ['id' => 'mpn', 'name' => $this->l('MPN')],
            ['id' => 'stock_quantity', 'name' => $this->l('Stock Quantity')],
            ['id' => 'min_stock', 'name' => $this->l('Minimum Stock')],
            ['id' => 'weight', 'name' => $this->l('Weight')],
            ['id' => 'width', 'name' => $this->l('Width')],
            ['id' => 'height', 'name' => $this->l('Height')],
            ['id' => 'length', 'name' => $this->l('Length')],
            ['id' => 'status', 'name' => $this->l('Status')],
            ['id' => 'visibility', 'name' => $this->l('Visibility')],
            ['id' => 'condition', 'name' => $this->l('Condition')],
            ['id' => 'brand', 'name' => $this->l('Brand')],
            ['id' => 'category', 'name' => $this->l('Category')],
            ['id' => 'tags', 'name' => $this->l('Tags')],
            ['id' => 'meta_title', 'name' => $this->l('Meta Title')],
            ['id' => 'meta_description', 'name' => $this->l('Meta Description')],
            ['id' => 'meta_keywords', 'name' => $this->l('Meta Keywords')],
            ['id' => 'slug', 'name' => $this->l('URL Slug')],
        ];
    }

    protected function getFieldTypes()
    {
        return [
            ['id' => 'string', 'name' => $this->l('Cadena de texto')],
            ['id' => 'integer', 'name' => $this->l('Número entero')],
            ['id' => 'decimal', 'name' => $this->l('Número decimal')],
            ['id' => 'boolean', 'name' => $this->l('Verdadero/Falso')],
            ['id' => 'date', 'name' => $this->l('Fecha')],
            ['id' => 'datetime', 'name' => $this->l('Fecha y Hora')],
            ['id' => 'text', 'name' => $this->l('Texto')],
            ['id' => 'html', 'name' => $this->l('HTML')],
            ['id' => 'json', 'name' => $this->l('JSON')],
        ];
    }



    protected function getTransformationRules()
    {
        return [
            ['id' => 'none', 'name' => $this->l('Ninguna')],
            ['id' => 'uppercase', 'name' => $this->l('Mayúsculas')],
            ['id' => 'lowercase', 'name' => $this->l('Minúsculas')],
            ['id' => 'capitalize', 'name' => $this->l('Capitalizar')],
            ['id' => 'strip_html', 'name' => $this->l('Eliminar HTML')],
            ['id' => 'strip_tags', 'name' => $this->l('Eliminar Etiquetas')],
            ['id' => 'trim', 'name' => $this->l('Recortar Espacios')],
            ['id' => 'number_format', 'name' => $this->l('Formato de Número')],
            ['id' => 'date_format', 'name' => $this->l('Formato de Fecha')],
            ['id' => 'boolean_convert', 'name' => $this->l('Convertir a Booleano')],
            ['id' => 'custom', 'name' => $this->l('Código PHP Personalizado')],
        ];
    }
}
