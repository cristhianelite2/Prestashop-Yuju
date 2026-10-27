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

require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuProductManager.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuSyncManager.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuSchemaRepair.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuCategoryMapping.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuExcelHelper.php';

class AdminYujuProductStatusController extends ModuleAdminController
{
    /** @var bool Evita repetir el UPDATE de corrección en la misma petición HTTP */
    protected static $productStatusInfrastructureRepairDone = false;
    protected static $productStatusSchemaRepairDone = false;

    protected $product_manager;

    protected $sync_manager;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'yuju_product_status';
        $this->className = 'YujuProductStatus';
        $this->identifier = 'id';
        $this->lang = false;
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('sync');

        $this->product_manager = new YujuProductManager();
        $this->sync_manager = new YujuSyncManager();

        // No ejecutar reparaciones/ALTER TABLE al abrir el listado: pueden tomar bloqueos
        // de metadatos y retrasar la pantalla. Las reparaciones se realizan desde la instalación
        // o las herramientas de mantenimiento del módulo.

        parent::__construct();

        $this->meta_title = 'Estado de Sincronización de Productos';

        $this->fields_list = [
        'id' => [
        'title' => 'ID',
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'prestashop_product_id' => [
        'title' => 'ID Producto PS',
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'prestashop_product_attribute_id' => [
        'title' => 'ID Combinación PS',
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'product_name' => [
        'title' => 'Nombre del Producto',
        'callback' => 'getProductName',
        ],
        'parent_yuju_product_id' => [
        'title' => 'ID Padre Yuju',
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'yuju_product_id' => [
        'title' => 'ID Producto Yuju',
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'sync_status' => [
        'title' => 'Estado de Sincronización',
        'align' => 'center',
        'class' => 'fixed-width-sm',
        'callback' => 'displaySyncStatus',
        ],
        'last_sync_at' => [
            'title' => 'Última Sincronización',
            'align' => 'center',
            'type' => 'datetime',
        ],
        'error_count' => [
        'title' => 'Errores',
        'align' => 'center',
        'class' => 'fixed-width-xs',
        'callback' => 'displayErrorCount',
        ],
        // TODO: Uncomment when is_active column is added to yuju_product_status table
        // 'is_active' => [
        // 'title' => 'Activo',
        // 'align' => 'center',
        // 'class' => 'fixed-width-xs',
        // 'type' => 'bool',
        // 'icon' => [
        // 0 => 'disabled.gif',
        // 1 => 'enabled.gif',
        // ],
        // ],
        ];

        $this->bulk_actions = [
        'enableSync' => [
        'text' => 'Habilitar sincronización',
        'icon' => 'icon-power-off text-success',
        ],
        'disableSync' => [
        'text' => 'Deshabilitar sincronización',
        'icon' => 'icon-power-off text-danger',
        ],
        'syncSelected' => [
        'text' => 'Sincronizar seleccionados',
        'icon' => 'icon-refresh',
        ],
        'resetErrors' => [
        'text' => 'Reiniciar errores',
        'icon' => 'icon-eraser',
        ],
        'delete' => [
        'text' => 'Eliminar seleccionados',
        'icon' => 'icon-trash',
        'confirm' => '¿Eliminar elementos seleccionados?',
        ],
        ];

        $this->_select = 'pl.name as product_name';
        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . 'product yuju_ps_prod ON (a.prestashop_product_id = yuju_ps_prod.id_product) '
            . 'LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (a.prestashop_product_id = pl.id_product AND pl.id_lang = ' . (int) $this->context->language->id
            . ' AND pl.id_shop = yuju_ps_prod.id_shop_default)';

        $this->_orderBy = 'last_sync_at';
        $this->_orderWay = 'DESC';
    }

    public function initContent()
    {
        YujuSchemaRepair::ensurePerformanceIndexes();

        // Variables usadas en product_status_stats.tpl (evita avisos si el alcance Smarty no coincide con renderList)
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuProductStatus',
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuProductStatus', true),
            'token' => $this->token,
            'yuju_logs_admin_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
            'yuju_product_info_ajax_url' => $this->context->link->getAdminLink('AdminYujuProductStatus', true),
            'yuju_product_info_token' => $this->token,
            'product_admin_url' => $this->context->link->getAdminLink('AdminProducts', true),
            'shop_base_uri' => defined('__PS_BASE_URI__') ? __PS_BASE_URI__ : '/',
        ]);
        parent::initContent();
        
        $this->setTemplate('product_status.tpl');
    }

    public function renderList()
    {
        require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuCategoryMapping.php';
        // Add toolbar buttons
        $this->toolbar_btn['sync_all'] = [
        'href' => self::$currentIndex . '&action=syncAll&token=' . $this->token,
        'desc' => 'Sincronizar Todos los Productos',
        'icon' => 'process-icon-refresh',
        ];

        $this->toolbar_btn['import_status'] = [
        'href' => self::$currentIndex . '&action=importStatus&token=' . $this->token,
        'desc' => 'Importar Estado',
        'icon' => 'process-icon-import',
        ];

        $this->toolbar_btn['export_status'] = [
        'href' => self::$currentIndex . '&action=exportStatus&token=' . $this->token,
        'desc' => 'Exportar Estado',
        'icon' => 'process-icon-export',
        ];

        // Add filters
        $this->fields_list['sync_status']['filter_key'] = 'a!sync_status';
        $this->fields_list['sync_status']['filter_type'] = 'select';
        $this->fields_list['sync_status']['select'] = [
        'synced' => 'Sincronizado',
        'synced_with_warnings' => 'Con Advertencias',
        'pending' => 'Pendiente',
        'error' => 'Error',
        'disabled' => 'Deshabilitado',
        'queued' => 'En Cola',
        'creating_in_yuju' => 'En espera de respuesta',
        'updating_in_yuju' => 'Actualizando',
        'deleting_in_yuju' => 'Eliminando',
        ];

        // Get PrestaShop stores
        $prestashop_stores = $this->getPrestashopStores();
        
        // Get mapped categories
        $mapped_categories = $this->getMappedCategories();
        
        // Get Yuju categories
        $yuju_categories = $this->getYujuCategories();
        
        // Get products with pagination
        $page = (int)Tools::getValue('page', 1);
        $per_page = (int)Tools::getValue('per_page', 25);
        $products_data = $this->getProductsWithFilters($page, $per_page);
        
        // La plantilla actual no consume product_status_stats. No ejecutar agregados globales
        // de yuju_product_status/yuju_sync_queue en cada apertura del listado.

        // Generate AJAX URL for the controller
        $ajax_url = $this->context->link->getAdminLink('AdminYujuProductStatus', true);

        // Bulk filter data from YujuExcelHelper
        $bulk_filter_id = (string) Tools::getValue('bulk_filter_id', '');
        $bulk_filter_items = [];
        if ($bulk_filter_id !== '') {
            $bulk_filter_items = YujuExcelHelper::getFilterList($bulk_filter_id);
        }
        $bulk_filter_items_text = implode("\n", $bulk_filter_items);
        $bulk_filter_count = count($bulk_filter_items);

        $this->context->smarty->assign([
        'sync_running' => $this->sync_manager->isSyncRunning(),
        'prestashop_stores' => $prestashop_stores,
        'mapped_categories' => $mapped_categories,
        'yuju_categories' => $yuju_categories,
        'products' => $products_data['products'],
        'pagination' => $products_data['pagination'],
        'product_status_has_active_filters' => $this->hasActiveProductStatusFilters(),
        'bulk_filter_id' => $bulk_filter_id,
        'bulk_filter_items_text' => $bulk_filter_items_text,
        'bulk_filter_count' => $bulk_filter_count,
        'download_demo_xlsx_url' => $ajax_url . '&action=downloadExcelTemplate',
        'download_demo_csv_url' => $ajax_url . '&action=downloadCsvTemplate',
        'ajax_url' => $ajax_url,
        'yuju_logs_admin_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
        'current_index' => self::$currentIndex,
        'token' => $this->token,
        'yuju_product_info_ajax_url' => $ajax_url,
        'yuju_product_info_token' => $this->token,
        'yuju_category_options' => YujuCategoryMapping::getYujuCategoryOptions(),
        'product_admin_url' => $this->context->link->getAdminLink('AdminProducts', true),
        'yuju_sync_history_table_missing' => !$this->isYujuProductSyncHistoryTablePresent(),
        'yuju_sync_history_table_name' => _DB_PREFIX_ . 'yuju_product_sync_history',
        ]);

        $stats_html = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'yuju/views/templates/admin/product_status_stats.tpl');

        return $stats_html;
    }

    /**
     * Filtros explícitos en la URL (misma lógica que los chips en product_status_stats.tpl).
     */
    protected function hasActiveProductStatusFilters()
    {
        $g = static function ($key) {
            return isset($_GET[$key]) ? (string) $_GET[$key] : '';
        };

        if ($g('store') !== '' && $g('store') !== '0') {
            return true;
        }
        if ($g('ps_category') !== '' && $g('ps_category') !== '0') {
            return true;
        }
        if ($g('yuju_category') !== '' && $g('yuju_category') !== '0') {
            return true;
        }
        if ($g('status') !== '') {
            return true;
        }
        if ($g('date_from') !== '') {
            return true;
        }
        if ($g('date_to') !== '') {
            return true;
        }
        if (trim($g('search')) !== '') {
            return true;
        }
        if (trim($g('yuju_id')) !== '') {
            return true;
        }
        if ($g('bulk_filter_id') !== '' || trim($g('bulk_search')) !== '') {
            return true;
        }
        if ($g('show_all') === '1') {
            return true;
        }
        if (isset($_GET['per_page']) && (string) $_GET['per_page'] !== '' && (int) $_GET['per_page'] !== 25) {
            return true;
        }

        return false;
    }

    protected function getPrestashopStores()
    {
        $configured_store_id = Configuration::get('YUJU_STORE_ID');
        $shops = Shop::getShops(true);
        
        $stores = [];
        foreach ($shops as $shop) {
            $stores[] = [
                'id_shop' => $shop['id_shop'],
                'name' => $shop['name'],
                'selected' => ($shop['id_shop'] == $configured_store_id)
            ];
        }
        
        return $stores;
    }
    
    protected function getMappedCategories()
    {
        $sql = 'SELECT 
                    ycm.prestashop_category_id,
                    cl.name as prestashop_category_name,
                    ycm.yuju_category_name,
                    ycm.yuju_category_id
                FROM ' . _DB_PREFIX_ . 'yuju_category_mapping ycm
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl 
                    ON ycm.prestashop_category_id = cl.id_category 
                    AND cl.id_lang = ' . (int)$this->context->language->id . '
                ORDER BY cl.name ASC';
        
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * IDs de categorías PrestaShop mapeadas a Yuju (lista corta, una consulta por petición).
     *
     * @return int[]
     */
    protected function getMappedPrestashopCategoryIds()
    {
        static $ids = null;
        if ($ids === null) {
            $rows = Db::getInstance()->executeS(
                'SELECT DISTINCT prestashop_category_id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
                 WHERE prestashop_category_id IS NOT NULL AND prestashop_category_id > 0'
            ) ?: [];
            $ids = array_values(array_unique(array_map('intval', array_column($rows, 'prestashop_category_id'))));
        }

        return $ids;
    }

    /**
     * COUNT(*) con caché de archivo de corta duración ($ttl = 0 desactiva la caché).
     *
     * @param string $sql
     * @param int    $ttl segundos
     *
     * @return int
     */
    protected function getCachedProductCount($sql, $ttl)
    {
        $file = _PS_CACHE_DIR_ . 'yuju_ps_count_' . md5($sql) . '.json';
        if ($ttl > 0 && is_readable($file) && (time() - (int) filemtime($file)) < $ttl) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_int($cached)) {
                return $cached;
            }
        }

        $total = (int) Db::getInstance()->getValue($sql);
        if ($ttl > 0) {
            @file_put_contents($file, json_encode($total), LOCK_EX);
        }

        return $total;
    }

    /**
     * Registra en el log de errores de PHP cuánto tardó cada fase del listado cuando supera 0.75 s.
     */
    protected function logSlowProductListing($tCount, $tIds, $tDetail, $total)
    {
        if (($tCount + $tIds + $tDetail) < 0.75) {
            return;
        }
        error_log(sprintf(
            '[Yuju ProductStatus] lento: count=%.3fs ids=%.3fs detalle=%.3fs total_productos=%d',
            $tCount,
            $tIds,
            $tDetail,
            (int) $total
        ));
    }

    protected function getYujuCategories()
    {
        $sql = 'SELECT DISTINCT
                    ycm.yuju_category_id,
                    ycm.yuju_category_name
                FROM ' . _DB_PREFIX_ . 'yuju_category_mapping ycm
                WHERE ycm.yuju_category_id IS NOT NULL
                ORDER BY ycm.yuju_category_name ASC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Comprueba si existe la tabla de historial de envíos a Yuju (puede faltar en instalaciones antiguas).
     *
     * @return bool
     */
    protected function isYujuProductSyncHistoryTablePresent()
    {
        return $this->product_manager->isProductSyncHistoryTablePresent();
    }

    /**
     * Corrige filas donde sync_status quedó en "error" solo por fallo de la tabla de historial (42S02),
     * no por un error real de sincronización con Yuju.
     *
     * @param array $products
     *
     * @return array
     */
    protected function sanitizeProductsYujuDisplayForInfrastructureErrors(array $products)
    {
        foreach ($products as &$row) {
            $err = '';
            foreach (['last_error', 'LAST_ERROR'] as $ek) {
                if (isset($row[$ek]) && $row[$ek] !== null && $row[$ek] !== '') {
                    $err = trim((string) $row[$ek]);
                    break;
                }
            }
            if ($err === '' || !$this->isInfrastructureSyncHistoryTableErrorMessage($err)) {
                continue;
            }
            $yid = '';
            foreach (['yuju_product_id', 'YUJU_PRODUCT_ID'] as $yk) {
                if (isset($row[$yk]) && $row[$yk] !== null && trim((string) $row[$yk]) !== '') {
                    $yid = trim((string) $row[$yk]);
                    break;
                }
            }
            if ($this->isValidYujuProductIdValue($yid)) {
                $row['yuju_status'] = 'synced';
            } else {
                $row['yuju_status'] = 'pending';
            }
            $row['last_error'] = null;
            if (isset($row['LAST_ERROR'])) {
                $row['LAST_ERROR'] = null;
            }
        }
        unset($row);

        return $products;
    }

    /**
     * Un producto no puede figurar como sincronizado sin ID Yuju enlazado (coherencia UI / BD).
     *
     * @param array $products
     *
     * @return array
     */
    protected function normalizeSyncedStatusRequiresYujuProductId(array $products)
    {
        foreach ($products as &$row) {
            $idVal = '';
            foreach (['yuju_product_id', 'YUJU_PRODUCT_ID'] as $yk) {
                if (isset($row[$yk]) && $row[$yk] !== null && (string) $row[$yk] !== '') {
                    $idVal = trim((string) $row[$yk]);
                    break;
                }
            }
            if ($this->isValidYujuProductIdValue($idVal)) {
                continue;
            }
            $st = isset($row['yuju_status']) ? (string) $row['yuju_status'] : '';
            if (in_array($st, ['synced', 'synced_with_warnings', 'synced_with_errors'], true)) {
                $row['yuju_status'] = 'pending';
            }
        }
        unset($row);

        return $products;
    }

    /**
     * Marca filas cuya referencia de producto (TRIM) está repetida en más de un id_product en PrestaShop.
     *
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<int, array<string, mixed>>
     */
    protected function annotateProductsWithDuplicateReferenceInCatalog(array $products)
    {
        if ($products === []) {
            return $products;
        }

        try {
            $dup = $this->product_manager->getGloballyDuplicatePrestaShopReferenceSet();
        } catch (Exception $e) {
            return $products;
        }

        foreach ($products as $k => $row) {
            $r = isset($row['reference']) ? trim((string) $row['reference']) : '';
            $products[$k]['reference_duplicate_in_catalog'] = ($r !== '' && isset($dup[$r]));
        }

        return $products;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function isValidYujuProductIdValue($value)
    {
        $v = trim((string) $value);

        return $v !== ''
            && strtolower($v) !== 'null'
            && $v !== '0';
    }

    /**
     * Limpia en BD mensajes de error que solo indicaban fallo al guardar historial (tabla ausente),
     * para que no queden en rojo productos ya enlazados con Yuju.
     */
    protected function repairStaleInfrastructureErrorsInProductStatus()
    {
        if (self::$productStatusInfrastructureRepairDone) {
            return;
        }
        self::$productStatusInfrastructureRepairDone = true;

        try {
            $sql = '
                UPDATE `' . _DB_PREFIX_ . 'yuju_product_status`
                SET
                    `sync_status` = CASE
                        WHEN `yuju_product_id` IS NOT NULL
                            AND CHAR_LENGTH(TRIM(`yuju_product_id`)) > 0
                            AND TRIM(`yuju_product_id`) <> "0" THEN "synced"
                        ELSE "pending"
                    END,
                    `last_error` = NULL
                WHERE `sync_status` = "error"
                    AND `last_error` IS NOT NULL
                    AND `last_error` != ""
                    AND (
                        `last_error` LIKE "%42S02%"
                        OR `last_error` LIKE "%yuju_product_sync_history%"
                        OR `last_error` LIKE "%Base table or view not found%"
                        OR `last_error` LIKE "%1146%"
                    )
            ';
            Db::getInstance()->execute($sql);

            $sql2 = '
                UPDATE `' . _DB_PREFIX_ . 'yuju_product_status`
                SET `sync_status` = "pending",
                    `last_error` = NULL
                WHERE `sync_status` IN ("synced", "synced_with_warnings", "synced_with_errors")
                    AND (
                        `yuju_product_id` IS NULL
                        OR CHAR_LENGTH(TRIM(`yuju_product_id`)) = 0
                        OR TRIM(`yuju_product_id`) = "0"
                    )
            ';
            Db::getInstance()->execute($sql2);
        } catch (Exception $e) {
            // No bloquear la pantalla si el UPDATE falla
        }
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    protected function isInfrastructureSyncHistoryTableErrorMessage($message)
    {
        if ($message === '') {
            return false;
        }
        $m = strtolower($message);

        return strpos($m, 'yuju_product_sync_history') !== false
            || strpos($m, '42s02') !== false
            || strpos($m, 'base table or view not found') !== false
            || strpos($m, '1146') !== false;
    }

    /**
     * AJAX: crea yuju_product_sync_history desde sql/install.sql y comprueba que exista.
     */
    protected function ajaxProcessCreateSyncHistoryTable()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->module || !method_exists($this->module, 'ensureSyncHistoryTable')) {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo acceder al módulo Yuju.',
            ]);

            return;
        }

        $this->module->ensureSyncHistoryTable();

        $present = $this->isYujuProductSyncHistoryTablePresent();
        $dbErr = Db::getInstance()->getMsgError();

        echo json_encode([
            'success' => $present,
            'message' => $present
                ? 'La tabla se creó correctamente. Recargando…'
                : ('No se pudo crear la tabla. ' . ($dbErr ?: 'Compruebe permisos MySQL y que exista sql/install.sql en el módulo.')),
        ]);
    }

    /**
     * Lista de IDs del listado filtrado (misma lógica que envío masivo filtrado).
     *
     * @return void
     */
    protected function ajaxProcessGetFilteredProductIds()
    {
        header('Content-Type: application/json; charset=utf-8');
        $logs_url = $this->context->link->getAdminLink('AdminYujuLogs', true);

        $filters = Tools::getValue('filters', []);
        if (!is_array($filters)) {
            $filters = [];
        }

        $_GET['store'] = isset($filters['store']) ? (string) $filters['store'] : '';
        $_GET['ps_category'] = isset($filters['ps_category']) ? (string) $filters['ps_category'] : '';
        $_GET['yuju_category'] = isset($filters['yuju_category']) ? (string) $filters['yuju_category'] : '';
        $_GET['status'] = isset($filters['status']) ? (string) $filters['status'] : '';
        $_GET['search'] = isset($filters['search']) ? (string) $filters['search'] : '';
        $_GET['yuju_id'] = isset($filters['yuju_id']) ? (string) $filters['yuju_id'] : '';
        $_GET['date_from'] = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        $_GET['date_to'] = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        $_GET['show_all'] = isset($filters['show_all']) ? (string) $filters['show_all'] : '';
        $_GET['bulk_filter_id'] = isset($filters['bulk_filter_id']) ? (string) $filters['bulk_filter_id'] : '';
        $_GET['bulk_search'] = isset($filters['bulk_search']) ? (string) $filters['bulk_search'] : '';
        if (isset($filters['per_page']) && $filters['per_page'] !== '') {
            $_GET['per_page'] = (string) $filters['per_page'];
        }

        if (!$this->hasActiveProductStatusFilters()) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay filtros activos. Aplique al menos un filtro antes de obtener el conjunto.',
                'logs_url' => $logs_url,
                'product_ids' => [],
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $result = $this->getProductsWithFilters(1, 999999);
        $products = $result['products'] ?? [];
        $only_without_yuju = (string) Tools::getValue('only_without_yuju', '') === '1';
        $ids = [];
        foreach ($products as $product) {
            if (empty($product['id_product'])) {
                continue;
            }
            if ($only_without_yuju && !empty($product['yuju_product_id'])) {
                continue;
            }
            $ids[] = (int) $product['id_product'];
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));

        echo json_encode([
            'success' => true,
            'product_ids' => $ids,
            'total' => count($ids),
            'logs_url' => $logs_url,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Validación previa al envío masivo "Crear": paso global de duplicados + trozos por producto.
     *
     * @return void
     */
    protected function ajaxProcessValidateCreateBatch()
    {
        header('Content-Type: application/json; charset=utf-8');
        $logs_url = $this->context->link->getAdminLink('AdminYujuLogs', true);

        if ($this->module && method_exists($this->module, 'ensureSyncHistoryTable')) {
            $this->module->ensureSyncHistoryTable();
        }

        $product_ids = [];
        $product_ids_json = (string) Tools::getValue('product_ids_json', '');
        if ($product_ids_json !== '') {
            $decoded = json_decode($product_ids_json, true);
            if (is_array($decoded)) {
                $product_ids = array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($id) {
                    return $id > 0;
                })));
            }
        }
        // Los IDs también pueden llegar como product_ids[] para evitar reglas WAF
        // que bloquean cuerpos POST con JSON serializado.
        if (empty($product_ids)) {
            $posted_ids = Tools::getValue('product_ids', []);
            if (!is_array($posted_ids)) {
                $posted_ids = ($posted_ids !== '' && $posted_ids !== null) ? [$posted_ids] : [];
            }
            $product_ids = array_values(array_unique(array_filter(array_map('intval', $posted_ids), static function ($id) {
                return $id > 0;
            })));
        }

        if (empty($product_ids)) {
            echo json_encode([
                'success' => false,
                'message' => 'No se recibieron productos para validar.',
                'logs_url' => $logs_url,
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $step = (string) Tools::getValue('validate_step', 'batch');
        $id_lang = (int) $this->context->language->id;
        $total_products = count($product_ids);
        $progress_total = $total_products + 1;

        if ($step === 'batch') {
            $conflicts = $this->getDuplicateSkuConflictsForProductIds($product_ids);
            $batch_invalid_ids = [];
            foreach ($conflicts as $c) {
                foreach ($c['products'] as $p) {
                    $pid = (int) $p['id_product'];
                    if ($pid > 0 && in_array($pid, $product_ids, true)) {
                        $batch_invalid_ids[$pid] = true;
                    }
                }
            }
            $batch_invalid_list = array_keys($batch_invalid_ids);
            sort($batch_invalid_list);

            echo json_encode([
                'success' => true,
                'validate_step' => 'batch',
                'progress_current' => 1,
                'progress_total' => $progress_total,
                'step_label' => 'Validando duplicación de referencias/SKU en PrestaShop (productos y combinaciones)…',
                'step_detail' => 'Se comparan las referencias del catálogo completo para el lote seleccionado.',
                'duplicate_sku_conflicts' => $conflicts,
                'batch_invalid_ids' => $batch_invalid_list,
                'logs_url' => $logs_url,
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        if ($step !== 'product') {
            echo json_encode([
                'success' => false,
                'message' => 'Paso de validación no reconocido.',
                'logs_url' => $logs_url,
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $batch_invalid_raw = Tools::getValue('batch_invalid_ids_json', '');
        $batch_invalid_decoded = $batch_invalid_raw !== '' ? json_decode((string) $batch_invalid_raw, true) : null;
        if (!is_array($batch_invalid_decoded)) {
            $batch_invalid_decoded = Tools::getValue('batch_invalid_ids', []);
        }
        $batch_invalid_map = [];
        if (is_array($batch_invalid_decoded)) {
            foreach ($batch_invalid_decoded as $bid) {
                $bid = (int) $bid;
                if ($bid > 0) {
                    $batch_invalid_map[$bid] = true;
                }
            }
        }

        $index = (int) Tools::getValue('product_index', 0);
        if ($index < 0) {
            $index = 0;
        }

        $requested_chunk = (int) Tools::getValue('chunk_size', 1);
        $chunk_size = max(1, min(50, $requested_chunk === 0 ? 1 : $requested_chunk));
        if ($total_products > 60) {
            $chunk_size = max($chunk_size, 5);
            $chunk_size = min(50, $chunk_size);
        }

        $results = [];
        $processed = 0;
        $t_start = microtime(true);

        for ($k = 0; $k < $chunk_size && $index + $k < $total_products; ++$k) {
            // Presupuesto de tiempo por petición: devolver lo avanzado y que el cliente continúe
            // con next_product_index (menos peticiones sin arriesgar un timeout).
            if ($processed > 0 && (microtime(true) - $t_start) > 20) {
                break;
            }
            $pid = $product_ids[$index + $k];
            $product = new Product($pid, false, $id_lang);
            $name = Validate::isLoadedObject($product) ? (string) $product->name : '(producto #' . $pid . ')';

            if (isset($batch_invalid_map[$pid])) {
                $msg = 'Referencia/SKU duplicada en PrestaShop (varios productos o combinaciones comparten el mismo código). Corrija el catálogo.';
                $this->product_manager->updateProductStatus($pid, 'error', $msg, null);
                $this->product_manager->logValidationErrorHistory($pid, $msg, 'create');
                $results[] = [
                    'product_id' => $pid,
                    'product_name' => $name,
                    'valid' => false,
                    'skipped_duplicate_batch' => true,
                    'errors' => [$msg],
                ];
                ++$processed;

                continue;
            }

            // Si ya existe en Yuju → no validar como create; se actualizará (o quedará sincronizado sin cambios)
            $resolvedYujuId = $this->product_manager->resolveExistingYujuProductId($pid);
            if ($resolvedYujuId !== '') {
                $results[] = [
                    'product_id' => $pid,
                    'product_name' => $name,
                    'valid' => true,
                    'already_in_yuju' => true,
                    'will_update' => true,
                    'errors' => [],
                ];
                ++$processed;

                continue;
            }

            // Un fallo inesperado al validar un producto no debe romper la respuesta AJAX
            // ni ocultar los resultados de los demás productos del lote.
            try {
                $validation = $this->product_manager->validateProductForYujuCreate($pid);
            } catch (\Throwable $e) {
                error_log('[Yuju ProductStatus] Excepción validando producto ' . (int) $pid . ': ' . $e->getMessage());
                $validation = [
                    'success' => false,
                    'errors' => ['No se pudo completar la validación de este producto: ' . $e->getMessage()],
                ];
            }
            if (!empty($validation['success'])) {
                $results[] = [
                    'product_id' => $pid,
                    'product_name' => $name,
                    'valid' => true,
                    'errors' => [],
                ];
            } else {
                $validation_msg = implode(' ', $validation['errors']);
                $this->product_manager->updateProductStatus($pid, 'error', $validation_msg, null);
                $this->product_manager->logValidationErrorHistory($pid, $validation_msg, 'create');
                $results[] = [
                    'product_id' => $pid,
                    'product_name' => $name,
                    'valid' => false,
                    'errors' => $validation['errors'],
                ];
            }
            ++$processed;
        }

        $next_index = $index + $processed;
        $done = $next_index >= $total_products;
        $progress_current = min($progress_total, 1 + $next_index);

        echo json_encode([
            'success' => true,
            'validate_step' => 'product',
            'progress_current' => $progress_current,
            'progress_total' => $progress_total,
            'step_label' => 'Validando categorías mapeadas, SKU efectivo y campos obligatorios para Yuju…',
            'step_detail' => $done
                ? ''
                : ('Productos ' . ($index + 1) . '–' . min($next_index, $total_products) . ' de ' . $total_products),
            'product_results' => $results,
            'next_product_index' => $next_index,
            'done' => $done,
            'logs_url' => $logs_url,
        ], JSON_UNESCAPED_UNICODE);
    }

    protected function getProductsWithFilters($page = 1, $per_page = 25)
    {
        // No ejecutar UPDATE correctivos de toda la tabla al abrir el listado.

        $store_id = (int)Tools::getValue('store', 0);
        $ps_category_id = (int)Tools::getValue('ps_category', 0);
        $yuju_category_id = (int)Tools::getValue('yuju_category', 0);
        $sync_status = Tools::getValue('status', '');
        $date_from = Tools::getValue('date_from', '');
        $date_to = Tools::getValue('date_to', '');
        $search = pSQL(Tools::getValue('search', ''));
        $yuju_id_search = trim((string) Tools::getValue('yuju_id', ''));
        $show_all = Tools::getValue('show_all', ''); // Nuevo filtro para mostrar todos los productos
        $bulk_filter_id = trim((string) Tools::getValue('bulk_filter_id', ''));
        $bulk_search = trim((string) Tools::getValue('bulk_search', ''));
        
        // Build WHERE clause
        $where = '1=1';
        
        if ($store_id > 0) {
            $where .= ' AND p.id_shop_default = ' . $store_id;
        }
        
        // FILTRO POR DEFECTO: Solo productos de categorías mapeadas (a menos que show_all esté activado)
        if ($show_all !== '1') {
            $mapped_ids = $this->getMappedPrestashopCategoryIds();
            if ($mapped_ids === []) {
                $where .= ' AND 1 = 0';
            } else {
                $where .= ' AND EXISTS (
                    SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp_mapped
                    WHERE cp_mapped.id_product = p.id_product
                        AND cp_mapped.id_category IN (' . implode(',', $mapped_ids) . ')
                )';
            }
        }
        
        // Filtro por categoría de PrestaShop
        if ($ps_category_id > 0) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp2 
                WHERE cp2.id_product = p.id_product AND cp2.id_category = ' . $ps_category_id . '
            )';
        }
        
        // Filtro por categoría de Yuju
        if ($yuju_category_id > 0) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp3
                INNER JOIN ' . _DB_PREFIX_ . 'yuju_category_mapping ycm 
                    ON cp3.id_category = ycm.prestashop_category_id
                WHERE cp3.id_product = p.id_product 
                    AND ycm.yuju_category_id = ' . $yuju_category_id . '
            )';
        }
        
        // Filtro por estado de sincronización
        if ($sync_status) {
            if ($sync_status == 'not_synced') {
                $where .= ' AND (yps.sync_status IS NULL OR yps.sync_status = "pending")';
            } elseif ($sync_status == 'all_synced') {
                $where .= ' AND yps.sync_status IN ("synced", "synced_with_warnings", "synced_with_errors")';
            } elseif ($sync_status == 'not_in_yuju') {
                // Producto sin ID de Yuju (jamás creado o ya eliminado): única condición
                // requerida — independientemente de su sync_status en la tabla local.
                $where .= ' AND (yps.yuju_product_id IS NULL'
                    . ' OR TRIM(yps.yuju_product_id) = ""'
                    . ' OR LOWER(TRIM(yps.yuju_product_id)) = "null"'
                    . ' OR TRIM(yps.yuju_product_id) = "0")';
            } else {
                $where .= ' AND yps.sync_status = "' . pSQL($sync_status) . '"';
            }
        }
        
        // Filtro por rango de fechas de sincronización
        if ($date_from) {
            $where .= ' AND yps.last_sync_at >= "' . pSQL($date_from) . ' 00:00:00"';
        }
        if ($date_to) {
            $where .= ' AND yps.last_sync_at <= "' . pSQL($date_to) . ' 23:59:59"';
        }
        
        if ($search) {
            $where .= ' AND (pl.name LIKE "%' . $search . '%" OR p.reference LIKE "%' . $search . '%")';
        }

        if ($yuju_id_search !== '') {
            $yid = pSQL($yuju_id_search);
            $where .= ' AND yps.yuju_product_id IS NOT NULL AND TRIM(yps.yuju_product_id) != ""'
                . ' AND yps.yuju_product_id LIKE "%' . $yid . '%"';
        }

        $bulk_items = [];
        if ($bulk_filter_id !== '') {
            $bulk_items = YujuExcelHelper::getFilterList($bulk_filter_id);
        }
        if (empty($bulk_items) && $bulk_search !== '') {
            $bulk_items = YujuExcelHelper::parseTextareaLines($bulk_search);
        }

        if (!empty($bulk_items)) {
            $safe_items = array_map('pSQL', $bulk_items);
            $in_list = "'" . implode("','", $safe_items) . "'";
            $bulk_clauses = [
                'p.reference IN (' . $in_list . ')',
                'pl.name IN (' . $in_list . ')',
            ];
            // Para listas de hasta 100 elementos, buscar también coincidencias parciales en el nombre
            if (count($bulk_items) <= 100) {
                $like_clauses = [];
                foreach ($bulk_items as $item) {
                    $like_clauses[] = 'pl.name LIKE "%' . pSQL($item) . '%"';
                }
                if (!empty($like_clauses)) {
                    $bulk_clauses[] = '(' . implode(' OR ', $like_clauses) . ')';
                }
            }
            $where .= ' AND (' . implode(' OR ', $bulk_clauses) . ')';
        }
        
        // Filter products without reference and/or name
        $where .= ' AND p.reference IS NOT NULL AND p.reference != "" AND pl.name IS NOT NULL AND pl.name != ""';
        
        // Solo necesitamos unir el historial de estado para filtrar por estado/fecha/ID Yuju.
        // Sin esos filtros, agrupar TODA yuju_product_status en cada apertura es innecesario y costoso.
        $needs_yps_filter = ($sync_status !== '' || $date_from !== '' || $date_to !== '' || $yuju_id_search !== '');
        $status_table = '`' . _DB_PREFIX_ . 'yuju_product_status`';
        // Usar siempre una búsqueda correlacionada por producto. El índice
        // (prestashop_product_id, id) resuelve MAX(id) sin agrupar toda la tabla,
        // lo que evita una tabla temporal/filesort cuando se filtra por estado o fecha.
        $yps_join = '
            LEFT JOIN ' . $status_table . ' yps
                ON yps.`id` = (
                    SELECT MAX(ys.`id`)
                    FROM ' . $status_table . ' ys
                    WHERE ys.`prestashop_product_id` = p.`id_product`
                )';

        // Multitienda: product_lang y category_lang tienen id_shop; sin filtrarlo, el JOIN duplica una fila por tienda
        $pl_shop = ' AND pl.`id_shop` = p.`id_shop_default` ';
        $cl_shop = ' AND cl.`id_shop` = p.`id_shop_default` ';

        // El contador no debe leer yuju_product_status si ningún filtro depende de esa tabla.
        $count_status_join = $needs_yps_filter ? $yps_join : '';
        $count_sql = 'SELECT COUNT(*) as total
                      FROM ' . _DB_PREFIX_ . 'product p
                      LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                          ON p.id_product = pl.id_product
                          AND pl.id_lang = ' . (int)$this->context->language->id . '
                          ' . $pl_shop . '
                      ' . $count_status_join . '
                      WHERE ' . $where;
        
        $t_start = microtime(true);
        // El total solo depende del catálogo y del mapeo de categorías cuando ningún filtro usa
        // yuju_product_status ni búsqueda de texto: se reutiliza unos minutos para no recorrer
        // todo el catálogo en cada apertura. Con filtros de estado/fecha/texto siempre se calcula.
        $count_cacheable = !$needs_yps_filter && $search === '' && empty($bulk_items);
        $total_products = $this->getCachedProductCount($count_sql, $count_cacheable ? 120 : 0);
        $t_count = microtime(true) - $t_start;
        
        // Evitar división entre cero y límites arbitrarios enviados por URL.
        $per_page = max(1, min(100, (int) $per_page));

        // Calculate pagination
        $total_pages = ceil($total_products / $per_page);
        $page = max(1, min($page, $total_pages ?: 1));
        $offset = ($page - 1) * $per_page;
        $from = $total_products > 0 ? $offset + 1 : 0;
        $to = min($offset + $per_page, $total_products);
        
        // Imagen portada: subconsulta para no duplicar filas si hay varias imágenes marcadas cover=1
        $cover_img_sub = '(SELECT MIN(img.`id_image`) FROM `' . _DB_PREFIX_ . 'image` img WHERE img.`id_product` = p.`id_product` AND img.`cover` = 1)';
        // Última acción pendiente/procesando en cola para este producto
        $queue_action_sub = '(SELECT q.`action` FROM `' . _DB_PREFIX_ . 'yuju_sync_queue` q WHERE q.`prestashop_product_id` = p.`id_product` AND q.`status` IN ("pending","processing") ORDER BY q.`id` DESC LIMIT 1)';

        $id_lang = (int) $this->context->language->id;

        // Paso 1: solo IDs de la página, sin subconsultas de imagen/cola/categoría.
        // El JOIN al último estado solo se incluye cuando los filtros lo necesitan.
        $ids_sql = 'SELECT p.id_product
                    FROM ' . _DB_PREFIX_ . 'product p
                    LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                        ON p.id_product = pl.id_product
                        AND pl.id_lang = ' . $id_lang . '
                        ' . $pl_shop . '
                    ' . $count_status_join . '
                    WHERE ' . $where . '
                    ORDER BY p.id_product DESC
                    LIMIT ' . (int) $offset . ', ' . (int) $per_page;
        $t_ids_start = microtime(true);
        $page_ids = array_map('intval', array_column(Db::getInstance()->executeS($ids_sql) ?: [], 'id_product'));
        $t_ids = microtime(true) - $t_ids_start;
        $t_detail_start = microtime(true);

        $products = [];
        if ($page_ids !== []) {
            // Paso 2: cargar el detalle solo para los IDs de la página (máximo 100 filas).
            $products_sql = 'SELECT
                                p.id_product,
                                p.reference,
                                pl.name,
                                cl.name as category_name,
                                ' . $cover_img_sub . ' AS id_image,
                                ' . $queue_action_sub . ' AS queue_action,
                                yps.sync_status as yuju_status,
                                yps.yuju_product_id,
                                yps.last_sync_at,
                                yps.last_error
                             FROM ' . _DB_PREFIX_ . 'product p
                             LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                                 ON p.id_product = pl.id_product
                                 AND pl.id_lang = ' . $id_lang . '
                                 ' . $pl_shop . '
                             LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl
                                 ON p.id_category_default = cl.id_category
                                 AND cl.id_lang = ' . $id_lang . '
                                 ' . $cl_shop . '
                             ' . $yps_join . '
                             WHERE p.id_product IN (' . implode(',', $page_ids) . ')
                             ORDER BY p.id_product DESC';

            $products = Db::getInstance()->executeS($products_sql) ?: [];
        }
        $this->logSlowProductListing($t_count, $t_ids, microtime(true) - $t_detail_start, $total_products);
        $products = $this->sanitizeProductsYujuDisplayForInfrastructureErrors($products);
        $products = $this->normalizeSyncedStatusRequiresYujuProductId($products);
        // La detección global de SKU duplicados es costosa y no es necesaria para dibujar
        // el listado. Se conserva la validación de duplicados al crear/actualizar productos.

        return [
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'from' => $from,
                'to' => $to,
                'total' => $total_products
            ]
        ];
    }

    public function postProcess()
    {
        $action = Tools::getValue('action');
        if ($action === 'downloadExcelTemplate') {
            $this->processDownloadExcelTemplate();
            return;
        } elseif ($action === 'downloadCsvTemplate') {
            $this->processDownloadCsvTemplate();
            return;
        }

        // Handle AJAX requests
        if (Tools::isSubmit('ajax') && Tools::getValue('ajax')) {
            $action = Tools::getValue('action');
            
            switch ($action) {
                case 'parseExcelFilter':
                    $this->ajaxProcessParseExcelFilter();
                    break;
                case 'saveBulkFilterList':
                    $this->ajaxProcessSaveBulkFilterList();
                    break;
                case 'getProducts':
                    $this->ajaxProcessGetProducts();
                    break;
                case 'sendProducts':
                    $this->ajaxProcessSendProducts();
                    break;
                case 'sendAllFiltered':
                    $this->ajaxProcessSendAllFiltered();
                    break;
                case 'getProductHistory':
                    $this->ajaxProcessGetProductHistory();
                    break;
                case 'getProductStatusId':
                    $this->ajaxProcessGetProductStatusId();
                    break;
                case 'createSyncHistoryTable':
                    $this->ajaxProcessCreateSyncHistoryTable();
                    break;
                case 'getInlineCategoryMappingOptions':
                    $this->ajaxProcessGetInlineCategoryMappingOptions();
                    break;
                case 'saveInlineCategoryMapping':
                    $this->ajaxProcessSaveInlineCategoryMapping();
                    break;
                case 'validateCreateBatch':
                    $this->ajaxProcessValidateCreateBatch();
                    break;
                case 'getFilteredProductIds':
                    $this->ajaxProcessGetFilteredProductIds();
                    break;
                case 'findProductCreatedWebhook':
                    $this->ajaxProcessFindProductCreatedWebhook();
                    break;
                case 'resendPendingCreate':
                    $this->ajaxProcessResendPendingCreate();
                    break;
                case 'retryQueuedProduct':
                    $this->ajaxProcessRetryQueuedProduct();
                    break;
            }
            exit;
        }
        
        if (Tools::isSubmit('submitBulkenableSync')) {
            $this->processBulkEnableSync();
        } elseif (Tools::isSubmit('submitBulkdisableSync')) {
            $this->processBulkDisableSync();
        } elseif (Tools::isSubmit('submitBulksyncSelected')) {
            $this->processBulkSyncSelected();
        } elseif (Tools::isSubmit('submitBulkresetErrors')) {
            $this->processBulkResetErrors();
        } elseif (Tools::isSubmit('delete' . $this->table)) {
            // Manejar delete para controlar la redirección
            $this->processDelete();
            $redirect_url = $this->context->link->getAdminLink('AdminYujuProductStatus');
            Tools::redirectAdmin($redirect_url);
            return;
        } elseif (Tools::isSubmit('action')) {
            $action = Tools::getValue('action');

            switch ($action) {
                case 'syncAll':
                    $this->processSyncAll();
                    break;
                case 'sync':
                    $this->processSyncSingle();
                    break;
                case 'importStatus':
                    $this->processImportStatus();
                    break;
                case 'exportStatus':
                    $this->processExportStatus();
                    break;
            }
        }

        return parent::postProcess();
    }
    
    protected function ajaxProcessGetProducts()
    {
        $filters = Tools::getValue('filters', []);
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $per_page = isset($filters['per_page']) ? (int)$filters['per_page'] : 25;
        
        // Override $_GET for getProductsWithFilters
        $_GET['page'] = $page;
        $_GET['per_page'] = $per_page;
        $_GET['store'] = isset($filters['store']) ? $filters['store'] : '';
        $_GET['ps_category'] = isset($filters['ps_category']) ? $filters['ps_category'] : (isset($filters['category']) ? $filters['category'] : '');
        $_GET['yuju_category'] = isset($filters['yuju_category']) ? $filters['yuju_category'] : '';
        $_GET['status'] = isset($filters['status']) ? $filters['status'] : '';
        $_GET['search'] = isset($filters['search']) ? $filters['search'] : '';
        $_GET['yuju_id'] = isset($filters['yuju_id']) ? (string) $filters['yuju_id'] : '';
        $_GET['bulk_filter_id'] = isset($filters['bulk_filter_id']) ? (string) $filters['bulk_filter_id'] : '';
        $_GET['bulk_search'] = isset($filters['bulk_search']) ? (string) $filters['bulk_search'] : '';
        
        $result = $this->getProductsWithFilters($page, $per_page);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'products' => $result['products'],
            'pagination' => $result['pagination']
        ]);
    }
    
    protected function ajaxProcessSendProducts()
    {
        require_once dirname(__FILE__) . '/../../classes/YujuSyncQueue.php';

        if ($this->module && method_exists($this->module, 'ensureSyncHistoryTable')) {
            $this->module->ensureSyncHistoryTable();
        }
        if ($this->module && method_exists($this->module, 'ensureSyncQueueActionIncludesDelete')) {
            $this->module->ensureSyncQueueActionIncludesDelete();
        }
        if ($this->module && method_exists($this->module, 'ensureQueueAndSyncLogsTables')) {
            $this->module->ensureQueueAndSyncLogsTables();
        }

        $logs_url = $this->context->link->getAdminLink('AdminYujuLogs', true);

        try {
        // Lista explícita vía JSON evita ambigüedades cuando hay otros campos POST con el mismo nombre.
        $product_ids = [];
        $product_ids_json = (string) Tools::getValue('product_ids_json', '');
        if ($product_ids_json !== '') {
            $decoded = json_decode($product_ids_json, true);
            if (is_array($decoded)) {
                $product_ids = array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($id) {
                    return $id > 0;
                })));
            }
        }
        if (empty($product_ids)) {
            $product_ids = Tools::getValue('product_ids', []);
            if (!is_array($product_ids)) {
                $product_ids = ($product_ids !== '' && $product_ids !== null) ? [(int) $product_ids] : [];
            }
            $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids), static function ($id) {
                return $id > 0;
            })));
        }

        $bulk_action = (string) Tools::getValue('bulk_action', 'create');
        if (!in_array($bulk_action, ['create', 'update', 'delete'], true)) {
            $bulk_action = 'create';
        }

        $bulk_delete_confirmed = Tools::getValue('bulk_delete_confirmed', '');
        $delete_ok = ($bulk_delete_confirmed === '1' || $bulk_delete_confirmed === 1 || $bulk_delete_confirmed === true);

        if ($bulk_action === 'delete' && !$delete_ok) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Debe confirmar la eliminación en Yuju antes de continuar.',
                'logs_url' => $logs_url,
                'errors' => [],
            ]);

            return;
        }

        if (empty($product_ids)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No se han seleccionado productos',
                'logs_url' => $logs_url,
                'errors' => [],
            ]);
            return;
        }

        // Eliminar productos en Yuju no depende del SKU en PrestaShop: usa yuju_product_id.
        $already_validated = (string) Tools::getValue('already_validated', '') === '1';

        // El modal ya verificó SKU y reglas de creación por producto. Evitar repetir
        // consultas costosas sobre todo el catálogo al recibir cada bloque validado.
        if ($bulk_action !== 'delete' && !$already_validated) {
            $sku_conflicts = $this->getDuplicateSkuConflictsForProductIds($product_ids);
            if (!empty($sku_conflicts)) {
                $this->respondJsonDuplicateSkuConflicts($sku_conflicts, $logs_url);

                return;
            }
        }

        $product_count = count($product_ids);

        // El flujo de validación previa siempre debe encolar: no ejecutar llamadas síncronas a Yuju
        // después de validar, aunque el usuario haya seleccionado 5 productos o menos.
        $force_queue = (string) Tools::getValue('force_queue', '') === '1';
        if ($product_count > 5 || $force_queue) {
            $sync_queue = new YujuSyncQueue();
            $queued_count = 0;
            $queued_product_ids = [];
            $waiting_ids = [];
            $skipped_count = 0;
            $error_count = 0;
            $errors = [];

            foreach ($product_ids as $product_id) {
                try {
                    $status = Db::getInstance()->getRow(
                        'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                        WHERE prestashop_product_id = ' . (int) $product_id
                    );
                    $resolved_yuju_id = $this->product_manager->resolveExistingYujuProductId((int) $product_id);
                    $has_yuju_link = $this->yujuStatusRowHasProductLink($status) || ($resolved_yuju_id !== '');

                    if ($bulk_action === 'delete') {
                        if (!$has_yuju_link) {
                            ++$skipped_count;

                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $product_id, false)) {
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': no válido o no encontrado en PrestaShop';

                            continue;
                        }
                        $result = $sync_queue->addToQueue((int) $product_id, 'delete', 'normal', []);
                    } elseif ($bulk_action === 'create' && !$has_yuju_link && !$already_validated) {
                        // Solo CREATE real si nunca existió en Yuju; el modal puede haberlo validado ya.
                        $validation = $this->product_manager->validateProductForYujuCreate((int) $product_id);
                        if (empty($validation['success'])) {
                            $validation_msg = implode(' ', $validation['errors']);
                            $this->product_manager->updateProductStatus((int) $product_id, 'error', $validation_msg, null);
                            $this->product_manager->logValidationErrorHistory((int) $product_id, $validation_msg, 'create');
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $validation_msg;

                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $product_id, true)) {
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': no válido o no encontrado en PrestaShop';

                            continue;
                        }
                        $result = $sync_queue->addToQueue((int) $product_id, 'create', 'normal', []);
                    } else {
                        // update (o "create" de producto YA existente): solo UPDATE con diff
                        if ($has_yuju_link && !$this->product_manager->productNeedsYujuUpdate((int) $product_id)) {
                            $this->product_manager->updateProductStatus(
                                (int) $product_id,
                                'synced',
                                null,
                                $resolved_yuju_id !== '' ? $resolved_yuju_id : null
                            );
                            ++$skipped_count;

                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $product_id, !$has_yuju_link)) {
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': no válido o no encontrado en PrestaShop';

                            continue;
                        }
                        if ($has_yuju_link) {
                            $payload = $this->product_manager->buildProductPayloadForYujuQueue((int) $product_id);
                            $payload = is_array($payload) ? $payload : [];
                            $result = $sync_queue->addToQueue((int) $product_id, 'update', 'normal', $payload);
                        } else {
                            $result = $sync_queue->addToQueue((int) $product_id, 'create', 'normal', []);
                        }
                    }

                    if ($result) {
                        if ($sync_queue->getLastOutcome() === 'waiting_webhook') {
                            // Ya enviado a Yuju y esperando webhook: no se encoló nada nuevo.
                            ++$skipped_count;
                            $waiting_ids[] = (int) $product_id;
                        } else {
                            ++$queued_count;
                            $queued_product_ids[] = (int) $product_id;
                        }
                    } else {
                        ++$error_count;
                        $detail = $sync_queue->getLastError();
                        $errors[] = 'Error agregando producto ID ' . (int) $product_id . ' a la cola'
                            . ($detail !== '' ? ': ' . $detail : '');
                    }
                } catch (Throwable $e) {
                    ++$error_count;
                    $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $e->getMessage();
                    error_log('[Yuju ProductStatus] Error en lote al encolar producto ' . (int) $product_id . ': ' . $e->getMessage());
                }
            }

            $message = sprintf(
                '%d producto(s) aceptado(s) en la cola. %d omitido(s). %d error(es).',
                $queued_count,
                $skipped_count,
                $error_count
            );

            // Registro del lote en AdminYujuLogs (verifica que el INSERT realmente se guarde).
            $log_result = $this->recordBulkRequestLog(
                $bulk_action,
                $product_ids,
                $queued_count,
                $skipped_count,
                $error_count,
                $errors,
                [
                    'event' => 'bulk_products_queued',
                    'queued_product_ids' => array_values(array_map('intval', $queued_product_ids)),
                    'waiting_webhook_ids' => array_values(array_map('intval', $waiting_ids)),
                    'note' => 'Productos aceptados en cola; el envío real a Yuju ocurre al procesar la cola.',
                ]
            );
            if (!empty($waiting_ids)) {
                $message .= ' ' . count($waiting_ids) . ' ya estaban enviados y esperan confirmación (webhook) de Yuju.';
            }
            if ($bulk_action === 'delete' && $skipped_count > 0) {
                $message .= ' Omitidos: sin ID Yuju (no hay nada que borrar en marketplaces).';
            }
            if ($queued_count > 0) {
                $message .= ' Se procesarán en el próximo ciclo del cron (cada 5 minutos).';
            }

            header('Content-Type: application/json');
            if ($error_count > 0 && !empty($errors)) {
                $message .= "\n\n" . implode("\n", $errors);
            }
            echo json_encode([
                'success' => ($queued_count > 0 || $skipped_count > 0),
                'message' => $message,
                'logs_url' => $logs_url,
                'errors' => $errors,
                'log_saved' => $log_result['saved'],
                'log_error' => $log_result['error'],
                'log_id' => (int) ($log_result['id'] ?? 0),
                'details' => [
                    'queued' => $queued_count,
                    'skipped' => $skipped_count,
                    'errors' => $error_count,
                    'error_messages' => $errors,
                    'bulk_action' => $bulk_action,
                ],
                'reload' => true,
            ]);

            return;
        }

        // 5 o menos: inmediato
        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $created_count = 0;
        $updated_count = 0;
        $synced_no_diff_count = 0;
        $errors = [];

        foreach ($product_ids as $product_id) {
            try {
                $status = Db::getInstance()->getRow(
                    'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                    WHERE prestashop_product_id = ' . (int) $product_id
                );
                $resolved_yuju_id = $this->product_manager->resolveExistingYujuProductId((int) $product_id);
                $has_yuju_link = $this->yujuStatusRowHasProductLink($status) || ($resolved_yuju_id !== '');

                if ($bulk_action === 'delete') {
                    if (!$has_yuju_link) {
                        ++$skipped_count;

                        continue;
                    }
                    $del = $this->product_manager->removeProductFromYujuAndLocalStatus((int) $product_id);
                    if (!empty($del['success'])) {
                        ++$success_count;
                    } else {
                        ++$error_count;
                        $errors[] = 'Producto ID ' . (int) $product_id . ': ' . ($del['message'] ?? 'Error al eliminar');
                    }

                    continue;
                }

                // create solo si NO existe en Yuju; si ya existe → sendProductToYuju hará UPDATE+diff
                if ($bulk_action === 'create' && !$has_yuju_link) {
                    $validation = $this->product_manager->validateProductForYujuCreate((int) $product_id);
                    if (empty($validation['success'])) {
                        $validation_msg = implode(' ', $validation['errors']);
                        $this->product_manager->updateProductStatus((int) $product_id, 'error', $validation_msg, null);
                        $this->product_manager->logValidationErrorHistory((int) $product_id, $validation_msg, 'create');
                        ++$error_count;
                        $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $validation_msg;

                        continue;
                    }
                }
                $result = $this->product_manager->sendProductToYuju((int) $product_id, ['origin' => 'bulk']);
                $ok = is_array($result) && !empty($result['success']);
                if ($ok) {
                    ++$success_count;
                    $actionDone = isset($result['action']) ? (string) $result['action'] : '';
                    if (!empty($result['skipped_no_diff'])) {
                        ++$synced_no_diff_count;
                    } elseif ($actionDone === 'create') {
                        ++$created_count;
                    } elseif ($actionDone === 'update') {
                        ++$updated_count;
                    }
                } else {
                    ++$error_count;
                    $err_detail = is_array($result)
                        ? ($result['error'] ?? $result['message'] ?? 'Error desconocido')
                        : 'Respuesta inválida del gestor de productos';
                    $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $err_detail;
                }
            } catch (Exception $e) {
                ++$error_count;
                $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $e->getMessage();
            }
        }

        $parts = [];
        if ($created_count > 0) {
            $parts[] = $created_count . ' creado(s)';
        }
        if ($updated_count > 0) {
            $parts[] = $updated_count . ' actualizado(s)';
        }
        if ($synced_no_diff_count > 0) {
            $parts[] = $synced_no_diff_count . ' ya sincronizado(s) (sin cambios)';
        }
        if ($skipped_count > 0) {
            $parts[] = $skipped_count . ' omitido(s)';
        }
        if ($error_count > 0) {
            $parts[] = $error_count . ' error(es)';
        }
        $message = $parts !== []
            ? implode('. ', $parts) . '.'
            : sprintf(
                '%d producto(s) procesado(s) correctamente. %d omitido(s). %d error(es).',
                $success_count,
                $skipped_count,
                $error_count
            );
        if ($bulk_action === 'delete' && $skipped_count > 0) {
            $message .= ' Omitidos: sin ID Yuju.';
        }
        if ($error_count > 0 && !empty($errors)) {
            $message .= "\n\n" . implode("\n", $errors);
        }

        $log_result = $this->recordBulkRequestLog(
            $bulk_action,
            $product_ids,
            $success_count,
            $skipped_count,
            $error_count,
            $errors,
            [
                'event' => 'bulk_products_direct',
                'created' => $created_count,
                'updated' => $updated_count,
                'synced_no_diff' => $synced_no_diff_count,
                'note' => 'Envío directo a Yuju (sin cola).',
            ]
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => ($success_count > 0 || $skipped_count > 0 || $synced_no_diff_count > 0) && $error_count == 0,
            'message' => $message,
            'logs_url' => $logs_url,
            'errors' => $errors,
            'log_saved' => $log_result['saved'],
            'log_error' => $log_result['error'],
            'log_id' => (int) ($log_result['id'] ?? 0),
            'details' => [
                'success' => $success_count,
                'created' => $created_count,
                'updated' => $updated_count,
                'synced_no_diff' => $synced_no_diff_count,
                'skipped' => $skipped_count,
                'errors' => $error_count,
                'error_messages' => $errors,
                'bulk_action' => $bulk_action,
            ],
            'reload' => true,
        ]);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $trace = '';
            if (defined('_PS_MODE_DEV_') && constant('_PS_MODE_DEV_')) {
                $trace = $e->getTraceAsString();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Error interno al procesar la petición: ' . $e->getMessage(),
                'logs_url' => $logs_url,
                'errors' => [$e->getMessage()],
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $trace,
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Registra y verifica el lote en yuju_sync_logs (AdminYujuLogs).
     *
     * @return array{saved: bool, error: string, id: int}
     */
    protected function recordBulkRequestLog($bulk_action, array $product_ids, $ok_count, $skipped_count, $error_count, array $errors, array $details = [])
    {
        try {
            $ok_count = (int) $ok_count;
            $skipped_count = (int) $skipped_count;
            $error_count = (int) $error_count;
            $now = date('Y-m-d H:i:s');
            $employee = isset($this->context->employee) && Validate::isLoadedObject($this->context->employee)
                ? (string) $this->context->employee->email
                : 'admin';

            $db = Db::getInstance();
            $inserted = (bool) $db->insert('yuju_sync_logs', [
                'sync_type' => 'manual',
                'entity_type' => 'products',
                'sync_direction' => 'prestashop_to_yuju',
                'status' => ($ok_count > 0 || $skipped_count > 0) ? 'completed' : 'failed',
                'total_items' => count($product_ids),
                'processed_items' => $ok_count + $skipped_count + $error_count,
                'success_items' => $ok_count + $skipped_count,
                'error_items' => $error_count,
                'start_time' => $now,
                'end_time' => $now,
                'duration' => 0,
                'error_message' => $error_count > 0
                    ? pSQL(Tools::substr(implode("\n", $errors), 0, 5000), true)
                    : '',
                'details' => pSQL(json_encode(array_merge([
                    'bulk_action' => $bulk_action,
                    'ok_count' => $ok_count,
                    'skipped_count' => $skipped_count,
                    'error_count' => $error_count,
                    'errors' => array_slice($errors, 0, 50),
                ], $details), JSON_UNESCAPED_UNICODE), true),
                'created_by' => pSQL($employee),
            ]);

            $logId = (int) $db->Insert_ID();
            if (!$inserted || $logId <= 0) {
                $dbError = (string) $db->getMsgError();
                $detail = $dbError !== '' ? $dbError : 'Db::insert no confirmó el ID del registro';
                error_log('[Yuju ProductStatus] No se pudo registrar el lote en yuju_sync_logs: ' . $detail);
                return ['saved' => false, 'error' => $detail, 'id' => 0];
            }

            // Confirmar que AdminYujuLogs pueda leer el registro insertado.
            // Db::getValue() ya limita internamente a una fila; no agregar LIMIT 1 aquí.
            $confirmedId = (int) $db->getValue(
                'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_sync_logs WHERE id = ' . $logId
            );
            if ($confirmedId !== $logId) {
                $dbError = (string) $db->getMsgError();
                $detail = 'INSERT devolvió el ID ' . $logId . ', pero no se pudo confirmar su lectura.'
                    . ($dbError !== '' ? ' SQL: ' . $dbError : '');
                error_log('[Yuju ProductStatus] ' . $detail);
                return ['saved' => false, 'error' => $detail, 'id' => 0];
            }

            return ['saved' => true, 'error' => '', 'id' => $logId];
        } catch (Throwable $e) {
            error_log('[Yuju ProductStatus] Excepción al registrar el lote en yuju_sync_logs: ' . $e->getMessage());
            return ['saved' => false, 'error' => $e->getMessage(), 'id' => 0];
        }
    }
    /**
     * Comprueba si la fila de estado del producto contiene una referencia válida a Yuju.
     *
     * @param array|false $status_row Fila de yuju_product_status o false
     * @return bool
     */
    protected function yujuStatusRowHasProductLink($status_row)
    {
        if (!$status_row || !isset($status_row['yuju_product_id'])) {
            return false;
        }

        $yuju_pid = trim((string) $status_row['yuju_product_id']);

        return $yuju_pid !== '' && strtolower($yuju_pid) !== 'null' && $yuju_pid !== '0';
    }

    /**
     * Referencias (SKU) que en PrestaShop están repetidas en más de un id_product,
     * cuando al menos uno de los IDs solicitados usa esa referencia (tras TRIM).
     *
     * @param int[] $product_ids
     *
     * @return array<int, array{reference: string, products: list<array{id_product: int, name: string}>}>
     */
    protected function getDuplicateSkuConflictsForProductIds(array $product_ids)
    {
        return $this->product_manager->getDuplicateSkuConflictsForProductIds($product_ids);
    }

    /**
     * @param array<int, array{reference: string, products: list<array{id_product: int, name: string}>}> $conflicts
     */
    protected function respondJsonDuplicateSkuConflicts(array $conflicts, $logs_url)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'No se puede continuar: hay la misma referencia (SKU) en varios productos de PrestaShop. Cada SKU debe ser único antes de enviar a Yuju.',
            'error_code' => 'duplicate_prestashop_sku',
            'duplicate_sku_conflicts' => $conflicts,
            'logs_url' => $logs_url,
            'errors' => [],
            'details' => [
                'duplicate_sku_conflicts' => $conflicts,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Garantiza fila en yuju_product_status antes de encolar.
     * No degrada a queued si el producto ya tiene ID Yuju.
     *
     * @param int  $product_id
     * @param bool $markAsQueued
     *
     * @return bool false si el producto PrestaShop no existe
     */
    protected function ensureProductQueuedRowForYuju($product_id, $markAsQueued = true)
    {
        $product_id = (int) $product_id;
        $product = new Product($product_id, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }

        $row = Db::getInstance()->getRow(
            'SELECT id, yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
            WHERE prestashop_product_id = ' . $product_id
        );

        $hasYujuId = false;
        if ($row && isset($row['yuju_product_id'])) {
            $yujuPid = trim((string) $row['yuju_product_id']);
            $hasYujuId = ($yujuPid !== '' && strtolower($yujuPid) !== 'null' && $yujuPid !== '0');
        }

        $shouldQueueStatus = $markAsQueued && !$hasYujuId;

        if (!$row) {
            Db::getInstance()->insert('yuju_product_status', [
                'prestashop_product_id' => $product_id,
                'sync_status' => pSQL($shouldQueueStatus ? 'queued' : 'pending'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif ($shouldQueueStatus) {
            Db::getInstance()->update(
                'yuju_product_status',
                [
                    'sync_status' => pSQL('queued'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'prestashop_product_id = ' . $product_id
            );
        }

        return true;
    }
    
    protected function ajaxProcessSendAllFiltered()
    {
        $filters = Tools::getValue('filters', []);
        if (!is_array($filters)) {
            $filters = [];
        }

        // Override $_GET for getProductsWithFilters (mismos nombres que la vista)
        $_GET['store'] = isset($filters['store']) ? (string) $filters['store'] : '';
        $_GET['ps_category'] = isset($filters['ps_category']) ? (string) $filters['ps_category'] : '';
        $_GET['yuju_category'] = isset($filters['yuju_category']) ? (string) $filters['yuju_category'] : '';
        $_GET['status'] = isset($filters['status']) ? (string) $filters['status'] : '';
        $_GET['search'] = isset($filters['search']) ? (string) $filters['search'] : '';
        $_GET['yuju_id'] = isset($filters['yuju_id']) ? (string) $filters['yuju_id'] : '';
        $_GET['date_from'] = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        $_GET['date_to'] = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        $_GET['show_all'] = isset($filters['show_all']) ? (string) $filters['show_all'] : '';
        $_GET['bulk_filter_id'] = isset($filters['bulk_filter_id']) ? (string) $filters['bulk_filter_id'] : '';
        $_GET['bulk_search'] = isset($filters['bulk_search']) ? (string) $filters['bulk_search'] : '';
        if (isset($filters['per_page']) && $filters['per_page'] !== '') {
            $_GET['per_page'] = (string) $filters['per_page'];
        }

        if (!$this->hasActiveProductStatusFilters()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No hay filtros activos. Aplique al menos un filtro antes de enviar el conjunto filtrado.',
                'logs_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
                'errors' => [],
            ]);

            return;
        }

        if ($this->module && method_exists($this->module, 'ensureSyncHistoryTable')) {
            $this->module->ensureSyncHistoryTable();
        }

        $logs_url = $this->context->link->getAdminLink('AdminYujuLogs', true);

        // Get all filtered products (without pagination)
        $result = $this->getProductsWithFilters(1, 999999);
        $products = $result['products'];
        
        if (empty($products)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No hay productos que coincidan con los filtros',
                'logs_url' => $logs_url,
                'errors' => [],
            ]);
            return;
        }

        $filtered_ids = [];
        foreach ($products as $product) {
            if (!empty($product['id_product'])) {
                $filtered_ids[] = (int) $product['id_product'];
            }
        }
        $filtered_ids = array_values(array_unique(array_filter($filtered_ids, static function ($id) {
            return $id > 0;
        })));
        $sku_conflicts = $this->getDuplicateSkuConflictsForProductIds($filtered_ids);
        if (!empty($sku_conflicts)) {
            $this->respondJsonDuplicateSkuConflicts($sku_conflicts, $logs_url);

            return;
        }

        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                // Verificar si el producto ya tiene ID de Yuju
                if (!empty($product['yuju_product_id'])) {
                    // Producto ya existe en Yuju, no lo enviamos
                    ++$skipped_count;
                    continue;
                }

                $validation = $this->product_manager->validateProductForYujuCreate((int) $product['id_product']);
                if (empty($validation['success'])) {
                    $validation_msg = implode(' ', $validation['errors']);
                    $this->product_manager->updateProductStatus((int) $product['id_product'], 'error', $validation_msg, null);
                    $this->product_manager->logValidationErrorHistory((int) $product['id_product'], $validation_msg, 'create');
                    ++$error_count;
                    $errors[] = 'Producto ID ' . (int) $product['id_product'] . ': ' . $validation_msg;

                    continue;
                }

                $result = $this->product_manager->sendProductToYuju((int) $product['id_product'], ['origin' => 'bulk']);
                $ok = is_array($result) && !empty($result['success']);
                if ($ok) {
                    ++$success_count;
                } else {
                    ++$error_count;
                    $pid = (int) $product['id_product'];
                    $err_detail = is_array($result)
                        ? ($result['error'] ?? $result['message'] ?? 'Error desconocido')
                        : 'Respuesta inválida del gestor de productos';
                    $errors[] = 'Producto ID ' . $pid . ': ' . $err_detail;
                }
            } catch (Exception $e) {
                ++$error_count;
                $errors[] = 'Producto ID ' . (int) $product['id_product'] . ': ' . $e->getMessage();
            }
        }

        $msg = sprintf(
            'Proceso completado: %d enviado(s), %d omitido(s) (ya en Yuju), %d error(es).',
            $success_count,
            $skipped_count,
            $error_count
        );
        if ($error_count > 0 && !empty($errors)) {
            $msg .= "\n\n" . implode("\n", $errors);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => ($success_count > 0 || $skipped_count > 0) && $error_count == 0,
            'message' => $msg,
            'logs_url' => $logs_url,
            'errors' => $errors,
            'details' => [
                'total' => count($products),
                'success' => $success_count,
                'skipped' => $skipped_count,
                'errors' => $error_count,
                'error_messages' => $errors,
            ],
        ]);
    }

    protected function processBulkEnableSync()
    {
        $product_ids = Tools::getValue($this->table . 'Box');

        if (empty($product_ids)) {
            $this->errors[] = 'No hay productos seleccionados';

            return;
        }

        $success_count = 0;

        foreach ($product_ids as $id) {
            if ($this->enableProductSync($id)) {
                ++$success_count;
            }
        }

        $this->confirmations[] = sprintf('Sincronización habilitada para %d productos', $success_count);
    }

    protected function processBulkDisableSync()
    {
        $product_ids = Tools::getValue($this->table . 'Box');

        if (empty($product_ids)) {
            $this->errors[] = 'No hay productos seleccionados';

            return;
        }

        $success_count = 0;

        foreach ($product_ids as $id) {
            if ($this->disableProductSync($id)) {
                ++$success_count;
            }
        }

        $this->confirmations[] = sprintf('Sincronización deshabilitada para %d productos', $success_count);
    }

    protected function processBulkSyncSelected()
    {
        $product_ids = Tools::getValue($this->table . 'Box');

        if (empty($product_ids)) {
            $this->errors[] = 'No hay productos seleccionados';

            return;
        }

        try {
            $prestashop_product_ids = [];

            foreach ($product_ids as $status_id) {
                $status = Db::getInstance()->getRow(
                    '
                SELECT prestashop_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status
                WHERE id = ' . (int) $status_id
                );

                if ($status) {
                    $prestashop_product_ids[] = $status['prestashop_product_id'];
                }
            }

            if (!empty($prestashop_product_ids)) {
                $sku_conflicts = $this->getDuplicateSkuConflictsForProductIds($prestashop_product_ids);
                if (!empty($sku_conflicts)) {
                    $this->errors[] = 'No se puede sincronizar: hay referencias (SKU) duplicadas en PrestaShop. Corrija el catálogo antes de continuar.';
                    foreach ($sku_conflicts as $c) {
                        $ids = [];
                        foreach ($c['products'] as $p) {
                            $ids[] = '#' . (int) $p['id_product'];
                        }
                        $this->errors[] = 'SKU "' . $c['reference'] . '": ' . implode(', ', $ids);
                    }

                    return;
                }

                $results = $this->product_manager->syncSpecificProducts($prestashop_product_ids);

                if ($results['success']) {
                    $this->confirmations[] = sprintf(
                        'Se sincronizaron %d productos exitosamente',
                        $results['synced_count']
                    );
                } else {
                    $this->errors[] = 'Error en la sincronización: ' . implode(', ', $results['errors']);
                }
            }
        } catch (Exception $e) {
            $this->errors[] = 'Error de sincronización: ' . $e->getMessage();
        }
    }

    protected function processBulkResetErrors()
    {
        $product_ids = Tools::getValue($this->table . 'Box');

        if (empty($product_ids)) {
            $this->errors[] = 'No hay productos seleccionados';

            return;
        }

        $success_count = 0;

        foreach ($product_ids as $id) {
            if ($this->resetProductErrors($id)) {
                ++$success_count;
            }
        }

        $this->confirmations[] = sprintf('Errores reiniciados para %d productos', $success_count);
    }
    
    /**
     * Process delete action - Elimina el producto de Yuju Y el registro de estado
     * Elimina el producto de Yuju usando la API y luego elimina los registros locales
     */
    public function processDelete()
    {
        $id = (int) Tools::getValue('id');
        
        if (!$id) {
            $this->errors[] = 'ID de registro no válido';
            return false;
        }
        
        // Obtener información del registro antes de eliminarlo
        $status_record = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status 
            WHERE id = ' . (int)$id
        );
        
        if (!$status_record) {
            $this->errors[] = 'Registro no encontrado';
            return false;
        }
        
        $prestashop_product_id = (int)$status_record['prestashop_product_id'];
        $yuju_product_id = $status_record['yuju_product_id'];
        
        $logger = new YujuLogger();
        $deleted_from_yuju = false;
        
        // Si tiene ID de Yuju, intentar eliminar de Yuju primero
        if (!empty($yuju_product_id)) {
            try {
                $api_client = new YujuApiClient();
                
                $logger->log(
                    'Intentando eliminar producto de Yuju: ID Yuju=' . $yuju_product_id . ', PrestaShop ID=' . $prestashop_product_id,
                    'info'
                );
                
                $start_time = microtime(true);
                $result = $api_client->deleteProduct($yuju_product_id);
                $sync_duration = microtime(true) - $start_time;
                
                // Log detallado de la respuesta
                $logger->log(
                    'Respuesta de eliminación de Yuju: ' . json_encode($result),
                    'info'
                );
                
                if ($result['success']) {
                    $deleted_from_yuju = true;
                    $logger->log(
                        'Producto eliminado de Yuju exitosamente: ID Yuju=' . $yuju_product_id . ', PrestaShop ID=' . $prestashop_product_id,
                        'info'
                    );
                } else {
                    // Si falla, registrar el error pero NO mostrar mensajes al usuario
                    $error_msg = 'Error al eliminar de Yuju (HTTP ' . $result['http_code'] . '): ' . 
                                ($result['message'] ?? 'Error desconocido');
                    $logger->log($error_msg . ' - Respuesta completa: ' . json_encode($result), 'warning');
                }
            } catch (Exception $e) {
                $logger->log('Excepción al eliminar de Yuju: ' . $e->getMessage(), 'error');
                $sync_duration = 0;
            }
        }
        
        // Solo eliminar el registro de estado local si se eliminó exitosamente de Yuju
        // O si no tenía ID de Yuju
        if ($deleted_from_yuju || empty($yuju_product_id)) {
            $result = Db::getInstance()->delete(
                'yuju_product_status',
                'id = ' . (int)$id
            );
        } else {
            // Si falló la eliminación de Yuju, MANTENER yuju_product_id y marcar como synced_with_errors
            // Esto permite reintentar la eliminación sin perder la referencia
            $result = Db::getInstance()->update(
                'yuju_product_status',
                [
                    'sync_status' => pSQL('synced_with_errors'),
                    // NO actualizar yuju_product_id - mantenerlo para reintentos de eliminación
                    'last_error' => pSQL('No se pudo eliminar de Yuju. El producto aún existe en Yuju.'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ' . (int)$id
            );
        }
        
        if ($result) {
            // Agregar entrada al historial indicando la eliminación
            $request_data = [
                'method' => 'DELETE',
                'url' => 'https://api.tp.yuju.io/products/' . $yuju_product_id,
                'yuju_product_id' => $yuju_product_id,
                'deleted_from_yuju' => $deleted_from_yuju
            ];
            
            $insert_result = Db::getInstance()->insert('yuju_product_sync_history', [
                'prestashop_product_id' => (int)$prestashop_product_id,
                'action' => pSQL('delete'),
                'status' => pSQL($deleted_from_yuju ? 'success' : 'error'),
                'http_status_code' => isset($result['http_code']) ? (int)$result['http_code'] : ($deleted_from_yuju ? 200 : 403),
                'request_data' => pSQL(json_encode($request_data)),
                'response_data' => isset($result) ? pSQL(json_encode($result)) : pSQL(json_encode([
                    'message' => $deleted_from_yuju ? 'Eliminado de Yuju y registro local' : 'Error al eliminar de Yuju',
                    'timestamp' => date('Y-m-d H:i:s')
                ])),
                'error_message' => $deleted_from_yuju ? null : pSQL(isset($result['message']) ? $result['message'] : 'Error al eliminar de Yuju'),
                'sync_duration' => isset($sync_duration) ? $sync_duration : 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$insert_result) {
                $logger->log('Error al insertar historial de eliminación: ' . Db::getInstance()->getMsgError(), 'error');
            }
            
            if ($deleted_from_yuju) {
                $this->confirmations[] = 'Producto eliminado correctamente.';
            } else {
                $this->confirmations[] = 'Estado actualizado. El producto se mantiene con errores.';
            }
            
            $logger->log(
                'Estado de sincronización procesado: ID=' . $id . ', Product ID=' . $prestashop_product_id . 
                ', Eliminado de Yuju: ' . ($deleted_from_yuju ? 'SI' : 'NO'),
                'info'
            );
            
            return true;
        } else {
            $this->errors[] = 'Error al actualizar/eliminar el registro de estado local';
            return false;
        }
    }

    protected function processSyncAll()
    {
        if ($this->sync_manager->isSyncRunning()) {
            $this->errors[] = 'La sincronización ya está en ejecución. Por favor espere a que termine.';

            return;
        }

        try {
            $results = $this->sync_manager->executeFullSync('bidirectional', false);

            if ($results['success']) {
                $this->confirmations[] = sprintf(
                    'Sincronización completa exitosa. Productos: %d, Tiempo: %d segundos',
                    isset($results['products']['synced_count']) ? $results['products']['synced_count'] : 0,
                    $results['total_time']
                );
            } else {
                $this->errors[] = 'Error en la sincronización: ' . implode(', ', $results['errors']);
            }
        } catch (Exception $e) {
            $this->errors[] = 'Error de sincronización: ' . $e->getMessage();
        }
    }

    protected function processSyncSingle()
    {
        $status_id = (int) Tools::getValue('id');

        if (!$status_id) {
            $this->errors[] = 'ID de estado de producto inválido';

            return;
        }

        try {
            $status = Db::getInstance()->getRow(
                '
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE id = ' . (int) $status_id
            );

            if (!$status) {
                $this->errors[] = 'Estado de producto no encontrado';

                return;
            }

            $prestashop_product_id = (int) $status['prestashop_product_id'];
            $sku_conflicts = $this->getDuplicateSkuConflictsForProductIds([$prestashop_product_id]);
            if (!empty($sku_conflicts)) {
                $this->errors[] = 'No se puede sincronizar: la referencia (SKU) de este producto está duplicada en PrestaShop.';
                foreach ($sku_conflicts as $c) {
                    $ids = [];
                    foreach ($c['products'] as $p) {
                        $ids[] = '#' . (int) $p['id_product'];
                    }
                    $this->errors[] = 'SKU "' . $c['reference'] . '": ' . implode(', ', $ids);
                }

                return;
            }

            $results = $this->product_manager->syncSpecificProducts([$prestashop_product_id]);

            if ($results['success']) {
                $this->confirmations[] = 'Producto sincronizado exitosamente';
            } else {
                $this->errors[] = 'Error en la sincronización del producto: ' . implode(', ', $results['errors']);
            }
        } catch (Exception $e) {
            $this->errors[] = 'Error de sincronización: ' . $e->getMessage();
        }
    }

    protected function processImportStatus()
    {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'Por favor seleccione un archivo CSV válido';

            return;
        }

        try {
            $file_path = $_FILES['import_file']['tmp_name'];
            $imported_count = $this->importProductStatus($file_path);

            $this->confirmations[] = sprintf('Se importaron %d registros de estado de producto', $imported_count);
        } catch (Exception $e) {
            $this->errors[] = 'Error de importación: ' . $e->getMessage();
        }
    }

    protected function processExportStatus()
    {
        try {
            $export_file = $this->exportProductStatus();

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="yuju_product_status_' . date('Y-m-d_H-i-s') . '.csv"');
            header('Content-Length: ' . filesize($export_file));

            readfile($export_file);
            unlink($export_file);
            exit;
        } catch (Exception $e) {
            $this->errors[] = 'Error de exportación: ' . $e->getMessage();
        }
    }

    protected function enableProductSync($status_id)
    {
        return Db::getInstance()->update(
            'yuju_product_status',
            ['sync_enabled' => 1, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $status_id
        );
    }

    protected function disableProductSync($status_id)
    {
        return Db::getInstance()->update(
            'yuju_product_status',
            ['sync_enabled' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $status_id
        );
    }

    protected function resetProductErrors($status_id)
    {
        return Db::getInstance()->update(
            'yuju_product_status',
            [
        'error_count' => 0,
        'last_error_message' => '',
        'updated_at' => date('Y-m-d H:i:s'),
        ],
            'id = ' . (int) $status_id
        );
    }

    protected function getProductStatusStats()
    {
        // Un solo recorrido para todos los contadores de estado.
        $row = Db::getInstance()->getRow('
            SELECT
                COUNT(*) AS total,
                SUM(sync_status = "synced") AS synced,
                SUM(sync_status = "synced_with_warnings") AS synced_with_warnings,
                SUM(sync_status = "pending") AS pending,
                SUM(sync_status = "error") AS error,
                SUM(sync_status = "disabled") AS disabled,
                SUM(sync_status = "queued") AS queued,
                SUM(sync_enabled = 1) AS active,
                SUM(sync_enabled = 0 OR sync_enabled IS NULL) AS inactive,
                SUM(error_count > 0 OR sync_status IN ("error", "synced_with_warnings")) AS with_errors,
                SUM(last_sync_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS recent_syncs
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');

        $stats = [];
        foreach ([
            'total', 'synced', 'synced_with_warnings', 'pending', 'error',
            'disabled', 'queued', 'active', 'inactive', 'with_errors', 'recent_syncs'
        ] as $key) {
            $stats[$key] = (int) ($row[$key] ?? 0);
        }

        // Conservar el desglose por estado que consume la plantilla.
        $stats['by_status'] = Db::getInstance()->executeS('
            SELECT sync_status, COUNT(*) AS count
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
            GROUP BY sync_status
        ');

        // Un solo agregado para la cola, sin SHOW TABLES en cada carga.
        try {
            $queue = Db::getInstance()->getRow('
                SELECT
                    COUNT(*) AS total,
                    SUM(status = "pending") AS pending,
                    SUM(status = "processing") AS processing,
                    SUM(status = "completed") AS completed,
                    SUM(status = "failed") AS failed
                FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
            ');
            $stats['queue'] = [
                'total' => (int) ($queue['total'] ?? 0),
                'pending' => (int) ($queue['pending'] ?? 0),
                'processing' => (int) ($queue['processing'] ?? 0),
                'completed' => (int) ($queue['completed'] ?? 0),
                'failed' => (int) ($queue['failed'] ?? 0),
            ];
        } catch (Exception $e) {
            $stats['queue'] = ['total' => 0, 'pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        }

        return $stats;
    }

    protected function importProductStatus($file_path)
    {
        $imported_count = 0;

        if (($handle = fopen($file_path, 'r')) !== false) {
            // Skip header row
            fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 4) {
                    $prestashop_product_id = (int) $data[0];
                    $yuju_product_id = $data[1];
                    $sync_status = $data[2];
                    $sync_enabled = (int) $data[3];

                    // Check if product exists
                    $product = new Product($prestashop_product_id);

                    if (!Validate::isLoadedObject($product)) {
                        continue;
                    }

                    // Check if status already exists
                    $existing = Db::getInstance()->getRow(
                        '
                    SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status
                    WHERE prestashop_product_id = ' . (int) $prestashop_product_id
                    );

                    $status_data = [
                    'prestashop_product_id' => $prestashop_product_id,
                    'yuju_product_id' => pSQL($yuju_product_id),
                    'sync_status' => pSQL($sync_status),
                    'sync_enabled' => $sync_enabled,
                    'updated_at' => date('Y-m-d H:i:s'),
                    ];

                    if ($existing) {
                        Db::getInstance()->update('yuju_product_status', $status_data, 'id = ' . (int) $existing['id']);
                    } else {
                        $status_data['created_at'] = date('Y-m-d H:i:s');
                        Db::getInstance()->insert('yuju_product_status', $status_data);
                    }

                    ++$imported_count;
                }
            }

            fclose($handle);
        }

        return $imported_count;
    }

    protected function exportProductStatus()
    {
        $export_file = tempnam(sys_get_temp_dir(), 'yuju_product_status_');

        $handle = fopen($export_file, 'w');

        // Write header
        fputcsv($handle, [
        'PrestaShop Product ID',
        'PrestaShop Combination ID',
        'Product Name',
        'Yuju Product ID',
        'Yuju Parent Product ID',
        'Sync Status',
        'Last Sync Date',
        'Error Count',
        'Last Error Message',
        'Sync Enabled',
        'Created At',
        'Updated At',
        ]);

        // Get all product status records
        $records = Db::getInstance()->executeS('
        SELECT ps.*, pl.name as product_name
        FROM ' . _DB_PREFIX_ . 'yuju_product_status ps
        LEFT JOIN ' . _DB_PREFIX_ . 'product ype ON (ype.id_product = ps.prestashop_product_id)
        LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON ps.prestashop_product_id = pl.id_product
            AND pl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
            AND pl.id_shop = ype.id_shop_default
        ORDER BY ps.created_at DESC
        ');

        foreach ($records as $record) {
            fputcsv($handle, [
            $record['prestashop_product_id'],
            $record['prestashop_product_attribute_id'] ?? '',
            $record['product_name'],
            $record['yuju_product_id'],
            $record['parent_yuju_product_id'] ?? '',
            $record['sync_status'],
            $record['last_sync_at'],
            $record['error_count'],
            $record['last_error_message'],
            $record['sync_enabled'],
            $record['created_at'],
            $record['updated_at'],
            ]);
        }

        fclose($handle);

        return $export_file;
    }

    public function getProductName($value, $row)
    {
        if (isset($row['product_name']) && !empty($row['product_name'])) {
            return $row['product_name'];
        }

        $product = new Product($row['prestashop_product_id'], false, $this->context->language->id);

        return Validate::isLoadedObject($product) ? $product->name : 'Producto no encontrado';
    }

    public function displaySyncStatus($value, $row)
    {
        $status_colors = [
            'synced' => 'success',
            'synced_with_warnings' => 'warning',
            'synced_with_errors' => 'danger',
            'pending' => 'warning',
            'error' => 'danger',
            'disabled' => 'default',
            'queued' => 'info',
            'creating_in_yuju' => 'info',
            'updating_in_yuju' => 'warning',
            'deleting_in_yuju' => 'warning',
        ];
        
        $status_icons = [
            'synced' => 'icon-check',
            'synced_with_warnings' => 'icon-exclamation-triangle',
            'synced_with_errors' => 'icon-exclamation-circle',
            'pending' => 'icon-clock-o',
            'error' => 'icon-exclamation-circle',
            'disabled' => 'icon-ban',
            'queued' => 'icon-list',
            'creating_in_yuju' => 'icon-time',
            'updating_in_yuju' => 'icon-refresh',
            'deleting_in_yuju' => 'icon-trash',
        ];
        
        $status_labels = [
            'synced' => 'Sincronizado',
            'synced_with_warnings' => 'Con Advertencias',
            'synced_with_errors' => 'Sincronizado (con errores)',
            'pending' => 'Pendiente',
            'error' => 'Error',
            'disabled' => 'Deshabilitado',
            'queued' => 'En Cola',
            'creating_in_yuju' => 'En espera de respuesta',
            'updating_in_yuju' => 'Actualizando',
            'deleting_in_yuju' => 'Eliminando',
        ];

        $color = isset($status_colors[$value]) ? $status_colors[$value] : 'default';
        $icon = isset($status_icons[$value]) ? $status_icons[$value] : 'icon-question';
        $label = isset($status_labels[$value]) ? $status_labels[$value] : ucfirst($value);
        
        // Evitar consultas por fila (SHOW TABLES + búsqueda y posición en cola).
        // La etiqueta conserva el estado sin calcular la posición en tiempo de render.
        // Si es synced_with_errors, mostrar badge verde + ícono rojo
        if ($value === 'synced_with_errors') {
            $tooltip = !empty($row['last_error']) ? 'title="' . htmlspecialchars($row['last_error']) . '"' : '';
            $product = new Product((int)$row['prestashop_product_id'], false, $this->context->language->id);
            $product_name = isset($product->name) ? addslashes($product->name) : '';
            $category = new Category($product->id_category_default, $this->context->language->id);
            $category_name = isset($category->name) ? addslashes($category->name) : '';
            $id_image = Product::getCover((int)$row['prestashop_product_id']);
            $id_image_val = $id_image ? $id_image['id_image'] : '';
            
            return '<span class="label label-success"><i class="icon-check"></i> Sincronizado</span> ' .
                   '<i class="icon-exclamation-circle" style="color: #d9534f; cursor: pointer; margin-left: 5px;" ' .
                   'onclick="showProductInfo(' . (int)$row['prestashop_product_id'] . ', \'' . $product->reference . '\', \'' . 
                   $product_name . '\', \'' . $category_name . '\', \'' . $id_image_val . '\', true)" ' . $tooltip . '></i>';
        }
        
        // Si hay advertencias, hacer el badge clickeable para abrir el historial
        if ($value === 'synced_with_warnings' || $value === 'error') {
            $product = new Product((int)$row['prestashop_product_id'], false, $this->context->language->id);
            $product_name = isset($product->name) ? addslashes($product->name) : '';
            $category = new Category($product->id_category_default, $this->context->language->id);
            $category_name = isset($category->name) ? addslashes($category->name) : '';
            $id_image = Product::getCover((int)$row['prestashop_product_id']);
            $id_image_val = $id_image ? $id_image['id_image'] : '';
            
            $clickable = 'style="cursor: pointer;" onclick="showProductInfo(' . (int)$row['prestashop_product_id'] . ', \'' . 
                        $product->reference . '\', \'' . $product_name . '\', \'' . $category_name . '\', \'' . 
                        $id_image_val . '\', true)"';
        } else {
            $clickable = '';
        }
        
        // Agregar tooltip con el mensaje de error/warning si existe
        $tooltip = !empty($row['last_error']) ? 'title="' . htmlspecialchars($row['last_error']) . '"' : '';

        return '<span class="label label-' . $color . '" ' . $clickable . ' ' . $tooltip . '><i class="' . $icon . '"></i> ' . $label . '</span>';
    }

    public function displayErrorCount($value, $row)
    {
        if ($value > 0) {
            $tooltip = !empty($row['last_error_message']) ? 'title="' . htmlspecialchars($row['last_error_message']) . '"' : '';

            return '<span class="badge badge-danger" ' . $tooltip . '>' . $value . '</span>';
        }

        return '<span class="badge badge-success">0</span>';
    }

    public function renderForm()
    {
        $this->fields_form = [
        'legend' => [
        'title' => 'Estado de Sincronización de Productos',
        'icon' => 'icon-cogs',
        ],
        'input' => [
        [
        'type' => 'text',
        'label' => 'ID Producto PrestaShop',
        'name' => 'prestashop_product_id',
        'required' => true,
        ],
        [
        'type' => 'text',
        'label' => 'ID Producto Yuju',
        'name' => 'yuju_product_id',
        ],
        [
        'type' => 'select',
        'label' => 'Estado de Sincronización',
        'name' => 'sync_status',
        'options' => [
        'query' => [
        ['id' => 'synced', 'name' => 'Sincronizado'],
        ['id' => 'pending', 'name' => 'Pendiente'],
        ['id' => 'error', 'name' => 'Error'],
        ['id' => 'disabled', 'name' => 'Deshabilitado'],
        ],
        'id' => 'id',
        'name' => 'name',
        ],
        ],
        [
        'type' => 'switch',
        'label' => 'Sincronización Habilitada',
        'name' => 'sync_enabled',
        'is_bool' => true,
        'values' => [
        ['id' => 'sync_enabled_on', 'value' => 1, 'label' => 'Habilitado'],
        ['id' => 'sync_enabled_off', 'value' => 0, 'label' => 'Deshabilitado'],
        ],
        ],
        ],
        'submit' => [
        'title' => 'Guardar',
        'class' => 'btn btn-default pull-right',
        ],
        ];

        return parent::renderForm();
    }
    
    /**
     * AJAX endpoint to get product sync history
     */
    protected function ajaxProcessGetProductHistory()
    {
        $product_id = (int) Tools::getValue('product_id');
        $page = (int) Tools::getValue('page', 1);
        $limit = 5; // Mostrar solo 5 registros por página
        
        if (!$product_id) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'ID de producto no proporcionado'
            ]);
            return;
        }
        
        try {
            // Calcular offset para paginación
            $offset = ($page - 1) * $limit;

            if (!$this->product_manager->isProductSyncHistoryTablePresent()) {
                $total_records = 0;
                $history = [];
                $stats = $this->product_manager->getProductSyncStatistics($product_id);
            } else {
                $total_records = (int) Db::getInstance()->getValue('
                    SELECT COUNT(*) 
                    FROM ' . _DB_PREFIX_ . 'yuju_product_sync_history 
                    WHERE prestashop_product_id = ' . (int) $product_id
                );
                $history = $this->product_manager->getProductSyncHistory($product_id, $limit, $offset);
                $stats = $this->product_manager->getProductSyncStatistics($product_id);
            }

            $total_pages = $limit > 0 ? (int) ceil($total_records / $limit) : 0;

            $pending_create = null;
            $status_row = $this->product_manager->getProductYujuStatusRow($product_id);
            if ($status_row && ($status_row['sync_status'] ?? '') === 'creating_in_yuju') {
                $sendOk = $this->product_manager->hasValidPendingCreateEvidence($product_id, $status_row);
                $lastCreate = $this->product_manager->getLastValidCreateHistory($product_id);
                // Si no hay create válido, mostrar el último create (aunque esté vacío) para diagnosticar.
                if (!$lastCreate && $this->product_manager->isProductSyncHistoryTablePresent()) {
                    $lastCreate = Db::getInstance()->getRow(
                        'SELECT `yuju_product_id`, `http_status_code`, `request_data`, `response_data`,
                                `error_message`, `status`, `created_at`, `sync_duration`
                         FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                         WHERE `prestashop_product_id` = ' . (int) $product_id . '
                           AND `action` = \'create\'
                         ORDER BY `id` DESC'
                    ) ?: null;
                }
                $meta = [];
                if (!empty($status_row['last_sync_data'])) {
                    $decodedMeta = json_decode((string) $status_row['last_sync_data'], true);
                    if (is_array($decodedMeta)) {
                        $meta = $decodedMeta;
                    }
                }
                $responseParsed = null;
                $requestParsed = null;
                if ($lastCreate) {
                    if ($this->product_manager->isNonEmptyJsonPayload($lastCreate['response_data'] ?? '')) {
                        $tmp = json_decode((string) $lastCreate['response_data'], true);
                        $responseParsed = is_array($tmp) ? $tmp : (string) $lastCreate['response_data'];
                    }
                    if ($this->product_manager->isNonEmptyJsonPayload($lastCreate['request_data'] ?? '')) {
                        $tmp = json_decode((string) $lastCreate['request_data'], true);
                        $requestParsed = is_array($tmp) ? $tmp : (string) $lastCreate['request_data'];
                    }
                }

                // Estado inconsistente: "Creando" sin envío real → permitir re-sync y avisar.
                if (!$sendOk) {
                    $this->product_manager->updateProductStatus(
                        $product_id,
                        'error',
                        'Estado inconsistente: quedó en «Creando» sin un envío válido a Yuju. Vuelva a sincronizar.',
                        !empty($status_row['yuju_product_id']) ? (string) $status_row['yuju_product_id'] : null,
                        null
                    );
                }

                $pending_create = [
                    'product_id' => (int) $product_id,
                    'sync_status' => $sendOk ? 'creating_in_yuju' : 'error',
                    'broken' => !$sendOk,
                    'message' => $sendOk
                        ? (string) ($status_row['last_error'] ?? 'Creando… Esperando confirmación de Yuju.')
                        : 'No hubo un envío válido del producto a Yuju (payload vacío / HTTP 500 de un bloqueo anterior). El estado se liberó: vuelva a sincronizar.',
                    'yuju_product_id' => !empty($status_row['yuju_product_id'])
                        ? (string) $status_row['yuju_product_id']
                        : (!empty($meta['api_yuju_id']) ? (string) $meta['api_yuju_id'] : null),
                    'meta' => $meta,
                    'last_create_at' => $lastCreate['created_at'] ?? null,
                    'http_status_code' => isset($lastCreate['http_status_code']) ? (int) $lastCreate['http_status_code'] : null,
                    'last_error_message' => $lastCreate['error_message'] ?? null,
                    'yuju_response' => $responseParsed,
                    'sent_payload' => $requestParsed,
                    'wait' => $this->product_manager->getPendingCreateWaitInfo((int) $product_id, $status_row),
                ];
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'history' => $history,
                'statistics' => $stats,
                'pending_create' => $pending_create,
                'current_status' => $status_row ? [
                    'yuju_product_id' => !empty($status_row['yuju_product_id']) ? (string) $status_row['yuju_product_id'] : null,
                    'sync_status' => !empty($status_row['sync_status']) ? (string) $status_row['sync_status'] : null,
                    'last_error' => !empty($status_row['last_error']) ? (string) $status_row['last_error'] : null,
                ] : null,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => max(1, $total_pages),
                    'total_records' => $total_records,
                    'limit' => $limit,
                ],
                'history_info' => !$this->product_manager->isProductSyncHistoryTablePresent()
                    ? 'La tabla de historial no existe todavía o no hay registros. Use «Crear tabla» en la parte superior si aparece el aviso.'
                    : null,
            ]);
        } catch (Exception $e) {
            $error_data = json_decode($e->getMessage(), true);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => is_array($error_data) ? $error_data['error'] : $e->getMessage(),
                'sql' => is_array($error_data) ? $error_data['sql'] : null
            ]);
        }
    }
    
    /**
     * AJAX: Obtener el ID del registro de estado de sincronización para un producto
     */
    protected function ajaxProcessGetProductStatusId()
    {
        $product_id = (int) Tools::getValue('product_id');
        
        if (!$product_id) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'ID de producto no proporcionado'
            ]);
            return;
        }
        
        try {
            $status_record = Db::getInstance()->getRow('
                SELECT id, yuju_product_id 
                FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                WHERE prestashop_product_id = ' . (int)$product_id
            );
            
            if ($status_record) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'status_id' => (int)$status_record['id'],
                    'yuju_product_id' => $status_record['yuju_product_id']
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró registro de estado para este producto'
                ]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Buscar webhook product-created por SKU (tras última creación) y vincular ID Yuju.
     */
    protected function ajaxProcessFindProductCreatedWebhook()
    {
        header('Content-Type: application/json');

        $product_id = (int) Tools::getValue('product_id');
        if ($product_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de producto no proporcionado',
            ]);

            return;
        }

        try {
            $result = $this->product_manager->findAndLinkProductCreatedWebhook($product_id, true);
            if (!is_array($result)) {
                $result = ['success' => false, 'message' => 'Respuesta inválida del gestor de productos'];
            }
            // No exponer payloads completos al front (pueden ser grandes)
            if (isset($result['match']['payload'])) {
                unset($result['match']['payload']);
            }
            if (!empty($result['matches']) && is_array($result['matches'])) {
                foreach ($result['matches'] as $i => $m) {
                    if (isset($result['matches'][$i]['payload'])) {
                        unset($result['matches'][$i]['payload']);
                    }
                }
            }
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al buscar webhook: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Reenviar create tras >1h sin webhook product-created.
     */
    protected function ajaxProcessResendPendingCreate()
    {
        header('Content-Type: application/json');

        $product_id = (int) Tools::getValue('product_id');
        if ($product_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de producto no proporcionado',
            ]);

            return;
        }

        try {
            $result = $this->product_manager->resendPendingCreate($product_id);
            if (!is_array($result)) {
                $result = ['success' => false, 'message' => 'Respuesta inválida del gestor de productos'];
            }
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al reenviar: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: obtiene las categorías del producto y las categorías Yuju para crear
     * un mapeo sin salir del modal de información.
     */
    protected function ajaxProcessGetInlineCategoryMappingOptions()
    {
        require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuCategoryMapping.php';
        header('Content-Type: application/json; charset=utf-8');
        $productId = (int) Tools::getValue('product_id');
        if ($productId <= 0 || !Validate::isLoadedObject(new Product($productId))) {
            die(json_encode(['success' => false, 'message' => 'Producto no válido.'], JSON_UNESCAPED_UNICODE));
        }

        try {
            $langId = (int) $this->context->language->id;
            $productCategories = Product::getProductCategories($productId);
            $categoryIds = array_values(array_unique(array_filter(array_map('intval', (array) $productCategories))));
            if (!$categoryIds) {
                die(json_encode(['success' => false, 'message' => 'El producto no tiene categorías asignadas en PrestaShop.'], JSON_UNESCAPED_UNICODE));
            }

            $idList = implode(',', $categoryIds);
            $psCategories = Db::getInstance()->executeS(
                'SELECT c.id_category, cl.name
                 FROM ' . _DB_PREFIX_ . 'category c
                 INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl
                    ON cl.id_category = c.id_category AND cl.id_lang = ' . $langId . '
                 WHERE c.id_category IN (' . $idList . ')
                 ORDER BY cl.name ASC'
            ) ?: [];

            $existing = Db::getInstance()->executeS(
                'SELECT prestashop_category_id, yuju_category_id, yuju_category_name, sync_enabled
                 FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
                 WHERE prestashop_category_id IN (' . $idList . ')'
            ) ?: [];

            die(json_encode([
                'success' => true,
                'product_id' => $productId,
                'prestashop_categories' => $psCategories,
                'existing_mappings' => $existing,
            ], JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            die(json_encode(['success' => false, 'message' => 'No se pudieron cargar las categorías: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * AJAX: guarda inmediatamente el mapeo de una categoría desde el modal del producto.
     * Solo escribe en la tabla local ps_yuju_category_mapping.
     */
    protected function ajaxProcessSaveInlineCategoryMapping()
    {
        require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuCategoryMapping.php';
        header('Content-Type: application/json; charset=utf-8');
        $productId = (int) Tools::getValue('product_id');
        $psCategoryId = (int) Tools::getValue('prestashop_category_id');
        $product = new Product($productId);
        if ($productId <= 0 || !Validate::isLoadedObject($product)) {
            die(json_encode(['success' => false, 'message' => 'El producto ya no existe en PrestaShop.'], JSON_UNESCAPED_UNICODE));
        }
        $productCategories = array_map('intval', (array) Product::getProductCategories($productId));
        if (!in_array($psCategoryId, $productCategories, true)) {
            die(json_encode(['success' => false, 'message' => 'La categoría seleccionada no está asignada a este producto.'], JSON_UNESCAPED_UNICODE));
        }
        try {
            die(json_encode(YujuCategoryMapping::saveInline($psCategoryId, (string) Tools::getValue('yuju_category_id', '')), JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            die(json_encode(['success' => false, 'message' => 'Error al guardar el mapeo: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * AJAX: Reintenta un producto atascado en cola desde el modal.
     */
    protected function ajaxProcessRetryQueuedProduct()
    {
        header('Content-Type: application/json; charset=utf-8');
        $productId = (int) Tools::getValue('product_id');
        if ($productId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de producto inválido.']);
            return;
        }

        try {
            require_once dirname(__FILE__) . '/../../classes/YujuSyncQueue.php';
            $queue = new YujuSyncQueue();
            $result = $queue->retryProduct($productId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo reintentar el producto: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * AJAX: Procesa un archivo subido (Excel o CSV) y extrae SKUs/nombres para el filtro masivo.
     */
    protected function ajaxProcessParseExcelFilter()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_FILES['excel_file']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
            echo json_encode([
                'success' => false,
                'message' => 'No se recibió ningún archivo o hubo un error al subirlo.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $file = $_FILES['excel_file'];
        $parseResult = YujuExcelHelper::parseFile($file['tmp_name'], $file['name']);

        if (!$parseResult['success'] || empty($parseResult['items'])) {
            echo json_encode([
                'success' => false,
                'message' => isset($parseResult['message']) ? $parseResult['message'] : 'No se encontraron SKUs ni nombres válidos en el archivo.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $filterId = YujuExcelHelper::saveFilterList($parseResult['items']);

        echo json_encode([
            'success' => true,
            'filter_id' => $filterId,
            'count' => $parseResult['count'],
            'items' => $parseResult['items'],
            'preview' => $parseResult['preview'],
            'message' => 'Se cargaron ' . $parseResult['count'] . ' elementos correctamente.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * AJAX: Guarda una lista de texto (del textarea) en caché y retorna su filter_id.
     */
    protected function ajaxProcessSaveBulkFilterList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $text = (string) Tools::getValue('text', '');
        $items = YujuExcelHelper::parseTextareaLines($text);

        if (empty($items)) {
            echo json_encode([
                'success' => false,
                'filter_id' => '',
                'count' => 0,
                'message' => 'La lista está vacía.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $filterId = YujuExcelHelper::saveFilterList($items);

        echo json_encode([
            'success' => true,
            'filter_id' => $filterId,
            'count' => count($items),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Descarga la plantilla demo en formato Excel (.xlsx).
     */
    protected function processDownloadExcelTemplate()
    {
        $content = YujuExcelHelper::generateDemoXlsx();
        if ($content === false) {
            $this->processDownloadCsvTemplate();
            return;
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_filtro_yuju.xlsx"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    /**
     * Descarga la plantilla demo en formato CSV (.csv).
     */
    protected function processDownloadCsvTemplate()
    {
        $content = YujuExcelHelper::generateDemoCsv();

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_filtro_yuju.csv"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    /** Carga los estilos y scripts propios del módulo en el back office. */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        if ($this->module) {
            $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
            $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
            $this->addJS($this->module->getPathUri() . 'views/js/yuju_product_info_modal.js?v=' . (int) @filemtime(_PS_MODULE_DIR_ . 'yuju/views/js/yuju_product_info_modal.js'));
            $this->addJS($this->module->getPathUri() . 'views/js/yuju_inline_category_map.js?v=' . (int) @filemtime(_PS_MODULE_DIR_ . 'yuju/views/js/yuju_inline_category_map.js'));
        }
    }
}


