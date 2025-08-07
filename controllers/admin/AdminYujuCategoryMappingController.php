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
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuCategoryMapping.php';

class AdminYujuCategoryMappingController extends ModuleAdminController
{
    protected $logger;

    public function __construct()
    {
        $this->table = 'yuju_category_mapping';
        $this->className = 'YujuCategoryMapping';
        $this->identifier = 'id';
        $this->bootstrap = true;
        $this->lang = false;

        parent::__construct();

        $this->logger = new YujuLogger();

        $this->fields_list = [
        'id' => [
        'title' => $this->trans('ID', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'prestashop_category_name' => [
        'title' => $this->trans('PrestaShop Category', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 200,
        ],
        'yuju_category_id' => [
        'title' => $this->trans('Yuju Category ID', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'width' => 150,
        ],
        'yuju_category_name' => [
        'title' => $this->trans('Yuju Category Name', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 200,
        ],
        'sync_enabled' => [
        'title' => $this->trans('Sync Enabled', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'active' => 'status',
        'type' => 'bool',
        'class' => 'fixed-width-sm',
        ],
        'created_at' => [
        'title' => $this->trans('Created', array(), 'Modules.Prestashopyuju.Admin'),
        'type' => 'datetime',
        'width' => 150,
        ],
        ];

        $this->actions = ['edit', 'delete'];
        $this->bulk_actions = [
        'delete' => [
        'text' => $this->trans('Delete selected', array(), 'Modules.Prestashopyuju.Admin'),
        'confirm' => $this->trans('Delete selected items?', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        'enableSync' => [
        'text' => $this->trans('Enable sync', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'icon-check'
        ],
        'disableSync' => [
        'text' => $this->trans('Disable sync', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'icon-remove'
        ],
        ];

        $this->toolbar_btn['new'] = [
        'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
        'desc' => $this->trans('Add new mapping', array(), 'Modules.Prestashopyuju.Admin'),
        ];

        $this->toolbar_btn['sync_categories'] = [
        'href' => self::$currentIndex . '&syncYujuCategories&token=' . $this->token,
        'desc' => $this->trans('Sync Yuju Categories', array(), 'Modules.Prestashopyuju.Admin'),
        'class' => 'process-icon-refresh',
        ];
    }

    public function initContent()
    {
        // Get existing mappings with category names
        $mappings = $this->getCategoryMappings();
        $prestashop_categories = $this->getPrestashopCategories();
        $yuju_categories = $this->getYujuCategories();
        
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuCategoryMapping',
            'category_mappings' => $mappings,
            'prestashop_categories' => $prestashop_categories,
            'yuju_categories' => $yuju_categories,
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuCategoryMapping'),
            'token' => $this->token
        ]);
        
        parent::initContent();
        $this->setTemplate('category_mapping.tpl');
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
        'title' => $this->trans('Mapeo de Categorías', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'icon-tags',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->trans('Categoría PrestaShop', array(), 'Modules.Prestashopyuju.Admin'),
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
        'label' => $this->trans('Categoría Yuju', array(), 'Modules.Prestashopyuju.Admin'),
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
        'label' => $this->trans('Habilitar Sincronización', array(), 'Modules.Prestashopyuju.Admin'),
        'name' => 'sync_enabled',
        'is_bool' => true,
        'values' => [
        [
        'id' => 'sync_enabled_on',
        'value' => 1,
        'label' => $this->trans('Habilitado', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        [
        'id' => 'sync_enabled_off',
        'value' => 0,
        'label' => $this->trans('Deshabilitado', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        ],
        ],
        ],
        'submit' => [
        'title' => $this->trans('Save', array(), 'Modules.Prestashopyuju.Admin'),
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
            $this->errors[] = $this->trans('This PrestaShop category is already mapped.', array(), 'Modules.Prestashopyuju.Admin');

            return false;
        }

        // Get Yuju category name
        $yuju_category_name = $this->getYujuCategoryName($yuju_category_id);

        if (!$yuju_category_name) {
            $this->errors[] = $this->trans('Invalid Yuju category selected.', array(), 'Modules.Prestashopyuju.Admin');

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
            $this->confirmations[] = $this->trans('Category mapping saved successfully.', array(), 'Modules.Prestashopyuju.Admin');
            $this->logger->log('Category mapping saved: PS Category ' . $prestashop_category_id . ' -> Yuju Category ' . $yuju_category_id, 'info');
        } else {
            $this->errors[] = $this->trans('Error saving category mapping.', array(), 'Modules.Prestashopyuju.Admin');

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
                    'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $this->confirmations[] = $this->trans('Yuju categories synchronized successfully.', array(), 'Modules.Prestashopyuju.Admin');
                $this->logger->log('Yuju categories synchronized: ' . count($categories['data']) . ' categories', 'info');
            } else {
                $this->errors[] = $this->trans('No categories found in Yuju.', array(), 'Modules.Prestashopyuju.Admin');
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error synchronizing Yuju categories: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
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
                $this->confirmations[] = $this->trans('Sync enabled for selected mappings.', array(), 'Modules.Prestashopyuju.Admin');
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
                $this->confirmations[] = $this->trans('Sync disabled for selected mappings.', array(), 'Modules.Prestashopyuju.Admin');
            }
        }
    }

    // AJAX Methods
    public function ajaxProcessSaveMapping()
    {
        $response = ['success' => false, 'message' => ''];
        
        try {
            $id = (int) Tools::getValue('id');
            $prestashop_category_id = (int) Tools::getValue('prestashop_category_id');
            $yuju_category_id = Tools::getValue('yuju_category_id');
            $sync_enabled = (int) Tools::getValue('sync_enabled');
            
            // Validate required fields
            if (!$prestashop_category_id || !$yuju_category_id) {
                throw new Exception('PrestaShop category and Yuju category are required.');
            }
            
            // Check if mapping already exists (for different mapping)
            $existing = Db::getInstance()->getRow(
                'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping 
                WHERE prestashop_category_id = ' . (int) $prestashop_category_id . '
                AND id != ' . (int) $id
            );
            
            if ($existing) {
                throw new Exception('This PrestaShop category is already mapped.');
            }
            
            // Get Yuju category name
            $yuju_category_name = $this->getYujuCategoryName($yuju_category_id);
            if (!$yuju_category_name) {
                throw new Exception('Invalid Yuju category selected.');
            }
            
            $data = [
                'prestashop_category_id' => $prestashop_category_id,
                'yuju_category_id' => pSQL($yuju_category_id),
                'yuju_category_name' => pSQL($yuju_category_name),
                'sync_enabled' => $sync_enabled,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($id > 0) {
                // Update existing mapping
                $result = Db::getInstance()->update(
                    'yuju_category_mapping',
                    $data,
                    'id = ' . (int) $id
                );
            } else {
                // Create new mapping
                $data['created_at'] = date('Y-m-d H:i:s');
                $result = Db::getInstance()->insert('yuju_category_mapping', $data);
            }
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Category mapping saved successfully.';
                $this->logger->log('Category mapping saved: PS Category ' . $prestashop_category_id . ' -> Yuju Category ' . $yuju_category_id, 'info');
            } else {
                throw new Exception('Error saving category mapping to database.');
            }
            
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error saving category mapping: ' . $e->getMessage(), 'error');
        }
        
        die(json_encode($response));
    }
    
    public function ajaxProcessGetMapping()
    {
        $response = ['success' => false, 'data' => null];
        
        try {
            $id = (int) Tools::getValue('id');
            
            $mapping = Db::getInstance()->getRow(
                'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE id = ' . (int) $id
            );
            
            if ($mapping) {
                $response['success'] = true;
                $response['data'] = $mapping;
            } else {
                throw new Exception('Mapping not found.');
            }
            
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
        die(json_encode($response));
    }
    
    public function ajaxProcessDeleteMapping()
    {
        $response = ['success' => false, 'message' => ''];
        
        try {
            $id = (int) Tools::getValue('id');
            
            $result = Db::getInstance()->delete(
                'yuju_category_mapping',
                'id = ' . (int) $id
            );
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Category mapping deleted successfully.';
                $this->logger->log('Category mapping deleted: ID ' . $id, 'info');
            } else {
                throw new Exception('Error deleting category mapping.');
            }
            
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error deleting category mapping: ' . $e->getMessage(), 'error');
        }
        
        die(json_encode($response));
    }
    
    public function ajaxProcessSyncMapping()
    {
        $response = ['success' => false, 'message' => ''];
        
        try {
            $id = (int) Tools::getValue('id');
            
            // Get mapping details
            $mapping = Db::getInstance()->getRow(
                'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE id = ' . (int) $id
            );
            
            if (!$mapping) {
                throw new Exception('Mapping not found.');
            }
            
            // Update last sync date
            Db::getInstance()->update(
                'yuju_category_mapping',
                ['last_sync_at' => date('Y-m-d H:i:s')],
                'id = ' . (int) $id
            );
            
            $response['success'] = true;
            $response['message'] = 'Category synchronized successfully.';
            $this->logger->log('Category mapping synced: ID ' . $id, 'info');
            
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $this->logger->log('Error syncing category mapping: ' . $e->getMessage(), 'error');
        }
        
        die(json_encode($response));
    }
    
    public function ajaxProcessRefreshYujuCategories()
    {
        $response = ['success' => false, 'message' => '', 'categories' => []];
        
        try {
            $this->syncYujuCategories();
            $categories = $this->getYujuCategories();
            
            $response['success'] = true;
            $response['message'] = 'Yuju categories refreshed successfully.';
            $response['categories'] = $categories;
            
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
        die(json_encode($response));
    }
    
    protected function getCategoryMappings()
    {
        $sql = '
            SELECT 
                cm.id,
                cm.prestashop_category_id,
                cm.yuju_category_id,
                cm.yuju_category_name,
                cm.sync_enabled,
                cm.last_sync_at,
                cm.created_at,
                cl.name as prestashop_category_name
            FROM ' . _DB_PREFIX_ . 'yuju_category_mapping cm
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (cm.prestashop_category_id = cl.id_category AND cl.id_lang = ' . (int) $this->context->language->id . ')
            ORDER BY cm.created_at DESC
        ';
        
        return Db::getInstance()->executeS($sql) ?: [];
    }
    
    protected function getPrestashopCategories()
    {
        $categories = Category::getCategories($this->context->language->id, true, false);
        $category_options = [];
        
        foreach ($categories as $category) {
            if ($category['id_category'] != 1) { // Exclude root category
                $category_options[] = [
                    'id' => $category['id_category'],
                    'name' => str_repeat('- ', $category['level_depth'] - 1) . $category['name']
                ];
            }
        }
        
        return $category_options;
    }
}
