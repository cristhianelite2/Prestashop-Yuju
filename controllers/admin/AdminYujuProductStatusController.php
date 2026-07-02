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

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuProductManager.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuSyncManager.php';

class AdminYujuProductStatusController extends ModuleAdminController
{
    /** @var bool Evita repetir el UPDATE de corrección en la misma petición HTTP */
    protected static $productStatusInfrastructureRepairDone = false;

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
        'product_name' => [
        'title' => 'Nombre del Producto',
        'callback' => 'getProductName',
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
        'sync_direction' => [
        'title' => 'Dirección',
        'align' => 'center',
        'class' => 'fixed-width-sm',
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
        // Variables usadas en product_status_stats.tpl (evita avisos si el alcance Smarty no coincide con renderList)
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuProductStatus',
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuProductStatus', true),
            'token' => $this->token,
            'yuju_logs_admin_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
        ]);
        parent::initContent();
        
        $this->setTemplate('product_status.tpl');
    }

    public function renderList()
    {
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
        'creating_in_yuju' => 'Creando en Yuju (webhook)',
        'updating_in_yuju' => 'Actualizando en Yuju',
        'deleting_in_yuju' => 'Eliminando en Yuju (webhook)',
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
        
        // Add statistics
        $stats = $this->getProductStatusStats();

        // Generate AJAX URL for the controller
        $ajax_url = $this->context->link->getAdminLink('AdminYujuProductStatus', true);

        $this->context->smarty->assign([
        'product_status_stats' => $stats,
        'sync_running' => $this->sync_manager->isSyncRunning(),
        'prestashop_stores' => $prestashop_stores,
        'mapped_categories' => $mapped_categories,
        'yuju_categories' => $yuju_categories,
        'products' => $products_data['products'],
        'pagination' => $products_data['pagination'],
        'product_status_has_active_filters' => $this->hasActiveProductStatusFilters(),
        'ajax_url' => $ajax_url,
        'yuju_logs_admin_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
        'current_index' => self::$currentIndex,
        'token' => $this->token,
        'yuju_sync_history_table_missing' => !$this->isYujuProductSyncHistoryTablePresent(),
        'yuju_sync_history_table_name' => _DB_PREFIX_ . 'yuju_product_sync_history',
        ]);

        $stats_html = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/product_status_stats.tpl');

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
     * AJAX: ejecuta sql/add_sync_history.sql vía el módulo y comprueba que la tabla exista.
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
                : ('No se pudo crear la tabla. ' . ($dbErr ?: 'Compruebe permisos MySQL y que exista sql/add_sync_history.sql en el módulo.')),
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

        $batch_invalid_raw = Tools::getValue('batch_invalid_ids_json', '[]');
        $batch_invalid_decoded = json_decode((string) $batch_invalid_raw, true);
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
        $chunk_size = max(1, min(25, $requested_chunk === 0 ? 1 : $requested_chunk));
        if ($total_products > 60) {
            $chunk_size = max($chunk_size, 5);
            $chunk_size = min(25, $chunk_size);
        }

        $results = [];
        $processed = 0;

        for ($k = 0; $k < $chunk_size && $index + $k < $total_products; ++$k) {
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

            $validation = $this->product_manager->validateProductForYujuCreate($pid);
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
        $this->repairStaleInfrastructureErrorsInProductStatus();

        $store_id = (int)Tools::getValue('store', 0);
        $ps_category_id = (int)Tools::getValue('ps_category', 0);
        $yuju_category_id = (int)Tools::getValue('yuju_category', 0);
        $sync_status = Tools::getValue('status', '');
        $date_from = Tools::getValue('date_from', '');
        $date_to = Tools::getValue('date_to', '');
        $search = pSQL(Tools::getValue('search', ''));
        $yuju_id_search = trim((string) Tools::getValue('yuju_id', ''));
        $show_all = Tools::getValue('show_all', ''); // Nuevo filtro para mostrar todos los productos
        
        // Build WHERE clause
        $where = '1=1';
        
        if ($store_id > 0) {
            $where .= ' AND p.id_shop_default = ' . $store_id;
        }
        
        // FILTRO POR DEFECTO: Solo productos de categorías mapeadas (a menos que show_all esté activado)
        if ($show_all !== '1') {
            $where .= ' AND EXISTS (
                SELECT 1 FROM ' . _DB_PREFIX_ . 'category_product cp_mapped
                INNER JOIN ' . _DB_PREFIX_ . 'yuju_category_mapping ycm_check
                    ON cp_mapped.id_category = ycm_check.prestashop_category_id
                WHERE cp_mapped.id_product = p.id_product
            )';
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
        
        // Filter products without reference and/or name
        $where .= ' AND p.reference IS NOT NULL AND p.reference != "" AND pl.name IS NOT NULL AND pl.name != ""';
        
        // Una sola fila yuju por producto (MAX id) por si en BD hubiera duplicados sin índice único
        $yps_join = '
                      LEFT JOIN (
                          SELECT y.`id`, y.`prestashop_product_id`, y.`yuju_product_id`, y.`sync_status`, y.`last_sync_at`, y.`last_error`
                          FROM `' . _DB_PREFIX_ . 'yuju_product_status` y
                          INNER JOIN (
                              SELECT `prestashop_product_id`, MAX(`id`) AS `max_id`
                              FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                              GROUP BY `prestashop_product_id`
                          ) ylast ON y.`prestashop_product_id` = ylast.`prestashop_product_id` AND y.`id` = ylast.`max_id`
                      ) yps ON (yps.`prestashop_product_id` = p.`id_product`)';

        // Multitienda: product_lang y category_lang tienen id_shop; sin filtrarlo, el JOIN duplica una fila por tienda
        $pl_shop = ' AND pl.`id_shop` = p.`id_shop_default` ';
        $cl_shop = ' AND cl.`id_shop` = p.`id_shop_default` ';

        // Count total products - SIN JOIN con category_product
        $count_sql = 'SELECT COUNT(DISTINCT p.id_product) as total
                      FROM ' . _DB_PREFIX_ . 'product p
                      LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                          ON p.id_product = pl.id_product 
                          AND pl.id_lang = ' . (int)$this->context->language->id . '
                          ' . $pl_shop . '
                      ' . $yps_join . '
                      WHERE ' . $where;
        
        $total_result = Db::getInstance()->getRow($count_sql);
        $total_products = (int)$total_result['total'];
        
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

        // Get products - Usar SOLO la categoría por defecto del producto
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
                             AND pl.id_lang = ' . (int)$this->context->language->id . '
                             ' . $pl_shop . '
                         LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl 
                             ON p.id_category_default = cl.id_category 
                             AND cl.id_lang = ' . (int)$this->context->language->id . '
                             ' . $cl_shop . '
                         ' . $yps_join . '
                         WHERE ' . $where . '
                         ORDER BY p.id_product DESC
                         LIMIT ' . (int)$offset . ', ' . (int)$per_page;
        
        $products = Db::getInstance()->executeS($products_sql);
        $products = $this->sanitizeProductsYujuDisplayForInfrastructureErrors($products ?: []);
        $products = $this->normalizeSyncedStatusRequiresYujuProductId($products);
        $products = $this->annotateProductsWithDuplicateReferenceInCatalog($products);

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
        // Handle AJAX requests
        if (Tools::isSubmit('ajax') && Tools::getValue('ajax')) {
            $action = Tools::getValue('action');
            
            switch ($action) {
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
                case 'validateCreateBatch':
                    $this->ajaxProcessValidateCreateBatch();
                    break;
                case 'getFilteredProductIds':
                    $this->ajaxProcessGetFilteredProductIds();
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
        // Solo validamos duplicados de referencia cuando vamos a crear/actualizar.
        if ($bulk_action !== 'delete') {
            $sku_conflicts = $this->getDuplicateSkuConflictsForProductIds($product_ids);
            if (!empty($sku_conflicts)) {
                $this->respondJsonDuplicateSkuConflicts($sku_conflicts, $logs_url);

                return;
            }
        }

        $product_count = count($product_ids);

        // Más de 5 productos: cola
        if ($product_count > 5) {
            $sync_queue = new YujuSyncQueue();
            $queued_count = 0;
            $skipped_count = 0;
            $error_count = 0;
            $errors = [];

            foreach ($product_ids as $product_id) {
                try {
                    $status = Db::getInstance()->getRow(
                        'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                        WHERE prestashop_product_id = ' . (int) $product_id
                    );
                    $has_yuju_link = $this->yujuStatusRowHasProductLink($status);

                    if ($bulk_action === 'delete') {
                        if (!$has_yuju_link) {
                            ++$skipped_count;

                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $product_id)) {
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': no válido o no encontrado en PrestaShop';

                            continue;
                        }
                        $result = $sync_queue->addToQueue((int) $product_id, 'delete', 'normal', []);
                    } elseif ($bulk_action === 'create') {
                        $validation = $this->product_manager->validateProductForYujuCreate((int) $product_id);
                        if (empty($validation['success'])) {
                            $validation_msg = implode(' ', $validation['errors']);
                            $this->product_manager->updateProductStatus((int) $product_id, 'error', $validation_msg, null);
                            $this->product_manager->logValidationErrorHistory((int) $product_id, $validation_msg, 'create');
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $validation_msg;

                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $product_id)) {
                            ++$error_count;
                            $errors[] = 'Producto ID ' . (int) $product_id . ': no válido o no encontrado en PrestaShop';

                            continue;
                        }
                        $result = $sync_queue->addToQueue((int) $product_id, 'create', 'normal', []);
                    } else {
                        // update: en cola se usa update con payload; si no hay enlace Yuju, encolar create
                        if (!$this->ensureProductQueuedRowForYuju((int) $product_id)) {
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
                        ++$queued_count;
                    } else {
                        ++$error_count;
                        $errors[] = 'Error agregando producto ID ' . (int) $product_id . ' a la cola';
                    }
                } catch (Exception $e) {
                    ++$error_count;
                    $errors[] = 'Producto ID ' . (int) $product_id . ': ' . $e->getMessage();
                }
            }

            $message = sprintf(
                '%d producto(s) en cola. %d omitido(s). %d error(es).',
                $queued_count,
                $skipped_count,
                $error_count
            );
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
                'success' => ($queued_count > 0 || $skipped_count > 0) && $error_count == 0,
                'message' => $message,
                'logs_url' => $logs_url,
                'errors' => $errors,
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
        $errors = [];

        foreach ($product_ids as $product_id) {
            try {
                $status = Db::getInstance()->getRow(
                    'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                    WHERE prestashop_product_id = ' . (int) $product_id
                );
                $has_yuju_link = $this->yujuStatusRowHasProductLink($status);

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

                // create y update: sendProductToYuju crea o actualiza según exista ID en Yuju
                if ($bulk_action === 'create') {
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
                $result = $this->product_manager->sendProductToYuju((int) $product_id);
                $ok = is_array($result) && !empty($result['success']);
                if ($ok) {
                    ++$success_count;
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

        $message = sprintf(
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

        header('Content-Type: application/json');
        echo json_encode([
            'success' => ($success_count > 0 || $skipped_count > 0) && $error_count == 0,
            'message' => $message,
            'logs_url' => $logs_url,
            'errors' => $errors,
            'details' => [
                'success' => $success_count,
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
     * @param array|false $status_row Fila de yuju_product_status o false
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
     * Garantiza fila en yuju_product_status y estado "queued" antes de encolar.
     *
     * @return bool false si el producto PrestaShop no existe
     */
    protected function ensureProductQueuedRowForYuju($product_id)
    {
        $product_id = (int) $product_id;
        $product = new Product($product_id, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }

        $existing = Db::getInstance()->getValue(
            'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
            WHERE prestashop_product_id = ' . $product_id
        );

        if (!$existing) {
            Db::getInstance()->insert('yuju_product_status', [
                'prestashop_product_id' => $product_id,
                'sync_status' => pSQL('queued'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
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

                $result = $this->product_manager->sendProductToYuju((int) $product['id_product']);
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
        $stats = [];

        // Total products
        $stats['total'] = (int) Db::getInstance()->getValue('
        SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');

        // By status
        $stats['by_status'] = Db::getInstance()->executeS('
        SELECT sync_status, COUNT(*) as count
        FROM ' . _DB_PREFIX_ . 'yuju_product_status
        GROUP BY sync_status
        ');
        
        // Contador específico para cada estado
        $stats['synced'] = 0;
        $stats['synced_with_warnings'] = 0;
        $stats['pending'] = 0;
        $stats['error'] = 0;
        $stats['disabled'] = 0;
        $stats['queued'] = 0;
        
        foreach ($stats['by_status'] as $status) {
            if (isset($status['sync_status']) && isset($status['count'])) {
                $stats[$status['sync_status']] = (int)$status['count'];
            }
        }
        
        // Estadísticas de la cola de sincronización
        try {
            $table_exists = Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_sync_queue"');
            
            if ($table_exists) {
                $queue_stats = Db::getInstance()->getRow('
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing,
                        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
                    FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
                ');
                
                $stats['queue'] = [
                    'total' => (int)($queue_stats['total'] ?? 0),
                    'pending' => (int)($queue_stats['pending'] ?? 0),
                    'processing' => (int)($queue_stats['processing'] ?? 0),
                    'completed' => (int)($queue_stats['completed'] ?? 0),
                    'failed' => (int)($queue_stats['failed'] ?? 0),
                ];
            } else {
                $stats['queue'] = [
                    'total' => 0,
                    'pending' => 0,
                    'processing' => 0,
                    'completed' => 0,
                    'failed' => 0,
                ];
            }
        } catch (Exception $e) {
            $stats['queue'] = [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
            ];
        }

        // Active vs inactive
        $stats['active'] = (int) Db::getInstance()->getValue('
        SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_enabled = 1
        ');

        $stats['inactive'] = $stats['total'] - $stats['active'];

        // With errors (incluir warnings)
        $stats['with_errors'] = (int) Db::getInstance()->getValue('
        SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status 
        WHERE error_count > 0 OR sync_status = "error" OR sync_status = "synced_with_warnings"
        ');

        // Recent syncs
        $stats['recent_syncs'] = (int) Db::getInstance()->getValue('
        SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status
        WHERE last_sync_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ');

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
        'Product Name',
        'Yuju Product ID',
        'Sync Status',
        'Sync Direction',
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
            $record['product_name'],
            $record['yuju_product_id'],
            $record['sync_status'],
            $record['sync_direction'],
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
        ];
        
        $status_icons = [
            'synced' => 'icon-check',
            'synced_with_warnings' => 'icon-exclamation-triangle',
            'synced_with_errors' => 'icon-exclamation-circle',
            'pending' => 'icon-clock-o',
            'error' => 'icon-exclamation-circle',
            'disabled' => 'icon-ban',
            'queued' => 'icon-list',
        ];
        
        $status_labels = [
            'synced' => 'Sincronizado',
            'synced_with_warnings' => 'Con Advertencias',
            'synced_with_errors' => 'Sincronizado (con errores)',
            'pending' => 'Pendiente',
            'error' => 'Error',
            'disabled' => 'Deshabilitado',
            'queued' => 'En Cola',
        ];

        $color = isset($status_colors[$value]) ? $status_colors[$value] : 'default';
        $icon = isset($status_icons[$value]) ? $status_icons[$value] : 'icon-question';
        $label = isset($status_labels[$value]) ? $status_labels[$value] : ucfirst($value);
        
        // Si está en cola, mostrar posición
        if ($value === 'queued') {
            try {
                // Verificar si la tabla existe
                $table_exists = Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_sync_queue"');
                
                if ($table_exists) {
                    $queue_info = Db::getInstance()->getRow('
                        SELECT id, created_at, attempts 
                        FROM ' . _DB_PREFIX_ . 'yuju_sync_queue 
                        WHERE prestashop_product_id = ' . (int)$row['prestashop_product_id'] . '
                        AND status = "pending"
                        ORDER BY created_at ASC
                        LIMIT 1
                    ');
                    
                    if ($queue_info && isset($queue_info['created_at'])) {
                        $position = (int)Db::getInstance()->getValue('
                            SELECT COUNT(*) + 1
                            FROM ' . _DB_PREFIX_ . 'yuju_sync_queue 
                            WHERE status = "pending" 
                            AND created_at < "' . pSQL($queue_info['created_at']) . '"
                        ');
                        
                        $tooltip = 'title="Posición en cola: #' . $position . ' | Creado: ' . htmlspecialchars($queue_info['created_at']) . '"';
                        return '<span class="label label-' . $color . '" ' . $tooltip . '><i class="' . $icon . '"></i> ' . $label . ' (#' . $position . ')</span>';
                    }
                }
            } catch (Exception $e) {
                // Si hay error, mostrar solo el badge sin posición
            }
        }
        
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
        'type' => 'select',
        'label' => 'Dirección de Sincronización',
        'name' => 'sync_direction',
        'options' => [
        'query' => [
        ['id' => 'bidirectional', 'name' => 'Bidireccional'],
        ['id' => 'yuju_to_ps', 'name' => 'Yuju a PrestaShop'],
        ['id' => 'ps_to_yuju', 'name' => 'PrestaShop a Yuju'],
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

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'history' => $history,
                'statistics' => $stats,
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
}


