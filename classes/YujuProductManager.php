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

            // Get value from PrestaShop product
            if (in_array($ps_field, ['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords', 'link_rewrite', 'available_now', 'available_later'])) {
                $value = isset($product->{$ps_field}[$default_lang]) ? $product->{$ps_field}[$default_lang] : '';
            } else {
                $value = isset($product->{$ps_field}) ? $product->{$ps_field} : '';
            }

            // Use default value if empty and required
            if (empty($value) && $mapping['is_required'] && !empty($mapping['default_value'])) {
                $value = $mapping['default_value'];
            }

            // Apply transformation
            $value = $this->applyTransformation($value, $mapping);

            $data[$yuju_field] = $value;
        }

        // Add category mapping
        if ($product->id_category_default) {
            $category_mapping = $this->getCategoryMappingByPrestashopId($product->id_category_default);

            if ($category_mapping) {
                $data['category_id'] = $category_mapping['yuju_category_id'];
            }
        }

        // Add manufacturer
        if ($product->id_manufacturer) {
            $manufacturer = new Manufacturer($product->id_manufacturer);

            if (Validate::isLoadedObject($manufacturer)) {
                $data['brand'] = $manufacturer->name;
            }
        }

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
            $data['error_message'] = pSQL($error_message);
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
            $data['error_message'] = pSQL($error_message);
        } else {
            $data['error_message'] = null;
        }

        if ($yuju_product_id) {
            $data['yuju_product_id'] = pSQL($yuju_product_id);
        }

        $existing = Db::getInstance()->getRow('
            SELECT id_status FROM ' . _DB_PREFIX_ . 'yuju_product_status
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
}
