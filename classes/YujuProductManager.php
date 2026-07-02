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
        $id = (int) $product_data['id_product'];
        $result = $this->sendProductToYuju($id);
        if (!empty($result['success'])) {
            return [
                'action' => (($result['action'] ?? '') === 'create') ? 'created' : 'updated',
                'yuju_product_id' => $result['yuju_product_id'] ?? null,
                'awaiting_webhook' => !empty($result['awaiting_webhook']),
            ];
        }

        throw new Exception($result['error'] ?? $result['message'] ?? 'Failed to sync product to Yuju');
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
    protected function prepareProductDataForYuju($product, array $options = [])
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
        
        // id_category es obligatorio - usar categoría mapeada del producto (default o cualquiera asignada)
        if ($product->id_category_default) {
            $preferredCategoryId = isset($options['preferred_ps_category_id']) ? (int) $options['preferred_ps_category_id'] : 0;
            $resolved = $this->resolveMappedYujuCategoryForProduct((int) $product->id, (int) $product->id_category_default, $preferredCategoryId);
            if (!empty($resolved['yuju_category_id'])) {
                $data['id_category'] = (int) $resolved['yuju_category_id'];
                $this->logger->log(
                    'SUCCESS: Using Yuju category ID ' . $data['id_category']
                    . ' from PrestaShop category ' . (int) ($resolved['prestashop_category_id'] ?? 0)
                    . ' (product ' . (int) $product->id . ')',
                    'info'
                );
            } else {
                $this->logger->log(
                    'Warning: No mapped category found among product categories for product ' . (int) $product->id
                    . '. Using default Yuju category ID: 582 (Otros).',
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
     * Fila yuju_product_status o null.
     *
     * @return array<string, mixed>|null
     */
    public function getProductYujuStatusRow($prestashop_product_id)
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
             WHERE prestashop_product_id = ' . (int) $prestashop_product_id
        );

        return $row ?: null;
    }

    /**
     * @param mixed $last_sync_data false = no tocar columna; null = vaciar; string = guardar tal cual
     */
    public function updateProductStatus($prestashop_product_id, $sync_status, $error_message = null, $yuju_product_id = null, $last_sync_data = false)
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

        if ($last_sync_data !== false) {
            $data['last_sync_data'] = $last_sync_data === null ? null : pSQL($last_sync_data, true);
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
     * Webhook product-created: confirma creación en Yuju y deja el producto en synced.
     *
     * @param string|null $parent_id Si viene, es variación (no enlazamos fila padre aquí)
     *
     * @return array{success: bool, message?: string}
     */
    public function confirmProductCreatedFromWebhook($resource_id, $sku, $sku_simple, $parent_id = null)
    {
        $resource_id_raw = trim((string) $resource_id);
        $resource_id = $this->normalizeWebhookResourceId($resource_id_raw);
        $sku = trim((string) $sku);
        $sku_simple = trim((string) $sku_simple);
        $parent_id_norm = $this->normalizeWebhookResourceId((string) $parent_id);

        if ($resource_id === '') {
            return ['success' => false, 'message' => 'resource_id vacío'];
        }

        // Solo tratar como variación cuando parent_id es realmente informativo (no "0"/"null"/vacío).
        if ($parent_id_norm !== '') {
            $this->logger->log('Yuju webhook product-created: variación (parent_id=' . $parent_id . '), sin actualizar estado PS padre', 'info');

            return ['success' => true, 'message' => 'Variación ignorada para estado PrestaShop'];
        }

        $matched = null;

        // 1) Match directo por id Yuju ya guardado (más confiable si hubo reprocesos/retentos).
        $q = new DbQuery();
        $q->select('*')
            ->from('yuju_product_status')
            ->where('TRIM(IFNULL(yuju_product_id, "")) = "' . pSQL($resource_id) . '"')
            ->limit(1);
        $rows = Db::getInstance()->executeS($q);
        if (!empty($rows)) {
            $matched = $rows[0];
        }

        $candidates = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
             WHERE sync_status = \'creating_in_yuju\''
        ) ?: [];

        if (!$matched) {
            foreach ($candidates as $c) {
                $meta = json_decode($c['last_sync_data'] ?? '{}', true) ?: [];
                $meta_id = $this->normalizeWebhookResourceId((string) ($meta['api_yuju_id'] ?? ''));
                if ($meta_id !== '' && $meta_id === $resource_id) {
                    $matched = $c;
                    break;
                }
            }
        }

        if (!$matched && $sku !== '') {
            $q = new DbQuery();
            $q->select('yps.*')
                ->from('yuju_product_status', 'yps')
                ->innerJoin('product', 'p', 'p.id_product = yps.prestashop_product_id')
                ->where('yps.sync_status = "creating_in_yuju"')
                ->where('TRIM(IFNULL(p.reference, "")) = "' . pSQL($sku) . '"');
            $rows = Db::getInstance()->executeS($q);
            $matched = !empty($rows) ? $rows[0] : false;
        }

        if (!$matched && $sku !== '' && !empty($candidates)) {
            foreach ($candidates as $c) {
                $meta = json_decode($c['last_sync_data'] ?? '{}', true) ?: [];
                $meta_sku = trim((string) ($meta['sku'] ?? ''));
                if ($meta_sku !== '' && strcasecmp($meta_sku, $sku) === 0) {
                    $matched = $c;
                    break;
                }
            }
        }

        if (!$matched && $sku_simple !== '' && $sku_simple !== $sku) {
            $q = new DbQuery();
            $q->select('yps.*')
                ->from('yuju_product_status', 'yps')
                ->innerJoin('product', 'p', 'p.id_product = yps.prestashop_product_id')
                ->where('yps.sync_status = "creating_in_yuju"')
                ->where('TRIM(IFNULL(p.reference, "")) = "' . pSQL($sku_simple) . '"');
            $rows = Db::getInstance()->executeS($q);
            $matched = !empty($rows) ? $rows[0] : false;
        }

        if (!$matched) {
            // 2) Rescate por historial (casos donde el estado quedó desfasado pero sí hubo create exitoso).
            try {
                $h = Db::getInstance()->getRow(
                    'SELECT `prestashop_product_id`
                     FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                     WHERE `action` = "create"
                       AND TRIM(IFNULL(`yuju_product_id`, "")) = "' . pSQL($resource_id) . '"
                     ORDER BY `id` DESC'
                );
                if (!empty($h['prestashop_product_id'])) {
                    $pid_hist = (int) $h['prestashop_product_id'];
                    $row_hist = Db::getInstance()->getRow(
                        'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                         WHERE `prestashop_product_id` = ' . $pid_hist
                    );
                    if ($row_hist) {
                        $matched = $row_hist;
                    }
                }
            } catch (Exception $e) {
                // No interrumpir el flujo de webhook por fallas de tabla/consulta de historial.
            }
        }

        if (!$matched) {
            $this->logger->log('product-created webhook sin fila PS en creating_in_yuju (resource=' . $resource_id_raw . ', resource_norm=' . $resource_id . ', sku=' . $sku . ')', 'warning');

            return ['success' => true, 'message' => 'Sin fila pendiente local; evento registrado'];
        }

        $pid = (int) $matched['prestashop_product_id'];
        // Limpiar también last_sync_data para quitar bandera webhook_pending.
        $this->updateProductStatus($pid, 'synced', null, $resource_id, null);

        $this->logger->log('Webhook product-created confirmado: PS ' . $pid . ' -> Yuju ' . $resource_id, 'info');

        return ['success' => true, 'prestashop_product_id' => $pid, 'yuju_product_id' => $resource_id];
    }

    /**
     * Normaliza IDs de webhook (pueden venir como "/products/123", "123", " 123 ", etc).
     *
     * @param string $value
     *
     * @return string
     */
    protected function normalizeWebhookResourceId($value)
    {
        $v = trim((string) $value);
        $v_l = strtolower($v);
        if (
            $v === ''
            || $v === '0'
            || $v_l === 'null'
            || $v_l === 'none'
            || $v_l === 'n/a'
            || $v_l === 'na'
            || $v_l === 'false'
        ) {
            return '';
        }
        if (preg_match('/(\d+)(?!.*\d)/', $v, $m)) {
            return (string) $m[1];
        }

        return $v;
    }

    /**
     * Webhook product-deleted: elimina vínculo local tras borrado en Yuju.
     *
     * @return array{success: bool, message?: string}
     */
    public function confirmProductDeletedFromWebhook($resource_id, $sku, $parent_id = null)
    {
        $resource_id = trim((string) $resource_id);
        $sku = trim((string) $sku);

        if (!empty($parent_id)) {
            $this->logger->log('Yuju webhook product-deleted: variación (parent_id=' . $parent_id . ')', 'info');

            return ['success' => true, 'message' => 'Variación ignorada'];
        }

        $q = new DbQuery();
        $q->select('*')
            ->from('yuju_product_status')
            ->where('yuju_product_id = "' . pSQL($resource_id) . '"')
            ->limit(1);
        $rows = Db::getInstance()->executeS($q);
        $row = !empty($rows) ? $rows[0] : false;

        if (!$row && $sku !== '') {
            $q = new DbQuery();
            $q->select('yps.*')
                ->from('yuju_product_status', 'yps')
                ->innerJoin('product', 'p', 'p.id_product = yps.prestashop_product_id')
                ->where('TRIM(IFNULL(p.reference, "")) = "' . pSQL($sku) . '"')
                ->limit(1);
            $rows = Db::getInstance()->executeS($q);
            $row = !empty($rows) ? $rows[0] : false;
        }

        if (!$row) {
            return ['success' => true, 'message' => 'Producto no encontrado en estado local'];
        }

        $pid = (int) $row['prestashop_product_id'];
        Db::getInstance()->delete('yuju_product_status', 'prestashop_product_id = ' . $pid);

        $this->logger->log('Webhook product-deleted: eliminado vínculo local PS ' . $pid . ' (Yuju ' . $resource_id . ')', 'info');

        return ['success' => true, 'prestashop_product_id' => $pid];
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
     * Idioma para nombres en avisos de SKU duplicado (BO o tienda por defecto).
     */
    protected function getDuplicateCheckLanguageId()
    {
        $ctx = Context::getContext();
        if ($ctx && isset($ctx->language) && $ctx->language && (int) $ctx->language->id > 0) {
            return (int) $ctx->language->id;
        }

        return (int) Configuration::get('PS_LANG_DEFAULT');
    }

    /** @var array<string, true>|null Cache por petición HTTP */
    private static $globalDuplicatePrestaShopReferenceSetCache = null;

    /**
     * Valores TRIM(reference) que en todo PrestaShop están asociados a más de un id_product
     * (tabla product o product_attribute).
     *
     * @return array<string, true> clave = referencia normalizada
     */
    public function getGloballyDuplicatePrestaShopReferenceSet()
    {
        if (self::$globalDuplicatePrestaShopReferenceSetCache !== null) {
            return self::$globalDuplicatePrestaShopReferenceSetCache;
        }

        $dup_ref_rows = Db::getInstance()->executeS(
            'SELECT t.ref
             FROM (
                SELECT TRIM(p.reference) AS ref, p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                WHERE TRIM(IFNULL(p.reference, \'\')) != \'\'
                UNION ALL
                SELECT TRIM(pa.reference) AS ref, pa.id_product
                FROM ' . _DB_PREFIX_ . 'product_attribute pa
                WHERE TRIM(IFNULL(pa.reference, \'\')) != \'\'
             ) t
             GROUP BY t.ref
             HAVING COUNT(DISTINCT t.id_product) > 1'
        );

        $global_dup_refs = [];
        if ($dup_ref_rows) {
            foreach ($dup_ref_rows as $r) {
                $ref = trim((string) $r['ref']);
                if ($ref !== '') {
                    $global_dup_refs[$ref] = true;
                }
            }
        }

        self::$globalDuplicatePrestaShopReferenceSetCache = $global_dup_refs;

        return $global_dup_refs;
    }

    /**
     * Referencias repetidas en más de un id_product en **todo** el catálogo PrestaShop
     * (ps_product.reference y ps_product_attribute.reference, TRIM), cuando algún ID
     * solicitado usa esa referencia. Incluye comprobación del SKU efectivo del mapeo
     * (igual que en validateProductForYujuCreate).
     *
     * @param int[] $product_ids
     *
     * @return array<int, array{reference: string, products: list<array{id_product: int, name: string}>}>
     */
    public function getDuplicateSkuConflictsForProductIds(array $product_ids)
    {
        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($product_ids)) {
            return [];
        }

        $id_lang = $this->getDuplicateCheckLanguageId();
        $ids_sql = implode(',', $product_ids);

        $global_dup_refs = $this->getGloballyDuplicatePrestaShopReferenceSet();

        // 2) Referencias que usa el lote (producto + combinaciones del lote)
        $used_ref_rows = Db::getInstance()->executeS(
            'SELECT DISTINCT TRIM(p.reference) AS ref
             FROM ' . _DB_PREFIX_ . 'product p
             WHERE p.id_product IN (' . $ids_sql . ')
             AND TRIM(IFNULL(p.reference, \'\')) != \'\'
             UNION
             SELECT DISTINCT TRIM(pa.reference) AS ref
             FROM ' . _DB_PREFIX_ . 'product_attribute pa
             WHERE pa.id_product IN (' . $ids_sql . ')
             AND TRIM(IFNULL(pa.reference, \'\')) != \'\''
        );

        $conflicts = [];
        $seen_conflict_keys = [];

        if ($used_ref_rows) {
            foreach ($used_ref_rows as $row) {
                $ref = (string) $row['ref'];
                if ($ref === '' || !isset($global_dup_refs[$ref]) || isset($seen_conflict_keys[$ref])) {
                    continue;
                }
                $seen_conflict_keys[$ref] = true;

                $list = $this->getProductIdsAndNamesSharingReferenceValue($ref, $id_lang);
                if (count($list) > 1) {
                    $conflicts[] = [
                        'reference' => $ref,
                        'products' => $list,
                    ];
                }
            }
        }

        // 3) SKU efectivo (mapeo): mismo criterio que validateProductForYujuCreate / envío real
        foreach ($product_ids as $pid) {
            $product = new Product((int) $pid);
            if (!Validate::isLoadedObject($product)) {
                continue;
            }
            $yuju_data = $this->prepareProductDataForYuju($product);
            $sku = trim((string) ($yuju_data['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $skip_eff = $sku !== '' && preg_match('/^PS-(\d+)$/', $sku, $m_eff) && (int) $m_eff[1] === (int) $pid;
            if ($skip_eff) {
                continue;
            }
            if (isset($seen_conflict_keys[$sku])) {
                continue;
            }
            $eff_dup = $this->getDuplicateSkuConflictsForEffectiveSku($sku);
            if (empty($eff_dup)) {
                continue;
            }
            $seen_conflict_keys[$sku] = true;
            foreach ($eff_dup as $block) {
                $conflicts[] = $block;
            }
        }

        return $conflicts;
    }

    /**
     * Lista de id_product + nombre que comparten el mismo valor TRIM de referencia
     * en ps_product.reference o ps_product_attribute.reference (catálogo completo).
     *
     * @param string $ref_trimmed Valor ya normalizado (sin TRIM adicional en SQL del parámetro)
     *
     * @return list<array{id_product: int, name: string}>
     */
    protected function getProductIdsAndNamesSharingReferenceValue($ref_trimmed, $id_lang)
    {
        $ref_trimmed = trim((string) $ref_trimmed);
        if ($ref_trimmed === '') {
            return [];
        }
        $ref_esc = pSQL($ref_trimmed);
        $rows = Db::getInstance()->executeS(
            'SELECT t.id_product, pl.name
             FROM (
                SELECT DISTINCT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                WHERE TRIM(IFNULL(p.reference, \'\')) = \'' . $ref_esc . '\'
                  AND TRIM(IFNULL(p.reference, \'\')) != \'\'
                UNION
                SELECT DISTINCT pa.id_product
                FROM ' . _DB_PREFIX_ . 'product_attribute pa
                WHERE TRIM(IFNULL(pa.reference, \'\')) = \'' . $ref_esc . '\'
                  AND TRIM(IFNULL(pa.reference, \'\')) != \'\'
             ) t
             INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON (t.id_product = pl.id_product AND pl.id_lang = ' . (int) $id_lang . ')
             ORDER BY t.id_product ASC'
        );
        if (!$rows) {
            return [];
        }
        $list = [];
        $seen_pid = [];
        foreach ($rows as $pr) {
            $pid = (int) $pr['id_product'];
            if (isset($seen_pid[$pid])) {
                continue;
            }
            $seen_pid[$pid] = true;
            $list[] = [
                'id_product' => $pid,
                'name' => (string) $pr['name'],
            ];
        }

        return $list;
    }

    /**
     * Varios productos comparten el mismo valor como referencia, EAN, UPC o ISBN (coincide con el SKU que se enviaría).
     *
     * @param string $sku_trimmed Valor final de sku en el payload Yuju
     *
     * @return array<int, array{reference: string, products: list<array{id_product: int, name: string}>}>
     */
    public function getDuplicateSkuConflictsForEffectiveSku($sku_trimmed)
    {
        $sku_trimmed = trim((string) $sku_trimmed);
        if ($sku_trimmed === '') {
            return [];
        }

        $id_lang = $this->getDuplicateCheckLanguageId();
        $esc = pSQL($sku_trimmed);

        $rows = Db::getInstance()->executeS(
            'SELECT p.id_product, pl.name
             FROM ' . _DB_PREFIX_ . 'product p
             INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int) $id_lang . ')
             WHERE TRIM(IFNULL(p.reference, \'\')) = \'' . $esc . '\'
                OR TRIM(IFNULL(p.ean13, \'\')) = \'' . $esc . '\'
                OR TRIM(IFNULL(p.upc, \'\')) = \'' . $esc . '\'
                OR TRIM(IFNULL(p.isbn, \'\')) = \'' . $esc . '\'
             ORDER BY p.id_product ASC'
        );

        $rows_attr = Db::getInstance()->executeS(
            'SELECT DISTINCT p.id_product, pl.name
             FROM ' . _DB_PREFIX_ . 'product_attribute pa
             INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = pa.id_product
             INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int) $id_lang . ')
             WHERE TRIM(IFNULL(pa.reference, \'\')) = \'' . $esc . '\'
             ORDER BY p.id_product ASC'
        );
        if ($rows_attr) {
            if (!$rows) {
                $rows = [];
            }
            $rows = array_merge($rows, $rows_attr);
        }

        if (!$rows) {
            return [];
        }

        $list = [];
        $seen_pid = [];
        foreach ($rows as $pr) {
            $pid = (int) $pr['id_product'];
            if (isset($seen_pid[$pid])) {
                continue;
            }
            $seen_pid[$pid] = true;
            $list[] = [
                'id_product' => $pid,
                'name' => (string) $pr['name'],
            ];
        }

        if (count($list) <= 1) {
            return [];
        }

        return [
            [
                'reference' => $sku_trimmed,
                'products' => $list,
            ],
        ];
    }

    /**
     * Valida reglas obligatorias antes de crear en Yuju.
     *
     * @param int $product_id
     *
     * @return array{success: bool, errors: array<int,string>}
     */
    public function validateProductForYujuCreate($product_id, array $options = [])
    {
        $product_id = (int) $product_id;
        $errors = [];

        $product = new Product($product_id);
        if (!Validate::isLoadedObject($product)) {
            return [
                'success' => false,
                'errors' => ['Producto no encontrado en PrestaShop (ID: ' . $product_id . ').'],
            ];
        }

        $yuju_data = $this->prepareProductDataForYuju($product, $options);

        $sku = trim((string) ($yuju_data['sku'] ?? ''));
        if ($sku === '') {
            $errors[] = 'El SKU está vacío. Revise el mapeo de campos (normalmente debe mapear a referencia).';
        } else {
            $dup = $this->getDuplicateSkuConflictsForEffectiveSku($sku);
            if (!empty($dup)) {
                $errors[] = $this->formatDuplicateSkuExceptionMessage($dup);
            }
        }

        foreach ($this->field_mappings as $mapping) {
            if ($mapping['sync_direction'] === 'yuju_to_ps') {
                continue;
            }
            if (empty($mapping['is_required'])) {
                continue;
            }
            $yuju_field = trim((string) ($mapping['yuju_field'] ?? ''));
            if ($yuju_field === '' || $yuju_field === 'id_category' || $yuju_field === 'category_id') {
                continue;
            }
            if (!array_key_exists($yuju_field, $yuju_data)) {
                $errors[] = 'Falta campo obligatorio para Yuju: ' . $yuju_field;
                continue;
            }
            $value = $yuju_data[$yuju_field];
            if ((is_string($value) && trim($value) === '') || $value === null || $value === []) {
                $errors[] = 'Campo obligatorio vacío para Yuju: ' . $yuju_field;
            }
        }

        if ((int) $product->id_category_default <= 0) {
            $errors[] = 'El producto no tiene categoría por defecto en PrestaShop.';
        } else {
            $preferredCategoryId = isset($options['preferred_ps_category_id']) ? (int) $options['preferred_ps_category_id'] : 0;
            $resolved = $this->resolveMappedYujuCategoryForProduct((int) $product->id, (int) $product->id_category_default, $preferredCategoryId);
            if (empty($resolved['yuju_category_id'])) {
                $candidateIds = $this->getProductCategoryIdsForMappingLookup((int) $product->id, (int) $product->id_category_default);
                $labels = [];
                foreach ($candidateIds as $cid) {
                    $labels[] = $this->getPrestashopCategoryLabelById((int) $cid);
                }
                $errors[] = 'Ninguna de las categorías del producto está mapeada con Yuju. '
                    . 'Categorías revisadas: ' . implode(' | ', $labels)
                    . '. Configure el Mapeo de Categorías antes de enviar.';
            } else {
                $yuju_cat_id = trim((string) $resolved['yuju_category_id']);
                if (!is_numeric($yuju_cat_id) || (int) $yuju_cat_id <= 0) {
                    $errors[] = 'El mapeo de categoría tiene un ID de Yuju inválido: "' . $yuju_cat_id . '".';
                }
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Devuelve IDs de categorías del producto priorizando la categoría por defecto.
     *
     * @param int $product_id
     * @param int $default_category_id
     *
     * @return int[]
     */
    protected function getProductCategoryIdsForMappingLookup($product_id, $default_category_id)
    {
        $product_id = (int) $product_id;
        $default_category_id = (int) $default_category_id;
        if ($product_id <= 0) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT id_category FROM `' . _DB_PREFIX_ . 'category_product` WHERE id_product = ' . $product_id
        );
        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $cid = isset($r['id_category']) ? (int) $r['id_category'] : 0;
                if ($cid > 0) {
                    $ids[] = $cid;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($default_category_id > 0) {
            $ids = array_values(array_diff($ids, [$default_category_id]));
            array_unshift($ids, $default_category_id);
        }

        return $ids;
    }

    /**
     * Busca el primer mapeo Yuju válido entre las categorías del producto.
     *
     * @param int $product_id
     * @param int $default_category_id
     *
     * @return array{prestashop_category_id:int, yuju_category_id:string}|array{}
     */
    protected function resolveMappedYujuCategoryForProduct($product_id, $default_category_id, $preferred_category_id = 0)
    {
        $categoryIds = $this->getProductCategoryIdsForMappingLookup((int) $product_id, (int) $default_category_id);
        $preferred_category_id = (int) $preferred_category_id;
        if ($preferred_category_id > 0 && in_array($preferred_category_id, $categoryIds, true)) {
            $categoryIds = array_values(array_diff($categoryIds, [$preferred_category_id]));
            array_unshift($categoryIds, $preferred_category_id);
        }
        foreach ($categoryIds as $cid) {
            $map = $this->getCategoryMappingByPrestashopId((int) $cid);
            if (!$map || empty($map['yuju_category_id'])) {
                continue;
            }
            $yuju_cat_id = trim((string) $map['yuju_category_id']);
            if (!is_numeric($yuju_cat_id) || (int) $yuju_cat_id <= 0) {
                continue;
            }

            return [
                'prestashop_category_id' => (int) $cid,
                'yuju_category_id' => (string) ((int) $yuju_cat_id),
            ];
        }

        return [];
    }

    /**
     * @param array<int, array{reference: string, products: list<array{id_product: int, name: string}>}> $conflicts
     */
    protected function formatDuplicateSkuExceptionMessage(array $conflicts)
    {
        $parts = ['La misma referencia/código (SKU) está duplicada en varios productos de PrestaShop. Corrija el catálogo antes de sincronizar con Yuju.'];
        foreach ($conflicts as $c) {
            $label = isset($c['reference']) ? (string) $c['reference'] : '';
            $ids = [];
            foreach ($c['products'] as $p) {
                $ids[] = '#' . (int) $p['id_product'] . ' — ' . (string) $p['name'];
            }
            $parts[] = 'Código "' . $label . '": ' . implode(' | ', $ids);
        }

        return implode(' ', $parts);
    }

    /**
     * Etiqueta legible para categoría PS: "ID X — Nombre".
     *
     * @param int $category_id
     *
     * @return string
     */
    protected function getPrestashopCategoryLabelById($category_id)
    {
        $category_id = (int) $category_id;
        if ($category_id <= 0) {
            return 'ID 0';
        }

        $id_lang = 0;
        $ctx = Context::getContext();
        if ($ctx && isset($ctx->language) && $ctx->language) {
            $id_lang = (int) $ctx->language->id;
        }
        if ($id_lang <= 0) {
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        $name = '';
        try {
            $row = Db::getInstance()->getRow(
                'SELECT cl.name
                 FROM `' . _DB_PREFIX_ . 'category_lang` cl
                 WHERE cl.id_category = ' . $category_id . '
                   AND cl.id_lang = ' . (int) $id_lang . '
                 ORDER BY cl.id_shop ASC'
            );
            if ($row && isset($row['name'])) {
                $name = trim((string) $row['name']);
            }
        } catch (Exception $e) {
            $name = '';
        }

        if ($name === '') {
            return 'ID ' . $category_id;
        }

        return 'ID ' . $category_id . ' — ' . $name;
    }

    /**
     * Send single product to Yuju with history tracking.
     * 
     * @param int $product_id PrestaShop product ID
     * @return array Result with success status, message and history ID
     */
    public function sendProductToYuju($product_id, array $options = [])
    {
        $start_time = microtime(true);
        $product_id = (int) $product_id;
        $existing_yuju_id = null;
        $action = 'create';
        $request_data = '';
        $yuju_data = [];
        $result = [];

        if (Module::isInstalled('prestashopyuju')) {
            $m = Module::getInstanceByName('prestashopyuju');
            if ($m && method_exists($m, 'ensureProductStatusIntermediateWebhookStates')) {
                $m->ensureProductStatusIntermediateWebhookStates();
            }
        }

        try {
            $product = new Product($product_id);
            if (!Validate::isLoadedObject($product)) {
                throw new Exception('Product not found: ' . $product_id);
            }

            $status_row = $this->getProductYujuStatusRow($product_id);
            if ($status_row && !empty($status_row['sync_status'])
                && in_array($status_row['sync_status'], ['creating_in_yuju', 'updating_in_yuju', 'deleting_in_yuju'], true)) {
                throw new Exception(
                    'Hay una operación pendiente con Yuju (estado: ' . $status_row['sync_status'] . '). Espere el webhook o el cierre de la operación.'
                );
            }

            $ref_dup = $this->getDuplicateSkuConflictsForProductIds([$product_id]);
            if (!empty($ref_dup)) {
                throw new Exception($this->formatDuplicateSkuExceptionMessage($ref_dup));
            }

            $yuju_data = $this->prepareProductDataForYuju($product, $options);

            $eff = trim((string) ($yuju_data['sku'] ?? ''));
            $skip_eff = ($eff !== '' && preg_match('/^PS-(\d+)$/', $eff, $m_eff) && (int) $m_eff[1] === (int) $product_id);
            if ($eff !== '' && !$skip_eff) {
                $eff_dup = $this->getDuplicateSkuConflictsForEffectiveSku($eff);
                if (!empty($eff_dup)) {
                    throw new Exception($this->formatDuplicateSkuExceptionMessage($eff_dup));
                }
            }

            $existing_yuju_id = $this->getYujuProductId($product_id);
            $action = $existing_yuju_id ? 'update' : 'create';

            if ($action === 'create') {
                $validation = $this->validateProductForYujuCreate($product_id, $options);
                if (empty($validation['success'])) {
                    throw new Exception(implode(' ', $validation['errors']));
                }
            }

            $request_data = json_encode($yuju_data, JSON_PRETTY_PRINT);

            if ($existing_yuju_id) {
                // En update, Yuju requiere sku/sku_simple pero sku_simple no es editable.
                // Se envían siempre ambos, fijándolos al SKU histórico bloqueado para no intentar editarlo.
                $currentSku = trim((string) ($yuju_data['sku'] ?? ''));
                $lockedSku = $this->getLockedSkuForExistingYujuProduct($product_id, $currentSku);
                if ($lockedSku !== '') {
                    $yuju_data['sku'] = $lockedSku;
                    $yuju_data['sku_simple'] = $lockedSku;
                } else {
                    // Fallback defensivo: mantener ambos sincronizados al valor actual.
                    $yuju_data['sku_simple'] = $currentSku;
                }
                $request_data = json_encode($yuju_data, JSON_PRETTY_PRINT);
                $this->updateProductStatus(
                    $product_id,
                    'updating_in_yuju',
                    'Actualizando en Yuju… (la confirmación final es por respuesta API; Yuju no envía webhook de solo actualización.)',
                    $existing_yuju_id
                );
                $result = $this->api_client->updateProduct($existing_yuju_id, $yuju_data);
            } else {
                $this->updateProductStatus(
                    $product_id,
                    'creating_in_yuju',
                    'Creando en Yuju… Pendiente de confirmación vía webhook (product-created).',
                    null
                );
                $result = $this->api_client->createProduct($yuju_data);
            }

            $duration = microtime(true) - $start_time;

            $response_data = $result['data'] ?? [];
            $http_code = $result['http_code'] ?? 500;

            $successful_products = isset($response_data['success']) && is_array($response_data['success'])
                ? $response_data['success']
                : [];

            $error_products = isset($response_data['errors']) && is_array($response_data['errors'])
                ? $response_data['errors']
                : [];

            if (!empty($successful_products) && count($successful_products) > 0) {
                $first_success = $successful_products[0];
                $returned_yuju_id = $first_success['id_product'] ?? null;

                $warnings = isset($first_success['warning']) && is_array($first_success['warning'])
                    ? $first_success['warning']
                    : [];

                $has_warnings = !empty($warnings);
                $warning_message = $has_warnings ? implode('; ', $warnings) : null;

                if ($action === 'update') {
                    $final_yuju_id = $returned_yuju_id ?: $existing_yuju_id;
                    if (!$final_yuju_id) {
                        throw new Exception('Actualización en Yuju sin ID de producto');
                    }
                    $sync_status = $has_warnings ? 'synced_with_warnings' : 'synced';
                    $this->updateProductStatus($product_id, $sync_status, $warning_message, $final_yuju_id);

                    $history_id = $this->logSyncHistory([
                        'prestashop_product_id' => $product_id,
                        'yuju_product_id' => $final_yuju_id,
                        'sync_direction' => 'to_yuju',
                        'action' => $action,
                        'status' => 'success',
                        'http_status_code' => $http_code,
                        'request_data' => $request_data,
                        'response_data' => json_encode($response_data, JSON_PRETTY_PRINT),
                        'error_message' => $warning_message,
                        'sync_duration' => $duration,
                    ]);

                    $message = 'Producto actualizado en Yuju';
                    if ($has_warnings) {
                        $message .= ' con advertencias';
                    }

                    return [
                        'success' => true,
                        'message' => $message,
                        'yuju_product_id' => $final_yuju_id,
                        'action' => $action,
                        'history_id' => $history_id,
                        'warnings' => $warnings,
                        'has_warnings' => $has_warnings,
                        'awaiting_webhook' => false,
                    ];
                }

                if ($action === 'create') {
                    $pending_meta = [
                        'webhook_pending' => true,
                        'pending_action' => 'create',
                        'api_yuju_id' => $returned_yuju_id ? (string) $returned_yuju_id : null,
                        'sku' => $eff,
                        'api_response_at' => date('Y-m-d H:i:s'),
                    ];
                    // Si la API ya devolvió id_product, dejamos el estado confirmado de inmediato.
                    // El webhook product-created pasa a ser confirmación adicional (no bloqueante).
                    if ($returned_yuju_id) {
                        $sync_status = $has_warnings ? 'synced_with_warnings' : 'synced';
                        $this->updateProductStatus(
                            $product_id,
                            $sync_status,
                            $warning_message,
                            (string) $returned_yuju_id,
                            json_encode($pending_meta, JSON_UNESCAPED_UNICODE)
                        );
                    } else {
                        $this->updateProductStatus(
                            $product_id,
                            'creating_in_yuju',
                            'Solicitud aceptada por Yuju. Esperando webhook product-created para confirmar el alta.',
                            null,
                            json_encode($pending_meta, JSON_UNESCAPED_UNICODE)
                        );
                    }

                    $history_id = $this->logSyncHistory([
                        'prestashop_product_id' => $product_id,
                        'yuju_product_id' => $returned_yuju_id ? (string) $returned_yuju_id : '',
                        'sync_direction' => 'to_yuju',
                        'action' => $action,
                        'status' => 'success',
                        'http_status_code' => $http_code,
                        'request_data' => $request_data,
                        'response_data' => json_encode($response_data, JSON_PRETTY_PRINT),
                        'error_message' => $returned_yuju_id ? $warning_message : 'Pendiente webhook product-created',
                        'sync_duration' => $duration,
                    ]);

                    return [
                        'success' => true,
                        'message' => $returned_yuju_id
                            ? 'Producto creado en Yuju correctamente.'
                            : 'Solicitud de creación enviada a Yuju. Estado: creando… Confirmación cuando llegue el webhook product-created.',
                        'yuju_product_id' => $returned_yuju_id ? (string) $returned_yuju_id : null,
                        'action' => $action,
                        'history_id' => $history_id,
                        'warnings' => $warnings,
                        'has_warnings' => $has_warnings,
                        'awaiting_webhook' => !$returned_yuju_id,
                    ];
                }
            }

            $error_message = 'Error desconocido';

            if (!empty($error_products)) {
                $first_error = $error_products[0];

                if (isset($first_error['message']) && is_array($first_error['message'])) {
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

            $this->updateProductStatus($product_id, 'error', $error_message, $existing_yuju_id);

            $history_id = $this->logSyncHistory([
                'prestashop_product_id' => $product_id,
                'yuju_product_id' => $existing_yuju_id ?: '',
                'sync_direction' => 'to_yuju',
                'action' => $action,
                'status' => 'error',
                'http_status_code' => isset($result['http_code']) ? $result['http_code'] : 500,
                'request_data' => $request_data !== '' ? $request_data : json_encode($yuju_data ?? [], JSON_PRETTY_PRINT),
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
     * Obtiene SKU "bloqueado" para productos ya creados en Yuju, evitando intentar editar sku_simple.
     *
     * @param int $product_id
     * @param string $fallbackSku
     *
     * @return string
     */
    protected function getLockedSkuForExistingYujuProduct($product_id, $fallbackSku = '')
    {
        $product_id = (int) $product_id;
        $fallbackSku = trim((string) $fallbackSku);
        if ($product_id <= 0) {
            return $fallbackSku;
        }

        // 1) Intentar extraer de la última petición exitosa guardada en historial.
        try {
            $row = Db::getInstance()->getRow(
                'SELECT request_data
                 FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                 WHERE prestashop_product_id = ' . $product_id . '
                   AND status = "success"
                   AND request_data IS NOT NULL
                   AND request_data != ""
                 ORDER BY id DESC
                 LIMIT 1'
            );
            if ($row && !empty($row['request_data'])) {
                $req = json_decode((string) $row['request_data'], true);
                if (is_array($req)) {
                    $skuSimple = isset($req['sku_simple']) ? trim((string) $req['sku_simple']) : '';
                    $sku = isset($req['sku']) ? trim((string) $req['sku']) : '';
                    if ($skuSimple !== '') {
                        return $skuSimple;
                    }
                    if ($sku !== '') {
                        return $sku;
                    }
                }
            }
        } catch (Exception $e) {
            // Continuar con fallback.
        }

        // 2) Fallback: usar SKU actual calculado.
        return $fallbackSku;
    }

    /**
     * Log sync history to database.
     * 
     * @param array $data History data
     * @return int|false History ID or false on failure
     */
    /**
     * Indica si existe la tabla de historial de envíos (evita excepciones SQL en PS 8).
     */
    public function isProductSyncHistoryTablePresent()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $rows = Db::getInstance()->executeS(
                'SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_product_sync_history"'
            );
            $cache = !empty($rows);
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    protected function logSyncHistory($data)
    {
        try {
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

            $db_err = Db::getInstance()->getMsgError();
            $this->logger->log(
                'No se pudo insertar en yuju_product_sync_history: ' . $db_err . ' | producto=' . ($data['prestashop_product_id'] ?? ''),
                'error'
            );

            return false;
        } catch (\Throwable $e) {
            $this->logger->log(
                'logSyncHistory excepción: ' . $e->getMessage() . ' | producto=' . ($data['prestashop_product_id'] ?? ''),
                'error'
            );

            return false;
        }
    }

    /**
     * Registra en historial un error de validación previo al envío a Yuju.
     *
     * @param int $prestashop_product_id
     * @param string $error_message
     * @param string $action
     * @return int|false
     */
    public function logValidationErrorHistory($prestashop_product_id, $error_message, $action = 'create')
    {
        $prestashop_product_id = (int) $prestashop_product_id;
        $action = in_array($action, ['create', 'update', 'delete'], true) ? $action : 'create';

        return $this->logSyncHistory([
            'prestashop_product_id' => $prestashop_product_id,
            'yuju_product_id' => '',
            'sync_direction' => 'to_yuju',
            'action' => $action,
            'status' => 'error',
            'http_status_code' => 422,
            'request_data' => null,
            'response_data' => null,
            'error_message' => (string) $error_message,
            'sync_duration' => 0,
        ]);
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
        if (!$this->isProductSyncHistoryTablePresent()) {
            return [];
        }

        $product_id = (int) $product_id;
        $limit = (int) $limit;
        $offset = (int) $offset;

        $query = new DbQuery();
        $query->select('*');
        $query->from('yuju_product_sync_history');
        $query->where('prestashop_product_id = ' . (int) $product_id);
        $query->orderBy('created_at DESC');
        $query->limit($limit, $offset);

        return Db::getInstance()->executeS($query) ?: [];
    }

    /**
     * Get sync statistics for a product.
     * 
     * @param int $product_id PrestaShop product ID
     * @return array Statistics
     */
    public function getProductSyncStatistics($product_id)
    {
        $product_id = (int) $product_id;
        $empty_stats = [
            'total_syncs' => 0,
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'last_sync' => null,
            'average_duration' => 0.0,
        ];

        if (!$this->isProductSyncHistoryTablePresent()) {
            return $empty_stats;
        }

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
            $this->logger->log('SQL Error in getProductSyncStatistics: ' . $e->getMessage() . ' | SQL: ' . $last_sql, 'error');

            return $empty_stats;
        }

        return $stats;
    }

    /**
     * Payload JSON para cola update / API (misma estructura que create).
     *
     * @param int $product_id
     *
     * @return array|null
     */
    public function buildProductPayloadForYujuQueue($product_id)
    {
        $product_id = (int) $product_id;
        $product = new Product($product_id, false, (int) Context::getContext()->language->id);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        return $this->prepareProductDataForYuju($product);
    }

    /**
     * Elimina el producto en Yuju (si hay ID) y el registro local yuju_product_status.
     *
     * @param int $prestashop_product_id
     *
     * @return array{success: bool, skipped: bool, message: string}
     */
    public function removeProductFromYujuAndLocalStatus($prestashop_product_id)
    {
        $prestashop_product_id = (int) $prestashop_product_id;
        $row = Db::getInstance()->getRow(
            '
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . $prestashop_product_id
        );

        if (!$row) {
            return ['success' => true, 'skipped' => true, 'message' => 'Sin registro local'];
        }

        $yuju_product_id = isset($row['yuju_product_id']) ? trim((string) $row['yuju_product_id']) : '';
        $has_yuju = $yuju_product_id !== ''
            && strtolower($yuju_product_id) !== 'null'
            && $yuju_product_id !== '0';

        if (!$has_yuju) {
            Db::getInstance()->delete('yuju_product_status', 'prestashop_product_id = ' . $prestashop_product_id);

            return ['success' => true, 'skipped' => false, 'message' => 'Registro local eliminado (sin ID Yuju)'];
        }

        if (Module::isInstalled('prestashopyuju')) {
            $m = Module::getInstanceByName('prestashopyuju');
            if ($m && method_exists($m, 'ensureProductStatusIntermediateWebhookStates')) {
                $m->ensureProductStatusIntermediateWebhookStates();
            }
        }

        $pending_meta = json_encode([
            'delete_pending' => true,
            'requested_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        $this->updateProductStatus(
            $prestashop_product_id,
            'deleting_in_yuju',
            'Eliminando en Yuju… Pendiente de confirmación vía webhook (product-deleted).',
            $yuju_product_id,
            $pending_meta
        );

        $result = $this->api_client->deleteProduct($yuju_product_id);
        $deleted_from_yuju = !empty($result['success']);

        if (!$deleted_from_yuju) {
            $this->updateProductStatus(
                $prestashop_product_id,
                'synced_with_errors',
                $result['message'] ?? 'Error al eliminar en Yuju',
                $yuju_product_id,
                null
            );

            return [
                'success' => false,
                'skipped' => false,
                'message' => $result['message'] ?? 'Error API eliminar',
            ];
        }

        return [
            'success' => true,
            'skipped' => false,
            'message' => 'Eliminación solicitada en Yuju. Estado: eliminando… Confirmación cuando llegue el webhook product-deleted.',
            'awaiting_webhook' => true,
        ];
    }
}
