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
require_once dirname(__FILE__) . '/classes/YujuMonitor.php';
require_once dirname(__FILE__) . '/classes/YujuApiClient.php';
require_once dirname(__FILE__) . '/classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
require_once dirname(__FILE__) . '/classes/YujuWebhookManager.php';
require_once dirname(__FILE__) . '/classes/YujuUpdateManager.php';

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
        $this->version = '1.1.5';
        $this->author = 'Yuju Integration Team';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '9.99.99',
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
                PrestaShopLogger::addLog('Failed to install tab: ' . $tab_data['class_name'], 3);

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
                    PrestaShopLogger::addLog('Failed to uninstall tab: ' . $class_name, 3);
                }
            }
        }

        return true;
    }

    /**
     * Lista de hooks que el módulo puede registrar.
     *
     * Se expone como método público para que el back-office pueda comprobar
     * cuáles están realmente registrados en la tienda (p. ej. tras una
     * actualización que no re-registró los hooks).
     *
     * - `displayBackOfficeHeader` y `actionAdminControllerSetMedia` solo cargan
     *   los assets del panel y van siempre activos.
     * - Los 4 hooks de sincronización puntual están DESHABILITADOS por defecto
     *   (ver YUJU_DISABLED_HOOKS) y se activan desde el switch del panel.
     *
     * @return string[] Nombres técnicos de los hooks
     */
    public function getModuleHooks()
    {
        return [
        'displayBackOfficeHeader',
        'actionAdminControllerSetMedia',
        'actionProductUpdate',
        'actionUpdateQuantity',
        'actionValidateOrder',
        'actionOrderStatusUpdate',
        ];
    }

    /**
     * Hooks de sincronización que vienen deshabilitados de fábrica.
     *
     * El usuario puede activarlos con el switch del panel de configuración.
     *
     * @return string[]
     */
    public static function getDefaultDisabledHooks()
    {
        return [
            'actionProductUpdate',
            'actionUpdateQuantity',
            'actionValidateOrder',
            'actionOrderStatusUpdate',
        ];
    }

    /**
     * Hooks deshabilitados manualmente desde el back-office.
     *
     * Se guardan como lista separada por comas en YUJU_DISABLED_HOOKS para que
     * el estado elegido sobreviva a reinstalaciones/actualizaciones (registerHooks()
     * los omite). Solo se devuelven nombres que siguen existiendo en el módulo.
     *
     * @return string[]
     */
    public function getDisabledHooks()
    {
        $raw = Configuration::get('YUJU_DISABLED_HOOKS');

        if (!is_string($raw) || $raw === '') {
            // Distinguir "no configurado" (aplicar los de fábrica) de "lista
            // vacía intencional" (el usuario activó todos los switches).
            if (!Configuration::hasKey('YUJU_DISABLED_HOOKS')) {
                return static::getDefaultDisabledHooks();
            }

            return [];
        }

        $module_hooks = $this->getModuleHooks();
        $list = array_map('trim', explode(',', $raw));

        return array_values(array_filter($list, function ($hook) use ($module_hooks) {
            return $hook !== '' && in_array($hook, $module_hooks, true);
        }));
    }

    /**
     * Habilita o deshabilita un hook del módulo.
     *
     * Registra/desregistra el hook en PrestaShop y persiste la elección en
     * YUJU_DISABLED_HOOKS. Devuelve true si el estado solicitado ya está aplicado.
     *
     * @param string $hook Nombre técnico del hook
     * @param bool $enabled true para habilitar, false para deshabilitar
     *
     * @return bool
     */
    public function setHookEnabled($hook, $enabled)
    {
        if (!in_array($hook, $this->getModuleHooks(), true)) {
            return false;
        }

        $disabled = $this->getDisabledHooks();

        if ($enabled) {
            $disabled = array_values(array_diff($disabled, [$hook]));
            Configuration::updateValue('YUJU_DISABLED_HOOKS', implode(',', $disabled));

            if ($this->isRegisteredInHook($hook)) {
                return true;
            }

            return (bool) $this->registerHook($hook);
        }

        if (!in_array($hook, $disabled, true)) {
            $disabled[] = $hook;
        }
        Configuration::updateValue('YUJU_DISABLED_HOOKS', implode(',', $disabled));

        if (!$this->isRegisteredInHook($hook)) {
            return true;
        }

        // Se resuelve el id y se desregistra por id: Hook::unregisterHook acepta
        // nombre o id, pero por id funciona también en PrestaShop 1.7.
        $hook_id = (int) Hook::getIdByName($hook);

        return $hook_id > 0 ? (bool) $this->unregisterHook($hook_id) : false;
    }

    /**
     * Migración de hooks de la versión 1.1.5 (idempotente).
     *
     * El actualizador propio del módulo (GitHub) copia los ficheros y actualiza
     * la versión en BD, pero NO ejecuta los scripts `upgrade/` de PrestaShop
     * (eso solo ocurre en el flujo `runUpgradeModule`). Por eso, la primera vez
     * que se entra al back-office tras actualizar, se aplica aquí la misma
     * migración que `upgrade/upgrade-1.1.5.php`:
     *  - desregistra los hooks retirados (23 → 6);
     *  - deja desactivados por defecto los 4 hooks de sincronización puntual.
     *
     * Un flag (YUJU_HOOKS_SCHEMA) evita repetirla y evita pisar una elección
     * posterior del usuario.
     */
    protected function ensureHookMigration()
    {
        if (Configuration::get('YUJU_HOOKS_SCHEMA') === '1.1.5') {
            return;
        }

        $removed_hooks = [
            'actionProductAdd',
            'actionProductDelete',
            'actionProductAttributeUpdate',
            'actionProductAttributeDelete',
            'actionCategoryAdd',
            'actionCategoryUpdate',
            'actionCategoryDelete',
            'actionOrderReturn',
            'actionAttributeGroupDelete',
            'actionAttributeDelete',
            'actionCarrierUpdate',
            'actionCustomerAccountAdd',
            'actionCustomerAccountUpdate',
            'actionObjectManufacturerAddAfter',
            'actionObjectManufacturerUpdateAfter',
            'actionObjectManufacturerDeleteAfter',
            'displayAdminProductsExtra',
        ];

        foreach ($removed_hooks as $hook) {
            $hook_id = (int) Hook::getIdByName($hook);

            if ($hook_id > 0 && $this->isRegisteredInHook($hook)) {
                $this->unregisterHook($hook_id);
            }
        }

        // Los hooks de sincronización puntual empiezan apagados: se añaden a la
        // lista de deshabilitados y se desregistran.
        $default_disabled = static::getDefaultDisabledHooks();
        $raw = Configuration::get('YUJU_DISABLED_HOOKS');
        $current = [];

        if (is_string($raw) && $raw !== '') {
            $current = array_filter(array_map('trim', explode(',', $raw)));
        }

        $merged = array_values(array_unique(array_merge($current, $default_disabled)));
        Configuration::updateValue('YUJU_DISABLED_HOOKS', implode(',', $merged));

        foreach ($default_disabled as $hook) {
            $hook_id = (int) Hook::getIdByName($hook);

            if ($hook_id > 0 && $this->isRegisteredInHook($hook)) {
                $this->unregisterHook($hook_id);
            }
        }

        Configuration::updateValue('YUJU_HOOKS_SCHEMA', '1.1.5');
    }

    /**
     * Register module hooks.
     *
     * Omite los hooks deshabilitados manualmente desde el back-office.
     */
    protected function registerHooks()
    {
        $disabled = $this->getDisabledHooks();

        foreach ($this->getModuleHooks() as $hook) {
            if (in_array($hook, $disabled, true)) {
                continue;
            }

            if (!$this->registerHook($hook)) {
                PrestaShopLogger::addLog('Failed to register hook: ' . $hook, 3);

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
                PrestaShopLogger::addLog('Failed to install configuration: ' . $key, 3);

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
                    PrestaShopLogger::addLog('Failed to create directory: ' . $dir, 3);

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
     * Al actualizar un producto: empuja el producto a Yuju.
     *
     * Disponible pero DESHABILITADO por defecto (ver YUJU_DISABLED_HOOKS). Se
     * activa desde el switch del panel. La sincronización es best-effort: si la
     * API falla se registra el error y NUNCA se interrumpe el guardado.
     */
    public function hookActionProductUpdate($params)
    {
        $product_id = 0;

        if (isset($params['product']) && Validate::isLoadedObject($params['product'])) {
            $product_id = (int) $params['product']->id;
        } elseif (isset($params['id_product'])) {
            $product_id = (int) $params['id_product'];
        }

        if ($product_id <= 0) {
            return;
        }

        try {
            $product_manager = new YujuProductManager();
            $product_manager->syncProductsToYuju([$product_id]);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[Yuju] actionProductUpdate (product ' . $product_id . '): ' . $e->getMessage(), 2);
        }
    }

    /**
     * Al actualizar el stock: empuja stock y precio del producto a Yuju.
     *
     * DESHABILITADO por defecto. Cubre "stock/precio": los cambios de precio
     * entran por actionProductUpdate, por lo que aquí se sincronizan ambos.
     */
    public function hookActionUpdateQuantity($params)
    {
        $product_id = isset($params['id_product']) ? (int) $params['id_product'] : 0;

        if ($product_id <= 0) {
            return;
        }

        try {
            $sync_manager = new YujuSyncManager();
            $sync_manager->syncStock([$product_id]);
            $sync_manager->syncPrices([$product_id]);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[Yuju] actionUpdateQuantity (product ' . $product_id . '): ' . $e->getMessage(), 2);
        }
    }

    /**
     * Al cambiar el estado de un pedido: lo replica en Yuju.
     *
     * DESHABILITADO por defecto.
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $order_id = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        $status_id = 0;

        if (isset($params['newOrderStatus']) && Validate::isLoadedObject($params['newOrderStatus'])) {
            $status_id = (int) $params['newOrderStatus']->id;
        } elseif (isset($params['id_order_state'])) {
            $status_id = (int) $params['id_order_state'];
        }

        if ($order_id <= 0 || $status_id <= 0) {
            return;
        }

        try {
            $order_manager = new YujuOrderManager($this->context);
            $order_manager->updateOrderStatusInYuju($order_id, $status_id);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[Yuju] actionOrderStatusUpdate (order ' . $order_id . '): ' . $e->getMessage(), 2);
        }
    }

    /**
     * Al validar un pedido: lo envía a Yuju.
     *
     * DESHABILITADO por defecto.
     */
    public function hookActionValidateOrder($params)
    {
        $order_id = 0;

        if (isset($params['order']) && Validate::isLoadedObject($params['order'])) {
            $order_id = (int) $params['order']->id;
        } elseif (isset($params['id_order'])) {
            $order_id = (int) $params['id_order'];
        }

        if ($order_id <= 0) {
            return;
        }

        try {
            $order_manager = new YujuOrderManager($this->context);
            $order_manager->sendOrderToYuju($order_id);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[Yuju] actionValidateOrder (order ' . $order_id . '): ' . $e->getMessage(), 2);
        }
    }

    /**
     * Back office header hook.
     */
    public function hookDisplayBackOfficeHeader()
    {
        // Red de seguridad: aplica una vez la migración de hooks de 1.1.5 en
        // tiendas que se actualizan con el actualizador propio del módulo (que
        // no ejecuta los scripts `upgrade/` de PrestaShop).
        $this->ensureHookMigration();

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
        $oauth = new YujuOAuth();

        return !empty(YujuConfig::get('YUJU_CLIENT_ID'))
        && !empty(YujuConfig::get('YUJU_CLIENT_SECRET'))
        && $oauth->hasValidToken();
    }

    /**
     * Get module status.
     */
    public function getModuleStatus()
    {
        $oauth = new YujuOAuth();

        return [
        'configured' => $this->isConfigured(),
        'connected' => $oauth->hasValidToken(),
        'sync_enabled' => YujuConfig::get('YUJU_ENABLE_AUTO_SYNC'),
        'last_sync' => YujuConfig::get('YUJU_LAST_SYNC_TIME'),
        'version' => $this->version,
        ];
    }
}

