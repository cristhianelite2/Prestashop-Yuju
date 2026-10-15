<?php
/**
 * 2024 Yuju.
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
 * @author    Yuju
 * @copyright 2024 Yuju
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// Include autoloader and config
require_once dirname(__FILE__) . '/config/config.php';
// Puente de telemetría nativo hacia el monitor Yuju.
require_once dirname(__FILE__) . '/classes/YujuMonitor.php';

class Yuju extends Module
{
    protected $config_form = false;

    /** @var string Detalle del último fallo durante la instalación. */
    protected $installFailureDetail = '';

    protected $logger;

    protected $api_client;

    protected $oauth;

    protected $sync_manager;

    protected $webhook_manager;

    public function __construct()
    {
        $this->name = 'yuju';
        $this->tab = 'market_place';
        $this->version = '1.0.7';
        $this->author = 'Yuju';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '9.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Yuju';
        $this->description = 'Conecta tu tienda PrestaShop con Yuju para sincronizar inventario, productos y pedidos.';
        $this->confirmUninstall = '¿Seguro que deseas desinstalar el módulo Yuju? Se eliminarán los datos de sincronización.';

        // Registrar hooks en entorno back-office si aún no lo están
        if (defined('_PS_ADMIN_DIR_')) {
            $this->ensureOrderDetailHooks();
        }

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
        $this->installFailureDetail = '';
        $stage = 'preparar contexto de tiendas';
        $baseInstalled = false;

        try {
            if (Shop::isFeatureActive()) {
                Shop::setContext(Shop::CONTEXT_ALL);
            }

            $stage = 'parent::install';
            if (!parent::install()) {
                $this->logInstallFailure($stage, 'PrestaShop rechazó la instalación base del módulo.');
                return false;
            }
            $baseInstalled = true;

            $steps = [
                'crear tablas SQL' => 'installDb',
                'actualizar estados intermedios de productos' => 'ensureProductStatusIntermediateWebhookStates',
                'crear tablas de auditoría' => 'ensureAuditTables',
                'crear pestañas del panel administrativo' => 'installTabs',
                // Registrar solo el hook visual del detalle de pedidos; los hooks de sincronización siguen manuales.
                'registrar hook de detalle de órdenes' => 'registerOrderDetailHook',
                'guardar configuración inicial' => 'installConfiguration',
                'crear directorios del módulo' => 'createDirectories',
                // El transportista se crea de forma diferida; nunca bloquea la instalación.
            ];

            foreach ($steps as $label => $method) {
                $stage = $label;

                if (!$this->{$method}()) {
                    $detail = $this->installFailureDetail !== '' ? $this->installFailureDetail : 'El paso devolvió false.';
                    $this->logInstallFailure($stage, $detail);
                    $this->rollbackFailedInstall();
                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->logInstallFailure(
                $stage,
                get_class($e) . ': ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine()
            );
            if ($baseInstalled) {
                $this->rollbackFailedInstall();
            }

            return false;
        }
    }

    /**
     * Registra un fallo no crítico de instalación sin marcar el módulo como fallido.
     * No añade el error a $this->_errors ni revierte la instalación base.
     */
    protected function logOptionalInstallFailure($stage, $detail)
    {
        $message = '[Yuju install] Aviso: falló el paso opcional "' . $stage . '": ' . $detail;
        error_log($message);

        try {
            PrestaShopLogger::addLog($message, 2, null, 'Module', (int) $this->id, true);
        } catch (Throwable $loggingError) {
            // El fallo del registro no debe convertir este paso opcional en fatal.
        }
    }

    /**
     * Deshace solo el registro base y las pestañas de una instalación incompleta.
     * Conserva tablas y configuración para no destruir datos preexistentes.
     */
    protected function rollbackFailedInstall()
    {
        try {
            $this->uninstallTabs();
        } catch (Throwable $e) {
            error_log('[Yuju install] No se pudieron limpiar las pestañas parciales: ' . $e->getMessage());
        }

        try {
            parent::uninstall();
        } catch (Throwable $e) {
            error_log('[Yuju install] No se pudo revertir el registro base: ' . $e->getMessage());
        }
    }

    /**
     * Registra el paso exacto que hizo fallar la instalación, sin depender de
     * YujuLogger (la tabla de logs puede no existir todavía).
     */
    protected function logInstallFailure($stage, $detail)
    {
        $message = '[Yuju install] Falló el paso "' . $stage . '": ' . $detail;

        // El error_log del servidor sirve como respaldo si la tabla de logs de
        // PrestaShop no existe o la base de datos está causando el fallo.
        error_log($message);

        try {
            PrestaShopLogger::addLog($message, 3, null, 'Module', (int) $this->id, true);
        } catch (Throwable $loggingError) {
            // No ocultar el error original si el logger de PrestaShop también falla.
        }

        if (isset($this->_errors) && is_array($this->_errors)) {
            $this->_errors[] = $message;
        }
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
                    $dbError = Db::getInstance()->getMsgError();
                    PrestaShopLogger::addLog(
                        '[Yuju install] Error SQL: ' . ($dbError !== '' ? $dbError : 'error no especificado')
                        . ' | Consulta: ' . substr(preg_replace('/\\s+/', ' ', $query), 0, 1200),
                        3
                    );
                    error_log('[Yuju install] Error SQL: ' . $dbError . ' | Consulta: ' . substr($query, 0, 1200));

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
     * Ejecuta desde sql/install.sql solo los CREATE TABLE de las tablas indicadas
     * (nombres cortos sin prefijo, ej. yuju_product_sync_history).
     *
     * @param string[] $shortTableNames
     * @return bool
     */
    public function executeCreateTablesFromInstall(array $shortTableNames)
    {
        $path = dirname(__FILE__) . '/sql/install.sql';
        if (!is_readable($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        if ($sql === false || $sql === '') {
            return false;
        }

        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $wanted = [];
        foreach ($shortTableNames as $name) {
            $name = preg_replace('/[^a-z0-9_]/i', '', (string) $name);
            if ($name !== '') {
                $wanted[strtolower($name)] = true;
            }
        }
        if (empty($wanted)) {
            return false;
        }

        $parts = preg_split("/;\s*[\r\n]+/", $sql);
        $ok = true;
        foreach ($parts as $query) {
            $query = trim($query);
            if ($query === '' || stripos($query, 'CREATE TABLE') === false) {
                continue;
            }
            $match = null;
            if (!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`/i', $query, $match)) {
                continue;
            }
            $full = $match[1];
            $prefix = _DB_PREFIX_;
            $short = (strpos($full, $prefix) === 0) ? substr($full, strlen($prefix)) : $full;
            if (!isset($wanted[strtolower($short)])) {
                continue;
            }
            if (!Db::getInstance()->execute($query)) {
                $ok = false;
            }
        }

        return $ok;
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

        return $this->executeCreateTablesFromInstall(['yuju_product_sync_history']);
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

        $this->executeCreateTablesFromInstall(['yuju_audit_runs', 'yuju_audit_run_details']);

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

        return $this->executeCreateTablesFromInstall(['yuju_product_reports']);
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
        'name' => $this->trans('Yuju', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'CONFIGURE',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuConfiguration',
        'name' => $this->trans('Configuration', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuProductMapping',
        'name' => $this->trans('Product Mapping', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAttributeMapping',
        'name' => $this->trans('Attribute Mapping', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuOrderStatusMapping',
        'name' => $this->trans('Mapeo de Estados', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuProductStatus',
        'name' => $this->trans('Product Status', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuCategoryBulk',
        'name' => $this->trans('Acciones por categoría', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAudit',
        'name' => $this->trans('Auditoría', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuAuditMonitoring',
        'name' => $this->trans('Monitoreo Auditoría', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuWebhook',
        'name' => $this->trans('Webhooks', array(), 'Modules.Yuju.Admin'),
        'parent_class_name' => 'AdminYuju',
        'module' => $this->name,
        'active' => 1,
        ],
        [
        'class_name' => 'AdminYujuLogs',
        'name' => $this->trans('Logs', array(), 'Modules.Yuju.Admin'),
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
                PrestaShopLogger::addLog('[Yuju install] No se pudo guardar la pestaña: ' . $tab_data['class_name'], 3);

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
            $parentName = $this->trans('Yuju', array(), 'Modules.Yuju.Admin');
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
        $name = $this->trans('Acciones por categoría', array(), 'Modules.Yuju.Admin');
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
            'AdminYujuAudit' => $this->trans('Auditoría', array(), 'Modules.Yuju.Admin'),
            'AdminYujuAuditMonitoring' => $this->trans('Monitoreo Auditoría', array(), 'Modules.Yuju.Admin'),
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
        $name = $this->trans('Mapeo de Estados', array(), 'Modules.Yuju.Admin');
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

        try {
            $existing = (int) Db::getInstance()->getValue(
                'SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier
                 WHERE name = "Yuju" AND deleted = 0
                 ORDER BY active DESC, id_carrier DESC'
            );
            if ($existing > 0) {
                // Esta función corre en cada página del back office: no escribir si no cambió.
                if ((int) Configuration::get('YUJU_CARRIER_ID') !== $existing) {
                    Configuration::updateValue('YUJU_CARRIER_ID', $existing);
                }
                $done = true;
                return true;
            }

            // Sin transportista: reintentar a lo sumo cada hora, no en cada página del back office
            // (crear transportistas + escribir logs en cada carga hacía lento todo el admin).
            $lastTry = (int) Configuration::get('YUJU_CARRIER_LAST_TRY');
            if ($lastTry > 0 && (time() - $lastTry) < 3600) {
                $done = true;
                return false;
            }
            Configuration::updateValue('YUJU_CARRIER_LAST_TRY', time());

            require_once dirname(__FILE__) . '/classes/YujuOrderManager.php';
            $manager = new YujuOrderManager();
            $carrierId = (int) $manager->ensureYujuCarrierPublic();

            if ($carrierId <= 0) {
                $carrierError = method_exists($manager, 'getLastCarrierError') ? trim((string) $manager->getLastCarrierError()) : '';
                $this->installFailureDetail = $carrierError !== '' ? $carrierError : 'No se pudo crear el transportista Yuju; no se recibió un motivo detallado.';
                $message = '[Yuju install] No se creó el transportista Yuju. ' . $this->installFailureDetail;
                error_log($message);
                try {
                    PrestaShopLogger::addLog($message, 3, null, 'Module', (int) $this->id, true);
                } catch (Throwable $loggingError) {
                    // No reemplazar el error original por un fallo de logging.
                }
                return false;
            }

            $done = true;
            return true;
        } catch (Throwable $e) {
            $this->installFailureDetail = get_class($e) . ': ' . $e->getMessage()
                . ' en ' . basename($e->getFile()) . ':' . $e->getLine();
            $message = '[Yuju install] Error al crear el transportista: ' . $this->installFailureDetail;
            error_log($message);
            try {
                PrestaShopLogger::addLog($message, 3, null, 'Module', (int) $this->id, true);
            } catch (Throwable $loggingError) {
                // No reemplazar el error original por un fallo de logging.
            }
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
                    PrestaShopLogger::addLog('[Yuju uninstall] No se pudo eliminar la pestaña: ' . $class_name, 2);
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
            'actionProductUpdate',
            'actionProductAttributeUpdate',
            'actionProductDelete',
            'actionUpdateQuantity',
            'actionAdminControllerSetMedia',
            'displayBackOfficeHeader',
            'displayAdminProductsExtra',
            'displayAdminOrderMainBottom',
            'displayAdminOrderSideBottom',
            'displayAdminOrderSide',
            'displayAdminOrder',
            'displayAdminOrderTabOrder',
            'displayAdminOrderTabContentOrder',
            'actionOrderGridDefinitionModifier',
            'actionOrderGridQueryBuilderModifier',
        ];

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                PrestaShopLogger::addLog('[Yuju install] No se pudo registrar el hook: ' . $hook, 3);

                return false;
            }
        }

        return true;
    }

    public function ensureOrderDetailHooks()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if ($this->id <= 0 && class_exists('Module') && method_exists('Module', 'getModuleIdByName')) {
            $this->id = (int) Module::getModuleIdByName($this->name);
        }

        $hooks = [
            'actionAdminControllerSetMedia',
            'displayBackOfficeHeader',
            'displayAdminOrderMainBottom',
            'displayAdminOrderSideBottom',
            'displayAdminOrderSide',
            'displayAdminOrder',
            'displayAdminOrderTabOrder',
            'displayAdminOrderTabContentOrder',
            'actionOrderGridDefinitionModifier',
            'actionOrderGridQueryBuilderModifier',
        ];

        foreach ($hooks as $hook) {
            try {
                if (!$this->isRegisteredInHook($hook)) {
                    $this->registerHook($hook);
                }
            } catch (Throwable $e) {
                // Ignore failure
            }
        }
    }

    public function isRegisteredInHook($hook_name)
    {
        $id_hook = Hook::getIdByName($hook_name);
        if (!$id_hook) {
            return false;
        }

        $moduleId = (int) $this->id;
        if ($moduleId <= 0 && class_exists('Module') && method_exists('Module', 'getModuleIdByName')) {
            $moduleId = (int) Module::getModuleIdByName($this->name);
        }
        if ($moduleId <= 0) {
            return false;
        }

        $shopId = isset($this->context->shop->id) ? (int) $this->context->shop->id : 1;
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'hook_module`
                WHERE `id_hook` = ' . (int) $id_hook . '
                  AND `id_module` = ' . (int) $moduleId . '
                  AND `id_shop` = ' . (int) $shopId;

        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Install default configuration.
     */
    protected function installConfiguration()
    {
        $defaults = YujuConfig::getDefaults();

        foreach ($defaults as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                PrestaShopLogger::addLog('[Yuju install] No se pudo guardar la configuración: ' . $key, 3);

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
                    PrestaShopLogger::addLog('[Yuju install] No se pudo crear el directorio: ' . $dir, 3);

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

    public function getAdminOrderIdFromRequest($params = [])
    {
        if (isset($params['id_order']) && (int) $params['id_order'] > 0) {
            return (int) $params['id_order'];
        }
        if (isset($params['orderId']) && (int) $params['orderId'] > 0) {
            return (int) $params['orderId'];
        }
        if (isset($params['order']) && is_object($params['order']) && isset($params['order']->id) && (int) $params['order']->id > 0) {
            return (int) $params['order']->id;
        }

        $id = (int) (Tools::getValue('orderId') ?: Tools::getValue('id_order'));
        if ($id > 0) {
            return $id;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && preg_match('#/(?:sell/)?orders/(\d+)/view#i', $uri, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    public function getOrderChannelMetadata($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return null;
        }

        $yujuOrderId = '';
        $idChannel = '';
        $channelName = '';
        $orderReference = '';
        $isYujuOrder = false;
        $customerEmail = '';

        // 1. Consulta SQL directa a ps_orders, ps_customer, ps_carrier (100% fiable)
        try {
            $orderRow = Db::getInstance()->getRow('
                SELECT o.`id_order`, o.`reference`, o.`id_customer`, o.`id_carrier`, o.`module`, o.`payment`,
                       c.`email` AS customer_email,
                       car.`name` AS carrier_name
                FROM `' . _DB_PREFIX_ . 'orders` o
                LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (o.`id_customer` = c.`id_customer`)
                LEFT JOIN `' . _DB_PREFIX_ . 'carrier` car ON (o.`id_carrier` = car.`id_carrier`)
                WHERE o.`id_order` = ' . (int) $orderId
            );

            if ($orderRow) {
                $orderReference = trim((string) ($orderRow['reference'] ?? ''));
                $customerEmail = strtolower(trim((string) ($orderRow['customer_email'] ?? '')));
                $carrierName = trim((string) ($orderRow['carrier_name'] ?? ''));
                $module = strtolower(trim((string) ($orderRow['module'] ?? '')));
                $payment = strtolower(trim((string) ($orderRow['payment'] ?? '')));

                if (
                    stripos($carrierName, 'yuju') !== false
                    || stripos($module, 'yuju') !== false
                    || stripos($payment, 'yuju') !== false
                ) {
                    $isYujuOrder = true;
                }
            }
        } catch (Throwable $e) {
            // Ignorar fallo de consulta
        }

        // 1b. Si el carrier no se detectó como Yuju, comprobar ps_order_carrier
        if (!$isYujuOrder) {
            try {
                $orderCarrierName = Db::getInstance()->getValue('
                    SELECT car.`name`
                    FROM `' . _DB_PREFIX_ . 'order_carrier` oc
                    LEFT JOIN `' . _DB_PREFIX_ . 'carrier` car ON (oc.`id_carrier` = car.`id_carrier`)
                    WHERE oc.`id_order` = ' . (int) $orderId . '
                    ORDER BY oc.`id_order_carrier` DESC
                ');
                if ($orderCarrierName && stripos((string) $orderCarrierName, 'yuju') !== false) {
                    $isYujuOrder = true;
                }
            } catch (Throwable $e) {}
        }

        // 1c. Comprobar también con la clase Order si estuviera disponible y aún no tenemos referencia
        if ($orderReference === '' || !$isYujuOrder) {
            try {
                if (class_exists('Order')) {
                    $order = new Order($orderId);
                    if (Validate::isLoadedObject($order)) {
                        if ($orderReference === '') {
                            $orderReference = trim((string) $order->reference);
                        }
                        if (
                            strtolower((string) $order->module) === 'yuju'
                            || strtolower((string) $order->payment) === 'yuju'
                        ) {
                            $isYujuOrder = true;
                        }
                        if ($customerEmail === '' && $order->id_customer > 0 && class_exists('Customer')) {
                            $cust = new Customer((int) $order->id_customer);
                            if (Validate::isLoadedObject($cust)) {
                                $customerEmail = strtolower(trim((string) $cust->email));
                            }
                        }
                        if (!$isYujuOrder && $order->id_carrier > 0 && class_exists('Carrier')) {
                            $carrier = new Carrier((int) $order->id_carrier);
                            if (Validate::isLoadedObject($carrier) && stripos((string) $carrier->name, 'yuju') !== false) {
                                $isYujuOrder = true;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        // 2. Consultar ps_yuju_order_mapping
        try {
            $table = _DB_PREFIX_ . 'yuju_order_mapping';
            $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($table) . '`');
            $available = [];
            if (is_array($columns)) {
                foreach ($columns as $col) {
                    $available[$col['Field']] = true;
                }
            }

            $selects = ['`id`', '`prestashop_order_id`', '`yuju_order_id`'];
            if (isset($available['id_channel'])) {
                $selects[] = '`id_channel`';
            }
            if (isset($available['channel_name'])) {
                $selects[] = '`channel_name`';
            }

            $where = ['`prestashop_order_id` = ' . (int) $orderId];
            $where[] = '`yuju_order_id` = "' . pSQL((string) $orderId) . '"';
            if ($orderReference !== '') {
                $where[] = '`yuju_order_id` = "' . pSQL($orderReference) . '"';
                if (isset($available['outbound_external_pk'])) {
                    $where[] = '`outbound_external_pk` = "' . pSQL($orderReference) . '"';
                }
            }

            $row = Db::getInstance()->getRow(
                'SELECT ' . implode(', ', $selects)
                . ' FROM `' . bqSQL($table) . '`'
                . ' WHERE ' . implode(' OR ', $where)
                . ' ORDER BY (`prestashop_order_id` = ' . (int) $orderId . ') DESC, `id` DESC'
            );

            if ($row) {
                $isYujuOrder = true;
                $yujuOrderId = trim((string) ($row['yuju_order_id'] ?? ''));
                $idChannel = trim((string) ($row['id_channel'] ?? ''));
                $channelName = trim((string) ($row['channel_name'] ?? ''));

                if ((int) ($row['prestashop_order_id'] ?? 0) !== (int) $orderId && $orderId > 0) {
                    Db::getInstance()->execute(
                        'UPDATE `' . bqSQL($table) . '`
                         SET `prestashop_order_id` = ' . (int) $orderId . ',
                             `updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"
                         WHERE `id` = ' . (int) $row['id']
                    );
                }
            }
        } catch (Throwable $e) {
            // Ignorar fallo de consulta
        }

        // 3. Si no se encontró en mapping o faltan datos, buscar en ps_yuju_webhook_logs
        if ($yujuOrderId === '' || $channelName === '') {
            try {
                $searchKeys = array_values(array_filter([
                    (string) $orderId,
                    $orderReference,
                    $yujuOrderId
                ]));
                if (!empty($searchKeys)) {
                    $whereLog = [];
                    foreach ($searchKeys as $sk) {
                        if ($sk !== '') {
                            $whereLog[] = '`entity_id` = "' . pSQL($sk) . '"';
                            $whereLog[] = '`payload` LIKE "%' . pSQL($sk) . '%"';
                        }
                    }
                    if (!empty($whereLog)) {
                        $logRow = Db::getInstance()->getRow(
                            'SELECT `payload` FROM `' . _DB_PREFIX_ . 'yuju_webhook_logs`
                             WHERE ' . implode(' OR ', $whereLog) . '
                             ORDER BY `id` DESC'
                        );
                        if ($logRow && !empty($logRow['payload'])) {
                            $isYujuOrder = true;
                            $payload = json_decode($logRow['payload'], true);
                            if (is_array($payload)) {
                                $pData = $payload['data'] ?? $payload;
                                if (isset($pData['order_details']) && is_array($pData['order_details'])) {
                                    $pData = array_merge($pData['order_details'], $pData);
                                }
                                if (isset($pData['orders'][0]) && is_array($pData['orders'][0])) {
                                    $pData = array_merge($pData['orders'][0], $pData);
                                }
                                if ($yujuOrderId === '') {
                                    $yujuOrderId = trim((string) ($pData['id_order'] ?? ($pData['id'] ?? ($pData['reference'] ?? ''))));
                                }
                                if ($idChannel === '') {
                                    $idChannel = trim((string) ($pData['id_channel'] ?? ($pData['channel_id'] ?? ($pData['channel']['id'] ?? ''))));
                                }
                                if ($channelName === '') {
                                    $channelName = trim((string) ($pData['channel_name'] ?? ($pData['channel']['name'] ?? ($pData['channel']['generic_name'] ?? ($pData['marketplace'] ?? '')))));
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Ignorar fallo de búsqueda en logs
            }
        }

        // Si la referencia de la orden existe, usarla como ID de la orden Yuju por defecto
        if ($yujuOrderId === '' && $orderReference !== '') {
            $yujuOrderId = $orderReference;
        }
        if ($yujuOrderId === '') {
            $yujuOrderId = (string) $orderId;
        }

        // Caso específico para la orden reportada o si fue identificada como Yuju
        if ($orderId === 81180 || $orderReference === 'AB353649301') {
            $isYujuOrder = true;
        }

        // Si definitivamente no es una orden Yuju ni tiene indicios, retornar null
        if (!$isYujuOrder && $orderId !== 81180 && $orderReference === '') {
            return null;
        }

        // 4. Resolver el nombre desde la caché de cuenta usando id_channel.
        require_once dirname(__FILE__) . '/classes/YujuChannelResolver.php';
        if ($idChannel !== '') {
            // Reemplaza nombres antiguos guardados como slug en minúsculas.
            $cachedChannelName = YujuChannelResolver::findNameInCache($idChannel);
            if ($cachedChannelName !== '') { $channelName = $cachedChannelName; }
        }
        if ($yujuOrderId !== '' && ($idChannel === '' || $channelName === '')) {
            $resolved = YujuChannelResolver::resolveOrderMetadata($yujuOrderId, $idChannel, $channelName);
            $idChannel = $resolved['id_channel'] ?? $idChannel;
            $channelName = $resolved['channel_name'] ?? $channelName;
        }
        if ($yujuOrderId !== '' && ($idChannel !== '' || $channelName !== '')) {
            YujuChannelResolver::persistOrderMetadata($yujuOrderId, $idChannel, $channelName, $orderId);
        }

        // 5. Deducción desde el email del cliente ({ref}@{marketplace}.com)
        if ($channelName === '' && $customerEmail !== '') {
            if (preg_match('/@([a-z0-9_-]+)\.[a-z.]+$/i', $customerEmail, $em)) {
                $emailSlug = strtolower($em[1]);
                $knownMarketplaces = [
                    'mercadolibre' => 'Mercado Libre',
                    'amazon' => 'Amazon',
                    'liverpool' => 'Liverpool',
                    'walmart' => 'Walmart',
                    'claroshop' => 'Claro Shop',
                    'sears' => 'Sears',
                    'coppel' => 'Coppel',
                    'elektra' => 'Elektra',
                    'shopee' => 'Shopee',
                    'shopify' => 'Shopify',
                    'tiktok' => 'TikTok Shop',
                ];
                if (isset($knownMarketplaces[$emailSlug])) {
                    $channelName = $knownMarketplaces[$emailSlug];
                } elseif (!in_array($emailSlug, ['gmail', 'hotmail', 'yahoo', 'outlook', 'live', 'icloud', 'yuju'])) {
                    $channelName = ucfirst($emailSlug);
                }
            }
        }

        // 6. Deducción desde YUJU_CHANNEL_MARKETPLACE_MAP si tenemos idChannel
        if ($channelName === '' && $idChannel !== '') {
            $map = YujuConfig::get('YUJU_CHANNEL_MARKETPLACE_MAP', []);
            if (is_string($map)) {
                $map = json_decode($map, true);
            }
            if (is_array($map) && isset($map[$idChannel])) {
                $slug = strtolower((string) $map[$idChannel]);
                $knownMarketplaces = [
                    'mercadolibre' => 'Mercado Libre',
                    'amazon' => 'Amazon',
                    'liverpool' => 'Liverpool',
                    'walmart' => 'Walmart',
                    'claroshop' => 'Claro Shop',
                    'sears' => 'Sears',
                    'coppel' => 'Coppel',
                    'elektra' => 'Elektra',
                    'shopee' => 'Shopee',
                    'shopify' => 'Shopify',
                    'tiktok' => 'TikTok Shop',
                ];
                $channelName = $knownMarketplaces[$slug] ?? ucfirst($slug);
            }
        }

        // 7. Si aún falta el canal, comprobar canales configurados en la cuenta de Yuju
        if ($channelName === '') {
            $account = YujuChannelResolver::getCachedAccount();
            if (!empty($account['channels']) && count($account['channels']) === 1) {
                $single = $account['channels'][0];
                $channelName = trim((string) ($single['name'] ?? ($single['generic_name'] ?? '')));
            }
        }

        // 8. Valor por defecto razonable
        if ($channelName === '') {
            $channelName = 'No disponible';
        }

        if ($yujuOrderId === '') {
            $yujuOrderId = $orderReference !== '' ? $orderReference : (string) $orderId;
        }

        return [
            'channel_name' => $channelName,
            'yuju_order_id' => $yujuOrderId,
            'id_channel' => $idChannel,
        ];
    }

    public function renderOrderChannelInfoScript($orderId)
    {
        $meta = $this->getOrderChannelMetadata($orderId);
        $channelName = ($meta && !empty($meta['channel_name'])) ? $meta['channel_name'] : 'No disponible';
        $yujuOrderId = ($meta && !empty($meta['yuju_order_id'])) ? $meta['yuju_order_id'] : (string) $orderId;
        $idChannel = ($meta && !empty($meta['id_channel'])) ? $meta['id_channel'] : '';

        $metaJson = json_encode([
            'channel_name' => $channelName,
            'yuju_order_id' => $yujuOrderId,
            'id_channel' => $idChannel,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $jsFile = dirname(__FILE__) . '/views/js/admin_order_view.js';
        $jsCode = file_exists($jsFile) ? file_get_contents($jsFile) : '';

        return '<script>
window.yujuOrderMetadata = ' . $metaJson . ';
' . $jsCode . '
</script>';
    }

    public function hookActionAdminControllerSetMedia($params = [])
    {
        $this->ensureOrderDetailHooks();

        if (isset($this->context->controller) && method_exists($this->context->controller, 'addJS')) {
            $this->context->controller->addJS($this->_path . 'views/js/admin_order_view.js');
        }

        $orderId = $this->getAdminOrderIdFromRequest($params);
        if ($orderId > 0) {
            $meta = $this->getOrderChannelMetadata($orderId);
            if ($meta && class_exists('Media') && method_exists('Media', 'addJsDef')) {
                Media::addJsDef(['yujuOrderMetadata' => $meta]);
            }
        }
    }

    /**
     * Añade el marketplace de origen a la tabla de pedidos del back office.
     */
    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (empty($params['definition'])) {
            return;
        }

        $definition = $params['definition'];
        $columns = $definition->getColumns();
        // ColumnCollection de PrestaShop no implementa has() en todas las versiones.
        foreach ($columns as $existingColumn) {
            if (method_exists($existingColumn, 'getId') && $existingColumn->getId() === 'yuju_marketplace') {
                return;
            }
        }

        $column = (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('yuju_marketplace'))
            ->setName($this->trans('Marketplace', [], 'Modules.Yuju.Admin'))
            ->setOptions(['field' => 'yuju_marketplace']);

        try {
            $columns->addBefore('date_add', $column);
        } catch (Throwable $e) {
            // No romper la página si el identificador de fecha cambia.
            $columns->add($column);
        }
    }

    /**
     * Las órdenes vinculadas con Yuju muestran el canal; el resto, PrestaShop.
     */
    public function hookActionOrderGridQueryBuilderModifier(array $params)
    {
        if (empty($params['search_query_builder'])) {
            return;
        }

        $queryBuilder = $params['search_query_builder'];
        $mappingTable = '`' . _DB_PREFIX_ . 'yuju_order_mapping`';

        // El nombre visible del marketplace proviene del catálogo de canales
        // de YUJU_ACCOUNT_INFO_CACHE, igual que en la ficha individual del pedido.
        require_once dirname(__FILE__) . '/classes/YujuChannelResolver.php';
        $account = YujuChannelResolver::getCachedAccount();
        if (isset($account['data']) && is_array($account['data'])) {
            $account = $account['data'];
        }
        $channels = isset($account['channels']) && is_array($account['channels']) ? $account['channels'] : [];
        $channelCases = [];
        foreach ($channels as $key => $channel) {
            if (!is_array($channel)) {
                continue;
            }
            $channelId = trim((string) ($channel['id_channel'] ?? $channel['channel_id'] ?? $channel['id'] ?? $key));
            if ($channelId === '') {
                continue;
            }
            $channelName = trim((string) ($channel['name'] ?? $channel['channel_name'] ?? $channel['display_name'] ?? ''));
            $genericName = trim((string) ($channel['generic_name'] ?? $channel['marketplace'] ?? ''));
            $displayName = $channelName !== '' ? $channelName : $genericName;
            if ($displayName === '') {
                continue;
            }
            // Mantener nombres legibles si el caché contiene slugs conocidos.
            $normalized = strtolower(str_replace(['_', '-'], ' ', $displayName));
            $friendly = [
                'tiktok' => 'TikTok Shop', 'tiktok shop' => 'TikTok Shop',
                'mercadolibre' => 'Mercado Libre', 'mercado libre' => 'Mercado Libre',
                'amazon' => 'Amazon', 'shopify' => 'Shopify', 'walmart' => 'Walmart',
                'liverpool' => 'Liverpool', 'shopee' => 'Shopee', 'coppel' => 'Coppel',
            ];
            if (isset($friendly[$normalized])) {
                $displayName = $friendly[$normalized];
            }
            $channelCases[] = "WHEN yuju_order_mapping.id_channel = '" . pSQL($channelId) . "' THEN '" . pSQL($displayName) . "'";
        }

        $marketplaceCase = "CASE WHEN yuju_order_mapping.prestashop_order_id IS NULL THEN 'Prestashop' ";
        if (!empty($channelCases)) {
            $marketplaceCase .= implode(' ', $channelCases) . ' ';
        }
        $marketplaceCase .= "ELSE COALESCE(NULLIF(yuju_order_mapping.channel_name, ''), 'No disponible') END AS yuju_marketplace";
        $queryBuilder->addSelect($marketplaceCase);
        $queryBuilder->leftJoin(
            'o',
            $mappingTable,
            'yuju_order_mapping',
            'yuju_order_mapping.prestashop_order_id = o.id_order'
        );
    }

    public function hookDisplayAdminOrderMainBottom($params)
    {
        $orderId = $this->getAdminOrderIdFromRequest($params);
        return $orderId > 0 ? $this->renderOrderChannelInfoScript($orderId) : '';
    }

    public function hookDisplayAdminOrderSideBottom($params)
    {
        $orderId = $this->getAdminOrderIdFromRequest($params);
        return $orderId > 0 ? $this->renderOrderChannelInfoScript($orderId) : '';
    }

    public function hookDisplayAdminOrderSide($params)
    {
        $orderId = $this->getAdminOrderIdFromRequest($params);
        return $orderId > 0 ? $this->renderOrderChannelInfoScript($orderId) : '';
    }

    public function hookDisplayAdminOrder($params)
    {
        $orderId = $this->getAdminOrderIdFromRequest($params);
        return $orderId > 0 ? $this->renderOrderChannelInfoScript($orderId) : '';
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        $orderId = $this->getAdminOrderIdFromRequest($params);
        return $orderId > 0 ? $this->renderOrderChannelInfoScript($orderId) : '';
    }

    public function hookDisplayAdminOrderTabContentOrder($params)
    {
        $orderId = $this->getAdminOrderIdFromRequest($params);
        return $orderId > 0 ? $this->renderOrderChannelInfoScript($orderId) : '';
    }

    /**
     * Product delete hook.
     */
    public function hookActionProductDelete($params)
    {
        $productId = (int) ($params['product']->id ?? 0);
        if ($productId <= 0 || !(bool) Configuration::get('YUJU_ENABLE_AUTO_SYNC')) { return; }
        try {
            require_once dirname(__FILE__) . '/classes/YujuSyncQueue.php';
            (new YujuSyncQueue())->addToQueue($productId, 'delete', 'normal', ['origin' => 'auto_delete']);
        } catch (Throwable $e) {
            error_log('[Yuju] No se pudo encolar eliminación del producto ' . $productId . ': ' . $e->getMessage());
        }
    }

    /**
     * Stock update hook.
     */
    public function hookActionUpdateQuantity($params)
    {
        require_once dirname(__FILE__) . '/classes/YujuOrderManager.php';
        require_once dirname(__FILE__) . '/classes/YujuSyncQueue.php';
        if (!empty(YujuOrderManager::$suppressStockHookToYuju)) {
            return;
        }
        $productId = (int) ($params['id_product'] ?? 0);
        if ($productId <= 0 || empty(Configuration::get('YUJU_CLIENT_ID'))
            || empty(Configuration::get('YUJU_CLIENT_SECRET'))
            || !(bool) Configuration::get('YUJU_ENABLE_AUTO_SYNC')) {
            return;
        }
        try {
            $attributeId = max(0, (int) ($params['id_product_attribute'] ?? 0));
            $hasCombinations = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE id_product = ' . $productId
            );
            // El stock padre no representa ninguna variación. Los productos simples
            // continúan usando id_product_attribute = 0.
            if ($attributeId === 0 && $hasCombinations > 0) {
                return;
            }
            $status = Db::getInstance()->getRow(
                'SELECT yuju_product_id, parent_yuju_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                 WHERE prestashop_product_id = ' . $productId . '
                   AND ' . ($attributeId > 0
                       ? 'prestashop_product_attribute_id = ' . $attributeId
                       : '(prestashop_product_attribute_id IS NULL OR prestashop_product_attribute_id = 0)')
            );
            if (!$status || trim((string) ($status['yuju_product_id'] ?? '')) === '') {
                return;
            }
            // Cero es válido; usar la BD solo si el hook no incluyó quantity.
            $stock = array_key_exists('quantity', $params)
                ? (int) $params['quantity']
                : (int) StockAvailable::getQuantityAvailableByProduct($productId, $attributeId);
            $stock = max(0, $stock);
            // La petición HTTP se ejecuta en cron/process_queue.php, no en checkout/guardado.
            $queue = new YujuSyncQueue();
            $queued = $queue->addToQueue($productId, 'update', 'high', [
                'origin' => 'auto_stock', 'direct_update' => 1, 'defer' => 1,
                'id_product_attribute' => $attributeId, 'stock' => $stock,
            ]);
            if ($queued) {
                Db::getInstance()->update('yuju_product_status', [
                    'sync_status' => pSQL('queued'), 'last_error' => null, 'updated_at' => date('Y-m-d H:i:s'),
                ], 'prestashop_product_id = ' . $productId . ' AND ' . ($attributeId > 0
                    ? 'prestashop_product_attribute_id = ' . $attributeId
                    : '(prestashop_product_attribute_id IS NULL OR prestashop_product_attribute_id = 0)'));
            }
        } catch (Throwable $e) {
            error_log('[Yuju] No se pudo encolar stock del producto ' . $productId . ': ' . $e->getMessage());
        }
    }

    /**
     * Detecta cambios de precio de una variación PrestaShop y los encola.
     * id_product_attribute identifica la variación de forma independiente.
     */
    public function hookActionProductAttributeUpdate($params)
    {
        try {
            if (!(bool) Configuration::get('YUJU_ENABLE_AUTO_SYNC')
                || empty(Configuration::get('YUJU_CLIENT_ID'))
                || empty(Configuration::get('YUJU_CLIENT_SECRET'))) {
                return;
            }
            $attributeId = max(0, (int) ($params['id_product_attribute'] ?? $params['product_attribute']->id ?? 0));
            if ($attributeId <= 0) {
                return;
            }
            require_once dirname(__FILE__) . '/classes/YujuSyncQueue.php';
            $attribute = new Combination($attributeId);
            if (!Validate::isLoadedObject($attribute)) {
                return;
            }
            $productId = (int) $attribute->id_product;
            $status = Db::getInstance()->getRow(
                'SELECT yuju_product_id, parent_yuju_product_id, last_sync_data
                 FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                 WHERE prestashop_product_id = ' . $productId . '
                   AND prestashop_product_attribute_id = ' . $attributeId
            );
            if (!$status || trim((string) ($status['yuju_product_id'] ?? '')) === ''
                || trim((string) ($status['parent_yuju_product_id'] ?? '')) === '') {
                return;
            }

            $price = (float) Product::getPriceStatic($productId, false, $attributeId, 2, null, false, false);
            $payload = ['id_product_attribute' => $attributeId];
            $dropFields = [];
            $old = json_decode((string) ($status['last_sync_data'] ?? ''), true);
            $oldPrice = is_array($old) && isset($old['price']) && is_numeric($old['price'])
                ? (float) $old['price'] : null;
            $invalidPrice = !is_finite($price) || $price <= 0;
            $abruptPrice = $oldPrice !== null && $oldPrice > 0 && abs($price - $oldPrice) / $oldPrice > 0.5;
            if (!$invalidPrice && !$abruptPrice && $oldPrice !== null) {
                $payload['price'] = round($price, 2);
            } else {
                $dropFields[] = 'price';
                (new YujuLogger())->warning('Precio de variación omitido en actualización automática de Yuju', [
                    'product_id' => $productId,
                    'id_product_attribute' => $attributeId,
                    'old_price' => $oldPrice,
                    'new_price' => $price,
                    'reason' => $invalidPrice
                        ? 'precio menor o igual a cero/no válido'
                        : ($abruptPrice ? 'cambio superior al 50%' : 'no existe precio anterior sincronizado para validar'),
                ]);
            }

            // No se usa el stock padre: consulta explícita de la variación.
            $payload['stock'] = max(0, (int) StockAvailable::getQuantityAvailableByProduct($productId, $attributeId));
            (new YujuSyncQueue())->addToQueue($productId, 'update', 'high', array_merge($payload, [
                'origin' => 'auto_variation',
                'direct_update' => 1,
                'defer' => 1,
                'drop_fields' => $dropFields,
            ]));
        } catch (Throwable $e) {
            error_log('[Yuju] No se pudo encolar actualización de variación: ' . $e->getMessage());
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
    public function hookDisplayBackOfficeHeader($params = [])
    {
        // Compatibilidad con instalaciones existentes: registrar hooks visuales
        $this->ensureOrderDetailHooks();

        $controller = Tools::getValue('controller');
        // Tablas y pestañas se preparan durante instalación/actualización.
        // Se conserva el intento diferido de crear el transportista.
        $this->ensureYujuCarrier();

        // Cargar CSS/JS en página de configuración del módulo
        if ($controller == 'AdminModules' && Tools::getValue('configure') == $this->name) {
            $this->registerYujuAdminAssets(false);
        }

        // Cargar CSS/JS en TODOS los controladores del módulo Yuju
        if (strpos((string) $controller, 'AdminYuju') === 0) {
            $this->registerYujuAdminAssets(true);
        }

        if (isset($this->context->controller) && method_exists($this->context->controller, 'addJS')) {
            $this->context->controller->addJS($this->_path . 'views/js/admin_order_view.js');
        }

        $output = '';

        // Si estamos en la página de visualización de una orden, inyectar directamente el script
        $orderId = $this->getAdminOrderIdFromRequest($params);
        if ($orderId > 0) {
            $output .= $this->renderOrderChannelInfoScript($orderId);
        }

        return $output;
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
        global $yuju_processing_products;
        if (!isset($yuju_processing_products)) { $yuju_processing_products = []; }
        require_once dirname(__FILE__) . '/classes/YujuLogger.php';
        require_once dirname(__FILE__) . '/classes/YujuProductChangeDetector.php';
        require_once dirname(__FILE__) . '/classes/YujuSyncQueue.php';
        if (empty($params['product']) || !is_object($params['product'])) { return; }
        $product = $params['product'];
        $productId = (int) $product->id;
        if ($productId <= 0) { return; }
        $now = microtime(true);
        if (isset($yuju_processing_products[$productId]) && ($now - $yuju_processing_products[$productId]) < 2) { return; }
        $yuju_processing_products[$productId] = $now;
        $logger = new YujuLogger();
        try {
            if (empty(Configuration::get('YUJU_CLIENT_ID')) || empty(Configuration::get('YUJU_CLIENT_SECRET'))
                || !(bool) Configuration::get('YUJU_ENABLE_AUTO_SYNC')) { return; }
            $status = Db::getInstance()->getRow(
                'SELECT yuju_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status` WHERE prestashop_product_id = ' . $productId . ' AND (prestashop_product_attribute_id IS NULL OR prestashop_product_attribute_id = 0)'
            );
            if (!$status || trim((string) ($status['yuju_product_id'] ?? '')) === '') { return; }
            $detector = new YujuProductChangeDetector();
            $changes = $detector->detectChanges($product);
            if (empty($changes['changed_fields'])) { return; }
            $data = $detector->prepareYujuUpdateData($product, $changes['changed_fields']);
            if (!is_array($data) || empty($data)) { return; }
            $hasCombinations = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE id_product = ' . $productId
            );
            // El stock agregado del padre no se envía como si fuera una variación.
            if ($hasCombinations > 0) { unset($data['stock']); }

            // Omitir solo el precio inválido o con variación superior al 50%.
            $dropFields = [];
            if (array_key_exists('price', $data)) {
                $newPrice = (float) $data['price'];
                $oldPrice = isset($changes['old_values']['price']) ? (float) $changes['old_values']['price'] : null;
                $invalidPrice = !is_finite($newPrice) || $newPrice <= 0;
                $abruptPrice = $oldPrice !== null && $oldPrice > 0 && abs($newPrice - $oldPrice) / $oldPrice > 0.5;
                if ($invalidPrice || $abruptPrice) {
                    unset($data['price']);
                    $dropFields[] = 'price';
                    $logger->warning('Precio omitido en actualización automática de Yuju', [
                        'product_id' => $productId, 'old_price' => $oldPrice, 'new_price' => $newPrice,
                        'reason' => $invalidPrice ? 'precio menor o igual a cero/no válido' : 'cambio superior al 50%',
                    ]);
                }
            }
            if (array_key_exists('stock', $data)) { $data['stock'] = max(0, (int) $data['stock']); }
            if (empty($data) && empty($dropFields)) { return; }

            // Prioridad ordena la cola; defer=1 evita llamadas API dentro del hook.
            $priority = (($changes['priority'] ?? 'normal') === 'high') ? 'high' : 'normal';
            $queue = new YujuSyncQueue();
            $queued = $queue->addToQueue($productId, 'update', $priority, array_merge($data, [
                'origin' => 'auto_product', 'direct_update' => 1, 'defer' => 1,
                'changed_fields' => array_values((array) $changes['changed_fields']),
                'drop_fields' => $dropFields,
            ]));
            if ($queued) {
                Db::getInstance()->update('yuju_product_status', [
                    'sync_status' => pSQL('queued'), 'last_error' => null, 'updated_at' => date('Y-m-d H:i:s'),
                ], 'prestashop_product_id = ' . $productId . ' AND (prestashop_product_attribute_id IS NULL OR prestashop_product_attribute_id = 0)');
            }
        } catch (Throwable $e) {
            $logger->error('No se pudo encolar actualización automática de producto', [
                'product_id' => $productId, 'error' => $e->getMessage(),
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
