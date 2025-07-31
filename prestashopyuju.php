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

        $this->displayName = $this->l('Yuju Integration');
        $this->description = $this->l('Synchronize your PrestaShop store with Yuju platform for seamless inventory, product, and order management.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the Yuju Integration module? This will remove all synchronization data.');

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
            $this->logger->error('Install SQL file not found: ' . $sql_file);

            return false;
        }

        $sql = file_get_contents($sql_file);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);

        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);

            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    $this->logger->error('Failed to execute query: ' . $query);

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
        'name' => 'Yuju Integration',
        'parent_class_name' => 'CONFIGURE',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuConfiguration',
        'name' => 'Configuration',
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuSync',
        'name' => 'Synchronization',
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuProductMapping',
        'name' => 'Product Mapping',
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAttributeMapping',
        'name' => 'Attribute Mapping',
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuProductStatus',
        'name' => 'Product Status',
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuWebhook',
        'name' => 'Webhooks',
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuLogs',
        'name' => 'Logs',
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
                $tab->name[$language['id_lang']] = $tab_data['name'];
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
        $output = '';

        // Handle OAuth callback

        if (Tools::isSubmit('oauth_callback')) {
            $output .= $this->handleOAuthCallback();
        }

        // Handle form submission

        if (Tools::isSubmit('submitYujuConfiguration')) {
            $output .= $this->postProcess();
        }

        // Handle OAuth authorization

        if (Tools::isSubmit('authorize_yuju')) {
            $output .= $this->handleOAuthAuthorization();
        }

        // Handle test connection

        if (Tools::isSubmit('test_connection')) {
            $output .= $this->testConnection();
        }

        return $output . $this->renderForm();
    }

    /**
     * Handle OAuth callback.
     */
    protected function handleOAuthCallback()
    {
        $code = Tools::getValue('code');
        $state = Tools::getValue('state');
        $error = Tools::getValue('error');

        if ($error) {
            return $this->displayError($this->l('OAuth authorization failed: ') . $error);
        }

        if (!$code) {
            return $this->displayError($this->l('No authorization code received'));
        }

        try {
            $token_data = $this->oauth->exchangeCodeForToken($code);

            if ($token_data) {
                return $this->displayConfirmation($this->l('Successfully connected to Yuju!'));
            } else {
                return $this->displayError($this->l('Failed to exchange authorization code for token'));
            }
        } catch (Exception $e) {
            $this->logger->error('OAuth callback error: ' . $e->getMessage());

            return $this->displayError($this->l('OAuth error: ') . $e->getMessage());
        }
    }

    /**
     * Handle OAuth authorization.
     */
    protected function handleOAuthAuthorization()
    {
        try {
            $auth_url = $this->oauth->getAuthorizationUrl();
            Tools::redirect($auth_url);
        } catch (Exception $e) {
            $this->logger->error('OAuth authorization error: ' . $e->getMessage());

            return $this->displayError($this->l('Failed to generate authorization URL: ') . $e->getMessage());
        }
    }

    /**
     * Test API connection.
     */
    protected function testConnection()
    {
        try {
            if ($this->api_client->testConnection()) {
                return $this->displayConfirmation($this->l('Connection test successful!'));
            } else {
                return $this->displayError($this->l('Connection test failed'));
            }
        } catch (Exception $e) {
            $this->logger->error('Connection test error: ' . $e->getMessage());

            return $this->displayError($this->l('Connection test error: ') . $e->getMessage());
        }
    }

    /**
     * Process form submission.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        return $this->displayConfirmation($this->l('Settings updated successfully'));
    }

    /**
     * Render configuration form.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitYujuConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
        'fields_value' => $this->getConfigFormValues(),
        'languages' => $this->context->controller->getLanguages(),
        'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Get configuration form structure.
     */
    protected function getConfigForm()
    {
        return [
        'form' => [
        'legend' => [
        'title' => $this->l('Yuju Integration Settings'),
        'icon' => 'icon-cogs',
        ],
        'input' => [
        [
        'type' => 'select',
        'label' => $this->l('Environment'),
        'name' => 'YUJU_API_ENVIRONMENT',
        'required' => true,
        'options' => [
        'query' => [
        ['id' => 'sandbox', 'name' => 'Sandbox'],
        ['id' => 'production', 'name' => 'Production'],
        ],
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'text',
        'label' => $this->l('Client ID'),
        'name' => 'YUJU_CLIENT_ID',
        'required' => true,
        'size' => 50,
        ],
        [
        'type' => 'text',
        'label' => $this->l('Client Secret'),
        'name' => 'YUJU_CLIENT_SECRET',
        'required' => true,
        'size' => 50,
        ],
        [
        'type' => 'text',
        'label' => $this->l('Redirect URI'),
        'name' => 'YUJU_REDIRECT_URI',
        'size' => 100,
        'desc' => $this->l('Leave empty to use default'),
        ],
        [
        'type' => 'switch',
        'label' => $this->l('Enable Auto Sync'),
        'name' => 'YUJU_ENABLE_AUTO_SYNC',
        'is_bool' => true,
        'values' => [
        ['id' => 'active_on', 'value' => true, 'label' => $this->l('Enabled')],
        ['id' => 'active_off', 'value' => false, 'label' => $this->l('Disabled')],
        ],
        ],
        [
        'type' => 'text',
        'label' => $this->l('Sync Frequency (seconds)'),
        'name' => 'YUJU_SYNC_FREQUENCY',
        'class' => 'fixed-width-sm',
        ],
        [
        'type' => 'text',
        'label' => $this->l('Batch Size'),
        'name' => 'YUJU_SYNC_BATCH_SIZE',
        'class' => 'fixed-width-sm',
        ],
        [
        'type' => 'switch',
        'label' => $this->l('Enable Email Notifications'),
        'name' => 'YUJU_ENABLE_EMAIL_NOTIFICATIONS',
        'is_bool' => true,
        'values' => [
        ['id' => 'active_on', 'value' => true, 'label' => $this->l('Enabled')],
        ['id' => 'active_off', 'value' => false, 'label' => $this->l('Disabled')],
        ],
        ],
        [
        'type' => 'text',
        'label' => $this->l('Notification Email'),
        'name' => 'YUJU_NOTIFICATION_EMAIL',
        'size' => 50,
        ],
        ],
        'submit' => [
        'title' => $this->l('Save'),
        ],
        'buttons' => [
        [
        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&authorize_yuju&token=' . Tools::getAdminTokenLite('AdminModules'),
        'title' => $this->l('Authorize with Yuju'),
        'icon' => 'process-icon-cogs',
        ],
        [
        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&test_connection&token=' . Tools::getAdminTokenLite('AdminModules'),
        'title' => $this->l('Test Connection'),
        'icon' => 'process-icon-refresh',
        ],
        ],
        ],
        ];
    }

    /**
     * Get configuration form values.
     */
    protected function getConfigFormValues()
    {
        $defaults = YujuConfig::getDefaults();
        $values = [];

        foreach ($defaults as $key => $default_value) {
            $values[$key] = YujuConfig::get($key, $default_value);
        }

        return $values;
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
     * Back office header hook.
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') == 'AdminModules' && Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
            $this->context->controller->addJS($this->_path . 'views/js/admin.js');
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
