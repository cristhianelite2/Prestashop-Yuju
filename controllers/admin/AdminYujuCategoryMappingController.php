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

class AdminYujuCategoryMappingController extends ModuleAdminController
{
    protected $logger;

    public function __construct()
    {
        $this->table = 'yuju_category_mapping';
        $this->className = 'YujuCategoryMapping';
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
        'prestashop_category_name' => [
        'title' => $this->l('PrestaShop Category'),
        'width' => 200,
        ],
        'yuju_category_id' => [
        'title' => $this->l('Yuju Category ID'),
        'align' => 'center',
        'width' => 150,
        ],
        'yuju_category_name' => [
        'title' => $this->l('Yuju Category Name'),
        'width' => 200,
        ],
        'sync_enabled' => [
        'title' => $this->l('Sync Enabled'),
        'align' => 'center',
        'active' => 'status',
        'type' => 'bool',
        'class' => 'fixed-width-sm',
        ],
        'created_at' => [
        'title' => $this->l('Created'),
        'type' => 'datetime',
        'width' => 150,
        ],
        ];

        $this->actions = ['edit', 'delete'];
        $this->bulk_actions = [
        'delete' => [
        'text' => $this->l('Delete selected'),
        'confirm' => $this->l('Delete selected items?'),
        ],
        'enableSync' => [
        'text' => $this->l('Enable sync'),
        ],
        'disableSync' => [
        'text' => $this->l('Disable sync'),
        ],
        ];

        $this->toolbar_btn['new'] = [
        'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
        'desc' => $this->l('Add new mapping'),
        ];

        $this->toolbar_btn['sync_categories'] = [
        'href' => self::$currentIndex . '&syncYujuCategories&token=' . $this->token,
        'desc' => $this->l('Sync Yuju Categories'),
        'class' => 'process-icon-refresh',
        ];
    }

    public function renderList()
    {
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        // Add custom SQL to get category names
        $this->_select = 'cl.name as prestashop_category_name';
        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (a.prestashop_category_id = cl.id_category AND cl.id_lang = ' . (int) $this->context->language->id . ')';

        return parent::renderList();
    }

    public function renderForm()
    {
        // Get PrestaShop categories
        $categories = Category::getCategories($this->context->language->id, true, false);
        $category_options = [];

        foreach ($categories as $category) {
            if ($category['id_category'] != 1) { // Exclude root category
                $category_options[] = [
                'id' => $category['id_category'],
                'name' => str_repeat('- ', $category['level_depth'] - 1) . $category['name'],
                ];
            }
        }

        // Get Yuju categories
        $yuju_categories = $this->getYujuCategories();

        $this->fields_form = [
        'legend' => [
        'title' => $this->l('Category Mapping'),
        'icon' => 'icon-tags',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->l('PrestaShop Category'),
        'name' => 'prestashop_category_id',
        'required' => true,
        'options' => [
        'query' => $category_options,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'select',
        'label' => $this->l('Yuju Category'),
        'name' => 'yuju_category_id',
        'required' => true,
        'options' => [
        'query' => $yuju_categories,
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'switch',
        'label' => $this->l('Enable Synchronization'),
        'name' => 'sync_enabled',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'sync_enabled_on',
        'value' => 1,
        'label' => $this->l('Enabled'),
        ],
        [
        'id' => 'sync_enabled_off',
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
        if (Tools::isSubmit('syncYujuCategories')) {
            $this->syncYujuCategories();
        } elseif (Tools::isSubmit('submitBulkenableSync')) {
            $this->processBulkEnableSync();
        } elseif (Tools::isSubmit('submitBulkdisableSync')) {
            $this->processBulkDisableSync();
        }

        return parent::postProcess();
    }

    public function processSave()
    {
        $prestashop_category_id = (int) Tools::getValue('prestashop_category_id');
        $yuju_category_id = Tools::getValue('yuju_category_id');

        // Check if mapping already exists
        $existing = Db::getInstance()->getRow(
            '
    SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
    WHERE prestashop_category_id = ' . (int) $prestashop_category_id . '
    AND id_mapping != ' . (int) Tools::getValue('id_mapping')
        );

        if ($existing) {
            $this->errors[] = $this->l('This PrestaShop category is already mapped.');

            return false;
        }

        // Get Yuju category name
        $yuju_category_name = $this->getYujuCategoryName($yuju_category_id);

        if (!$yuju_category_name) {
            $this->errors[] = $this->l('Invalid Yuju category selected.');

            return false;
        }

        $data = [
        'prestashop_category_id' => $prestashop_category_id,
        'yuju_category_id' => $yuju_category_id,
        'yuju_category_name' => pSQL($yuju_category_name),
        'sync_enabled' => (int) Tools::getValue('sync_enabled'),
        'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (Tools::getValue('id_mapping')) {
            // Update
            $result = Db::getInstance()->update(
                'yuju_category_mapping',
                $data,
                'id_mapping = ' . (int) Tools::getValue('id_mapping')
            );
        } else {
            // Insert
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert('yuju_category_mapping', $data);
        }

        if ($result) {
            $this->confirmations[] = $this->l('Category mapping saved successfully.');
            $this->logger->log('Category mapping saved: PS Category ' . $prestashop_category_id . ' -> Yuju Category ' . $yuju_category_id, 'info');
        } else {
            $this->errors[] = $this->l('Error saving category mapping.');

            return false;
        }
    }

    protected function syncYujuCategories()
    {
        try {
            $api_client = new YujuApiClient();
            $categories = $api_client->getCategories();

            if ($categories && isset($categories['data'])) {
                // Store categories in cache table for faster access
                Db::getInstance()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'yuju_categories_cache');

                foreach ($categories['data'] as $category) {
                    Db::getInstance()->insert('yuju_categories_cache', [
                    'yuju_category_id' => pSQL($category['id']),
                    'name' => pSQL($category['name']),
                    'parent_id' => pSQL($category['parent_id'] ?? ''),
                    'level' => (int) ($category['level'] ?? 0),
                    'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $this->confirmations[] = $this->l('Yuju categories synchronized successfully.');
                $this->logger->log('Yuju categories synchronized: ' . count($categories['data']) . ' categories', 'info');
            } else {
                $this->errors[] = $this->l('No categories found in Yuju.');
            }
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error synchronizing Yuju categories: ') . $e->getMessage();
            $this->logger->log('Error synchronizing Yuju categories: ' . $e->getMessage(), 'error');
        }
    }

    protected function getYujuCategories()
    {
        $categories = [];

        $result = Db::getInstance()->executeS('
    SELECT yuju_category_id as id, name
    FROM ' . _DB_PREFIX_ . 'yuju_categories_cache
    ORDER BY level, name
    ');

        if ($result) {
            foreach ($result as $category) {
                $categories[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                ];
            }
        }

        return $categories;
    }

    protected function getYujuCategoryName($category_id)
    {
        $result = Db::getInstance()->getRow('
    SELECT name FROM ' . _DB_PREFIX_ . 'yuju_categories_cache
    WHERE yuju_category_id = "' . pSQL($category_id) . '"
    ');

        return $result ? $result['name'] : false;
    }

    protected function processBulkEnableSync()
    {
        $ids = Tools::getValue($this->table . 'Box');

        if (is_array($ids) && count($ids)) {
            $result = Db::getInstance()->update(
                'yuju_category_mapping',
                ['sync_enabled' => 1],
                'id_mapping IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->l('Sync enabled for selected mappings.');
            }
        }
    }

    protected function processBulkDisableSync()
    {
        $ids = Tools::getValue($this->table . 'Box');

        if (is_array($ids) && count($ids)) {
            $result = Db::getInstance()->update(
                'yuju_category_mapping',
                ['sync_enabled' => 0],
                'id_mapping IN (' . implode(',', array_map('intval', $ids)) . ')'
            );

            if ($result) {
                $this->confirmations[] = $this->l('Sync disabled for selected mappings.');
            }
        }
    }
}
