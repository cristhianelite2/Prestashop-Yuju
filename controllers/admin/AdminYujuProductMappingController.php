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
        
        // Fix constraint issue automatically
        $this->fixMappingConstraint();

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
        // Handle AJAX requests
        if (Tools::getValue('ajax')) {
            $this->processAjaxRequests();
            return;
        }
        
        // Load default mappings if none exist
        $this->ensureDefaultMappings();
        
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
    
    /**
     * Ensure default mappings exist in database
     */
    private function ensureDefaultMappings()
    {
        // Check if mappings already exist
        $existingMappings = Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_mapping`'
        );
        
        if ($existingMappings > 0) {
            return; // Mappings already exist
        }
        
        // Defaults canónicos basados en documentación oficial de Yuju.
        $defaultMappings = $this->getCanonicalDefaultMappings();
        
        // Insert default mappings
        foreach ($defaultMappings as $mapping) {
            $data = [
                'prestashop_field' => pSQL($mapping['prestashop_field']),
                'yuju_field' => pSQL($mapping['yuju_field']),
                'default_value' => pSQL($mapping['default_value']),
                'field_type' => 'string',
                'sync_direction' => 'bidirectional',
                'transformation_rule' => 'none',
                'is_required' => (int)$mapping['is_required'],
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            Db::getInstance()->insert('yuju_product_mapping', $data);
        }
    }
    
    /**
     * Handle AJAX requests
     */
    private function processAjaxRequests()
    {
        $action = Tools::getValue('action');
        
        switch ($action) {
            case 'saveMappings':
                $this->ajaxSaveMappings();
                break;
            default:
                die(json_encode(['success' => false, 'message' => 'Acción no válida']));
        }
    }
    
    /**
     * Save mappings via AJAX
     */
    private function ajaxSaveMappings()
    {
        try {
            $mappingsJson = Tools::getValue('mappings');
            
            if (empty($mappingsJson)) {
                throw new Exception('No se recibieron datos de mapeo');
            }
            
            $mappings = json_decode($mappingsJson, true);
            
            if (!is_array($mappings) || empty($mappings)) {
                throw new Exception('Datos de mapeo inválidos o vacíos');
            }

            // Normalizar estructura y asegurar cobertura de campos obligatorios.
            $mappings = $this->normalizeAndCompleteRequiredMappings($mappings);
            
            // Clear existing mappings
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'yuju_product_mapping`');
            
            $insertedCount = 0;
            
            // Insert new mappings
            foreach ($mappings as $mapping) {
                if (empty($mapping['prestashop_field']) || empty($mapping['yuju_field'])) {
                    continue;
                }
                
                $data = [
                    'prestashop_field' => pSQL($mapping['prestashop_field']),
                    'yuju_field' => pSQL($mapping['yuju_field']),
                    'default_value' => pSQL($mapping['default_value'] ?? ''),
                    'field_type' => 'string',
                    'sync_direction' => 'bidirectional',
                    'transformation_rule' => 'none',
                    'is_required' => !empty($mapping['is_required']) ? 1 : 0,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                
                if (Db::getInstance()->insert('yuju_product_mapping', $data)) {
                    $insertedCount++;
                }
            }
            
            header('Content-Type: application/json');
            die(json_encode([
                'success' => true, 
                'message' => 'Mapeos guardados exitosamente',
                'count' => $insertedCount
            ]));
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public function renderForm()
    {
        // PrestaShop product fields
        $prestashop_fields = [
        ['id' => 'name', 'name' => 'Nombre del Producto'],
        ['id' => 'description', 'name' => 'Descripción'],
        ['id' => 'description_short', 'name' => 'Descripción Corta'],
        ['id' => 'price', 'name' => 'Precio'],
        ['id' => 'price_final', 'name' => 'Precio Final (con impuestos y descuentos)'],
        ['id' => 'price_with_tax', 'name' => 'Precio Normal (con impuestos)'],
        ['id' => 'price_without_tax', 'name' => 'Precio sin Impuestos'],
        ['id' => 'wholesale_price', 'name' => 'Precio de Mayoreo'],
        ['id' => 'reference', 'name' => 'Referencia/SKU'],
        ['id' => 'ean13', 'name' => 'EAN13'],
        ['id' => 'upc', 'name' => 'UPC'],
        ['id' => 'isbn', 'name' => 'ISBN'],
        ['id' => 'mpn', 'name' => 'MPN (Número de Parte)'],
        ['id' => 'weight', 'name' => 'Peso'],
        ['id' => 'width', 'name' => 'Ancho'],
        ['id' => 'height', 'name' => 'Alto'],
        ['id' => 'depth', 'name' => 'Profundidad'],
        ['id' => 'quantity', 'name' => 'Cantidad/Stock'],
        ['id' => 'minimal_quantity', 'name' => 'Cantidad Mínima'],
        ['id' => 'active', 'name' => 'Activo'],
        ['id' => 'available_for_order', 'name' => 'Disponible para Pedidos'],
        ['id' => 'show_price', 'name' => 'Mostrar Precio'],
        ['id' => 'online_only', 'name' => 'Solo Online'],
        ['id' => 'condition', 'name' => 'Condición (nuevo/usado/reacondicionado)'],
        ['id' => 'manufacturer_name', 'name' => 'Marca/Fabricante'],
        ['id' => 'images', 'name' => 'Imágenes del Producto'],
        ['id' => 'id_category_default', 'name' => 'Categoría Principal'],
        ['id' => 'unit_dimension', 'name' => 'Unidad de Dimensión'],
        ['id' => 'unit_weight', 'name' => 'Unidad de Peso'],
        ['id' => 'shipping_cost', 'name' => 'Costo de Envío'],
        ['id' => 'meta_title', 'name' => 'Meta Título'],
        ['id' => 'meta_description', 'name' => 'Meta Descripción'],
        ['id' => 'meta_keywords', 'name' => 'Meta Palabras Clave'],
        ['id' => 'link_rewrite', 'name' => 'URL Amigable'],
        ['id' => 'available_now', 'name' => 'Texto Disponible Ahora'],
        ['id' => 'available_later', 'name' => 'Texto Disponible Más Tarde'],
        ];

        // Yuju product fields - Campos según API documentation (✅ = obligatorio)
        $yuju_fields = [
        // Campos obligatorios según API
        ['id' => 'sku_simple', 'name' => '✅ SKU Simple', 'required' => true],
        ['id' => 'sku', 'name' => '✅ SKU', 'required' => true],
        ['id' => 'name', 'name' => '✅ Nombre', 'required' => true],
        ['id' => 'description', 'name' => '✅ Descripción', 'required' => true],
        ['id' => 'id_category', 'name' => '✅ ID Categoría', 'required' => true],
        ['id' => 'stock', 'name' => '✅ Stock', 'required' => true],
        ['id' => 'price', 'name' => '✅ Precio', 'required' => true],
        ['id' => 'brand', 'name' => '✅ Marca', 'required' => true],
        ['id' => 'shipping', 'name' => '✅ Envío (0=Gratis, 1=Marketplace, 2=Por Mi)', 'required' => true],
        ['id' => 'dimensions_unit', 'name' => '✅ Unidad de Dimensiones', 'required' => true],
        ['id' => 'shipping_width', 'name' => '✅ Ancho de Envío', 'required' => true],
        ['id' => 'shipping_depth', 'name' => '✅ Profundidad de Envío', 'required' => true],
        ['id' => 'shipping_height', 'name' => '✅ Alto de Envío', 'required' => true],
        ['id' => 'weight_unit', 'name' => '✅ Unidad de Peso', 'required' => true],
        ['id' => 'weight', 'name' => '✅ Peso del Paquete', 'required' => true],
        ['id' => 'images', 'name' => '✅ Imágenes (Lista de URLs)', 'required' => true],
        // Campos opcionales
        ['id' => 'condition', 'name' => 'Condición (nuevo/usado/reacondicionado)', 'required' => false],
        ['id' => 'characteristics', 'name' => 'Características', 'required' => false],
        ['id' => 'warranty', 'name' => 'Garantía', 'required' => false],
        ['id' => 'video_url', 'name' => 'URL de Video', 'required' => false],
        ['id' => 'listing_type', 'name' => 'Tipo de Publicación (gold_special/gold_premium)', 'required' => false],
        ['id' => 'ean', 'name' => 'EAN (European Article Number)', 'required' => false],
        ['id' => 'upc', 'name' => 'UPC (Universal Product Code)', 'required' => false],
        ['id' => 'isbn_10', 'name' => 'ISBN-10', 'required' => false],
        ['id' => 'isbn_13', 'name' => 'ISBN-13', 'required' => false],
        ['id' => 'mpn', 'name' => 'MPN (Número de Parte del Fabricante)', 'required' => false],
        ['id' => 'product_weight', 'name' => 'Peso del Producto (no del paquete)', 'required' => false],
        ['id' => 'net_content', 'name' => 'Contenido Neto', 'required' => false],
        ['id' => 'variations', 'name' => 'Variaciones', 'required' => false],
        ['id' => 'channel_fields', 'name' => 'Campos por Canal', 'required' => false],
        ['id' => 'channel_categories', 'name' => 'Categorías por Canal', 'required' => false],
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
        $default_mappings = $this->getCanonicalDefaultMappings();

        // Recarga real: reemplazar completamente los mapeos actuales.
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'yuju_product_mapping`');

        $inserted = 0;
        foreach ($default_mappings as $mapping) {
            $mapping['field_type'] = 'string';
            $mapping['sync_direction'] = 'bidirectional';
            $mapping['transformation_rule'] = 'none';
            $mapping['is_active'] = 1;
            $mapping['created_at'] = date('Y-m-d H:i:s');
            $mapping['updated_at'] = date('Y-m-d H:i:s');

            if (Db::getInstance()->insert('yuju_product_mapping', $mapping)) {
                ++$inserted;
            }
        }

        $this->confirmations[] = sprintf($this->l('%d default mappings loaded successfully.'), $inserted);
        $this->logger->log('Default product mappings reloaded: ' . $inserted . ' mappings', 'info');
    }

    protected function updateRequiredFields()
    {
        // Campos obligatorios de Yuju según API oficial.
        $required_fields = [
            'sku_simple', 'sku', 'name', 'description',
            'stock', 'price', 'brand', 'shipping', 'dimensions_unit',
            'shipping_width', 'shipping_depth', 'shipping_height',
            'weight_unit', 'weight', 'images',
        ];

        $updated = 0;
        foreach ($required_fields as $field) {
            $result = Db::getInstance()->update(
                'yuju_product_mapping',
                ['is_required' => 1],
                'yuju_field = "' . pSQL($field) . '"'
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
        // Ordenar por ID para mostrar los últimos creados al final
        $mappings = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
            ORDER BY id_mapping ASC
        ');
        
        return $mappings;
    }

    protected function getPrestashopFields()
    {
        return [
            ['id' => 'name', 'name' => 'Nombre del Producto'],
            ['id' => 'description', 'name' => 'Descripción'],
            ['id' => 'description_short', 'name' => 'Descripción Corta'],
            ['id' => 'price', 'name' => 'Precio'],
            ['id' => 'price_final', 'name' => 'Precio Final (con impuestos y descuentos)'],
            ['id' => 'price_with_tax', 'name' => 'Precio Normal (con impuestos)'],
            ['id' => 'price_without_tax', 'name' => 'Precio sin Impuestos'],
            ['id' => 'wholesale_price', 'name' => 'Precio de Mayoreo'],
            ['id' => 'reference', 'name' => 'Referencia/SKU'],
            ['id' => 'ean13', 'name' => 'EAN13'],
            ['id' => 'upc', 'name' => 'UPC'],
            ['id' => 'isbn', 'name' => 'ISBN'],
            ['id' => 'mpn', 'name' => 'MPN (Número de Parte)'],
            ['id' => 'weight', 'name' => 'Peso'],
            ['id' => 'width', 'name' => 'Ancho'],
            ['id' => 'height', 'name' => 'Alto'],
            ['id' => 'depth', 'name' => 'Profundidad'],
            ['id' => 'quantity', 'name' => 'Cantidad/Stock'],
            ['id' => 'minimal_quantity', 'name' => 'Cantidad Mínima'],
            ['id' => 'active', 'name' => 'Activo'],
            ['id' => 'available_for_order', 'name' => 'Disponible para Pedidos'],
            ['id' => 'show_price', 'name' => 'Mostrar Precio'],
            ['id' => 'online_only', 'name' => 'Solo Online'],
            ['id' => 'condition', 'name' => 'Condición (nuevo/usado/reacondicionado)'],
            ['id' => 'manufacturer_name', 'name' => 'Marca/Fabricante'],
            ['id' => 'images', 'name' => 'Imágenes del Producto'],
            ['id' => 'id_category_default', 'name' => 'Categoría Principal'],
            ['id' => 'unit_dimension', 'name' => 'Unidad de Dimensión'],
            ['id' => 'unit_weight', 'name' => 'Unidad de Peso'],
            ['id' => 'shipping_cost', 'name' => 'Costo de Envío'],
            ['id' => 'visibility', 'name' => 'Visibilidad'],
            ['id' => 'meta_title', 'name' => 'Meta Título'],
            ['id' => 'meta_description', 'name' => 'Meta Descripción'],
            ['id' => 'meta_keywords', 'name' => 'Meta Palabras Clave'],
            ['id' => 'link_rewrite', 'name' => 'URL Amigable'],
            ['id' => 'available_now', 'name' => 'Texto Disponible Ahora'],
            ['id' => 'available_later', 'name' => 'Texto Disponible Más Tarde'],
        ];
    }

    protected function getYujuFields()
    {
        return [
            // Campos obligatorios según API de Yuju
            ['id' => 'sku_simple', 'name' => 'SKU Simple', 'required' => true],
            ['id' => 'sku', 'name' => 'SKU', 'required' => true],
            ['id' => 'name', 'name' => 'Nombre', 'required' => true],
            ['id' => 'description', 'name' => 'Descripción', 'required' => true],
            ['id' => 'id_category', 'name' => 'ID Categoría', 'required' => true],
            ['id' => 'stock', 'name' => 'Stock', 'required' => true],
            ['id' => 'price', 'name' => 'Precio', 'required' => true],
            ['id' => 'brand', 'name' => 'Marca', 'required' => true],
            ['id' => 'shipping', 'name' => 'Envío (0=Gratis, 1=Marketplace, 2=Por Mi)', 'required' => true],
            ['id' => 'dimensions_unit', 'name' => 'Unidad de Dimensiones', 'required' => true],
            ['id' => 'shipping_width', 'name' => 'Ancho de Envío', 'required' => true],
            ['id' => 'shipping_depth', 'name' => 'Profundidad de Envío', 'required' => true],
            ['id' => 'shipping_height', 'name' => 'Alto de Envío', 'required' => true],
            ['id' => 'weight_unit', 'name' => 'Unidad de Peso', 'required' => true],
            ['id' => 'weight', 'name' => 'Peso del Paquete', 'required' => true],
            ['id' => 'images', 'name' => 'Imágenes (Lista de URLs)', 'required' => true],
            // Campos opcionales
            ['id' => 'condition', 'name' => 'Condición (nuevo/usado/reacondicionado)', 'required' => false],
            ['id' => 'characteristics', 'name' => 'Características', 'required' => false],
            ['id' => 'warranty', 'name' => 'Garantía', 'required' => false],
            ['id' => 'video_url', 'name' => 'URL de Video', 'required' => false],
            ['id' => 'listing_type', 'name' => 'Tipo de Publicación (gold_special/gold_premium)', 'required' => false],
            ['id' => 'ean', 'name' => 'EAN (European Article Number)', 'required' => false],
            ['id' => 'upc', 'name' => 'UPC (Universal Product Code)', 'required' => false],
            ['id' => 'isbn_10', 'name' => 'ISBN-10', 'required' => false],
            ['id' => 'isbn_13', 'name' => 'ISBN-13', 'required' => false],
            ['id' => 'mpn', 'name' => 'MPN (Número de Parte del Fabricante)', 'required' => false],
            ['id' => 'product_weight', 'name' => 'Peso del Producto (no del paquete)', 'required' => false],
            ['id' => 'net_content', 'name' => 'Contenido Neto', 'required' => false],
            ['id' => 'variations', 'name' => 'Variaciones', 'required' => false],
            ['id' => 'channel_fields', 'name' => 'Campos por Canal', 'required' => false],
            ['id' => 'channel_categories', 'name' => 'Categorías por Canal', 'required' => false],
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
    
    /**
     * Fix mapping constraint to allow multiple Yuju fields from same PrestaShop field
     * This method runs automatically and checks if the constraint needs updating
     */
    private function fixMappingConstraint()
    {
        try {
            // Check if old constraint exists
            $sql = "SHOW INDEXES FROM `" . _DB_PREFIX_ . "yuju_product_mapping` 
                    WHERE Key_name = 'unique_mapping'";
            $result = Db::getInstance()->executeS($sql);
            
            // If constraint has only one column (prestashop_field), we need to fix it
            if ($result && count($result) === 1 && isset($result[0]['Column_name']) && $result[0]['Column_name'] === 'prestashop_field') {
                // Drop old constraint
                Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "yuju_product_mapping` DROP INDEX `unique_mapping`");
                
                // Clear existing data to avoid conflicts
                Db::getInstance()->execute("TRUNCATE TABLE `" . _DB_PREFIX_ . "yuju_product_mapping`");
                
                // Add new combined constraint
                Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "yuju_product_mapping` 
                    ADD UNIQUE KEY `unique_mapping` (`prestashop_field`, `yuju_field`)");
            }
        } catch (Exception $e) {
            // Log error but don't break the controller
            $this->logger->error('Failed to fix mapping constraint: ' . $e->getMessage());
        }
    }

    /**
     * Defaults canónicos: campos requeridos Yuju + defaults útiles.
     */
    private function getCanonicalDefaultMappings()
    {
        $shopName = trim((string) Configuration::get('PS_SHOP_NAME'));
        if ($shopName === '') {
            $shopName = 'PrestaShop';
        }

        return [
            // Requeridos por Yuju (https://api-docs.yuju.io/docs/product-model)
            ['prestashop_field' => 'reference', 'yuju_field' => 'sku_simple', 'default_value' => '', 'is_required' => 1],
            ['prestashop_field' => 'reference', 'yuju_field' => 'sku', 'default_value' => '', 'is_required' => 1],
            ['prestashop_field' => 'name', 'yuju_field' => 'name', 'default_value' => '', 'is_required' => 1],
            ['prestashop_field' => 'description', 'yuju_field' => 'description', 'default_value' => 'Descripcion no disponible', 'is_required' => 1],
            ['prestashop_field' => 'quantity', 'yuju_field' => 'stock', 'default_value' => '', 'is_required' => 1],
            ['prestashop_field' => 'price', 'yuju_field' => 'price', 'default_value' => '', 'is_required' => 1],
            ['prestashop_field' => 'manufacturer_name', 'yuju_field' => 'brand', 'default_value' => $shopName, 'is_required' => 1],
            ['prestashop_field' => 'available_for_order', 'yuju_field' => 'shipping', 'default_value' => '1', 'is_required' => 1],
            ['prestashop_field' => 'unit_dimension', 'yuju_field' => 'dimensions_unit', 'default_value' => 'cm', 'is_required' => 1],
            ['prestashop_field' => 'width', 'yuju_field' => 'shipping_width', 'default_value' => '8', 'is_required' => 1],
            ['prestashop_field' => 'depth', 'yuju_field' => 'shipping_depth', 'default_value' => '35', 'is_required' => 1],
            ['prestashop_field' => 'height', 'yuju_field' => 'shipping_height', 'default_value' => '44', 'is_required' => 1],
            ['prestashop_field' => 'unit_weight', 'yuju_field' => 'weight_unit', 'default_value' => 'kg', 'is_required' => 1],
            ['prestashop_field' => 'weight', 'yuju_field' => 'weight', 'default_value' => '1', 'is_required' => 1],
            ['prestashop_field' => 'images', 'yuju_field' => 'images', 'default_value' => '', 'is_required' => 1],
            // Opcionales recomendados
            ['prestashop_field' => 'condition', 'yuju_field' => 'condition', 'default_value' => 'new', 'is_required' => 0],
            ['prestashop_field' => 'ean13', 'yuju_field' => 'ean', 'default_value' => '', 'is_required' => 0],
            ['prestashop_field' => 'upc', 'yuju_field' => 'upc', 'default_value' => '', 'is_required' => 0],
        ];
    }

    /**
     * Normaliza llaves del payload y agrega faltantes requeridos.
     */
    private function normalizeAndCompleteRequiredMappings(array $mappings)
    {
        $normalized = [];
        $yujuIndex = [];
        $defaultByYuju = [];

        foreach ($this->getCanonicalDefaultMappings() as $defaultMap) {
            $defaultByYuju[$defaultMap['yuju_field']] = $defaultMap;
        }

        foreach ($mappings as $mapping) {
            $psField = isset($mapping['prestashop_field']) ? $mapping['prestashop_field'] : (isset($mapping['ps_field']) ? $mapping['ps_field'] : '');
            $yujuField = isset($mapping['yuju_field']) ? $mapping['yuju_field'] : '';
            $defaultValue = isset($mapping['default_value']) ? $mapping['default_value'] : '';

            if (empty($psField) || empty($yujuField)) {
                continue;
            }

            // id_category se gestiona desde el mapeo de categorías.
            if ($yujuField === 'id_category') {
                continue;
            }

            if ($yujuField === 'brand' && ($defaultValue === '' || $defaultValue === 'Global-Laptops')) {
                $defaultValue = trim((string) Configuration::get('PS_SHOP_NAME'));
                if ($defaultValue === '') {
                    $defaultValue = 'PrestaShop';
                }
            }

            // Si viene requerido desde defaults canónicos, prevalece.
            $isRequired = isset($defaultByYuju[$yujuField]) ? (int) $defaultByYuju[$yujuField]['is_required'] : 0;

            $row = [
                'prestashop_field' => $psField,
                'yuju_field' => $yujuField,
                'default_value' => $defaultValue,
                'is_required' => $isRequired,
            ];

            $yujuIndex[$yujuField] = count($normalized);
            $normalized[] = $row;
        }

        // Garantizar que todos los requeridos de Yuju existan.
        foreach ($defaultByYuju as $yujuField => $defaultMap) {
            if ((int) $defaultMap['is_required'] !== 1) {
                continue;
            }

            if (!isset($yujuIndex[$yujuField])) {
                $normalized[] = [
                    'prestashop_field' => $defaultMap['prestashop_field'],
                    'yuju_field' => $yujuField,
                    'default_value' => $defaultMap['default_value'],
                    'is_required' => 1,
                ];
            } else {
                // Si existe, asegurar flag required.
                $normalized[$yujuIndex[$yujuField]]['is_required'] = 1;
            }
        }

        return $normalized;
    }
}
