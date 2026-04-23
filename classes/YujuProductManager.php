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

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuCategoryManager.php';

class YujuProductManager
{
    private $api_client;
    private $logger;
    private $category_manager;
    private $field_mappings;

    public function __construct()
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->category_manager = new YujuCategoryManager();
        $this->loadFieldMappings();
    }

    /**
     * Load active field mappings.
     */
    protected function loadFieldMappings()
    {
        $this->field_mappings = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
            WHERE is_active = 1
            ORDER BY `prestashop_field`
        ');
    }

    /**
     * Synchronize products from Yuju to PrestaShop.
     */
    public function syncProductsFromYuju($product_ids = null, $force_update = false)
    {
        try {
            $this->logger->log('Starting product synchronization from Yuju', 'info');

            $filters = [];

            if ($product_ids && is_array($product_ids)) {
                $filters['ids'] = $product_ids;
            }

            $yuju_products = $this->api_client->getProducts($filters);

            if (!$yuju_products || !isset($yuju_products['data'])) {
                throw new Exception('No products received from Yuju API');
            }

            $synced_count = 0;
            $created_count = 0;
            $updated_count = 0;
            $errors = [];

            foreach ($yuju_products['data'] as $yuju_product) {
                try {
                    $result = $this->syncSingleProductFromYuju($yuju_product, $force_update);

                    if ($result) {
                        ++$synced_count;

                        if ($result['action'] === 'created') {
                            ++$created_count;
                        } else {
                            ++$updated_count;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Product ' . $yuju_product['id'] . ': ' . $e->getMessage();
                    $this->logger->log('Error syncing product ' . $yuju_product['id'] . ': ' . $e->getMessage(), 'error');
                }
            }

            $this->logger->log('Product synchronization from Yuju completed. Synced: ' . $synced_count . ' (Created: ' . $created_count . ', Updated: ' . $updated_count . '), Errors: ' . count($errors), 'info');

            return [
                'success' => true,
                'synced_count' => $synced_count,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->log('Product synchronization from Yuju failed: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synchronize products from PrestaShop to Yuju.
     */
    public function syncProductsToYuju($product_ids = null, $category_ids = null)
    {
        try {
            $this->logger->log('Starting product synchronization to Yuju', 'info');

            $products = $this->getProductsForSync($product_ids, $category_ids);

            if (empty($products)) {
                throw new Exception('No products found for synchronization');
            }

            $synced_count = 0;
            $created_count = 0;
            $updated_count = 0;
            $errors = [];

            foreach ($products as $product_data) {
                try {
                    $result = $this->syncSingleProductToYuju($product_data);

                    if ($result) {
                        ++$synced_count;

                        if ($result['action'] === 'created') {
                            ++$created_count;
                        } else {
                            ++$updated_count;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Product ' . $product_data['id_product'] . ': ' . $e->getMessage();
                    $this->logger->log('Error syncing product ' . $product_data['id_product'] . ': ' . $e->getMessage(), 'error');
                }
            }

            $this->logger->log('Product synchronization to Yuju completed. Synced: ' . $synced_count . ' (Created: ' . $created_count . ', Updated: ' . $updated_count . '), Errors: ' . count($errors), 'info');

            return [
                'success' => true,
                'synced_count' => $synced_count,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->log('Product synchronization to Yuju failed: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync single product from Yuju to PrestaShop.
     */
    protected function syncSingleProductFromYuju($yuju_product, $force_update = false)
    {
        // Check if product already exists in PrestaShop
        $existing_product = $this->findProductByYujuId($yuju_product['id']);

        if ($existing_product) {
            // Update existing product
            if (!$force_update && !$this->isProductUpdateNeeded($existing_product, $yuju_product)) {
                return false;
            }

            $product = new Product($existing_product['id_product']);
            $this->updateProductFromYujuData($product, $yuju_product);

            if ($product->save()) {
                $this->updateProductStatus($existing_product['id_product'], 'synced', null, $yuju_product['id']);
                $this->logger->log('Product updated from Yuju: ' . $yuju_product['id'] . ' -> ' . $product->id, 'info');

                return ['action' => 'updated', 'product_id' => $product->id];
            } else {
                throw new Exception('Failed to save updated product');
            }
        } else {
            // Create new product
            $product = new Product();
            $this->updateProductFromYujuData($product, $yuju_product);

            if ($product->save()) {
                // Create product status record
                $this->createProductStatus($product->id, 'synced', null, $yuju_product['id']);
                $this->logger->log('Product created from Yuju: ' . $yuju_product['id'] . ' -> ' . $product->id, 'info');

                return ['action' => 'created', 'product_id' => $product->id];
            } else {
                throw new Exception('Failed to save new product');
            }
        }
    }

    /**
     * Sync single product from PrestaShop to Yuju.
     */
    protected function syncSingleProductToYuju($product_data)
    {
        $product = new Product($product_data['id_product']);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception('PrestaShop product not found: ' . $product_data['id_product']);
        }

        // Prepare product data for Yuju
        $yuju_data = $this->prepareProductDataForYuju($product);

        // Check if product exists in Yuju
        $yuju_product_id = $this->getYujuProductId($product->id);

        if ($yuju_product_id) {
            // Update existing product in Yuju
            $result = $this->api_client->updateProduct($yuju_product_id, $yuju_data);
            $action = 'updated';
        } else {
            // Create new product in Yuju
            $result = $this->api_client->createProduct($yuju_data);
            $action = 'created';

            if ($result && isset($result['id'])) {
                $yuju_product_id = $result['id'];
            }
        }

        if ($result) {
            $this->updateProductStatus($product->id, 'synced', null, $yuju_product_id);
            $this->logger->log('Product synced to Yuju: ' . $product->id . ' -> ' . $yuju_product_id, 'info');

            return ['action' => $action, 'yuju_product_id' => $yuju_product_id];
        } else {
            throw new Exception('Failed to sync product to Yuju');
        }
    }

    /**
     * Update PrestaShop product with Yuju data.
     */
    protected function updateProductFromYujuData($product, $yuju_product)
    {
        $languages = Language::getLanguages(false);
        $default_lang = Configuration::get('PS_LANG_DEFAULT');

        foreach ($this->field_mappings as $mapping) {
            if ($mapping['sync_direction'] === 'ps_to_yuju') {
                continue; // Skip fields that only sync from PS to Yuju
            }

            $yuju_field = $mapping['yuju_field'];
            $ps_field = $mapping['prestashop_field'];

            if (!isset($yuju_product[$yuju_field])) {
                if ($mapping['is_required'] && !empty($mapping['default_value'])) {
                    $value = $mapping['default_value'];
                } else {
                    continue;
                }
            } else {
                $value = $yuju_product[$yuju_field];
            }

            // Apply transformation
            $value = $this->applyTransformation($value, $mapping);

            // Set field value based on type
            if (in_array($ps_field, ['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords', 'link_rewrite', 'available_now', 'available_later'])) {
                // Multilingual fields
                foreach ($languages as $language) {
                    $product->{$ps_field}[$language['id_lang']] = $value;
                }
            } else {
                // Single value fields
                $product->{$ps_field} = $value;
            }
        }

        // Set category if mapped
        if (isset($yuju_product['category_id'])) {
            $category_mapping = $this->getCategoryMappingByYujuId($yuju_product['category_id']);

            if ($category_mapping) {
                $product->id_category_default = $category_mapping['prestashop_category_id'];
            }
        }

        // Set manufacturer if available
        if (isset($yuju_product['brand']) && !empty($yuju_product['brand'])) {
            $manufacturer = $this->getOrCreateManufacturer($yuju_product['brand']);

            if ($manufacturer) {
                $product->id_manufacturer = $manufacturer->id;
            }
        }
    }

    /**
     * Prepare PrestaShop product data for Yuju.
     */
    protected function prepareProductDataForYuju($product)
    {
        $default_lang = Configuration::get('PS_LANG_DEFAULT');
        $data = [];

        foreach ($this->field_mappings as $mapping) {
            if ($mapping['sync_direction'] === 'yuju_to_ps') {
                continue; // Skip fields that only sync from Yuju to PS
            }

            $ps_field = $mapping['prestashop_field'];
            $yuju_field = $mapping['yuju_field'];
            
            // NUNCA tomar id_category de field mappings - se maneja por separado con mapeo de categorías
            if ($yuju_field === 'id_category' || $yuju_field === 'category_id') {
                continue;
            }

            // Get value from PrestaShop product
            if (in_array($ps_field, ['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords', 'link_rewrite', 'available_now', 'available_later'])) {
                $value = isset($product->{$ps_field}[$default_lang]) ? $product->{$ps_field}[$default_lang] : '';
            } elseif ($ps_field === 'price_final') {
                // Precio final con impuestos y descuentos aplicados
                $value = Product::getPriceStatic($product->id, true, null, 2, null, false, true);
            } elseif ($ps_field === 'price_with_tax') {
                // Precio base con impuestos (sin descuentos)
                $value = Product::getPriceStatic($product->id, true, null, 2, null, false, false);
            } elseif ($ps_field === 'price_without_tax') {
                // Precio sin impuestos
                $value = Product::getPriceStatic($product->id, false, null, 2, null, false, false);
            } else {
                $value = isset($product->{$ps_field}) ? $product->{$ps_field} : '';
            }
            
            // Limitar decimales a 2 para campos numéricos
            if (in_array($ps_field, ['price', 'price_final', 'price_with_tax', 'price_without_tax', 'wholesale_price', 'weight', 'width', 'height', 'depth']) && is_numeric($value)) {
                $value = round((float) $value, 2);
            }

            // Use default value if empty, 0 or required
            // Para dimensiones de envío (shipping_depth, shipping_height, shipping_width), usar default si es 0 o vacío
            if ($yuju_field === 'shipping_depth' || $yuju_field === 'shipping_height' || $yuju_field === 'shipping_width') {
                if ((empty($value) || $value == 0) && !empty($mapping['default_value'])) {
                    $value = $mapping['default_value'];
                }
            } elseif (empty($value) && $mapping['is_required'] && !empty($mapping['default_value'])) {
                $value = $mapping['default_value'];
            }

            // Apply transformation
            $value = $this->applyTransformation($value, $mapping);

            // Solo agregar si tiene valor (no enviar campos vacíos)
            if ($value !== '' && $value !== null) {
                $data[$yuju_field] = $value;
            }
        }

        // CAMPOS OBLIGATORIOS según documentación de Yuju
        
        // SKU es obligatorio
        if (empty($data['sku'])) {
            $data['sku'] = $product->reference ?: 'PS-' . $product->id;
        }
        if (empty($data['sku_simple'])) {
            $data['sku_simple'] = $data['sku'];
        }
        
        // Nombre es obligatorio
        if (empty($data['name'])) {
            $data['name'] = isset($product->name[$default_lang]) ? $product->name[$default_lang] : 'Producto ' . $product->id;
        }
        
        // Precio es obligatorio - 2 decimales
        if (!isset($data['price']) || $data['price'] === '') {
            $data['price'] = round((float) $product->price, 2);
        } else {
            $data['price'] = round((float) $data['price'], 2);
        }
        
        // Stock es obligatorio - SIEMPRE tomar de PrestaShop
        $stock = StockAvailable::getQuantityAvailableByProduct($product->id);
        $data['stock'] = (int) $stock;
        
        // id_category es obligatorio - SIEMPRE usar mapeo de categorías, NUNCA field mapping
        if ($product->id_category_default) {
            $category_mapping = $this->getCategoryMappingByPrestashopId($product->id_category_default);
            
            // Debug: Log del mapeo encontrado
            $this->logger->log(
                'Category mapping lookup for PrestaShop category ' . $product->id_category_default . 
                ' (product ' . $product->id . '): ' . 
                ($category_mapping ? json_encode($category_mapping) : 'NOT FOUND'),
                'info'
            );
            
            if ($category_mapping && !empty($category_mapping['yuju_category_id'])) {
                // Obtener el ID de Yuju del mapeo
                $yuju_cat_id = trim($category_mapping['yuju_category_id']);
                
                // Log detallado del valor obtenido
                $this->logger->log(
                    'Yuju category ID from mapping: "' . $yuju_cat_id . '" (type: ' . gettype($yuju_cat_id) . 
                    ', is_numeric: ' . (is_numeric($yuju_cat_id) ? 'YES' : 'NO') . 
                    ', intval: ' . (int)$yuju_cat_id . ')',
                    'info'
                );
                
                // Validar que sea un número válido
                if (is_numeric($yuju_cat_id) && (int)$yuju_cat_id > 0) {
                    // Usar el ID de Yuju del mapeo
                    $data['id_category'] = (int) $yuju_cat_id;
                    
                    $this->logger->log(
                        'SUCCESS: Using Yuju category ID ' . $data['id_category'] . ' from mapping table',
                        'info'
                    );
                } else {
                    // El ID en el mapeo no es válido
                    $this->logger->log(
                        'ERROR: Invalid Yuju category ID in mapping: "' . $yuju_cat_id . 
                        '". Must be a numeric ID according to https://api-docs.yuju.io/docs/colores-y-categorias. ' .
                        'Using default category ID: 582 (Otros)',
                        'error'
                    );
                    $data['id_category'] = 582; // Categoría "Otros" de Yuju
                }
            } else {
                // Si no hay mapeo, usar categoría "Otros" de Yuju
                $this->logger->log(
                    'Warning: No category mapping found for PrestaShop category ' . 
                    $product->id_category_default . ' (product ' . $product->id . '). ' .
                    'Using default Yuju category ID: 582 (Otros). ' .
                    'Please configure category mapping in Admin → Yuju → Category Mapping',
                    'warning'
                );
                $data['id_category'] = 582; // Categoría "Otros" de Yuju
            }
        } else {
            // Si no tiene categoría en PrestaShop, usar categoría "Otros" de Yuju
            $this->logger->log(
                'Product ' . $product->id . ' has no default category in PrestaShop. Using default Yuju category ID: 582 (Otros)',
                'warning'
            );
            $data['id_category'] = 582; // Categoría "Otros" de Yuju
        }

        // CAMPOS OPCIONALES - Solo enviar si tienen valor
        
        // Descripción (opcional pero recomendado) - SIEMPRE quitar HTML
        if (empty($data['description'])) {
            $desc = isset($product->description[$default_lang]) ? $product->description[$default_lang] : '';
            if ($desc) {
                $data['description'] = strip_tags($desc);
            }
        } else {
            // Si ya existe en data, asegurar que no tenga HTML
            $data['description'] = strip_tags($data['description']);
        }
        
        // Marca (opcional)
        if (empty($data['brand']) && $product->id_manufacturer) {
            $manufacturer = new Manufacturer($product->id_manufacturer);
            if (Validate::isLoadedObject($manufacturer)) {
                $data['brand'] = $manufacturer->name;
            }
        }
        
        // Shipping (opcional, por defecto 1)
        if (!isset($data['shipping'])) {
            $data['shipping'] = 1;
        }
        
        // UNIDADES - SIEMPRE REQUERIDAS POR YUJU
        // Obtener de configuración de PrestaShop
        $ps_weight_unit = Configuration::get('PS_WEIGHT_UNIT');
        $ps_dimension_unit = Configuration::get('PS_DIMENSION_UNIT');
        
        // Si no hay configuración, usar valores por defecto
        if (!$ps_weight_unit) {
            $ps_weight_unit = 'kg';
        }
        if (!$ps_dimension_unit) {
            $ps_dimension_unit = 'cm';
        }
        
        // PESO - Yuju requiere weight_unit SIEMPRE
        if ($product->weight > 0) {
            $weight = (float) $product->weight;
            
            // Convertir a kg según la unidad de PrestaShop
            switch ($ps_weight_unit) {
                case 'g':
                    $weight = $weight / 1000; // gramos a kg
                    break;
                case 'lbs':
                    $weight = $weight * 0.453592; // libras a kg
                    break;
                case 'oz':
                    $weight = $weight * 0.0283495; // onzas a kg
                    break;
                // 'kg' no necesita conversión
            }
            
            if (!isset($data['weight'])) {
                $data['weight'] = round($weight, 3);
            }
        } else {
            // Si no tiene peso, usar valor mínimo por defecto
            if (!isset($data['weight'])) {
                $data['weight'] = 0.1;
            }
        }
        // SIEMPRE enviar weight_unit (requerido por Yuju)
        if (!isset($data['weight_unit'])) {
            $data['weight_unit'] = 'kg';
        }
        
        // DIMENSIONES - Yuju requiere dimensions_unit SIEMPRE
        if ($product->width > 0 || $product->height > 0 || $product->depth > 0) {
            $width = (float) $product->width;
            $height = (float) $product->height;
            $depth = (float) $product->depth;
            
            // Convertir a cm según la unidad de PrestaShop
            switch ($ps_dimension_unit) {
                case 'm':
                    $width = $width * 100; // metros a cm
                    $height = $height * 100;
                    $depth = $depth * 100;
                    break;
                case 'mm':
                    $width = $width / 10; // milímetros a cm
                    $height = $height / 10;
                    $depth = $depth / 10;
                    break;
                case 'in':
                    $width = $width * 2.54; // pulgadas a cm
                    $height = $height * 2.54;
                    $depth = $depth * 2.54;
                    break;
                // 'cm' no necesita conversión
            }
            
            if (!isset($data['shipping_width'])) {
                $data['shipping_width'] = $width > 0 ? round($width, 2) : 1.0;
            }
            if (!isset($data['shipping_height'])) {
                $data['shipping_height'] = $height > 0 ? round($height, 2) : 1.0;
            }
            if (!isset($data['shipping_depth'])) {
                $data['shipping_depth'] = $depth > 0 ? round($depth, 2) : 1.0;
            }
        } else {
            // Si no tiene dimensiones, usar valores mínimos por defecto
            if (!isset($data['shipping_width'])) {
                $data['shipping_width'] = 1.0;
            }
            if (!isset($data['shipping_height'])) {
                $data['shipping_height'] = 1.0;
            }
            if (!isset($data['shipping_depth'])) {
                $data['shipping_depth'] = 1.0;
            }
        }
        // SIEMPRE enviar dimensions_unit (requerido por Yuju)
        if (!isset($data['dimensions_unit'])) {
            $data['dimensions_unit'] = 'cm';
        }
        
        // EAN - Solo si existe
        if (!isset($data['ean']) && !empty($product->ean13)) {
            $data['ean'] = $product->ean13;
        }
        
        // UPC - Solo si existe
        if (!isset($data['upc']) && !empty($product->upc)) {
            $data['upc'] = $product->upc;
        }
        
        // Imágenes - Solo si existen
        if (!isset($data['images'])) {
            $images = Image::getImages($default_lang, $product->id);
            if (!empty($images)) {
                $data['images'] = [];
                $context = Context::getContext();
                foreach ($images as $image) {
                    $image_url = $context->link->getImageLink(
                        $product->link_rewrite[$default_lang],
                        $image['id_image'],
                        'large_default'
                    );
                    $data['images'][] = $image_url;
                }
            }
        }
        
        // NO enviar variations si el producto no tiene variaciones
        // Yuju espera variations como array de diccionarios, no como string vacío
        
        // NO enviar campos vacíos - limpiar nulls y strings vacíos
        $data = array_filter($data, function($value) {
            return $value !== '' && $value !== null && $value !== [];
        });

        return $data;
    }

    /**
     * Apply field transformation.
     */
    protected function applyTransformation($value, $mapping)
    {
        switch ($mapping['transformation_rule']) {
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'capitalize':
                return ucwords($value);
            case 'strip_html':
                return strip_tags($value);
            case 'currency_convert':
                // Implement currency conversion logic
                return $value;
            case 'date_format':
                if ($value && strtotime($value)) {
                    return date('Y-m-d H:i:s', strtotime($value));
                }
                return $value;
            case 'custom':
                if (!empty($mapping['custom_transformation'])) {
                    // Execute custom PHP code (be careful with security)
                    return $this->executeCustomTransformation($value, $mapping['custom_transformation']);
                }
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Execute custom transformation (with security considerations).
     */
    protected function executeCustomTransformation($value, $code)
    {
        // This is a simplified implementation
        // In production, you should implement proper sandboxing

        try {
            $result = eval('return ' . $code . ';');

            return $result !== false ? $result : $value;
        } catch (Exception $e) {
            $this->logger->log('Custom transformation error: ' . $e->getMessage(), 'error');

            return $value;
        }
    }

    /**
     * Find product by Yuju ID.
     */
    protected function findProductByYujuId($yuju_product_id)
    {
        return Db::getInstance()->getRow('
            SELECT ps.*, p.id_product
            FROM ' . _DB_PREFIX_ . 'yuju_product_status ps
            JOIN ' . _DB_PREFIX_ . 'product p ON ps.prestashop_product_id = p.id_product
            WHERE ps.yuju_product_id = "' . pSQL($yuju_product_id) . '"
        ');
    }

    /**
     * Get Yuju product ID for PrestaShop product.
     */
    protected function getYujuProductId($prestashop_product_id)
    {
        $result = Db::getInstance()->getRow('
            SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . (int) $prestashop_product_id . '
        ');

        return $result ? $result['yuju_product_id'] : null;
    }

    /**
     * Check if product update is needed.
     */
    protected function isProductUpdateNeeded($existing_product, $yuju_product)
    {
        if (isset($yuju_product['updated_at'])) {
            $yuju_updated = strtotime($yuju_product['updated_at']);
            $ps_updated = strtotime($existing_product['date_upd']);

            return $yuju_updated > $ps_updated;
        }

        return true;
    }

    /**
     * Get products for synchronization.
     */
    protected function getProductsForSync($product_ids = null, $category_ids = null)
    {
        $sql = 'SELECT p.* FROM ' . _DB_PREFIX_ . 'product p WHERE p.active = 1';

        if ($product_ids && is_array($product_ids)) {
            $sql .= ' AND p.id_product IN (' . implode(',', array_map('intval', $product_ids)) . ')';
        }

        if ($category_ids && is_array($category_ids)) {
            $sql .= ' AND p.id_category_default IN (' . implode(',', array_map('intval', $category_ids)) . ')';
        }

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Create product status record.
     */
    protected function createProductStatus($prestashop_product_id, $sync_status, $error_message = null, $yuju_product_id = null)
    {
        $data = [
            'prestashop_product_id' => (int) $prestashop_product_id,
            'sync_status' => pSQL($sync_status),
            'last_sync_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($error_message) {
            $data['last_error'] = pSQL($error_message);
        }

        if ($yuju_product_id) {
            $data['yuju_product_id'] = pSQL($yuju_product_id);
        }

        return Db::getInstance()->insert('yuju_product_status', $data);
    }

    /**
     * Update product status.
     */
    protected function updateProductStatus($prestashop_product_id, $sync_status, $error_message = null, $yuju_product_id = null)
    {
        $data = [
            'sync_status' => pSQL($sync_status),
            'last_sync_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($error_message) {
            $data['last_error'] = pSQL($error_message);
        } else {
            $data['last_error'] = null;
        }

        if ($yuju_product_id) {
            $data['yuju_product_id'] = pSQL($yuju_product_id);
        }

        $existing = Db::getInstance()->getRow('
            SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . (int) $prestashop_product_id . '
        ');

        if ($existing) {
            return Db::getInstance()->update(
                'yuju_product_status',
                $data,
                'prestashop_product_id = ' . (int) $prestashop_product_id
            );
        } else {
            $data['prestashop_product_id'] = (int) $prestashop_product_id;
            $data['created_at'] = date('Y-m-d H:i:s');

            return Db::getInstance()->insert('yuju_product_status', $data);
        }
    }

    /**
     * Get category mapping by Yuju ID.
     */
    protected function getCategoryMappingByYujuId($yuju_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE yuju_category_id = "' . pSQL($yuju_category_id) . '" AND sync_enabled = 1
        ');
    }

    /**
     * Get category mapping by PrestaShop ID.
     */
    protected function getCategoryMappingByPrestashopId($prestashop_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE prestashop_category_id = ' . (int) $prestashop_category_id . ' AND sync_enabled = 1
        ');
    }

    /**
     * Get or create manufacturer.
     */
    protected function getOrCreateManufacturer($brand_name)
    {
        $manufacturer = Manufacturer::getIdByName($brand_name);

        if ($manufacturer) {
            return new Manufacturer($manufacturer);
        }

        // Create new manufacturer
        $manufacturer = new Manufacturer();
        $manufacturer->name = $brand_name;
        $manufacturer->active = true;

        if ($manufacturer->save()) {
            return $manufacturer;
        }

        return null;
    }

    /**
     * Get product synchronization statistics.
     */
    public function getProductSyncStats()
    {
        $stats = [];

        $stats['total_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product WHERE active = 1
        ');

        $stats['synced_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_status = \'synced\'
        ');

        $stats['error_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_status = \'error\'
        ');

        $stats['pending_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_status = \'pending\'
        ');

        $stats['last_sync'] = Db::getInstance()->getValue('
            SELECT MAX(last_sync_at) FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');

        return $stats;
    }

    /**
     * Send single product to Yuju with history tracking.
     * 
     * @param int $product_id PrestaShop product ID
     * @return array Result with success status, message and history ID
     */
    public function sendProductToYuju($product_id)
    {
        $start_time = microtime(true);
        $product_id = (int) $product_id;
        
        try {
            // Load product
            $product = new Product($product_id);
            if (!Validate::isLoadedObject($product)) {
                throw new Exception('Product not found: ' . $product_id);
            }

            // Prepare product data for Yuju
            $yuju_data = $this->prepareProductDataForYuju($product);
            
            // Check if product already exists in Yuju
            $yuju_product_id = $this->getYujuProductId($product_id);
            
            $action = $yuju_product_id ? 'update' : 'create';
            $request_data = json_encode($yuju_data, JSON_PRETTY_PRINT);
            
            // Call Yuju API
            if ($yuju_product_id) {
                // Update existing product
                $result = $this->api_client->updateProduct($yuju_product_id, $yuju_data);
            } else {
                // Create new product
                $result = $this->api_client->createProduct($yuju_data);
            }
            
            $duration = microtime(true) - $start_time;
            
            // YujuApiClient devuelve: ['success' => bool, 'data' => array, 'http_code' => int, 'message' => string]
            // Parsear respuesta de Yuju que puede tener formato: {errors: [...], success: [...]}
            $response_data = $result['data'] ?? [];
            $http_code = $result['http_code'] ?? 500;
            
            // Verificar si hay productos exitosos en la respuesta
            $successful_products = isset($response_data['success']) && is_array($response_data['success']) 
                ? $response_data['success'] 
                : [];
            
            // Verificar si hay errores en la respuesta
            $error_products = isset($response_data['errors']) && is_array($response_data['errors']) 
                ? $response_data['errors'] 
                : [];
            
            // Determinar si fue exitoso
            if (!empty($successful_products) && count($successful_products) > 0) {
                // Éxito - extraer el id_product del primer producto exitoso
                $first_success = $successful_products[0];
                $yuju_product_id = $first_success['id_product'] ?? null;
                
                // Verificar si hay warnings
                $warnings = isset($first_success['warning']) && is_array($first_success['warning']) 
                    ? $first_success['warning'] 
                    : [];
                
                $has_warnings = !empty($warnings);
                $warning_message = $has_warnings ? implode('; ', $warnings) : null;
                
                if ($yuju_product_id) {
                    // Estado: 'synced' si no hay warnings, 'synced_with_warnings' si hay warnings
                    $sync_status = $has_warnings ? 'synced_with_warnings' : 'synced';
                    $this->updateProductStatus($product_id, $sync_status, $warning_message, $yuju_product_id);
                    
                    $history_id = $this->logSyncHistory([
                        'prestashop_product_id' => $product_id,
                        'yuju_product_id' => $yuju_product_id,
                        'sync_direction' => 'to_yuju',
                        'action' => $action,
                        'status' => 'success',
                        'http_status_code' => $http_code,
                        'request_data' => $request_data,
                        'response_data' => json_encode($response_data, JSON_PRETTY_PRINT),
                        'error_message' => $warning_message, // Guardar warnings aquí
                        'sync_duration' => $duration,
                    ]);
                    
                    $log_message = 'Product sent to Yuju successfully: ' . $product_id . ' -> ' . $yuju_product_id;
                    if ($has_warnings) {
                        $log_message .= ' (with warnings: ' . $warning_message . ')';
                    }
                    $this->logger->log($log_message, $has_warnings ? 'warning' : 'info');
                    
                    $message = $action === 'create' ? 'Producto creado en Yuju' : 'Producto actualizado en Yuju';
                    if ($has_warnings) {
                        $message .= ' con advertencias';
                    }
                    
                    return [
                        'success' => true,
                        'message' => $message,
                        'yuju_product_id' => $yuju_product_id,
                        'action' => $action,
                        'history_id' => $history_id,
                        'warnings' => $warnings,
                        'has_warnings' => $has_warnings,
                    ];
                }
            }
            
            // Si llegamos aquí, hubo un error
            // Construir mensaje de error desde el array de errores de Yuju
            $error_message = 'Error desconocido';
            
            if (!empty($error_products)) {
                $first_error = $error_products[0];
                
                if (isset($first_error['message']) && is_array($first_error['message'])) {
                    // Concatenar todos los mensajes de error
                    $error_message = implode('; ', $first_error['message']);
                } elseif (isset($first_error['message'])) {
                    $error_message = $first_error['message'];
                }
            } elseif (isset($result['message'])) {
                $error_message = $result['message'];
            } elseif (empty($successful_products) && $http_code == 200) {
                $error_message = 'API returned empty result or missing ID';
            }
            
            throw new Exception($error_message);
            
        } catch (Exception $e) {
            $duration = microtime(true) - $start_time;
            $error_message = $e->getMessage();
            
            // Log error
            $this->updateProductStatus($product_id, 'error', $error_message, $yuju_product_id ?? null);
            
            $history_id = $this->logSyncHistory([
                'prestashop_product_id' => $product_id,
                'yuju_product_id' => $yuju_product_id ?? null,
                'sync_direction' => 'to_yuju',
                'action' => $action ?? 'create',
                'status' => 'error',
                'http_status_code' => isset($result['http_code']) ? $result['http_code'] : 500,
                'request_data' => $request_data ?? json_encode($yuju_data ?? [], JSON_PRETTY_PRINT),
                'response_data' => isset($result['data']) ? json_encode($result['data'], JSON_PRETTY_PRINT) : null,
                'error_message' => $error_message,
                'sync_duration' => $duration,
            ]);
            
            $this->logger->log('Failed to send product to Yuju: ' . $product_id . ' - ' . $error_message, 'error');
            
            return [
                'success' => false,
                'message' => 'Error al enviar producto: ' . $error_message,
                'error' => $error_message,
                'history_id' => $history_id,
            ];
        }
    }

    /**
     * Log sync history to database.
     * 
     * @param array $data History data
     * @return int|false History ID or false on failure
     */
    protected function logSyncHistory($data)
    {
        $created_by = 'system';
        if (isset(Context::getContext()->employee->id)) {
            $created_by = 'employee_' . Context::getContext()->employee->id;
        }

        $insert_data = [
            'prestashop_product_id' => (int) $data['prestashop_product_id'],
            'yuju_product_id' => pSQL($data['yuju_product_id']),
            'sync_direction' => pSQL($data['sync_direction']),
            'action' => pSQL($data['action']),
            'status' => pSQL($data['status']),
            'http_status_code' => (int) ($data['http_status_code'] ?? 0),
            'request_data' => pSQL($data['request_data'], true),
            'response_data' => pSQL($data['response_data'], true),
            'error_message' => pSQL($data['error_message'], true),
            'sync_duration' => (float) ($data['sync_duration'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => pSQL($created_by),
        ];

        if (Db::getInstance()->insert('yuju_product_sync_history', $insert_data)) {
            return Db::getInstance()->Insert_ID();
        }

        return false;
    }

    /**
     * Get sync history for a product.
     * 
     * @param int $product_id PrestaShop product ID
     * @param int $limit Number of records to retrieve
     * @return array History records
     */
    public function getProductSyncHistory($product_id, $limit = 10, $offset = 0)
    {
        $product_id = (int) $product_id;
        $limit = (int) $limit;
        $offset = (int) $offset;
        
        $query = new DbQuery();
        $query->select('*');
        $query->from('yuju_product_sync_history');
        $query->where('prestashop_product_id = ' . (int)$product_id);
        $query->orderBy('created_at DESC');
        $query->limit($limit, $offset);
        
        return Db::getInstance()->executeS($query);
    }    /**
     * Get sync statistics for a product.
     * 
     * @param int $product_id PrestaShop product ID
     * @return array Statistics
     */
    public function getProductSyncStatistics($product_id)
    {
        $product_id = (int) $product_id;
        $stats = [];
        $last_sql = '';

        try {
            // Total syncs
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $stats['total_syncs'] = (int) Db::getInstance()->getValue($query);

            // Successful syncs
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->where('status = "success"');
            $stats['successful_syncs'] = (int) Db::getInstance()->getValue($query);

            // Failed syncs
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->where('status = "error"');
            $stats['failed_syncs'] = (int) Db::getInstance()->getValue($query);

            // Last sync - Use executeS and get first result instead of getRow with LIMIT
            $query = new DbQuery();
            $query->select('*');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->orderBy('id DESC');
            $query->limit(1);
            $last_sql = str_replace(["\n", "\r"], ' ', $query->build());
            $result = Db::getInstance()->executeS($query);
            $stats['last_sync'] = !empty($result) ? $result[0] : null;

            // Average duration
            $query = new DbQuery();
            $query->select('AVG(sync_duration)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->where('status = "success"');
            $stats['average_duration'] = (float) Db::getInstance()->getValue($query);
        } catch (Exception $e) {
            // Log the actual SQL query that caused the error
            $this->logger->log('SQL Error in getProductSyncStatistics: ' . $e->getMessage() . ' | SQL: ' . $last_sql, 'error');
            // Re-throw with SQL as array
            throw new Exception(json_encode([
                'error' => $e->getMessage(),
                'sql' => $last_sql
            ]));
        }

        return $stats;
    }
}
