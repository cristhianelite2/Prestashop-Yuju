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

class YujuCategoryManager
{
    private $api_client;
    private $logger;
    private $context;

    public function __construct($context = null)
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->context = $context ?: Context::getContext();
    }

    /**
     * Synchronize categories from Yuju to PrestaShop.
     */
    public function syncCategoriesFromYuju($force_update = false)
    {
        try {
            $this->logger->log('Starting category synchronization from Yuju', 'info');

            $yuju_categories = $this->api_client->getCategories();

            if (!$yuju_categories || !isset($yuju_categories['data'])) {
                throw new Exception('No categories received from Yuju API');
            }

            $synced_count = 0;
            $errors = [];

            foreach ($yuju_categories['data'] as $yuju_category) {
                try {
                    if ($this->syncSingleCategoryFromYuju($yuju_category, $force_update)) {
                        ++$synced_count;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Category ' . $yuju_category['id'] . ': ' . $e->getMessage();
                    $this->logger->log('Error syncing category ' . $yuju_category['id'] . ': ' . $e->getMessage(), 'error');
                }
            }

            $this->logger->log('Category synchronization completed. Synced: ' . $synced_count . ', Errors: ' . count($errors), 'info');

            return [
                'success' => true,
                'synced_count' => $synced_count,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->log('Category synchronization failed: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synchronize categories from PrestaShop to Yuju.
     */
    public function syncCategoriesToYuju($category_ids = null)
    {
        try {
            $this->logger->log('Starting category synchronization to Yuju', 'info');

            // Get mapped categories
            $mapped_categories = $this->getMappedCategories($category_ids);

            if (empty($mapped_categories)) {
                throw new Exception('No mapped categories found for synchronization');
            }

            $synced_count = 0;
            $errors = [];

            foreach ($mapped_categories as $mapping) {
                try {
                    if ($this->syncSingleCategoryToYuju($mapping)) {
                        ++$synced_count;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Category ' . $mapping['prestashop_category_id'] . ': ' . $e->getMessage();
                    $this->logger->log('Error syncing category ' . $mapping['prestashop_category_id'] . ': ' . $e->getMessage(), 'error');
                }
            }

            $this->logger->log('Category synchronization to Yuju completed. Synced: ' . $synced_count . ', Errors: ' . count($errors), 'info');

            return [
                'success' => true,
                'synced_count' => $synced_count,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->log('Category synchronization to Yuju failed: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync single category from Yuju to PrestaShop.
     */
    protected function syncSingleCategoryFromYuju($yuju_category, $force_update = false)
    {
        // Check if category is mapped
        $mapping = $this->getCategoryMappingByYujuId($yuju_category['id']);

        if (!$mapping || !$mapping['sync_enabled']) {
            return false;
        }

        $prestashop_category = new Category($mapping['prestashop_category_id']);

        if (!Validate::isLoadedObject($prestashop_category)) {
            throw new Exception('PrestaShop category not found: ' . $mapping['prestashop_category_id']);
        }

        // Check if update is needed
        if (!$force_update && !$this->isCategoryUpdateNeeded($prestashop_category, $yuju_category)) {
            return false;
        }

        // Update category data
        $this->updateCategoryFromYujuData($prestashop_category, $yuju_category);

        if ($prestashop_category->save()) {
            $this->updateCategoryMapping($mapping['id_mapping'], [
                'last_sync_at' => date('Y-m-d H:i:s'),
                'sync_status' => 'success',
            ]);

            $this->logger->log('Category synced from Yuju: ' . $yuju_category['id'] . ' -> ' . $prestashop_category->id, 'info');

            return true;
        } else {
            throw new Exception('Failed to save PrestaShop category');
        }
    }

    /**
     * Sync single category from PrestaShop to Yuju.
     */
    protected function syncSingleCategoryToYuju($mapping)
    {
        $prestashop_category = new Category($mapping['prestashop_category_id']);

        if (!Validate::isLoadedObject($prestashop_category)) {
            throw new Exception('PrestaShop category not found: ' . $mapping['prestashop_category_id']);
        }

        // Prepare category data for Yuju
        $yuju_data = $this->prepareCategoryDataForYuju($prestashop_category);

        // Check if category exists in Yuju
        $yuju_category = $this->api_client->getCategory($mapping['yuju_category_id']);

        if ($yuju_category) {
            // Update existing category
            $result = $this->api_client->updateCategory($mapping['yuju_category_id'], $yuju_data);
        } else {
            // Create new category
            $result = $this->api_client->createCategory($yuju_data);

            if ($result && isset($result['id'])) {
                // Update mapping with new Yuju category ID
                $this->updateCategoryMapping($mapping['id_mapping'], [
                    'yuju_category_id' => $result['id'],
                ]);
            }
        }

        if ($result) {
            $this->updateCategoryMapping($mapping['id_mapping'], [
                'last_sync_at' => date('Y-m-d H:i:s'),
                'sync_status' => 'success',
            ]);

            $this->logger->log('Category synced to Yuju: ' . $prestashop_category->id . ' -> ' . $mapping['yuju_category_id'], 'info');

            return true;
        } else {
            throw new Exception('Failed to sync category to Yuju');
        }
    }

    /**
     * Get category mapping by Yuju category ID.
     */
    protected function getCategoryMappingByYujuId($yuju_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE yuju_category_id = \'' . pSQL($yuju_category_id) . '\'
        ');
    }

    /**
     * Get mapped categories for synchronization.
     */
    protected function getMappedCategories($category_ids = null)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE sync_enabled = 1';

        if ($category_ids && is_array($category_ids)) {
            $sql .= ' AND prestashop_category_id IN (' . implode(',', array_map('intval', $category_ids)) . ')';
        }

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Check if category update is needed.
     */
    protected function isCategoryUpdateNeeded($prestashop_category, $yuju_category)
    {
        // Compare modification dates or other criteria
        // This is a simplified check - you might want to implement more sophisticated logic

        if (isset($yuju_category['updated_at'])) {
            $yuju_updated = strtotime($yuju_category['updated_at']);
            $ps_updated = strtotime($prestashop_category->date_upd);

            return $yuju_updated > $ps_updated;
        }

        return true; // Default to update if we can't determine
    }

    /**
     * Update PrestaShop category with Yuju data.
     */
    protected function updateCategoryFromYujuData($prestashop_category, $yuju_category)
    {
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            if (isset($yuju_category['name'])) {
                $prestashop_category->name[$language['id_lang']] = $yuju_category['name'];
            }

            if (isset($yuju_category['description'])) {
                $prestashop_category->description[$language['id_lang']] = $yuju_category['description'];
            }

            if (isset($yuju_category['meta_title'])) {
                $prestashop_category->meta_title[$language['id_lang']] = $yuju_category['meta_title'];
            }

            if (isset($yuju_category['meta_description'])) {
                $prestashop_category->meta_description[$language['id_lang']] = $yuju_category['meta_description'];
            }

            if (isset($yuju_category['slug'])) {
                $prestashop_category->link_rewrite[$language['id_lang']] = Tools::str2url($yuju_category['slug']);
            }
        }

        if (isset($yuju_category['active'])) {
            $prestashop_category->active = (bool) $yuju_category['active'];
        }

        if (isset($yuju_category['position'])) {
            $prestashop_category->position = (int) $yuju_category['position'];
        }
    }

    /**
     * Prepare PrestaShop category data for Yuju.
     */
    protected function prepareCategoryDataForYuju($prestashop_category)
    {
        $default_lang = Configuration::get('PS_LANG_DEFAULT');

        $data = [
            'name' => $prestashop_category->name[$default_lang],
            'description' => $prestashop_category->description[$default_lang],
            'active' => (bool) $prestashop_category->active,
            'position' => (int) $prestashop_category->position,
        ];

        if (!empty($prestashop_category->meta_title[$default_lang])) {
            $data['meta_title'] = $prestashop_category->meta_title[$default_lang];
        }

        if (!empty($prestashop_category->meta_description[$default_lang])) {
            $data['meta_description'] = $prestashop_category->meta_description[$default_lang];
        }

        if (!empty($prestashop_category->link_rewrite[$default_lang])) {
            $data['slug'] = $prestashop_category->link_rewrite[$default_lang];
        }

        // Add parent category mapping if exists
        if ($prestashop_category->id_parent > 1) {
            $parent_mapping = $this->getCategoryMappingByPrestashopId($prestashop_category->id_parent);

            if ($parent_mapping) {
                $data['parent_id'] = $parent_mapping['yuju_category_id'];
            }
        }

        return $data;
    }

    /**
     * Get category mapping by PrestaShop category ID.
     */
    protected function getCategoryMappingByPrestashopId($prestashop_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE prestashop_category_id = ' . (int) $prestashop_category_id . '
        ');
    }

    /**
     * Update category mapping.
     */
    protected function updateCategoryMapping($mapping_id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return Db::getInstance()->update(
            'yuju_category_mapping',
            $data,
            'id_mapping = ' . (int) $mapping_id
        );
    }

    /**
     * Create category mapping.
     */
    public function createCategoryMapping($prestashop_category_id, $yuju_category_id, $sync_enabled = true)
    {
        // Check if mapping already exists
        $existing = Db::getInstance()->getRow('
            SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE prestashop_category_id = ' . (int) $prestashop_category_id . '
        ');

        if ($existing) {
            return false; // Mapping already exists
        }

        // Get Yuju category name
        $yuju_category = $this->api_client->getCategory($yuju_category_id);
        $yuju_category_name = $yuju_category ? $yuju_category['name'] : '';

        $data = [
            'prestashop_category_id' => (int) $prestashop_category_id,
            'yuju_category_id' => pSQL($yuju_category_id),
            'yuju_category_name' => pSQL($yuju_category_name),
            'sync_enabled' => (int) $sync_enabled,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return Db::getInstance()->insert('yuju_category_mapping', $data);
    }

    /**
     * Delete category mapping.
     */
    public function deleteCategoryMapping($mapping_id)
    {
        return Db::getInstance()->delete(
            'yuju_category_mapping',
            'id_mapping = ' . (int) $mapping_id
        );
    }

    /**
     * Get all category mappings.
     */
    public function getAllCategoryMappings($active_only = false)
    {
        $sql = 'SELECT cm.*, cl.name as prestashop_category_name
                FROM ' . _DB_PREFIX_ . 'yuju_category_mapping cm
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (cm.prestashop_category_id = cl.id_category AND cl.id_lang = ' . (int) $this->context->language->id . ')';

        if ($active_only) {
            $sql .= ' WHERE cm.sync_enabled = 1';
        }

        $sql .= ' ORDER BY cl.name';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get category mapping statistics.
     */
    public function getCategoryMappingStats()
    {
        $stats = [];

        $stats['total_mappings'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
        ');

        $stats['active_mappings'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE sync_enabled = 1
        ');

        $stats['last_sync'] = Db::getInstance()->getValue('
            SELECT MAX(last_sync_at) FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
        ');

        $stats['sync_errors'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE sync_status = \'error\'
        ');

        return $stats;
    }
}
