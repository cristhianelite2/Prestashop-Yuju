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

// Include required files
require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/classes/YujuLogger.php';
require_once dirname(__FILE__) . '/classes/YujuApiClient.php';
require_once dirname(__FILE__) . '/classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
require_once dirname(__FILE__) . '/classes/YujuWebhookManager.php';

class Prestashopyuju extends Module
{
    protected $config_form = false;

    protected $logger;

    protected $api_client;

    protected $oauth;

    protected $sync_manager;

    protected $webhook_manager;

    public function __construct()
    {
        $this->name = 'prestashopyuju';
        $this->tab = 'market_place';
        $this->version = '1.0.0';
        $this->author = 'Yuju Integration Team';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Yuju Integration', array(), 'Modules.Prestashopyuju.Admin');
        $this->description = $this->trans('Synchronize your PrestaShop store with the Yuju platform for perfect inventory, product and order management.', array(), 'Modules.Prestashopyuju.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall the Yuju Integration module? This will remove all synchronization data.', array(), 'Modules.Prestashopyuju.Admin');

        // Initialize components
        // Temporarily commented for installation
        // $this->logger = new YujuLogger();
        // $this->api_client = new YujuApiClient();
        // $this->oauth = new YujuOAuth();
        // $this->sync_manager = new YujuSyncManager();
        // $this->webhook_manager = new YujuWebhookManager();
    }

    /**
     * Module installation.
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install()
        && $this->installDb()
        && $this->installTabs()
        && $this->registerHooks()
        && $this->installConfiguration()
        && $this->createDirectories();
    }

    /**
     * Module uninstallation.
     */
    public function uninstall()
    {
        return $this->uninstallConfiguration()
        && $this->uninstallTabs()
        && $this->uninstallDb()
        && parent::uninstall();
    }

    /**
     * Install database tables.
     */
    protected function installDb()
    {
        $sql_file = dirname(__FILE__) . '/sql/install.sql';

        if (!file_exists($sql_file)) {
            // Use PrestaShop's error logging instead of $this->logger during installation
            PrestaShopLogger::addLog('Install SQL file not found: ' . $sql_file, 3);

            return false;
        }

        $sql = file_get_contents($sql_file);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);

        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);

            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    // Use PrestaShop's error logging instead of $this->logger during installation
                    PrestaShopLogger::addLog('Failed to execute query: ' . $query, 3);

                    return false;
                }
            }
        }

        return true;
    }



    /**
     * Uninstall database tables.
     */
    protected function uninstallDb()
    {
        $sql_file = dirname(__FILE__) . '/sql/uninstall.sql';

        if (!file_exists($sql_file)) {
            return true; // If no uninstall file, assume success
        }

        $sql = file_get_contents($sql_file);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);

            if (!empty($query)) {
                Db::getInstance()->execute($query);
            }
        }

        return true;
    }

    /**
     * Install admin tabs.
     */
    protected function installTabs()
    {
        $tabs = [
        [
        'class_name' => 'AdminYuju',
        'name' => $this->trans('Yuju Integration', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'CONFIGURE',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuConfiguration',
        'name' => $this->trans('Configuration', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuSync',
        'name' => $this->trans('Synchronization', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuProductMapping',
        'name' => $this->trans('Product Mapping', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAttributeMapping',
        'name' => $this->trans('Attribute Mapping', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuProductStatus',
        'name' => $this->trans('Product Status', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuWebhook',
        'name' => $this->trans('Webhooks', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuLogs',
        'name' => $this->trans('Logs', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        ];

        foreach ($tabs as $tab_data) {
            $tab = new Tab();
            $tab->class_name = $tab_data['class_name'];
            $tab->module = $tab_data['module'];
            $tab->active = (bool) $tab_data['active'];

            // Use newer method to get parent tab ID
            $parent_tab = Tab::getInstanceFromClassName($tab_data['parent_class_name']);
            $tab->id_parent = (Validate::isLoadedObject($parent_tab) && isset($parent_tab->id)) ? (int) $parent_tab->id : 0;

            foreach (Language::getLanguages(false) as $language) {
                $tab->name[$language['id_lang']] = is_string($tab_data['name']) ? $tab_data['name'] : $tab_data['name'];
            }

            if (!$tab->save()) {
                $this->logger->error('Failed to install tab: ' . $tab_data['class_name']);

                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall admin tabs.
     */
    protected function uninstallTabs()
    {
        $tab_classes = [
        'AdminYujuLogs',
        'AdminYujuWebhook',
        'AdminYujuProductStatus',
        'AdminYujuAttributeMapping',
        'AdminYujuProductMapping',
        'AdminYujuSync',
        'AdminYujuConfiguration',
        'AdminYuju',
        ];

        foreach ($tab_classes as $class_name) {
            // Use newer method to get tab ID
            $tab = Tab::getInstanceFromClassName($class_name);

            if (Validate::isLoadedObject($tab) && $tab->id > 0) {
                if (!$tab->delete()) {
                    $this->logger->error('Failed to uninstall tab: ' . $class_name);
                }
            }
        }

        return true;
    }

    /**
     * Register module hooks.
     */
    protected function registerHooks()
    {
        $hooks = [
        'actionProductAdd',
        'actionProductUpdate',
        'actionProductDelete',
        'actionUpdateQuantity',
        'actionProductAttributeUpdate',
        'actionCategoryAdd',
        'actionCategoryUpdate',
        'actionCategoryDelete',
        'actionOrderStatusUpdate',
        'actionValidateOrder',
        'actionOrderReturn',
        'actionProductAttributeDelete',
        'actionAttributeGroupDelete',
        'actionAttributeDelete',
        'actionCarrierUpdate',
        'actionCustomerAccountAdd',
        'actionCustomerAccountUpdate',
        'actionObjectManufacturerAddAfter',
        'actionObjectManufacturerUpdateAfter',
        'actionObjectManufacturerDeleteAfter',
        'displayBackOfficeHeader',
        'displayAdminProductsExtra',
        'actionAdminControllerSetMedia',
        ];

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                $this->logger->error('Failed to register hook: ' . $hook);

                return false;
            }
        }

        return true;
    }

    /**
     * Install default configuration.
     */
    protected function installConfiguration()
    {
        $defaults = YujuConfig::getDefaults();

        foreach ($defaults as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                $this->logger->error('Failed to install configuration: ' . $key);

                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall configuration.
     */
    protected function uninstallConfiguration()
    {
        $defaults = YujuConfig::getDefaults();

        foreach (array_keys($defaults) as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Create necessary directories.
     */
    protected function createDirectories()
    {
        $directories = [
        YUJU_LOG_DIR,
        dirname(__FILE__) . '/cache/',
        dirname(__FILE__) . '/exports/',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->logger->error('Failed to create directory: ' . $dir);

                    return false;
                }
            }

            // Create index.php for security
            $index_file = $dir . 'index.php';

            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php\n// Silence is golden\nexit;');
            }
        }

        return true;
    }

    /**
     * Get module configuration form.
     */
    public function getContent()
    {
        // Redirigir al controlador personalizado de configuración
        $admin_link = $this->context->link->getAdminLink('AdminYujuConfiguration');
        Tools::redirectAdmin($admin_link);
    }



    // Hook implementations

    /**
     * Product add hook.
     */
    public function hookActionProductAdd($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
            $this->sync_manager->queueProductSync($params['product']->id, 'create');
        }
    }

    /**
     * Product update hook.
     */
    public function hookActionProductUpdate($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
            $this->sync_manager->queueProductSync($params['product']->id, 'update');
        }
    }

    /**
     * Product delete hook.
     */
    public function hookActionProductDelete($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
            $this->sync_manager->queueProductSync($params['product']->id, 'delete');
        }
    }

    /**
     * Stock update hook.
     */
    public function hookActionUpdateQuantity($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_STOCK_SYNC')) {
            $this->sync_manager->queueStockSync($params['id_product'], $params['id_product_attribute']);
        }
    }

    /**
     * Product attribute update hook.
     */
    public function hookActionProductAttributeUpdate($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
            $this->sync_manager->queueProductSync($params['id_product'], 'attribute_update');
        }
    }

    /**
     * Category add hook.
     */
    public function hookActionCategoryAdd($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CATEGORY_SYNC')) {
            $this->sync_manager->queueCategorySync($params['category']->id, 'create');
        }
    }

    /**
     * Category update hook.
     */
    public function hookActionCategoryUpdate($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CATEGORY_SYNC')) {
            $this->sync_manager->queueCategorySync($params['category']->id, 'update');
        }
    }

    /**
     * Category delete hook.
     */
    public function hookActionCategoryDelete($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CATEGORY_SYNC')) {
            $this->sync_manager->queueCategorySync($params['category']->id, 'delete');
        }
    }

    /**
     * Order status update hook.
     */
    public function hookActionOrderStatusUpdate($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ORDER_SYNC')) {
            $this->sync_manager->queueOrderSync($params['id_order'], 'status_update');
        }
    }

    /**
     * Order validation hook.
     */
    public function hookActionValidateOrder($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ORDER_SYNC')) {
            $this->sync_manager->queueOrderSync($params['order']->id, 'create');
        }
    }

    /**
     * Order return hook.
     */
    public function hookActionOrderReturn($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ORDER_SYNC')) {
            $this->sync_manager->queueOrderSync($params['order']->id, 'return');
        }
    }

    /**
     * Product attribute delete hook.
     */
    public function hookActionProductAttributeDelete($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
            $this->sync_manager->queueProductSync($params['id_product'], 'attribute_delete');
        }
    }

    /**
     * Attribute group delete hook.
     */
    public function hookActionAttributeGroupDelete($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ATTRIBUTE_SYNC')) {
            $this->sync_manager->queueAttributeSync($params['object']->id, 'group_delete');
        }
    }

    /**
     * Attribute delete hook.
     */
    public function hookActionAttributeDelete($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ATTRIBUTE_SYNC')) {
            $this->sync_manager->queueAttributeSync($params['object']->id, 'delete');
        }
    }

    /**
     * Carrier update hook.
     */
    public function hookActionCarrierUpdate($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CARRIER_SYNC')) {
            $this->sync_manager->queueCarrierSync($params['carrier']->id, 'update');
        }
    }

    /**
     * Customer account add hook.
     */
    public function hookActionCustomerAccountAdd($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CUSTOMER_SYNC')) {
            $this->sync_manager->queueCustomerSync($params['newCustomer']->id, 'create');
        }
    }

    /**
     * Customer account update hook.
     */
    public function hookActionCustomerAccountUpdate($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CUSTOMER_SYNC')) {
            $this->sync_manager->queueCustomerSync($params['customer']->id, 'update');
        }
    }

    /**
     * Manufacturer add hook.
     */
    public function hookActionObjectManufacturerAddAfter($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_MANUFACTURER_SYNC')) {
            $this->sync_manager->queueManufacturerSync($params['object']->id, 'create');
        }
    }

    /**
     * Manufacturer update hook.
     */
    public function hookActionObjectManufacturerUpdateAfter($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_MANUFACTURER_SYNC')) {
            $this->sync_manager->queueManufacturerSync($params['object']->id, 'update');
        }
    }

    /**
     * Manufacturer delete hook.
     */
    public function hookActionObjectManufacturerDeleteAfter($params)
    {
        if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_MANUFACTURER_SYNC')) {
            $this->sync_manager->queueManufacturerSync($params['object']->id, 'delete');
        }
    }

    /**
     * Back office header hook.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $controller = Tools::getValue('controller');
        
        // Cargar CSS/JS en página de configuración del módulo
        if ($controller == 'AdminModules' && Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
            $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        }
        
        // Cargar CSS/JS en TODOS los controladores del módulo Yuju
        if (strpos($controller, 'AdminYuju') === 0) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
            $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        }
    }

    /**
     * Hook para cargar assets en controladores admin (PrestaShop 8 compatible).
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        // Only load on module's configuration page and all Yuju module controllers
        if (isset($this->context->controller)) {
            $controller = get_class($this->context->controller);
            
            // Load on module configuration page
            if ($this->context->controller instanceof AdminModulesController && 
                Tools::getValue('configure') == $this->name) {
                $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
                $this->context->controller->addJS($this->_path . 'views/js/admin.js');
            }
            
            // Load on all Yuju module controllers
            if (strpos($controller, 'AdminYuju') !== false) {
                $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
                $this->context->controller->addJS($this->_path . 'views/js/admin.js');
            }
        }
    }

    /**
     * Admin products extra hook.
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $product_id = (int) Tools::getValue('id_product');

        if ($product_id) {
            $sync_status = $this->sync_manager->getProductSyncStatus($product_id);

            $this->context->smarty->assign([
            'product_id' => $product_id,
            'sync_status' => $sync_status,
            'yuju_product_id' => $this->sync_manager->getYujuProductId($product_id),
            ]);

            return $this->display(__FILE__, 'views/templates/admin/product_sync_info.tpl');
        }
    }

    /**
     * Get module instance.
     */
    public static function getInstance()
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Check if module is properly configured.
     */
    public function isConfigured()
    {
        return !empty(YujuConfig::get('YUJU_CLIENT_ID'))
        && !empty(YujuConfig::get('YUJU_CLIENT_SECRET'))
        && $this->oauth->hasValidToken();
    }

    /**
     * Get module status.
     */
    public function getModuleStatus()
    {
        return [
        'configured' => $this->isConfigured(),
        'connected' => $this->oauth->hasValidToken(),
        'sync_enabled' => YujuConfig::get('YUJU_ENABLE_AUTO_SYNC'),
        'last_sync' => YujuConfig::get('YUJU_LAST_SYNC_TIME'),
        'version' => $this->version,
        ];
    }
}
