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

// Include autoloader and config
require_once dirname(__FILE__) . '/config/config.php';

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
        $this->version = '1.0.1';
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
        && $this->ensureProductStatusIntermediateWebhookStates()
        && $this->ensureAuditTables()
        && $this->installTabs()
        && $this->registerHooks()
        && $this->installConfiguration()
        && $this->createDirectories()
        && $this->ensureYujuCarrier();
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
     * Re-ejecuta sql/install.sql (todas las sentencias usan IF NOT EXISTS),
     * útil para "reparar" tablas faltantes sin reinstalar el módulo.
     *
     * @return array{success: bool, executed: int, errors: array<int, string>}
     */
    public function ensureAllYujuTables()
    {
        $sql_file = dirname(__FILE__) . '/sql/install.sql';
        $result = ['success' => false, 'executed' => 0, 'errors' => []];

        if (!file_exists($sql_file)) {
            $result['errors'][] = 'install.sql no encontrado en: ' . $sql_file;
            return $result;
        }

        $sql = file_get_contents($sql_file);
        if ($sql === false) {
            $result['errors'][] = 'No se pudo leer el contenido de install.sql.';
            return $result;
        }

        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }
            try {
                if (Db::getInstance()->execute($query)) {
                    $result['executed']++;
                } else {
                    $msg = Db::getInstance()->getMsgError();
                    if ($msg !== '') {
                        $result['errors'][] = $msg;
                    }
                }
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }

        // También aplicar parches incrementales conocidos.
        try {
            $this->ensureSyncHistoryTable();
            $this->ensureSyncQueueActionIncludesDelete();
            $this->ensureProductStatusIntermediateWebhookStates();
            $this->ensureAuditTables();
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        $result['success'] = empty($result['errors']) || $result['executed'] > 0;
        return $result;
    }

    /**
     * Crea la tabla de historial de envíos a Yuju si no existe (instalaciones antiguas / sql omitido).
     */
    public function ensureSyncHistoryTable()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $path = dirname(__FILE__) . '/sql/add_sync_history.sql';
        if (!is_readable($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                Db::getInstance()->execute($query);
            }
        }

        return true;
    }

    /**
     * Crea tablas de auditoría de ofertas si faltan (instalaciones ya existentes).
     *
     * @return bool
     */
    public function ensureAuditTables()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        // Evitar ALTER/SHOW en cada AJAX de chunk (eran ~14+ por auditoría)
        // Bump este entero cuando haya un cambio de esquema de auditoría.
        $schemaVer = 2;
        if ((int) Configuration::get('YUJU_AUDIT_SCHEMA_OK') >= $schemaVer) {
            return true;
        }

        $path = dirname(__FILE__) . '/sql/add_audit_tables.sql';
        if (!is_readable($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                Db::getInstance()->execute($query);
            }
        }

        // result diff_found = solo comparación (sin corregir Yuju)
        try {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_audit_run_details`
                 MODIFY COLUMN `result` ENUM(\'matched\',\'diff_fixed\',\'diff_found\',\'diff_error\',\'not_found\')
                 NOT NULL DEFAULT \'matched\''
            );
        } catch (Exception $e) {
            // ya actualizado
        }

        try {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_audit_runs`
                 MODIFY COLUMN `status` ENUM(\'pending\',\'running\',\'paused\',\'completed\',\'failed\',\'cancelled\')
                 NOT NULL DEFAULT \'pending\''
            );
        } catch (Exception $e) {
            // ya actualizado
        }

        try {
            $cols = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'yuju_audit_runs` LIKE "duration_seconds"');
            if (empty($cols)) {
                Db::getInstance()->execute(
                    'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_audit_runs` ADD COLUMN `duration_seconds` INT(11) DEFAULT NULL AFTER `finished_at`'
                );
            }
        } catch (Exception $e) {
            // ignore
        }

        try {
            $cols = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'yuju_audit_run_details` LIKE "product_name"');
            if (empty($cols)) {
                Db::getInstance()->execute(
                    'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_audit_run_details` ADD COLUMN `product_name` VARCHAR(512) DEFAULT NULL AFTER `sku`'
                );
            }
        } catch (Exception $e) {
            // ignore
        }

        // Índice compuesto para filtros del panel de monitoreo
        try {
            $idx = Db::getInstance()->executeS(
                'SHOW INDEX FROM `' . _DB_PREFIX_ . 'yuju_audit_run_details` WHERE Key_name = \'idx_run_result\''
            );
            if (empty($idx)) {
                Db::getInstance()->execute(
                    'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_audit_run_details`
                     ADD KEY `idx_run_result` (`id_audit_run`, `result`)'
                );
            }
        } catch (Exception $e) {
            // ignore
        }

        $this->ensureProductReportsTable();

        Configuration::updateValue('YUJU_AUDIT_SCHEMA_OK', $schemaVer);

        return true;
    }

    /**
     * Tabla histórico products-gral-report (upgrades).
     *
     * @return bool
     */
    public function ensureProductReportsTable()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $path = dirname(__FILE__) . '/sql/add_product_reports_table.sql';
        if (!is_readable($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $queries = preg_split("/;\s*$/m", $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                Db::getInstance()->execute($query);
            }
        }

        return true;
    }

    /**
     * Amplía el ENUM de la cola para permitir acciones delete (instalaciones ya existentes).
     */
    public function ensureSyncQueueActionIncludesDelete()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        try {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_sync_queue` MODIFY COLUMN `action` ENUM(\'create\',\'update\',\'delete\') NOT NULL'
            );
        } catch (Exception $e) {
            // Ya alterada o permisos; el siguiente INSERT fallará si hace falta
        }

        return true;
    }

    /**
     * Añade estados intermedios mientras se espera confirmación por webhook de Yuju.
     */
    public function ensureProductStatusIntermediateWebhookStates()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        try {
            Db::getInstance()->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_product_status` MODIFY COLUMN `sync_status` ENUM(
                    \'pending\',\'syncing\',\'synced\',\'synced_with_warnings\',\'synced_with_errors\',\'error\',\'disabled\',\'queued\',
                    \'creating_in_yuju\',\'updating_in_yuju\',\'deleting_in_yuju\'
                ) DEFAULT \'pending\''
            );
        } catch (Exception $e) {
            // Ya aplicado o sin permisos ALTER
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
        'class_name' => 'AdminYujuOrderStatusMapping',
        'name' => $this->trans('Mapeo de Estados', array(), 'Modules.Prestashopyuju.Admin'),
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
        'class_name' => 'AdminYujuCategoryBulk',
        'name' => $this->trans('Acciones por categoría', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAudit',
        'name' => $this->trans('Auditoría', array(), 'Modules.Prestashopyuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAuditMonitoring',
        'name' => $this->trans('Monitoreo Auditoría', array(), 'Modules.Prestashopyuju.Admin'),
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
     * Crea la pestaña "Acciones por categoría" si falta (instalaciones anteriores al añadir el menú).
     *
     * @return bool
     */
    public function ensureYujuCategoryBulkTab()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $existing = Tab::getInstanceFromClassName('AdminYujuCategoryBulk');
        if (Validate::isLoadedObject($existing) && (int) $existing->id > 0) {
            return true;
        }

        $parentTab = Tab::getInstanceFromClassName('AdminYuju');
        if (!Validate::isLoadedObject($parentTab) || !(int) $parentTab->id) {
            $root = Tab::getInstanceFromClassName('CONFIGURE');
            if (!Validate::isLoadedObject($root) || !(int) $root->id) {
                return false;
            }

            $parentTab = new Tab();
            $parentTab->class_name = 'AdminYuju';
            $parentTab->module = $this->name;
            $parentTab->active = true;
            $parentTab->id_parent = (int) $root->id;
            $parentName = $this->trans('Yuju Integration', array(), 'Modules.Prestashopyuju.Admin');
            foreach (Language::getLanguages(false) as $language) {
                $parentTab->name[$language['id_lang']] = $parentName;
            }
            if (!$parentTab->save()) {
                return false;
            }
        }

        $tab = new Tab();
        $tab->class_name = 'AdminYujuCategoryBulk';
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = (int) $parentTab->id;
        $name = $this->trans('Acciones por categoría', array(), 'Modules.Prestashopyuju.Admin');
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[$language['id_lang']] = $name;
        }

        return (bool) $tab->save();
    }

    /**
     * Crea pestañas de Auditoría / Monitoreo si faltan (upgrades).
     *
     * @return bool
     */
    public function ensureYujuAuditTabs()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $this->ensureYujuCategoryBulkTab();

        $parentTab = Tab::getInstanceFromClassName('AdminYuju');
        if (!Validate::isLoadedObject($parentTab) || !(int) $parentTab->id) {
            return false;
        }

        $tabs = [
            'AdminYujuAudit' => $this->trans('Auditoría', array(), 'Modules.Prestashopyuju.Admin'),
            'AdminYujuAuditMonitoring' => $this->trans('Monitoreo Auditoría', array(), 'Modules.Prestashopyuju.Admin'),
        ];

        foreach ($tabs as $className => $name) {
            $existing = Tab::getInstanceFromClassName($className);
            if (Validate::isLoadedObject($existing) && (int) $existing->id > 0) {
                continue;
            }

            $tab = new Tab();
            $tab->class_name = $className;
            $tab->module = $this->name;
            $tab->active = true;
            $tab->id_parent = (int) $parentTab->id;
            foreach (Language::getLanguages(false) as $language) {
                $tab->name[$language['id_lang']] = $name;
            }
            if (!$tab->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Crea pestaña de mapeo de estados de pedido si falta (upgrades).
     *
     * @return bool
     */
    public function ensureYujuOrderStatusMappingTab()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $existing = Tab::getInstanceFromClassName('AdminYujuOrderStatusMapping');
        if (Validate::isLoadedObject($existing) && (int) $existing->id > 0) {
            return true;
        }

        $parentTab = Tab::getInstanceFromClassName('AdminYuju');
        if (!Validate::isLoadedObject($parentTab) || !(int) $parentTab->id) {
            return false;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminYujuOrderStatusMapping';
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = (int) $parentTab->id;
        $name = $this->trans('Mapeo de Estados', array(), 'Modules.Prestashopyuju.Admin');
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[$language['id_lang']] = $name;
        }

        return (bool) $tab->save();
    }

    /**
     * Asegura que exista el carrier "Yuju" para pedidos marketplace.
     *
     * @return bool
     */
    public function ensureYujuCarrier()
    {
        static $done = false;
        if ($done) {
            return true;
        }
        $done = true;

        $existing = (int) Db::getInstance()->getValue('
            SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier
            WHERE name = "Yuju" AND deleted = 0
            ORDER BY id_carrier DESC
        ');
        if ($existing > 0) {
            return true;
        }

        require_once dirname(__FILE__) . '/classes/YujuOrderManager.php';
        try {
            $manager = new YujuOrderManager();
            $carrier_id = $manager->ensureYujuCarrierPublic();

            return (int) $carrier_id > 0;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Yuju carrier ensure failed: ' . $e->getMessage(), 3);

            return false;
        }
    }

    /**
     * Uninstall admin tabs.
     */
    protected function uninstallTabs()
    {
        $tab_classes = [
        'AdminYujuLogs',
        'AdminYujuWebhook',
        'AdminYujuAuditMonitoring',
        'AdminYujuAudit',
        'AdminYujuCategoryBulk',
        'AdminYujuProductStatus',
        'AdminYujuOrderStatusMapping',
        'AdminYujuAttributeMapping',
        'AdminYujuProductMapping',
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
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueProductSync')) {
                    $this->sync_manager->queueProductSync($params['product']->id, 'create');
                }
            }
        } catch (Exception $e) {
            // Silenciar error para no romper el guardado del producto
        }
    }

    /**
     * Product delete hook.
     */
    public function hookActionProductDelete($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueProductSync')) {
                    $this->sync_manager->queueProductSync($params['product']->id, 'delete');
                }
            }
        } catch (Exception $e) {
            // Silenciar error para no romper el borrado del producto
        }
    }

    /**
     * Stock update hook.
     */
    public function hookActionUpdateQuantity($params)
    {
        require_once dirname(__FILE__) . '/classes/YujuLogger.php';
        require_once dirname(__FILE__) . '/classes/YujuApiClient.php';
        require_once dirname(__FILE__) . '/classes/YujuOrderManager.php';

        // Evitar doble push: tras crear orden Yuju el OrderManager ya empuja el stock
        if (!empty(YujuOrderManager::$suppressStockHookToYuju)) {
            return;
        }
        
        $logger = new YujuLogger();
        
        $logger->info('=== hookActionUpdateQuantity TRIGGERED ===', [
            'id_product' => $params['id_product'] ?? 'N/A',
            'quantity' => $params['quantity'] ?? 'N/A',
            'params' => $params,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Verificar configuración directamente desde Configuration de PrestaShop
        $client_id = Configuration::get('YUJU_CLIENT_ID');
        $client_secret = Configuration::get('YUJU_CLIENT_SECRET');
        $auto_sync = Configuration::get('YUJU_ENABLE_AUTO_SYNC');
        
        // Verificar que el módulo esté configurado
        if (empty($client_id) || empty($client_secret)) {
            $logger->warning('hookActionUpdateQuantity: Módulo NO configurado - DETENIDO');
            return;
        }
        
        $logger->info('hookActionUpdateQuantity: Módulo configurado OK');
        
        // Verificar que la sincronización automática esté habilitada
        if (!$auto_sync) {
            $logger->warning('hookActionUpdateQuantity: Auto-sync DESHABILITADO - DETENIDO', [
                'YUJU_ENABLE_AUTO_SYNC' => $auto_sync
            ]);
            return;
        }
        
        $logger->info('hookActionUpdateQuantity: Auto-sync habilitado OK');
        
        $product_id = (int)$params['id_product'];
        
        try {
            // Verificar si el producto está sincronizado con Yuju
            $status = Db::getInstance()->getRow('
                SELECT yuju_product_id, sync_status 
                FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                WHERE prestashop_product_id = ' . $product_id
            );
            
            // Solo sincronizar si ya existe en Yuju
            if (!$status || empty($status['yuju_product_id'])) {
                return;
            }
            
            $yuju_product_id = $status['yuju_product_id'];
            
            // Obtener el stock ACTUAL del parámetro quantity (es el stock después del cambio)
            // El parámetro 'quantity' en actionUpdateQuantity contiene el nuevo valor
            $new_stock = isset($params['quantity']) ? (int)$params['quantity'] : 0;
            
            // Si no viene en params, obtener de la BD como fallback
            if ($new_stock === 0) {
                $new_stock = StockAvailable::getQuantityAvailableByProduct($product_id);
            }
            
            // Calcular el valor anterior usando el delta
            // delta_quantity es negativo si se resta, positivo si se suma
            $delta = isset($params['delta_quantity']) ? (int)$params['delta_quantity'] : 0;
            $old_stock = $new_stock - $delta;
            
            $logger->info('Auto-sync (Stock): Valores detectados', [
                'product_id' => $product_id,
                'quantity_param' => $params['quantity'] ?? 'N/A',
                'delta_quantity' => $delta,
                'old_stock' => $old_stock,
                'new_stock' => $new_stock,
                'stock_to_send' => $new_stock
            ]);
            
            // Preparar datos para actualizar solo el stock
            $yuju_data = [
                'stock' => $new_stock
            ];
            
            $logger->info('Auto-sync (Stock): Actualizando cantidad en Yuju', [
                'product_id' => $product_id,
                'yuju_product_id' => $yuju_product_id,
                'new_stock' => $new_stock,
                'hook' => 'actionUpdateQuantity'
            ]);

            // Marcar como "actualizando" en la tabla de estado ANTES de hacer la llamada
            // a Yuju, así otra pestaña/usuario verá el estado intermedio "Actualizando…".
            Db::getInstance()->update(
                'yuju_product_status',
                [
                    'sync_status' => pSQL('updating_in_yuju'),
                    'last_error' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'prestashop_product_id = ' . (int) $product_id
            );

            $start_time = microtime(true);
            $api_client = new YujuApiClient();
            $result = $api_client->updateProduct($yuju_product_id, $yuju_data);
            $sync_duration = microtime(true) - $start_time;
            
            // Guardar en historial
            $this->saveProductUpdateHistory(
                $product_id,
                $yuju_product_id,
                [
                    'changed_fields' => ['quantity', 'stock'],
                    'priority' => 'high', // Stock siempre es prioridad alta
                    'old_values' => ['quantity' => $old_stock],
                    'new_values' => ['quantity' => $new_stock]
                ],
                $yuju_data,
                $result,
                $sync_duration,
                'auto_stock'
            );
            
            // Actualizar estado si hubo error
            if (!$result['success']) {
                Db::getInstance()->update(
                    'yuju_product_status',
                    [
                        'sync_status' => pSQL('synced_with_errors'),
                        'last_error' => pSQL($result['message'] ?? 'Error en actualización de stock'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'prestashop_product_id = ' . $product_id
                );
            } else {
                // Actualizar estado a synced y timestamp de última sincronización
                Db::getInstance()->update(
                    'yuju_product_status',
                    [
                        'sync_status' => pSQL('synced'),
                        'last_sync_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'prestashop_product_id = ' . $product_id
                );
            }
            
        } catch (Exception $e) {
            $logger = new YujuLogger();
            $logger->error('Error en auto-sync de stock', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Product attribute update hook.
     */
    public function hookActionProductAttributeUpdate($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueProductSync')) {
                    $this->sync_manager->queueProductSync($params['id_product'], 'attribute_update');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Category add hook.
     */
    public function hookActionCategoryAdd($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CATEGORY_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueCategorySync')) {
                    $this->sync_manager->queueCategorySync($params['category']->id, 'create');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Category update hook.
     */
    public function hookActionCategoryUpdate($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CATEGORY_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueCategorySync')) {
                    $this->sync_manager->queueCategorySync($params['category']->id, 'update');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Category delete hook.
     */
    public function hookActionCategoryDelete($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_CATEGORY_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueCategorySync')) {
                    $this->sync_manager->queueCategorySync($params['category']->id, 'delete');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Order status update hook.
     */
    public function hookActionOrderStatusUpdate($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ORDER_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueOrderSync')) {
                    $this->sync_manager->queueOrderSync($params['id_order'], 'status_update');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Order validation hook.
     */
    public function hookActionValidateOrder($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ORDER_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueOrderSync')) {
                    $this->sync_manager->queueOrderSync($params['order']->id, 'create');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Order return hook.
     */
    public function hookActionOrderReturn($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ORDER_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueOrderSync')) {
                    $this->sync_manager->queueOrderSync($params['order']->id, 'return');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Product attribute delete hook.
     */
    public function hookActionProductAttributeDelete($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_PRODUCT_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueProductSync')) {
                    $this->sync_manager->queueProductSync($params['id_product'], 'attribute_delete');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Attribute group delete hook.
     */
    public function hookActionAttributeGroupDelete($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ATTRIBUTE_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueAttributeSync')) {
                    $this->sync_manager->queueAttributeSync($params['object']->id, 'group_delete');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
        }
    }

    /**
     * Attribute delete hook.
     */
    public function hookActionAttributeDelete($params)
    {
        try {
            if (YujuConfig::get('YUJU_ENABLE_AUTO_SYNC') && YujuConfig::get('YUJU_ENABLE_ATTRIBUTE_SYNC')) {
                if (!isset($this->sync_manager) || !$this->sync_manager) {
                    require_once dirname(__FILE__) . '/classes/YujuSyncManager.php';
                    $this->sync_manager = new YujuSyncManager();
                }
                if (method_exists($this->sync_manager, 'queueAttributeSync')) {
                    $this->sync_manager->queueAttributeSync($params['object']->id, 'delete');
                }
            }
        } catch (Exception $e) {
            // Silenciar error
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
     * Registra CSS/JS admin del BO.
     * Usa addCSS/addJS (fiable en controladores AdminYuju*).
     * No añadir ?v= a la ruta: en PS 8 rompe la resolución del archivo.
     *
     * @param bool $includeProductModal
     *
     * @return void
     */
    protected function registerYujuAdminAssets($includeProductModal = false)
    {
        if (!isset($this->context->controller) || !is_object($this->context->controller)) {
            return;
        }

        $controller = $this->context->controller;

        // addCSS/addJS: mismo patrón que AdminYujuController (probado en este módulo)
        if (method_exists($controller, 'addCSS')) {
            $controller->addCSS($this->_path . 'views/css/admin.css');
        }
        if (method_exists($controller, 'addJS')) {
            $controller->addJS($this->_path . 'views/js/admin.js');
            if ($includeProductModal) {
                $controller->addJS($this->_path . 'views/js/yuju_product_info_modal.js');
            }
        }
    }

    /**
     * Back office header hook.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $controller = Tools::getValue('controller');
        $this->ensureYujuCategoryBulkTab();
        $this->ensureYujuAuditTabs();
        $this->ensureYujuOrderStatusMappingTab();
        $this->ensureAuditTables();
        $this->ensureYujuCarrier();

        // Cargar CSS/JS en página de configuración del módulo
        if ($controller == 'AdminModules' && Tools::getValue('configure') == $this->name) {
            $this->registerYujuAdminAssets(false);
        }

        // Cargar CSS/JS en TODOS los controladores del módulo Yuju
        if (strpos($controller, 'AdminYuju') === 0) {
            $this->registerYujuAdminAssets(true);
        }
    }

    /**
     * Hook para cargar assets en controladores admin (PrestaShop 8 compatible).
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        // Only load on module's configuration page and all Yuju module controllers
        if (isset($this->context->controller)) {
            $this->ensureYujuCategoryBulkTab();
            $this->ensureYujuAuditTabs();
            $this->ensureYujuOrderStatusMappingTab();
            $this->ensureAuditTables();
            $this->ensureYujuCarrier();
            $controller = get_class($this->context->controller);

            // Load on module configuration page
            if ($this->context->controller instanceof AdminModulesController &&
                Tools::getValue('configure') == $this->name) {
                $this->registerYujuAdminAssets(false);
            }

            // Load on all Yuju module controllers
            if (strpos($controller, 'AdminYuju') !== false) {
                $this->registerYujuAdminAssets(true);
            }
        }
    }

    /**
     * Admin products extra hook.
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        try {
            $product_id = (int) Tools::getValue('id_product');

            if ($product_id) {
                // Verificar si el producto tiene mapping con Yuju
                $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_status` 
                        WHERE `prestashop_product_id` = ' . (int)$product_id;
                $status = Db::getInstance()->getRow($sql);
                
                $sync_status = $status ? $status['sync_status'] : 'not_synced';
                $yuju_product_id = $status ? $status['yuju_product_id'] : null;

                $this->context->smarty->assign([
                    'product_id' => $product_id,
                    'sync_status' => $sync_status,
                    'yuju_product_id' => $yuju_product_id,
                ]);

                return $this->display(__FILE__, 'views/templates/admin/product_sync_info.tpl');
            }
        } catch (Exception $e) {
            // Silenciar errores para no romper la página de productos
            return '';
        }
        
        return '';
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
     * Hook: Detecta cambios en productos y sincroniza con Yuju
     * Se ejecuta después de actualizar un producto
     */
    public function hookActionProductUpdate($params)
    {
        // Prevenir ejecuciones múltiples - usar variable global en lugar de estática
        global $yuju_processing_products;
        if (!isset($yuju_processing_products)) {
            $yuju_processing_products = [];
        }
        
        require_once dirname(__FILE__) . '/classes/YujuLogger.php';
        require_once dirname(__FILE__) . '/classes/YujuProductChangeDetector.php';
        require_once dirname(__FILE__) . '/classes/YujuApiClient.php';
        require_once dirname(__FILE__) . '/classes/YujuSyncQueue.php';
        
        $logger = new YujuLogger();
        
        if (!isset($params['product'])) {
            return;
        }
        
        $product = $params['product'];
        $product_id = (int)$product->id;
        
        // Evitar procesamiento duplicado con marca de tiempo
        $current_time = microtime(true);
        if (isset($yuju_processing_products[$product_id])) {
            $time_diff = $current_time - $yuju_processing_products[$product_id];
            if ($time_diff < 2) { // Ignorar si se ejecutó hace menos de 2 segundos
                $logger->info('hookActionProductUpdate: Ejecutado recientemente, saltando', [
                    'product_id' => $product_id,
                    'time_diff' => $time_diff
                ]);
                return;
            }
        }
        
        $yuju_processing_products[$product_id] = $current_time;
        
        try {
            // Log inicial para debug
            $logger->info('=== hookActionProductUpdate TRIGGERED ===', [
                'product_id' => $product_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        
            // Verificar configuración directamente desde Configuration de PrestaShop
            $client_id = Configuration::get('YUJU_CLIENT_ID');
            $client_secret = Configuration::get('YUJU_CLIENT_SECRET');
            $auto_sync = Configuration::get('YUJU_ENABLE_AUTO_SYNC');
            
            $logger->info('hookActionProductUpdate: Configuración cargada', [
                'has_client_id' => !empty($client_id),
                'has_client_secret' => !empty($client_secret),
                'auto_sync' => $auto_sync,
                'client_id_length' => strlen($client_id)
            ]);
            
            // Verificar que el módulo esté configurado
            if (empty($client_id) || empty($client_secret)) {
                $logger->warning('hookActionProductUpdate: Módulo NO configurado - DETENIDO', [
                    'client_id' => $client_id,
                    'client_secret_set' => !empty($client_secret)
                ]);
                return;
            }
            
            $logger->info('hookActionProductUpdate: Módulo configurado OK');
            
            // Verificar que la sincronización automática esté habilitada
            if (!$auto_sync) {
                $logger->warning('hookActionProductUpdate: Auto-sync DESHABILITADO - DETENIDO', [
                    'YUJU_ENABLE_AUTO_SYNC' => $auto_sync
                ]);
                return;
            }
            
            $logger->info('hookActionProductUpdate: Auto-sync habilitado OK');
            
            $logger->info('hookActionProductUpdate: Procesando producto', [
                'product_id' => $product_id,
                'product_name' => isset($product->name) ? $product->name : 'N/A',
                'product_reference' => $product->reference
            ]);
            
            // Verificar si el producto está sincronizado con Yuju
            $status = Db::getInstance()->getRow('
                SELECT yuju_product_id, sync_status 
                FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                WHERE prestashop_product_id = ' . $product_id
            );
            
            $logger->info('hookActionProductUpdate: Consulta BD ejecutada', [
                'product_id' => $product_id,
                'status_found' => !empty($status),
                'yuju_product_id' => !empty($status) ? $status['yuju_product_id'] : 'N/A',
                'sync_status' => !empty($status) ? $status['sync_status'] : 'N/A'
            ]);
            
            // Solo sincronizar si ya existe en Yuju
            if (!$status || empty($status['yuju_product_id'])) {
                $logger->warning('hookActionProductUpdate: Producto NO existe en Yuju - DETENIDO', [
                    'product_id' => $product_id,
                    'reason' => empty($status) ? 'No hay registro en BD' : 'yuju_product_id está vacío'
                ]);
                return;
            }
            
            $yuju_product_id = $status['yuju_product_id'];
            
            $logger->info('hookActionProductUpdate: Producto encontrado en Yuju OK', [
                'product_id' => $product_id,
                'yuju_product_id' => $yuju_product_id
            ]);
            
            // Detectar cambios
            $logger->info('hookActionProductUpdate: Iniciando detector de cambios...');
            $detector = new YujuProductChangeDetector();
            $logger->info('hookActionProductUpdate: Detector instanciado');
            
            $changes_info = $detector->detectChanges($product);
            $logger->info('hookActionProductUpdate: detectChanges() ejecutado');
            
            $logger->info('hookActionProductUpdate: Detector ejecutado', [
                'changed_fields' => $changes_info['changed_fields'],
                'priority' => $changes_info['priority'],
                'has_changes' => !empty($changes_info['changed_fields']),
                'old_values' => $changes_info['old_values'],
                'new_values' => $changes_info['new_values']
            ]);            // Si no hay cambios, salir
            if (empty($changes_info['changed_fields'])) {
                $logger->warning('hookActionProductUpdate: NO hay cambios detectados - DETENIDO', [
                    'product_id' => $product_id,
                    'note' => 'El detector no encontró diferencias con la última sincronización'
                ]);
                return;
            }
            
            // Preparar datos para Yuju (solo campos modificados)
            $yuju_data = $detector->prepareYujuUpdateData($product, $changes_info['changed_fields']);
            
            $logger->info('hookActionProductUpdate: Datos preparados para Yuju', [
                'yuju_data' => $yuju_data,
                'data_size' => count($yuju_data)
            ]);
            
            if (empty($yuju_data)) {
                $logger->warning('hookActionProductUpdate: Datos preparados están VACÍOS - DETENIDO', [
                    'changed_fields' => $changes_info['changed_fields']
                ]);
                return;
            }
            
            // Determinar si es prioridad alta (precio/stock) o usar cola
            $priority = $changes_info['priority'];
            $sync_queue = new YujuSyncQueue();
            
            if ($priority === 'high') {
                // PRECIO/STOCK: Sincronizar inmediatamente
                $logger->info('hookActionProductUpdate: PRIORIDAD ALTA - Sincronizando inmediatamente', [
                    'product_id' => $product_id,
                    'changed_fields' => $changes_info['changed_fields']
                ]);

                // Marcar como "actualizando" antes de la llamada a Yuju para que
                // la UI muestre el estado intermedio mientras esperamos la respuesta.
                Db::getInstance()->update(
                    'yuju_product_status',
                    [
                        'sync_status' => pSQL('updating_in_yuju'),
                        'last_error' => null,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'prestashop_product_id = ' . (int) $product_id
                );

                $start_time = microtime(true);
                $api_client = new YujuApiClient();
                $result = $api_client->updateProduct($yuju_product_id, $yuju_data);
                $sync_duration = microtime(true) - $start_time;
            } else {
                // OTROS CAMPOS: Agregar a cola para procesamiento por lotes
                $logger->info('hookActionProductUpdate: PRIORIDAD NORMAL - Agregando a cola', [
                    'product_id' => $product_id,
                    'changed_fields' => $changes_info['changed_fields']
                ]);
                
                $queued = $sync_queue->addToQueue($product_id, 'update', 'normal', array_merge(
                    is_array($yuju_data) ? $yuju_data : [],
                    ['origin' => 'auto_product']
                ));
                
                if ($queued) {
                    // Actualizar estado a queued
                    Db::getInstance()->update(
                        'yuju_product_status',
                        [
                            'sync_status' => pSQL('queued'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ],
                        'prestashop_product_id = ' . $product_id
                    );
                    
                    $logger->info('hookActionProductUpdate: Producto agregado a cola - COMPLETADO', [
                        'product_id' => $product_id,
                        'status' => 'queued'
                    ]);
                }
                
                // Salir sin guardar historial (se guardará al procesar la cola)
                return;
            }
            
            // Solo llega aquí si fue prioridad alta (sincronización inmediata)
            $start_time = $start_time ?? microtime(true);
            $sync_duration = $sync_duration ?? 0;
            
            // Guardar en historial
            $this->saveProductUpdateHistory(
                $product_id,
                $yuju_product_id,
                $changes_info,
                $yuju_data,
                $result,
                $sync_duration,
                'auto_product'
            );
            
            // Actualizar estado si hubo error
            if (!$result['success']) {
                $logger->error('hookActionProductUpdate: ERROR en Yuju - Actualizando estado', [
                    'product_id' => $product_id,
                    'error' => $result['message'] ?? 'Error desconocido'
                ]);
                
                Db::getInstance()->update(
                    'yuju_product_status',
                    [
                        'sync_status' => pSQL('synced_with_errors'),
                        'last_error' => pSQL($result['message'] ?? 'Error en actualización automática'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'prestashop_product_id = ' . $product_id
                );
            } else {
                $logger->info('hookActionProductUpdate: SUCCESS - Actualizando estado a synced', [
                    'product_id' => $product_id,
                    'yuju_product_id' => $yuju_product_id
                ]);
                
                // Guardar los datos sincronizados para futuras comparaciones
                $last_sync_data = json_encode($yuju_data);
                
                // Actualizar estado a synced y timestamp de última sincronización
                Db::getInstance()->update(
                    'yuju_product_status',
                    [
                        'sync_status' => pSQL('synced'),
                        'last_sync_at' => date('Y-m-d H:i:s'),
                        'last_sync_data' => pSQL($last_sync_data),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'prestashop_product_id = ' . $product_id
                );
            }
            
            $logger->info('=== hookActionProductUpdate COMPLETADO ===');
            
        } catch (Exception $e) {
            $logger->error('=== hookActionProductUpdate EXCEPTION ===', [
                'product_id' => $product_id ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
    /**
     * Guarda el historial de actualización automática
     */
    private function saveProductUpdateHistory($product_id, $yuju_product_id, $changes_info, $yuju_data, $result, $sync_duration, $origin = 'auto_stock')
    {
        require_once dirname(__FILE__) . '/classes/YujuProductManager.php';
        $pm = new YujuProductManager();
        $request_data = $pm->buildHistoryRequestPayload($yuju_data, [
            'origin' => $origin,
            'changed_fields' => $changes_info['changed_fields'] ?? array_keys((array) $yuju_data),
            'old_values' => $changes_info['old_values'] ?? null,
            'new_values' => $changes_info['new_values'] ?? null,
            'priority' => $changes_info['priority'] ?? 'high',
            'action' => 'update',
        ]);

        Db::getInstance()->insert('yuju_product_sync_history', [
            'prestashop_product_id' => (int) $product_id,
            'yuju_product_id' => pSQL($yuju_product_id),
            'sync_direction' => 'to_yuju',
            'action' => pSQL('update'),
            'status' => pSQL($result['success'] ? 'success' : 'error'),
            'http_status_code' => isset($result['http_code']) ? (int) $result['http_code'] : 0,
            'request_data' => pSQL($request_data, true),
            'response_data' => pSQL(json_encode($result), true),
            'error_message' => $result['success'] ? null : pSQL($result['message'] ?? 'Error desconocido'),
            'sync_duration' => $sync_duration,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => pSQL($origin),
        ]);
    }

    /**
     * Check if module is properly configured.
     */
    public function isConfigured()
    {
        // Verificar configuración básica
        $has_client_id = !empty(YujuConfig::get('YUJU_CLIENT_ID'));
        $has_client_secret = !empty(YujuConfig::get('YUJU_CLIENT_SECRET'));
        
        // Si no hay OAuth inicializado, solo verificar credenciales
        if (!isset($this->oauth) || !$this->oauth) {
            return $has_client_id && $has_client_secret;
        }
        
        // Si está inicializado, verificar también el token
        return $has_client_id && $has_client_secret && $this->oauth->hasValidToken();
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
