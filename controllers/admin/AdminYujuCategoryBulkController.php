<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuCategoryMapping.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuProductManager.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuSyncQueue.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/config/config.php';

class AdminYujuCategoryBulkController extends ModuleAdminController
{
    /** @var YujuProductManager */
    protected $product_manager;

    /** @var array<int, array{reference:string,name:string}> */
    protected $bulkProductInfoCache = [];

    public function __construct()
    {
        $this->table = 'yuju_category_mapping';
        $this->className = 'YujuCategoryMapping';
        $this->identifier = 'id';
        $this->bootstrap = true;
        $this->lang = false;

        parent::__construct();

        $this->meta_title = $this->trans('Acciones masivas por categoría', [], 'Modules.Prestashopyuju.Admin');
        $this->toolbar_title = $this->meta_title;

        $this->product_manager = new YujuProductManager();

        if ($this->module && method_exists($this->module, 'ensureYujuCategoryBulkTab')) {
            $this->module->ensureYujuCategoryBulkTab();
        }
    }

    public function initContent()
    {
        $idCategory = (int) Tools::getValue('id_category', 0);
        $overviewFilters = $this->getOverviewFiltersFromRequest();

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuCategoryBulk',
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuCategoryBulk', true),
            'token' => $this->token,
            'yuju_logs_admin_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
            'product_status_url' => $this->context->link->getAdminLink('AdminYujuProductStatus', true),
            'product_status_token' => Tools::getAdminTokenLite('AdminYujuProductStatus'),
            'yuju_product_info_ajax_url' => $this->context->link->getAdminLink('AdminYujuProductStatus', true),
            'yuju_product_info_token' => Tools::getAdminTokenLite('AdminYujuProductStatus'),
            'product_admin_url' => $this->context->link->getAdminLink('AdminProducts', true),
            'category_mapping_url' => $this->context->link->getAdminLink('AdminYujuCategoryMapping', true),
            'id_category' => $idCategory,
            'search_q' => $overviewFilters['q'],
            'overview_filters' => $overviewFilters,
            'overview_filters_active' => $this->overviewFiltersAreActive($overviewFilters),
            'overview_sort_links' => $idCategory > 0 ? [] : $this->buildOverviewSortLinks($overviewFilters),
            'overview_rows' => $idCategory > 0 ? [] : $this->getCategoryOverviewRows($overviewFilters),
            'overview_sku_hits' => [],
            'detail' => $idCategory > 0 ? $this->buildCategoryDetail($idCategory) : null,
            'yuju_category_options' => $this->getYujuCategoryOptions(),
            'yuju_sync_history_table_missing' => !$this->isYujuProductSyncHistoryTablePresent(),
            'yuju_sync_history_table_name' => _DB_PREFIX_ . 'yuju_product_sync_history',
            'yuju_enable_bulk_resend_pending' => (int) YujuConfig::get('YUJU_ENABLE_BULK_RESEND_PENDING', 0) === 1,
        ]);

        if ($idCategory <= 0 && $overviewFilters['sku'] !== '') {
            $this->context->smarty->assign(
                'overview_sku_hits',
                $this->findProductsBySkuForOverview($overviewFilters['sku'], 12)
            );
        }

        parent::initContent();
        $this->setTemplate('category_bulk.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('ajax') && Tools::getValue('ajax')) {
            $action = (string) Tools::getValue('action', '');
            switch ($action) {
                case 'categoryBulkProducts':
                    $this->ajaxProcessCategoryBulkProducts();
                    break;
                case 'categoryBulkSendChunk':
                    $this->ajaxProcessCategoryBulkSendChunk();
                    break;
                case 'categoryBulkProductIds':
                    $this->ajaxProcessCategoryBulkProductIds();
                    break;
                case 'categoryBulkMultiProductIds':
                    $this->ajaxProcessCategoryBulkMultiProductIds();
                    break;
                case 'createSyncHistoryTable':
                    $this->ajaxProcessCreateSyncHistoryTable();
                    break;
                case 'saveInlineCategoryMapping':
                    $this->ajaxProcessSaveInlineCategoryMapping();
                    break;
                case 'categoryBulkErrorDetails':
                    $this->ajaxProcessCategoryBulkErrorDetails();
                    break;
                case 'categoryBulkResendChunk':
                    $this->ajaxProcessCategoryBulkResendChunk();
                    break;
                default:
                    break;
            }
            exit;
        }

        return parent::postProcess();
    }

    /**
     * Comprueba si existe la tabla de historial de envíos a Yuju.
     *
     * @return bool
     */
    protected function isYujuProductSyncHistoryTablePresent()
    {
        return $this->product_manager->isProductSyncHistoryTablePresent();
    }

    /**
     * AJAX: crea/repara infraestructura de historial (tabla y columnas relacionadas).
     *
     * @return void
     */
    protected function ajaxProcessCreateSyncHistoryTable()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->module || !method_exists($this->module, 'ensureSyncHistoryTable')) {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo acceder al módulo Yuju.',
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $this->module->ensureSyncHistoryTable();
        if (method_exists($this->module, 'ensureSyncQueueActionIncludesDelete')) {
            $this->module->ensureSyncQueueActionIncludesDelete();
        }
        if (method_exists($this->module, 'ensureProductStatusIntermediateWebhookStates')) {
            $this->module->ensureProductStatusIntermediateWebhookStates();
        }

        $present = $this->isYujuProductSyncHistoryTablePresent();
        $dbErr = Db::getInstance()->getMsgError();

        echo json_encode([
            'success' => $present,
            'message' => $present
                ? 'La infraestructura se creó/corrigió correctamente. Recargando…'
                : ('No se pudo crear la tabla. ' . ($dbErr ?: 'Compruebe permisos MySQL y el archivo sql/add_sync_history.sql.')),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Guarda mapeo de categoría desde el modal inline de esta vista.
     *
     * @return void
     */
    protected function ajaxProcessSaveInlineCategoryMapping()
    {
        header('Content-Type: application/json; charset=utf-8');

        $psCategoryId = (int) Tools::getValue('prestashop_category_id', 0);
        $yujuCategoryId = trim((string) Tools::getValue('yuju_category_id', ''));
        if ($psCategoryId <= 0 || $yujuCategoryId === '') {
            echo json_encode([
                'success' => false,
                'message' => 'Debe seleccionar categoría de PrestaShop y categoría Yuju.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $yujuCategoryName = $this->getYujuCategoryNameById($yujuCategoryId);
        if ($yujuCategoryName === '') {
            echo json_encode([
                'success' => false,
                'message' => 'La categoría Yuju seleccionada no es válida.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Varias categorías PS pueden compartir la misma categoría Yuju.
        // Solo se exige unicidad por prestashop_category_id.
        $this->ensureCategoryMappingAllowsSharedYujuCategory();

        $exists = (int) Db::getInstance()->getValue(
            'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE prestashop_category_id = ' . (int) $psCategoryId
        );

        $data = [
            'prestashop_category_id' => (int) $psCategoryId,
            'yuju_category_id' => pSQL($yujuCategoryId),
            'yuju_category_name' => pSQL($yujuCategoryName),
            'sync_enabled' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($exists > 0) {
            $ok = Db::getInstance()->update('yuju_category_mapping', $data, 'id = ' . (int) $exists);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $ok = Db::getInstance()->insert('yuju_category_mapping', $data);
        }

        echo json_encode([
            'success' => (bool) $ok,
            'message' => $ok
                ? 'Mapeo guardado correctamente.'
                : ('No se pudo guardar el mapeo. ' . Db::getInstance()->getMsgError()),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Varias categorías PrestaShop pueden apuntar a la misma categoría Yuju.
     * Quita el UNIQUE antiguo sobre yuju_category_id si aún existe en BD.
     *
     * @return void
     */
    protected function ensureCategoryMappingAllowsSharedYujuCategory()
    {
        require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuCategoryMapping.php';
        YujuCategoryMapping::ensureSharedYujuCategoryAllowed();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function getYujuCategoryOptions()
    {
        $byId = [];

        // 1) Fuente oficial local del módulo (completa): config/yuju_allowed_values.json
        $jsonPath = _PS_MODULE_DIR_ . 'prestashopyuju/config/yuju_allowed_values.json';
        if (is_readable($jsonPath)) {
            $raw = @file_get_contents($jsonPath);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && !empty($decoded['attributes']) && is_array($decoded['attributes'])) {
                foreach ($decoded['attributes'] as $attr) {
                    if (!is_array($attr)) {
                        continue;
                    }
                    $attrId = isset($attr['id']) ? (string) $attr['id'] : '';
                    if ($attrId !== 'id_category' || empty($attr['values']) || !is_array($attr['values'])) {
                        continue;
                    }
                    foreach ($attr['values'] as $v) {
                        $id = isset($v['id']) ? trim((string) $v['id']) : '';
                        $name = isset($v['name']) ? trim((string) $v['name']) : '';
                        if ($id === '' || $name === '') {
                            continue;
                        }
                        $byId[$id] = ['id' => $id, 'name' => $name];
                    }
                    break;
                }
            }
        }

        // 2) Complementar con cache si existe (por si hay categorías nuevas en API)
        $cacheTable = _DB_PREFIX_ . 'yuju_categories_cache';
        $cacheExistsRows = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($cacheTable) . "'");
        if (is_array($cacheExistsRows) && !empty($cacheExistsRows)) {
            $cacheRows = Db::getInstance()->executeS(
                'SELECT DISTINCT yuju_category_id AS id, name FROM ' . $cacheTable . ' ORDER BY name ASC'
            );
            foreach ((array) $cacheRows as $r) {
                $id = isset($r['id']) ? trim((string) $r['id']) : '';
                $name = isset($r['name']) ? trim((string) $r['name']) : '';
                if ($id === '' || $name === '') {
                    continue;
                }
                if (!isset($byId[$id])) {
                    $byId[$id] = ['id' => $id, 'name' => $name];
                }
            }
        }

        // 3) Último fallback: categorías presentes en mapeos guardados
        $mapRows = Db::getInstance()->executeS(
            'SELECT DISTINCT yuju_category_id AS id, yuju_category_name AS name FROM '
            . _DB_PREFIX_ . 'yuju_category_mapping WHERE yuju_category_id IS NOT NULL AND yuju_category_id != "" ORDER BY yuju_category_name ASC'
        );
        foreach ((array) $mapRows as $r) {
            $id = isset($r['id']) ? trim((string) $r['id']) : '';
            $name = isset($r['name']) ? trim((string) $r['name']) : '';
            if ($id === '' || $name === '') {
                continue;
            }
            if (!isset($byId[$id])) {
                $byId[$id] = ['id' => $id, 'name' => $name];
            }
        }

        $out = array_values($byId);
        usort($out, function ($a, $b) {
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $out;
    }

    /**
     * @param string $yujuCategoryId
     *
     * @return string
     */
    protected function getYujuCategoryNameById($yujuCategoryId)
    {
        $yujuCategoryId = trim((string) $yujuCategoryId);
        if ($yujuCategoryId === '') {
            return '';
        }
        foreach ($this->getYujuCategoryOptions() as $opt) {
            if ((string) $opt['id'] === $yujuCategoryId) {
                return (string) $opt['name'];
            }
        }

        return '';
    }

    /**
     * JSON: productos de una categoría filtrados por segmento (paginado).
     */
    protected function ajaxProcessCategoryBulkProducts()
    {
        header('Content-Type: application/json; charset=utf-8');
        $idCategory = (int) Tools::getValue('id_category', 0);
        $segment = (string) Tools::getValue('segment', 'all');
        $page = max(1, (int) Tools::getValue('page', 1));
        $perPage = min(100, max(5, (int) Tools::getValue('per_page', 25)));

        if ($idCategory <= 0) {
            echo json_encode(['success' => false, 'message' => 'Categoría no válida.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->fetchCategoryProductsPage($idCategory, $segment, $page, $perPage);
        echo json_encode([
            'success' => true,
            'products' => $data['products'],
            'pagination' => $data['pagination'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON: lista todos los productos con error de una categoría y clasifica su tipo de error.
     *
     * @return void
     */
    protected function ajaxProcessCategoryBulkErrorDetails()
    {
        header('Content-Type: application/json; charset=utf-8');
        $idCategory = (int) Tools::getValue('id_category', 0);
        if ($idCategory <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Categoría no válida.',
                'products' => [],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->fetchCategoryProductsPage($idCategory, 'errors', 1, 5000);
        $products = is_array($data['products']) ? $data['products'] : [];
        foreach ($products as $k => $p) {
            $msg = isset($p['last_error']) ? trim((string) $p['last_error']) : '';
            $status = isset($p['yuju_status']) ? (string) $p['yuju_status'] : '';
            $products[$k]['error_type'] = $this->classifyProductSyncErrorType($msg, $status);
        }

        echo json_encode([
            'success' => true,
            'products' => $products,
            'total' => count($products),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON: reenvía create de productos en «En espera» ≥1h sin webhook.
     *
     * @return void
     */
    protected function ajaxProcessCategoryBulkResendChunk()
    {
        header('Content-Type: application/json; charset=utf-8');
        $logsUrl = $this->context->link->getAdminLink('AdminYujuLogs', true);

        if ((int) YujuConfig::get('YUJU_ENABLE_BULK_RESEND_PENDING', 0) !== 1) {
            echo json_encode([
                'success' => false,
                'message' => 'La acción masiva «Enviar de nuevo» está deshabilitada en la configuración del módulo.',
                'logs_url' => $logsUrl,
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $productIds = [];
        $json = (string) Tools::getValue('product_ids_json', '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $productIds = array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($id) {
                    return $id > 0;
                })));
            }
        }

        if ($productIds === []) {
            echo json_encode([
                'success' => false,
                'message' => 'No se recibieron productos en este lote.',
                'logs_url' => $logsUrl,
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];

        $this->preloadBulkProductInfo($productIds);

        foreach ($productIds as $productId) {
            try {
                $result = $this->product_manager->resendPendingCreate((int) $productId);
                if (!is_array($result)) {
                    ++$errorCount;
                    $errors[] = $this->buildBulkErrorItem((int) $productId, 'Respuesta inválida al reenviar');
                    continue;
                }
                if (!empty($result['success'])) {
                    ++$successCount;
                    continue;
                }
                if (!empty($result['too_early'])) {
                    ++$skippedCount;
                    continue;
                }
                ++$errorCount;
                $errors[] = $this->buildBulkErrorItem(
                    (int) $productId,
                    (string) ($result['message'] ?? 'No se pudo reenviar')
                );
            } catch (Exception $e) {
                ++$errorCount;
                $errors[] = $this->buildBulkErrorItem((int) $productId, $e->getMessage());
            }
        }

        $message = sprintf(
            '%d reenviado(s). %d omitido(s) (<1h o no elegibles). %d error(es).',
            $successCount,
            $skippedCount,
            $errorCount
        );

        echo json_encode([
            'success' => ($successCount > 0 || $skippedCount > 0) && $errorCount === 0,
            'message' => $message,
            'logs_url' => $logsUrl,
            'errors' => $errors,
            'details' => [
                'success' => $successCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'bulk_action' => 'resend_stale',
            ],
            'reload' => $successCount > 0,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $errorMessage
     * @param string $status
     *
     * @return string
     */
    protected function classifyProductSyncErrorType($errorMessage, $status)
    {
        $msg = strtolower(trim((string) $errorMessage));
        if ($msg === '') {
            return $status === 'synced_with_errors' ? 'Advertencia de sincronización' : 'Error no especificado';
        }
        if (strpos($msg, 'sku') !== false || strpos($msg, 'reference') !== false || strpos($msg, 'duplic') !== false) {
            return 'SKU/referencia duplicada';
        }
        if (strpos($msg, 'category') !== false || strpos($msg, 'categor') !== false) {
            return 'Categoría/mapeo inválido';
        }
        if (strpos($msg, 'required') !== false || strpos($msg, 'obligatorio') !== false || strpos($msg, 'validation') !== false) {
            return 'Validación de campos';
        }
        if (strpos($msg, 'timeout') !== false || strpos($msg, 'timed out') !== false || strpos($msg, 'curl') !== false || strpos($msg, 'http') !== false) {
            return 'Conectividad/API';
        }
        if (strpos($msg, 'stock') !== false || strpos($msg, 'price') !== false || strpos($msg, 'precio') !== false) {
            return 'Datos comerciales inválidos';
        }

        return 'Error de sincronización';
    }

    /**
     * JSON: lista de IDs del segmento (paginado en servidor para catálogos grandes).
     */
    protected function ajaxProcessCategoryBulkProductIds()
    {
        header('Content-Type: application/json; charset=utf-8');
        $idCategory = (int) Tools::getValue('id_category', 0);
        $segment = (string) Tools::getValue('segment', 'all');
        $offset = max(0, (int) Tools::getValue('offset', 0));
        $limit = min(800, max(50, (int) Tools::getValue('limit', 400)));

        if ($idCategory <= 0) {
            echo json_encode(['success' => false, 'message' => 'Categoría no válida.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ids = $this->fetchCategoryProductIdsSlice($idCategory, $segment, $offset, $limit + 1);
        $hasMore = count($ids) > $limit;
        if ($hasMore) {
            $ids = array_slice($ids, 0, $limit);
        }

        echo json_encode([
            'success' => true,
            'product_ids' => $ids,
            'next_offset' => $offset + count($ids),
            'has_more' => $hasMore,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON: IDs únicos de productos activos en varias categorías (paginado).
     * Input: category_ids_json=[1,2,3], segment, offset, limit
     */
    protected function ajaxProcessCategoryBulkMultiProductIds()
    {
        header('Content-Type: application/json; charset=utf-8');

        $categoryIds = [];
        $json = (string) Tools::getValue('category_ids_json', '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $categoryIds = array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($id) {
                    return $id > 0;
                })));
            }
        }

        $segment = (string) Tools::getValue('segment', 'all');
        $offset = max(0, (int) Tools::getValue('offset', 0));
        $limit = min(800, max(50, (int) Tools::getValue('limit', 400)));

        if ($categoryIds === []) {
            echo json_encode([
                'success' => false,
                'message' => 'Seleccione al menos una categoría.',
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        // Cap defensivo: no más de 50 categorías por petición
        if (count($categoryIds) > 50) {
            $categoryIds = array_slice($categoryIds, 0, 50);
        }

        $idLang = (int) $this->context->language->id;
        $segWhere = $this->getSegmentWhereSql($segment);
        $ypsJoin = $this->getYpsJoinSql();
        $idsSql = implode(',', $categoryIds);

        $sql = 'SELECT DISTINCT p.id_product
            FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = cp.id_product AND p.active = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            ' . $ypsJoin . '
            WHERE cp.id_category IN (' . $idsSql . ')' . $segWhere . '
            ORDER BY p.id_product DESC
            LIMIT ' . (int) $offset . ', ' . (int) ($limit + 1);

        $rows = Db::getInstance()->executeS($sql);
        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!empty($r['id_product'])) {
                    $ids[] = (int) $r['id_product'];
                }
            }
        }

        $hasMore = count($ids) > $limit;
        if ($hasMore) {
            $ids = array_slice($ids, 0, $limit);
        }

        echo json_encode([
            'success' => true,
            'product_ids' => $ids,
            'next_offset' => $offset + count($ids),
            'has_more' => $hasMore,
            'category_count' => count($categoryIds),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return int[]
     */
    protected function fetchCategoryProductIdsSlice($idCategory, $segment, $offset, $limit)
    {
        $idLang = (int) $this->context->language->id;
        $segWhere = $this->getSegmentWhereSql($segment);
        $ypsJoin = $this->getYpsJoinSql();

        $sql = 'SELECT DISTINCT p.id_product
            FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = cp.id_product AND p.active = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            ' . $ypsJoin . '
            WHERE cp.id_category = ' . (int) $idCategory . $segWhere . '
            ORDER BY p.id_product DESC
            LIMIT ' . (int) $offset . ', ' . (int) $limit;

        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if (!empty($r['id_product'])) {
                $out[] = (int) $r['id_product'];
            }
        }

        return $out;
    }

    /**
     * JSON: encola o procesa un lote de IDs (misma lógica que estado de productos).
     */
    protected function ajaxProcessCategoryBulkSendChunk()
    {
        header('Content-Type: application/json; charset=utf-8');
        $logsUrl = $this->context->link->getAdminLink('AdminYujuLogs', true);

        if ($this->module && method_exists($this->module, 'ensureSyncHistoryTable')) {
            $this->module->ensureSyncHistoryTable();
        }
        if ($this->module && method_exists($this->module, 'ensureSyncQueueActionIncludesDelete')) {
            $this->module->ensureSyncQueueActionIncludesDelete();
        }

        $productIds = [];
        $json = (string) Tools::getValue('product_ids_json', '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $productIds = array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($id) {
                    return $id > 0;
                })));
            }
        }

        $bulkAction = (string) Tools::getValue('bulk_action', 'create');
        $contextCategoryId = (int) Tools::getValue('id_category', 0);
        if (!in_array($bulkAction, ['create', 'update', 'delete'], true)) {
            $bulkAction = 'create';
        }

        // Create: si hay categoría de contexto, exigir mapeo (propio o padre) antes de validar producto a producto
        if ($bulkAction === 'create' && $contextCategoryId > 0) {
            $coverage = $this->getCategoryOrAncestorMappingCoverage($contextCategoryId);
            if (empty($coverage['covered'])) {
                $mapUrl = $this->context->link->getAdminLink('AdminYujuCategoryMapping', true)
                    . '&openModal=1&prefill_ps_category_id=' . $contextCategoryId;
                echo json_encode([
                    'success' => false,
                    'message' => 'La categoría PrestaShop #' . $contextCategoryId
                        . ' no tiene mapeo Yuju (ni por herencia de padre). '
                        . 'Mapee la categoría antes de crear/sincronizar.',
                    'logs_url' => $logsUrl,
                    'error_code' => 'category_not_mapped',
                    'id_category' => (int) $contextCategoryId,
                    'map_url' => $mapUrl,
                ], JSON_UNESCAPED_UNICODE);

                return;
            }
        }

        $deleteOk = (string) Tools::getValue('bulk_delete_confirmed', '') === '1';
        if ($bulkAction === 'delete' && !$deleteOk) {
            echo json_encode([
                'success' => false,
                'message' => 'Debe confirmar la eliminación en Yuju antes de continuar.',
                'logs_url' => $logsUrl,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($productIds === []) {
            echo json_encode([
                'success' => false,
                'message' => 'No se recibieron productos en este lote.',
                'logs_url' => $logsUrl,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 1) Create/Update: reintentar también productos que ya están en error
        //    (p. ej. fallaron antes por falta de mapeo). Solo informamos cuántos se reintentan.
        $this->preloadBulkProductInfo($productIds);
        $initialErrorRows = $this->getProductErrorSummaries($productIds);
        $initialErrorIds = array_map(static function ($row) {
            return (int) $row['id_product'];
        }, $initialErrorRows);

        $skippedReasons = [];
        $skippedProductDetails = [];

        // 2) Si quedan conflictos de SKU, omitir solo esos y continuar
        $conflicts = $this->product_manager->getDuplicateSkuConflictsForProductIds($productIds);
        if (!empty($conflicts)) {
            $conflictIds = [];
            foreach ($conflicts as $conflict) {
                if (empty($conflict['products']) || !is_array($conflict['products'])) {
                    continue;
                }
                foreach ($conflict['products'] as $p) {
                    $pid = isset($p['id_product']) ? (int) $p['id_product'] : 0;
                    if ($pid > 0) {
                        $conflictIds[$pid] = true;
                    }
                }
            }
            if (!empty($conflictIds)) {
                $productIds = array_values(array_diff($productIds, array_keys($conflictIds)));
                $skippedReasons[] = count($conflictIds) . ' omitido(s) por referencia/SKU duplicada en el catálogo PrestaShop';
                foreach (array_keys($conflictIds) as $cid) {
                    $info = $this->getBulkProductInfo((int) $cid);
                    $skippedProductDetails[] = [
                        'id_product' => (int) $cid,
                        'reference' => (string) ($info['reference'] ?? ''),
                        'name' => (string) ($info['name'] ?? ''),
                        'reason' => 'SKU/referencia duplicada en PrestaShop',
                        'last_error' => 'No se puede enviar mientras exista otra ficha con la misma referencia.',
                        'message' => 'No se puede enviar mientras exista otra ficha con la misma referencia.',
                    ];
                }
            }
        }

        if ($productIds === []) {
            $msg = 'No se procesó ningún producto.';
            if (!empty($skippedReasons)) {
                $msg .= "\n\nMotivo:\n• " . implode("\n• ", $skippedReasons);
            }
            if (!empty($initialErrorRows) && empty($conflicts)) {
                $msg .= "\n\nEstos productos tenían error de un intento anterior. Corrija el problema (mapeo, descripción, etc.) y vuelva a intentar Crear.";
            }
            echo json_encode([
                'success' => false,
                'message' => $msg,
                'logs_url' => $logsUrl,
                'error_code' => !empty($conflicts) ? 'duplicate_prestashop_sku' : 'no_eligible_products',
                'duplicate_sku_conflicts' => !empty($conflicts) ? $conflicts : [],
                'skipped_products' => $skippedProductDetails,
                'previous_error_products' => $initialErrorRows,
                'errors' => array_map(function ($row) {
                    return $this->buildBulkErrorItem(
                        (int) ($row['id_product'] ?? 0),
                        trim((string) ($row['last_error'] ?? '')) !== ''
                            ? (string) $row['last_error']
                            : 'Error previo sin detalle'
                    );
                }, $initialErrorRows),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = $this->runBulkSendForProductIds($productIds, $bulkAction, $logsUrl, $contextCategoryId);
        if (!empty($initialErrorIds)) {
            $payload['retried_previous_errors'] = count($initialErrorIds);
            $payload['previous_error_products'] = $initialErrorRows;
        }
        if (!empty($skippedReasons)) {
            $suffix = ' Omitidos: ' . implode(' | ', $skippedReasons) . '.';
            $payload['message'] = (isset($payload['message']) ? (string) $payload['message'] : '') . $suffix;
            $payload['skipped_products'] = $skippedProductDetails;
            if (!empty($conflicts)) {
                $payload['error_code'] = 'duplicate_prestashop_sku';
                $payload['duplicate_sku_conflicts'] = $conflicts;
            }
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resumen de productos en error (para mensajes claros / UI).
     *
     * @param int[] $productIds
     *
     * @return array<int, array{id_product:int,reference:string,name:string,sync_status:string,last_error:string}>
     */
    protected function getProductErrorSummaries(array $productIds)
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $productIds = array_values(array_filter($productIds, static function ($id) {
            return $id > 0;
        }));
        if (empty($productIds)) {
            return [];
        }

        $idLang = (int) $this->context->language->id;
        $sql = 'SELECT yps.prestashop_product_id AS id_product,
                       yps.sync_status,
                       yps.last_error,
                       p.reference,
                       pl.name
                FROM ' . _DB_PREFIX_ . 'yuju_product_status yps
                INNER JOIN ' . _DB_PREFIX_ . 'product p ON (p.id_product = yps.prestashop_product_id)
                LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                    ON (pl.id_product = p.id_product AND pl.id_lang = ' . (int) $idLang . ')
                WHERE yps.prestashop_product_id IN (' . implode(',', $productIds) . ')
                  AND yps.sync_status IN ("error", "synced_with_errors")
                ORDER BY yps.prestashop_product_id ASC';
        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['id_product'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $out[] = [
                'id_product' => $pid,
                'reference' => (string) ($row['reference'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'sync_status' => (string) ($row['sync_status'] ?? ''),
                'last_error' => (string) ($row['last_error'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Precarga referencia/nombre de productos para errores del lote.
     *
     * @param int[] $productIds
     *
     * @return void
     */
    protected function preloadBulkProductInfo(array $productIds)
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return;
        }
        $idLang = (int) $this->context->language->id;
        $rows = Db::getInstance()->executeS(
            'SELECT p.id_product, p.reference, pl.name
             FROM `' . _DB_PREFIX_ . 'product` p
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_lang = ' . (int) $idLang . '
             WHERE p.id_product IN (' . implode(',', $productIds) . ')'
        );
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            $pid = (int) ($row['id_product'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $this->bulkProductInfoCache[$pid] = [
                'reference' => (string) ($row['reference'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ];
        }
    }

    /**
     * @param int $productId
     *
     * @return array{reference:string,name:string}
     */
    protected function getBulkProductInfo($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return ['reference' => '', 'name' => ''];
        }
        if (!isset($this->bulkProductInfoCache[$productId])) {
            $this->preloadBulkProductInfo([$productId]);
        }
        if (!isset($this->bulkProductInfoCache[$productId])) {
            $this->bulkProductInfoCache[$productId] = ['reference' => '', 'name' => ''];
        }

        return $this->bulkProductInfoCache[$productId];
    }

    /**
     * Error estructurado para la tabla del modal (ID, referencia, nombre, mensaje).
     *
     * @param int $productId
     * @param string $message
     *
     * @return array{id_product:int,reference:string,name:string,message:string}
     */
    protected function buildBulkErrorItem($productId, $message)
    {
        $productId = (int) $productId;
        $info = $this->getBulkProductInfo($productId);

        return [
            'id_product' => $productId,
            'reference' => (string) ($info['reference'] ?? ''),
            'name' => (string) ($info['name'] ?? ''),
            'message' => (string) $message,
        ];
    }

    /**
     * @deprecated Usar getProductErrorSummaries(); se mantiene por compatibilidad interna.
     *
     * @param int[] $productIds
     *
     * @return int[]
     */
    protected function getProductIdsWithInitialErrorStatus(array $productIds)
    {
        return array_map(static function ($row) {
            return (int) $row['id_product'];
        }, $this->getProductErrorSummaries($productIds));
    }

    /**
     * @return array<string, mixed>
     */
    protected function runBulkSendForProductIds(array $productIds, $bulkAction, $logsUrl, $contextCategoryId = 0)
    {
        $productCount = count($productIds);
        $contextCategoryId = (int) $contextCategoryId;
        $createOptions = [
            'origin' => 'category_bulk',
        ];
        if ($contextCategoryId > 0) {
            $createOptions['preferred_ps_category_id'] = $contextCategoryId;
        }
        // Multi-categoría desde overview: preferir la primera seleccionada como pista de mapeo
        $multiCatJson = (string) Tools::getValue('category_ids_json', '');
        if ($multiCatJson !== '' && empty($createOptions['preferred_ps_category_id'])) {
            $multiDecoded = json_decode($multiCatJson, true);
            if (is_array($multiDecoded) && !empty($multiDecoded)) {
                $first = (int) $multiDecoded[0];
                if ($first > 0) {
                    $createOptions['preferred_ps_category_id'] = $first;
                }
            }
        }

        if ($productCount > 5) {
            $this->preloadBulkProductInfo($productIds);
            $syncQueue = new YujuSyncQueue();
            $queuedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($productIds as $productId) {
                try {
                    $status = Db::getInstance()->getRow(
                        'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                        WHERE prestashop_product_id = ' . (int) $productId
                    );
                    $resolvedYujuId = $this->product_manager->resolveExistingYujuProductId((int) $productId);
                    $hasYujuLink = $this->yujuStatusRowHasProductLink($status) || ($resolvedYujuId !== '');

                    if ($bulkAction === 'delete') {
                        if (!$hasYujuLink) {
                            ++$skippedCount;
                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $productId, false)) {
                            ++$errorCount;
                            $errors[] = $this->buildBulkErrorItem((int) $productId, 'no válido o no encontrado en PrestaShop');
                            continue;
                        }
                        $result = $syncQueue->addToQueue((int) $productId, 'delete', 'normal', []);
                    } elseif ($bulkAction === 'create' && !$hasYujuLink) {
                        $validation = $this->product_manager->validateProductForYujuCreate((int) $productId, $createOptions);
                        if (empty($validation['success'])) {
                            $validationMsg = implode(' ', $validation['errors']);
                            $this->product_manager->updateProductStatus((int) $productId, 'error', $validationMsg, null);
                            $this->product_manager->logValidationErrorHistory((int) $productId, $validationMsg, 'create');
                            ++$errorCount;
                            $errors[] = $this->buildBulkErrorItem((int) $productId, $validationMsg);
                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $productId, true)) {
                            ++$errorCount;
                            $errors[] = $this->buildBulkErrorItem((int) $productId, 'no válido o no encontrado en PrestaShop');
                            continue;
                        }
                        $queueData = $createOptions;
                        $result = $syncQueue->addToQueue((int) $productId, 'create', 'normal', $queueData);
                    } else {
                        // Update (o create con ID ya existente): no degradar status a queued
                        if ($hasYujuLink && !$this->product_manager->productNeedsYujuUpdate((int) $productId, $createOptions)) {
                            // Sin cambios vs último envío: seguir como synced / En Yuju OK
                            $this->product_manager->updateProductStatus(
                                (int) $productId,
                                'synced',
                                null,
                                $resolvedYujuId !== '' ? $resolvedYujuId : null
                            );
                            ++$skippedCount;
                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $productId, !$hasYujuLink)) {
                            ++$errorCount;
                            $errors[] = $this->buildBulkErrorItem((int) $productId, 'no válido o no encontrado en PrestaShop');
                            continue;
                        }
                        if ($hasYujuLink) {
                            $payload = $this->product_manager->buildProductPayloadForYujuQueue(
                                (int) $productId,
                                $createOptions
                            );
                            $payload = is_array($payload) ? $payload : [];
                            $result = $syncQueue->addToQueue((int) $productId, 'update', 'normal', $payload);
                        } else {
                            $queueData = $createOptions;
                            $result = $syncQueue->addToQueue((int) $productId, 'create', 'normal', $queueData);
                        }
                    }

                    if ($result) {
                        ++$queuedCount;
                    } else {
                        ++$errorCount;
                        $errors[] = $this->buildBulkErrorItem((int) $productId, 'Error agregando a la cola');
                    }
                } catch (Exception $e) {
                    ++$errorCount;
                    $errors[] = $this->buildBulkErrorItem((int) $productId, $e->getMessage());
                }
            }

            $message = sprintf(
                '%d producto(s) en cola. %d omitido(s) (sin cambios o no aplicables). %d error(es).',
                $queuedCount,
                $skippedCount,
                $errorCount
            );
            if ($bulkAction === 'delete' && $skippedCount > 0) {
                $message .= ' Omitidos: sin ID Yuju.';
            }
            if ($queuedCount > 0) {
                $message .= ' Se procesarán en el próximo ciclo del cron.';
            }

            return [
                'success' => ($queuedCount > 0 || $skippedCount > 0) && $errorCount === 0,
                'message' => $message,
                'logs_url' => $logsUrl,
                'errors' => $errors,
                'details' => [
                    'queued' => $queuedCount,
                    'skipped' => $skippedCount,
                    'errors' => $errorCount,
                    'bulk_action' => $bulkAction,
                ],
                'reload' => $queuedCount > 0,
            ];
        }

        $this->preloadBulkProductInfo($productIds);
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];
        $waitingNotes = [];

        foreach ($productIds as $productId) {
            try {
                $status = Db::getInstance()->getRow(
                    'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                    WHERE prestashop_product_id = ' . (int) $productId
                );
                $resolvedYujuId = $this->product_manager->resolveExistingYujuProductId((int) $productId);
                $hasYujuLink = $this->yujuStatusRowHasProductLink($status) || ($resolvedYujuId !== '');

                if ($bulkAction === 'delete') {
                    if (!$hasYujuLink) {
                        ++$skippedCount;
                        continue;
                    }
                    $del = $this->product_manager->removeProductFromYujuAndLocalStatus((int) $productId);
                    if (!empty($del['success'])) {
                        ++$successCount;
                    } else {
                        ++$errorCount;
                        $errors[] = $this->buildBulkErrorItem(
                            (int) $productId,
                            (string) ($del['message'] ?? 'Error al eliminar')
                        );
                    }
                    continue;
                }

                if ($bulkAction === 'create' && !$hasYujuLink) {
                    $validation = $this->product_manager->validateProductForYujuCreate((int) $productId, $createOptions);
                    if (empty($validation['success'])) {
                        $validationMsg = implode(' ', $validation['errors']);
                        $this->product_manager->updateProductStatus((int) $productId, 'error', $validationMsg, null);
                        $this->product_manager->logValidationErrorHistory((int) $productId, $validationMsg, 'create');
                        ++$errorCount;
                        $errors[] = $this->buildBulkErrorItem((int) $productId, $validationMsg);
                        continue;
                    }
                }
                $result = $this->product_manager->sendProductToYuju((int) $productId, $createOptions);
                $ok = is_array($result) && !empty($result['success']);
                if ($ok) {
                    ++$successCount;
                    if (!empty($result['awaiting_webhook'])) {
                        $waitingNotes[] = $this->buildBulkErrorItem(
                            (int) $productId,
                            (string) ($result['message'] ?? 'En espera de respuesta de Yuju.')
                        );
                    }
                } else {
                    ++$errorCount;
                    $errDetail = is_array($result)
                        ? ($result['error'] ?? $result['message'] ?? 'Error desconocido')
                        : 'Respuesta inválida del gestor de productos';
                    $errors[] = $this->buildBulkErrorItem((int) $productId, (string) $errDetail);
                }
            } catch (Exception $e) {
                ++$errorCount;
                $errors[] = $this->buildBulkErrorItem((int) $productId, $e->getMessage());
            }
        }

        $message = sprintf(
            '%d producto(s) procesado(s) correctamente. %d omitido(s). %d error(es).',
            $successCount,
            $skippedCount,
            $errorCount
        );
        if ($bulkAction === 'delete' && $skippedCount > 0) {
            $message .= ' Omitidos: sin ID Yuju.';
        }
        if (!empty($waitingNotes)) {
            $message .= ' En espera de respuesta de Yuju: ' . count($waitingNotes) . '.';
        }

        return [
            'success' => ($successCount > 0 || $skippedCount > 0) && $errorCount === 0,
            'message' => $message,
            'logs_url' => $logsUrl,
            'errors' => $errors,
            'waiting_notes' => $waitingNotes,
            'details' => [
                'success' => $successCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'waiting' => count($waitingNotes),
                'bulk_action' => $bulkAction,
            ],
            'reload' => $successCount > 0,
        ];
    }

    /**
     * @param array|false $statusRow
     *
     * @return bool
     */
    protected function yujuStatusRowHasProductLink($statusRow)
    {
        if (!$statusRow || !isset($statusRow['yuju_product_id'])) {
            return false;
        }
        $yujuPid = trim((string) $statusRow['yuju_product_id']);

        return $yujuPid !== '' && strtolower($yujuPid) !== 'null' && $yujuPid !== '0';
    }

    /**
     * Garantiza fila en yuju_product_status antes de encolar.
     * Solo marca sync_status=queued si aún NO tiene ID Yuju (los ya sincronizados
     * deben seguir contando como "En Yuju OK" hasta que el cron los procese).
     *
     * @param int  $productId
     * @param bool $markAsQueued Forzar estado queued (p.ej. create sin ID)
     *
     * @return bool false si el producto PrestaShop no existe
     */
    protected function ensureProductQueuedRowForYuju($productId, $markAsQueued = true)
    {
        $productId = (int) $productId;
        $product = new Product($productId, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }

        $row = Db::getInstance()->getRow(
            'SELECT id, yuju_product_id, sync_status FROM ' . _DB_PREFIX_ . 'yuju_product_status 
            WHERE prestashop_product_id = ' . $productId
        );

        $hasYujuId = false;
        if ($row && isset($row['yuju_product_id'])) {
            $yujuPid = trim((string) $row['yuju_product_id']);
            $hasYujuId = ($yujuPid !== '' && strtolower($yujuPid) !== 'null' && $yujuPid !== '0');
        }

        // Productos ya en Yuju: no degradar synced → queued (rompe los KPIs)
        $shouldQueueStatus = $markAsQueued && !$hasYujuId;

        if (!$row) {
            Db::getInstance()->insert('yuju_product_status', [
                'prestashop_product_id' => $productId,
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
                'prestashop_product_id = ' . $productId
            );
        }

        return true;
    }

    /**
     * @return string
     */
    protected function getYpsJoinSql()
    {
        return '
            LEFT JOIN (
                SELECT y.`id`, y.`prestashop_product_id`, y.`yuju_product_id`, y.`sync_status`, y.`last_sync_at`, y.`updated_at`, y.`last_error`
                FROM `' . _DB_PREFIX_ . 'yuju_product_status` y
                INNER JOIN (
                    SELECT `prestashop_product_id`, MAX(`id`) AS `max_id`
                    FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                    GROUP BY `prestashop_product_id`
                ) ylast ON y.`prestashop_product_id` = ylast.`prestashop_product_id` AND y.`id` = ylast.`max_id`
            ) yps ON (yps.`prestashop_product_id` = p.`id_product`)
        ';
    }

    /**
     * @return string
     */
    protected function sqlInvalidCatalog()
    {
        return '(p.reference IS NULL OR TRIM(p.reference) = "" OR pl.name IS NULL OR TRIM(pl.name) = "")';
    }

    /**
     * @return string
     */
    protected function sqlValidYujuId()
    {
        return '(yps.yuju_product_id IS NOT NULL AND TRIM(yps.yuju_product_id) <> "" AND TRIM(yps.yuju_product_id) <> "0" AND LOWER(TRIM(yps.yuju_product_id)) <> "null")';
    }

    /**
     * @param string $segment
     *
     * @return string
     */
    protected function getSegmentWhereSql($segment)
    {
        $inv = $this->sqlInvalidCatalog();
        $vy = $this->sqlValidYujuId();

        switch ($segment) {
            case 'errors':
                return ' AND NOT ' . $inv . ' AND (
                    yps.sync_status = "error"
                    OR (yps.sync_status = "synced_with_errors" AND ' . $vy . ')
                ) ';
            case 'queued':
                return ' AND NOT ' . $inv . ' AND yps.sync_status = "queued" ';
            case 'pending':
            case 'not_sent':
                // "No enviado": sin ID Yuju y aún no en cola / proceso
                return ' AND NOT ' . $inv . ' AND NOT ' . $vy . '
                    AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","synced","synced_with_warnings","synced_with_errors")) ';
            case 'in_progress':
                return ' AND NOT ' . $inv . ' AND yps.sync_status IN ("syncing","creating_in_yuju","updating_in_yuju","deleting_in_yuju") ';
            case 'stale_creating':
                // En espera de webhook product-created desde hace ≥ 1 hora (elegibles para reenvío)
                return ' AND NOT ' . $inv . '
                    AND yps.sync_status = "creating_in_yuju"
                    AND NOT ' . $vy . '
                    AND COALESCE(yps.updated_at, yps.last_sync_at) IS NOT NULL
                    AND COALESCE(yps.updated_at, yps.last_sync_at) <= DATE_SUB(NOW(), INTERVAL 1 HOUR) ';
            case 'synced_ok':
                return ' AND NOT ' . $inv . ' AND ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings") ';
            case 'disabled':
                return ' AND yps.sync_status = "disabled" ';
            case 'invalid':
                return ' AND ' . $inv . ' ';
            case 'orphan_synced':
                // Compat: mismo criterio que "No enviados" con status synced*
                return ' AND NOT ' . $inv . ' AND NOT ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings","synced_with_errors") ';
            case 'all':
            default:
                return '';
        }
    }

    /**
     * Filtros del listado overview (GET).
     *
     * @return array<string, mixed>
     */
    protected function getOverviewFiltersFromRequest()
    {
        $mapping = (string) Tools::getValue('mapping', 'all');
        if (!in_array($mapping, ['all', 'mapped', 'own', 'ancestor', 'unmapped'], true)) {
            $mapping = 'all';
        }

        $sort = (string) Tools::getValue('sort', 'total');
        if ($sort === 'pending') {
            $sort = 'queued';
        }
        if ($sort === 'orphan') {
            $sort = 'not_sent';
        }
        $allowedSort = [
            'total', 'name', 'synced_ok', 'queued', 'not_sent', 'errors',
            'in_progress', 'invalid', 'mapped',
        ];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'total';
        }

        $dir = strtolower((string) Tools::getValue('dir', 'desc'));
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'desc';
        }

        $boolKeys = [
            'has_synced', 'has_queued', 'has_not_sent', 'has_errors', 'has_in_progress',
            'has_invalid', 'only_issues',
        ];
        $flags = [];
        foreach ($boolKeys as $key) {
            $flags[$key] = (int) Tools::getValue($key, 0) === 1 ? 1 : 0;
        }
        // Compat con URLs antiguas
        if ((int) Tools::getValue('has_pending', 0) === 1) {
            $flags['has_queued'] = 1;
        }
        if ((int) Tools::getValue('has_orphan', 0) === 1) {
            $flags['has_not_sent'] = 1;
        }

        return array_merge([
            'q' => trim((string) Tools::getValue('q', '')),
            'sku' => trim((string) Tools::getValue('sku', '')),
            'mapping' => $mapping,
            'sort' => $sort,
            'dir' => $dir,
            'min_total' => max(0, (int) Tools::getValue('min_total', 0)),
            'filters_open' => (int) Tools::getValue('filters_open', 0) === 1 ? 1 : 0,
        ], $flags);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return bool
     */
    protected function overviewFiltersAreActive(array $filters)
    {
        if ($filters['q'] !== '' || $filters['sku'] !== '') {
            return true;
        }
        if ($filters['mapping'] !== 'all') {
            return true;
        }
        if ((int) $filters['min_total'] > 0) {
            return true;
        }
        if ($filters['sort'] !== 'total' || $filters['dir'] !== 'desc') {
            return true;
        }
        foreach (['has_synced', 'has_queued', 'has_not_sent', 'has_errors', 'has_in_progress', 'has_invalid', 'only_issues'] as $key) {
            if (!empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * URLs de ordenación por columna (conservan el resto de filtros).
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, array<string, string|int>>
     */
    protected function buildOverviewSortLinks(array $filters)
    {
        $base = $this->context->link->getAdminLink('AdminYujuCategoryBulk', true);
        $columns = [
            'name' => 'name',
            'total' => 'total',
            'synced_ok' => 'synced_ok',
            'queued' => 'queued',
            'not_sent' => 'not_sent',
            'errors' => 'errors',
            'in_progress' => 'in_progress',
            'invalid' => 'invalid',
            'mapped' => 'mapped',
        ];
        $links = [];
        foreach ($columns as $key => $sort) {
            $nextDir = ($filters['sort'] === $sort && $filters['dir'] === 'asc') ? 'desc' : 'asc';
            if ($filters['sort'] !== $sort) {
                $nextDir = in_array($sort, ['name', 'mapped'], true) ? 'asc' : 'desc';
            }
            $params = array_merge($filters, [
                'sort' => $sort,
                'dir' => $nextDir,
                'filters_open' => !empty($filters['filters_open']) || $this->overviewFiltersAreActive($filters) ? 1 : 0,
            ]);
            $links[$key] = [
                'url' => $base . '&' . http_build_query($this->overviewFiltersToQuery($params)),
                'active' => $filters['sort'] === $sort ? 1 : 0,
                'dir' => $filters['sort'] === $sort ? $filters['dir'] : '',
            ];
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, scalar>
     */
    protected function overviewFiltersToQuery(array $filters)
    {
        $out = [];
        foreach ($filters as $k => $v) {
            if ($k === 'q' || $k === 'sku') {
                if ((string) $v !== '') {
                    $out[$k] = (string) $v;
                }
                continue;
            }
            if ($k === 'mapping') {
                if ((string) $v !== 'all') {
                    $out[$k] = (string) $v;
                }
                continue;
            }
            if ($k === 'sort') {
                if ((string) $v !== 'total') {
                    $out[$k] = (string) $v;
                }
                continue;
            }
            if ($k === 'dir') {
                if ((string) $v !== 'desc') {
                    $out[$k] = (string) $v;
                }
                continue;
            }
            if ((int) $v > 0) {
                $out[$k] = (int) $v;
            }
        }

        return $out;
    }

    /**
     * Productos que coinciden con el SKU (para resumen del filtro).
     *
     * @param string $sku
     * @param int $limit
     *
     * @return array<int, array<string, mixed>>
     */
    protected function findProductsBySkuForOverview($sku, $limit = 12)
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return [];
        }
        $idLang = (int) $this->context->language->id;
        $like = '%' . pSQL($sku) . '%';
        $exact = pSQL($sku);
        $limit = max(1, min(50, (int) $limit));

        $sql = 'SELECT DISTINCT p.id_product, p.reference, pl.name,
                (CASE WHEN UPPER(TRIM(p.reference)) = UPPER("' . $exact . '") THEN 0 ELSE 1 END) AS rank_exact
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON pa.id_product = p.id_product
            WHERE p.active = 1
              AND (
                p.reference LIKE "' . $like . '"
                OR pa.reference LIKE "' . $like . '"
              )
            ORDER BY rank_exact ASC, p.reference ASC
            LIMIT ' . (int) $limit;

        $rows = Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed>|string $filters
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getCategoryOverviewRows($filters)
    {
        if (!is_array($filters)) {
            $filters = [
                'q' => trim((string) $filters),
                'sku' => '',
                'mapping' => 'all',
                'sort' => 'total',
                'dir' => 'desc',
                'min_total' => 0,
                'has_synced' => 0,
                'has_queued' => 0,
                'has_not_sent' => 0,
                'has_errors' => 0,
                'has_in_progress' => 0,
                'has_invalid' => 0,
                'only_issues' => 0,
            ];
        }

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $whereExtra = '';
        $havingParts = ['total > 0'];

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $like = '%' . pSQL($q) . '%';
            $whereExtra .= ' AND cl.name LIKE "' . $like . '" ';
        }

        $sku = isset($filters['sku']) ? trim((string) $filters['sku']) : '';
        if ($sku !== '') {
            $likeSku = '%' . pSQL($sku) . '%';
            $whereExtra .= ' AND EXISTS (
                SELECT 1
                FROM ' . _DB_PREFIX_ . 'category_product cp_sku
                INNER JOIN ' . _DB_PREFIX_ . 'product p_sku
                    ON p_sku.id_product = cp_sku.id_product AND p_sku.active = 1
                LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa_sku
                    ON pa_sku.id_product = p_sku.id_product
                WHERE cp_sku.id_category = cp.id_category
                  AND (
                    p_sku.reference LIKE "' . $likeSku . '"
                    OR pa_sku.reference LIKE "' . $likeSku . '"
                  )
            ) ';
        }

        $minTotal = isset($filters['min_total']) ? (int) $filters['min_total'] : 0;
        if ($minTotal > 0) {
            $havingParts[] = 'total >= ' . (int) $minTotal;
        }
        if (!empty($filters['has_synced'])) {
            $havingParts[] = 'cnt_synced_ok > 0';
        }
        if (!empty($filters['has_queued']) || !empty($filters['has_pending'])) {
            $havingParts[] = 'cnt_queued > 0';
        }
        if (!empty($filters['has_not_sent']) || !empty($filters['has_orphan'])) {
            $havingParts[] = 'cnt_not_sent > 0';
        }
        if (!empty($filters['has_errors'])) {
            $havingParts[] = 'cnt_errors > 0';
        }
        if (!empty($filters['has_in_progress'])) {
            $havingParts[] = 'cnt_in_progress > 0';
        }
        if (!empty($filters['has_invalid'])) {
            $havingParts[] = 'cnt_invalid > 0';
        }
        if (!empty($filters['only_issues'])) {
            $havingParts[] = '(cnt_queued > 0 OR cnt_not_sent > 0 OR cnt_errors > 0 OR cnt_in_progress > 0 OR cnt_invalid > 0)';
        }

        $sortMap = [
            'total' => 'total',
            'name' => 'category_name',
            'synced_ok' => 'cnt_synced_ok',
            'queued' => 'cnt_queued',
            'not_sent' => 'cnt_not_sent',
            'pending' => 'cnt_queued',
            'errors' => 'cnt_errors',
            'in_progress' => 'cnt_in_progress',
            'invalid' => 'cnt_invalid',
            'orphan' => 'cnt_not_sent',
            'mapped' => 'mapped_yuju',
        ];
        $sortKey = isset($filters['sort']) ? (string) $filters['sort'] : 'total';
        if (!isset($sortMap[$sortKey])) {
            $sortKey = 'total';
        }
        $dir = (isset($filters['dir']) && strtolower((string) $filters['dir']) === 'asc') ? 'ASC' : 'DESC';
        $orderSql = $sortMap[$sortKey] . ' ' . $dir . ', total DESC, category_name ASC';

        // Si filtramos por mapeo (incluye herencia), necesitamos más filas antes del corte
        $mapping = isset($filters['mapping']) ? (string) $filters['mapping'] : 'all';
        $needsPhpMappingFilter = in_array($mapping, ['mapped', 'own', 'ancestor', 'unmapped'], true);
        $fetchLimit = $needsPhpMappingFilter ? 2000 : 400;

        $ypsJoin = $this->getYpsJoinSql();
        $inv = $this->sqlInvalidCatalog();
        $vy = $this->sqlValidYujuId();

        $sql = 'SELECT 
                cp.id_category,
                MAX(cl.name) AS category_name,
                MAX(IF(ycm.id IS NOT NULL, 1, 0)) AS mapped_own,
                MAX(IF(ycm.id IS NOT NULL, 1, 0)) AS mapped_yuju,
                MAX(IF(ycm.id IS NOT NULL AND IFNULL(ycm.sync_enabled, 1) = 1, 1, 0)) AS mapped_sync_on,
                COUNT(DISTINCT p.id_product) AS total,
                SUM(IF(' . $inv . ', 1, 0)) AS cnt_invalid,
                SUM(IF(NOT ' . $inv . ' AND ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings"), 1, 0)) AS cnt_synced_ok,
                SUM(IF(NOT ' . $inv . ' AND (
                    yps.sync_status = "error"
                    OR (yps.sync_status = "synced_with_errors" AND ' . $vy . ')
                ), 1, 0)) AS cnt_errors,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status IN ("syncing","creating_in_yuju","updating_in_yuju","deleting_in_yuju"), 1, 0)) AS cnt_in_progress,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status = "queued", 1, 0)) AS cnt_queued,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","synced","synced_with_warnings","synced_with_errors")), 1, 0)) AS cnt_not_sent,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status = "queued", 1, 0)) AS cnt_pending,
                SUM(IF(yps.sync_status = "disabled", 1, 0)) AS cnt_disabled,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","synced","synced_with_warnings","synced_with_errors")), 1, 0)) AS cnt_orphan_state
            FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = cp.id_product AND p.active = 1
            INNER JOIN ' . _DB_PREFIX_ . 'category c ON c.id_category = cp.id_category AND c.active = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl 
                ON c.id_category = cl.id_category AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            LEFT JOIN ' . _DB_PREFIX_ . 'yuju_category_mapping ycm
                ON ycm.prestashop_category_id = cp.id_category
            ' . $ypsJoin . '
            WHERE c.id_category NOT IN (' . (int) Configuration::get('PS_ROOT_CATEGORY') . ', ' . (int) Configuration::get('PS_HOME_CATEGORY') . ')
            ' . $whereExtra . '
            GROUP BY cp.id_category
            HAVING ' . implode(' AND ', $havingParts) . '
            ORDER BY ' . $orderSql . '
            LIMIT ' . (int) $fetchLimit;

        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        // Marcar como mapeada también si hereda del padre (misma regla de envío)
        foreach ($rows as &$row) {
            $row['mapped_own'] = !empty($row['mapped_own']) ? 1 : 0;
            $row['mapped_via_ancestor'] = 0;
            if (!empty($row['mapped_yuju'])) {
                $row['mapped_yuju'] = 1;
                continue;
            }
            $coverage = $this->getCategoryOrAncestorMappingCoverage((int) $row['id_category']);
            if (!empty($coverage['covered'])) {
                $row['mapped_yuju'] = 1;
                $row['mapped_via_ancestor'] = !empty($coverage['from_ancestor']) ? 1 : 0;
            } else {
                $row['mapped_yuju'] = 0;
            }
        }
        unset($row);

        // Tras resolver herencia de mapeo, reordenar si el criterio depende de mapped_yuju
        if ($sortKey === 'mapped' && !$needsPhpMappingFilter) {
            usort($rows, function ($a, $b) use ($dir) {
                $cmp = ((int) $a['mapped_yuju'] < (int) $b['mapped_yuju']) ? -1 : (((int) $a['mapped_yuju'] > (int) $b['mapped_yuju']) ? 1 : 0);
                if ($cmp === 0) {
                    $cmp = ((int) $b['total'] - (int) $a['total']);
                }

                return ($dir === 'ASC') ? $cmp : -$cmp;
            });
        }

        if ($needsPhpMappingFilter) {
            $filtered = [];
            foreach ($rows as $row) {
                $isMapped = !empty($row['mapped_yuju']);
                $viaAncestor = !empty($row['mapped_via_ancestor']);
                $isOwn = !empty($row['mapped_own']);
                $keep = false;
                switch ($mapping) {
                    case 'mapped':
                        $keep = $isMapped;
                        break;
                    case 'own':
                        $keep = $isOwn;
                        break;
                    case 'ancestor':
                        $keep = $isMapped && $viaAncestor && !$isOwn;
                        break;
                    case 'unmapped':
                        $keep = !$isMapped;
                        break;
                }
                if ($keep) {
                    $filtered[] = $row;
                }
            }
            $rows = $filtered;

            // Reordenar en PHP tras el filtro de mapeo
            usort($rows, function ($a, $b) use ($sortKey, $dir) {
                $map = [
                    'total' => 'total',
                    'name' => 'category_name',
                    'synced_ok' => 'cnt_synced_ok',
                    'queued' => 'cnt_queued',
                    'not_sent' => 'cnt_not_sent',
                    'pending' => 'cnt_queued',
                    'errors' => 'cnt_errors',
                    'in_progress' => 'cnt_in_progress',
                    'invalid' => 'cnt_invalid',
                    'orphan' => 'cnt_not_sent',
                    'mapped' => 'mapped_yuju',
                ];
                $field = isset($map[$sortKey]) ? $map[$sortKey] : 'total';
                $va = isset($a[$field]) ? $a[$field] : 0;
                $vb = isset($b[$field]) ? $b[$field] : 0;
                if ($field === 'category_name') {
                    $cmp = strcasecmp((string) $va, (string) $vb);
                } else {
                    $cmp = ((int) $va < (int) $vb) ? -1 : (((int) $va > (int) $vb) ? 1 : 0);
                }
                if ($cmp === 0) {
                    $cmp = ((int) $b['total'] - (int) $a['total']);
                }

                return ($dir === 'ASC') ? $cmp : -$cmp;
            });
        }

        return array_slice($rows, 0, 400);
    }

    /**
     * @param int $idCategory
     *
     * @return array<string, mixed>|null
     */
    protected function buildCategoryDetail($idCategory)
    {
        $cat = new Category($idCategory, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($cat) || !$cat->active) {
            return null;
        }

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $ypsJoin = $this->getYpsJoinSql();
        $inv = $this->sqlInvalidCatalog();
        $vy = $this->sqlValidYujuId();

        $sql = 'SELECT 
                COUNT(DISTINCT p.id_product) AS total,
                SUM(IF(' . $inv . ', 1, 0)) AS cnt_invalid,
                SUM(IF(NOT ' . $inv . ' AND ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings"), 1, 0)) AS cnt_synced_ok,
                SUM(IF(NOT ' . $inv . ' AND (
                    yps.sync_status = "error"
                    OR (yps.sync_status = "synced_with_errors" AND ' . $vy . ')
                ), 1, 0)) AS cnt_errors,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status IN ("syncing","creating_in_yuju","updating_in_yuju","deleting_in_yuju"), 1, 0)) AS cnt_in_progress,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status = "queued", 1, 0)) AS cnt_queued,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","synced","synced_with_warnings","synced_with_errors")), 1, 0)) AS cnt_not_sent,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status = "queued", 1, 0)) AS cnt_pending,
                SUM(IF(yps.sync_status = "disabled", 1, 0)) AS cnt_disabled,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","synced","synced_with_warnings","synced_with_errors")), 1, 0)) AS cnt_orphan_state
            FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = cp.id_product AND p.active = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            ' . $ypsJoin . '
            WHERE cp.id_category = ' . (int) $idCategory;

        $row = Db::getInstance()->getRow($sql);
        if (!$row) {
            return null;
        }

        $mapped = Db::getInstance()->getRow(
            'SELECT yuju_category_id, yuju_category_name, sync_enabled FROM ' . _DB_PREFIX_ . 'yuju_category_mapping 
            WHERE prestashop_category_id = ' . (int) $idCategory
        );
        $coverage = $this->getCategoryOrAncestorMappingCoverage((int) $idCategory);

        $total = (int) $row['total'];
        $cntOk = (int) $row['cnt_synced_ok'];
        $cntInv = (int) $row['cnt_invalid'];
        $cntErr = (int) $row['cnt_errors'];
        $cntQueued = (int) $row['cnt_queued'];
        $cntNotSent = (int) $row['cnt_not_sent'];
        $cntProg = (int) $row['cnt_in_progress'];
        $pct = $total > 0 ? round(100 * $cntOk / $total) : 0;
        $bar = ['ok' => 0.0, 'queued' => 0.0, 'not_sent' => 0.0, 'err' => 0.0, 'prog' => 0.0, 'inv' => 0.0];
        if ($total > 0) {
            $bar['ok'] = round(100 * $cntOk / $total, 2);
            $bar['queued'] = round(100 * $cntQueued / $total, 2);
            $bar['not_sent'] = round(100 * $cntNotSent / $total, 2);
            $bar['err'] = round(100 * $cntErr / $total, 2);
            $bar['prog'] = round(100 * $cntProg / $total, 2);
            $bar['inv'] = round(100 * $cntInv / $total, 2);
        }

        return [
            'category' => $cat,
            'name' => $cat->name,
            'stats' => [
                'total' => $total,
                'cnt_invalid' => $cntInv,
                'cnt_synced_ok' => $cntOk,
                'cnt_errors' => $cntErr,
                'cnt_in_progress' => $cntProg,
                'cnt_queued' => $cntQueued,
                'cnt_not_sent' => $cntNotSent,
                'cnt_pending' => $cntQueued,
                'cnt_disabled' => (int) $row['cnt_disabled'],
                'cnt_orphan_state' => $cntNotSent,
                'pct_uploaded_ok' => $pct,
            ],
            'bar_pct' => $bar,
            'mapping' => $mapped ?: null,
            'mapping_covered_by_self_or_parent' => !empty($coverage['covered']),
            'mapping_covered_by_ancestor' => !empty($coverage['from_ancestor']),
            'mapping_covered_category_id' => isset($coverage['mapped_category_id']) ? (int) $coverage['mapped_category_id'] : 0,
            'ps_category_edit_url' => $this->context->link->getAdminLink('AdminCategories', true, [], [
                'id_category' => (int) $idCategory,
                'updatecategory' => 1,
            ]),
            'map_category_url' => $this->context->link->getAdminLink('AdminYujuCategoryMapping', true) . '&openModal=1&prefill_ps_category_id=' . (int) $idCategory,
        ];
    }

    /**
     * Cobertura de mapeo para una categoría: propia o heredada por ancestro.
     *
     * @param int $idCategory
     *
     * @return array{covered: bool, from_ancestor: bool, mapped_category_id: int}
     */
    protected function getCategoryOrAncestorMappingCoverage($idCategory)
    {
        $lineage = $this->getCategoryLineageIds((int) $idCategory);
        if ($lineage === []) {
            return ['covered' => false, 'from_ancestor' => false, 'mapped_category_id' => 0];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT prestashop_category_id, sync_enabled
             FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
             WHERE sync_enabled = 1
               AND prestashop_category_id IN (' . implode(',', array_map('intval', $lineage)) . ')
             LIMIT ' . (int) count($lineage)
        );
        if (!is_array($rows) || $rows === []) {
            return ['covered' => false, 'from_ancestor' => false, 'mapped_category_id' => 0];
        }

        $set = [];
        foreach ($rows as $r) {
            $cid = isset($r['prestashop_category_id']) ? (int) $r['prestashop_category_id'] : 0;
            if ($cid > 0) {
                $set[$cid] = true;
            }
        }

        foreach ($lineage as $idx => $cid) {
            if (!isset($set[$cid])) {
                continue;
            }

            return [
                'covered' => true,
                'from_ancestor' => $idx > 0,
                'mapped_category_id' => (int) $cid,
            ];
        }

        return ['covered' => false, 'from_ancestor' => false, 'mapped_category_id' => 0];
    }

    /**
     * IDs desde la categoría actual hacia su cadena de padres.
     *
     * @param int $idCategory
     *
     * @return int[]
     */
    protected function getCategoryLineageIds($idCategory)
    {
        $out = [];
        $seen = [];
        $current = (int) $idCategory;
        $safety = 0;

        while ($current > 0 && $safety < 20) {
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;
            $out[] = $current;

            $parent = (int) Db::getInstance()->getValue(
                'SELECT id_parent FROM ' . _DB_PREFIX_ . 'category WHERE id_category = ' . (int) $current
            );
            if ($parent <= 0 || $parent === $current) {
                break;
            }
            $current = $parent;
            ++$safety;
        }

        return $out;
    }

    /**
     * @param string $segment
     *
     * @return array{products: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    protected function fetchCategoryProductsPage($idCategory, $segment, $page, $perPage)
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $segWhere = $this->getSegmentWhereSql($segment);
        $ypsJoin = $this->getYpsJoinSql();

        $baseFrom = ' FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = cp.id_product AND p.active = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            ' . $ypsJoin . '
            WHERE cp.id_category = ' . (int) $idCategory . $segWhere;

        $countSql = 'SELECT COUNT(DISTINCT p.id_product) AS total ' . $baseFrom;
        $total = (int) Db::getInstance()->getValue($countSql);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $coverImgSub = '(SELECT MIN(img.`id_image`) FROM `' . _DB_PREFIX_ . 'image` img WHERE img.`id_product` = p.`id_product` AND img.`cover` = 1)';

        $listSql = 'SELECT DISTINCT p.id_product, p.reference, pl.name,
                ' . $coverImgSub . ' AS id_image,
                yps.sync_status AS yuju_status,
                yps.yuju_product_id,
                yps.last_sync_at,
                yps.updated_at,
                yps.last_error
            ' . $baseFrom . '
            ORDER BY p.id_product DESC
            LIMIT ' . (int) $offset . ', ' . (int) $perPage;

        $products = Db::getInstance()->executeS($listSql);

        return [
            'products' => is_array($products) ? $products : [],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }
}
