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

class AdminYujuCategoryBulkController extends ModuleAdminController
{
    /** @var YujuProductManager */
    protected $product_manager;

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
        $search = trim((string) Tools::getValue('q', ''));

        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuCategoryBulk',
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuCategoryBulk', true),
            'token' => $this->token,
            'yuju_logs_admin_url' => $this->context->link->getAdminLink('AdminYujuLogs', true),
            'product_status_url' => $this->context->link->getAdminLink('AdminYujuProductStatus', true),
            'category_mapping_url' => $this->context->link->getAdminLink('AdminYujuCategoryMapping', true),
            'id_category' => $idCategory,
            'search_q' => $search,
            'overview_rows' => $idCategory > 0 ? [] : $this->getCategoryOverviewRows($search),
            'detail' => $idCategory > 0 ? $this->buildCategoryDetail($idCategory) : null,
            'yuju_category_options' => $this->getYujuCategoryOptions(),
            'yuju_sync_history_table_missing' => !$this->isYujuProductSyncHistoryTablePresent(),
            'yuju_sync_history_table_name' => _DB_PREFIX_ . 'yuju_product_sync_history',
        ]);

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
                case 'createSyncHistoryTable':
                    $this->ajaxProcessCreateSyncHistoryTable();
                    break;
                case 'saveInlineCategoryMapping':
                    $this->ajaxProcessSaveInlineCategoryMapping();
                    break;
                case 'categoryBulkErrorDetails':
                    $this->ajaxProcessCategoryBulkErrorDetails();
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

        $exists = (int) Db::getInstance()->getValue(
            'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE prestashop_category_id = ' . (int) $psCategoryId
        );
        $duplicateYuju = (int) Db::getInstance()->getValue(
            'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping WHERE yuju_category_id = "' . pSQL($yujuCategoryId) . '"'
            . ($exists > 0 ? ' AND id != ' . (int) $exists : '')
        );
        if ($duplicateYuju > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Esa categoría de Yuju ya está mapeada con otra categoría de PrestaShop.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

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

        // 1) Omitir productos que ya están en error desde el inicio del lote
        $initialErrorIds = $this->getProductIdsWithInitialErrorStatus($productIds);
        if (!empty($initialErrorIds)) {
            $productIds = array_values(array_diff($productIds, $initialErrorIds));
        }

        $skippedReasons = [];
        if (!empty($initialErrorIds)) {
            $skippedReasons[] = count($initialErrorIds) . ' omitido(s) por estado inicial con error';
        }

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
                $skippedReasons[] = count($conflictIds) . ' omitido(s) por SKU duplicado';
            }
        }

        if ($productIds === []) {
            echo json_encode([
                'success' => false,
                'message' => 'No quedaron productos elegibles para procesar. ' . implode('. ', $skippedReasons) . '.',
                'logs_url' => $logsUrl,
                'error_code' => !empty($conflicts) ? 'duplicate_prestashop_sku' : null,
                'duplicate_sku_conflicts' => !empty($conflicts) ? $conflicts : [],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = $this->runBulkSendForProductIds($productIds, $bulkAction, $logsUrl, $contextCategoryId);
        if (!empty($skippedReasons)) {
            $suffix = ' Omitidos: ' . implode(' | ', $skippedReasons) . '.';
            $payload['message'] = (isset($payload['message']) ? (string) $payload['message'] : '') . $suffix;
            $payload['initial_error_skipped'] = $initialErrorIds;
            if (!empty($conflicts)) {
                $payload['error_code'] = 'duplicate_prestashop_sku';
                $payload['duplicate_sku_conflicts'] = $conflicts;
            }
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * IDs con estado inicial en error para omitir antes de procesar lote.
     *
     * @param int[] $productIds
     *
     * @return int[]
     */
    protected function getProductIdsWithInitialErrorStatus(array $productIds)
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $productIds = array_values(array_filter($productIds, static function ($id) {
            return $id > 0;
        }));
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT DISTINCT prestashop_product_id
                FROM ' . _DB_PREFIX_ . 'yuju_product_status
                WHERE prestashop_product_id IN (' . implode(',', $productIds) . ')
                  AND sync_status IN ("error", "synced_with_errors")';
        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $pid = isset($row['prestashop_product_id']) ? (int) $row['prestashop_product_id'] : 0;
            if ($pid > 0) {
                $out[] = $pid;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    protected function runBulkSendForProductIds(array $productIds, $bulkAction, $logsUrl, $contextCategoryId = 0)
    {
        $productCount = count($productIds);
        $contextCategoryId = (int) $contextCategoryId;
        $createOptions = $contextCategoryId > 0 ? ['preferred_ps_category_id' => $contextCategoryId] : [];

        if ($productCount > 5) {
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
                    $hasYujuLink = $this->yujuStatusRowHasProductLink($status);

                    if ($bulkAction === 'delete') {
                        if (!$hasYujuLink) {
                            ++$skippedCount;
                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $productId)) {
                            ++$errorCount;
                            $errors[] = 'Producto ID ' . (int) $productId . ': no válido o no encontrado en PrestaShop';
                            continue;
                        }
                        $result = $syncQueue->addToQueue((int) $productId, 'delete', 'normal', []);
                    } elseif ($bulkAction === 'create') {
                        $validation = $this->product_manager->validateProductForYujuCreate((int) $productId, $createOptions);
                        if (empty($validation['success'])) {
                            $validationMsg = implode(' ', $validation['errors']);
                            $this->product_manager->updateProductStatus((int) $productId, 'error', $validationMsg, null);
                            $this->product_manager->logValidationErrorHistory((int) $productId, $validationMsg, 'create');
                            ++$errorCount;
                            $errors[] = 'Producto ID ' . (int) $productId . ': ' . $validationMsg;
                            continue;
                        }
                        if (!$this->ensureProductQueuedRowForYuju((int) $productId)) {
                            ++$errorCount;
                            $errors[] = 'Producto ID ' . (int) $productId . ': no válido o no encontrado en PrestaShop';
                            continue;
                        }
                        $queueData = $createOptions;
                        $result = $syncQueue->addToQueue((int) $productId, 'create', 'normal', $queueData);
                    } else {
                        if (!$this->ensureProductQueuedRowForYuju((int) $productId)) {
                            ++$errorCount;
                            $errors[] = 'Producto ID ' . (int) $productId . ': no válido o no encontrado en PrestaShop';
                            continue;
                        }
                        if ($hasYujuLink) {
                            $payload = $this->product_manager->buildProductPayloadForYujuQueue((int) $productId);
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
                        $errors[] = 'Error agregando producto ID ' . (int) $productId . ' a la cola';
                    }
                } catch (Exception $e) {
                    ++$errorCount;
                    $errors[] = 'Producto ID ' . (int) $productId . ': ' . $e->getMessage();
                }
            }

            $message = sprintf(
                '%d producto(s) en cola. %d omitido(s). %d error(es).',
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
            if ($errorCount > 0 && $errors !== []) {
                $message .= "\n\n" . implode("\n", $errors);
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

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($productIds as $productId) {
            try {
                $status = Db::getInstance()->getRow(
                    'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                    WHERE prestashop_product_id = ' . (int) $productId
                );
                $hasYujuLink = $this->yujuStatusRowHasProductLink($status);

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
                        $errors[] = 'Producto ID ' . (int) $productId . ': ' . ($del['message'] ?? 'Error al eliminar');
                    }
                    continue;
                }

                if ($bulkAction === 'create') {
                    $validation = $this->product_manager->validateProductForYujuCreate((int) $productId, $createOptions);
                    if (empty($validation['success'])) {
                        $validationMsg = implode(' ', $validation['errors']);
                        $this->product_manager->updateProductStatus((int) $productId, 'error', $validationMsg, null);
                        $this->product_manager->logValidationErrorHistory((int) $productId, $validationMsg, 'create');
                        ++$errorCount;
                        $errors[] = 'Producto ID ' . (int) $productId . ': ' . $validationMsg;
                        continue;
                    }
                }
                $result = $this->product_manager->sendProductToYuju((int) $productId, $createOptions);
                $ok = is_array($result) && !empty($result['success']);
                if ($ok) {
                    ++$successCount;
                } else {
                    ++$errorCount;
                    $errDetail = is_array($result)
                        ? ($result['error'] ?? $result['message'] ?? 'Error desconocido')
                        : 'Respuesta inválida del gestor de productos';
                    $errors[] = 'Producto ID ' . (int) $productId . ': ' . $errDetail;
                }
            } catch (Exception $e) {
                ++$errorCount;
                $errors[] = 'Producto ID ' . (int) $productId . ': ' . $e->getMessage();
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
        if ($errorCount > 0 && $errors !== []) {
            $message .= "\n\n" . implode("\n", $errors);
        }

        return [
            'success' => ($successCount > 0 || $skippedCount > 0) && $errorCount === 0,
            'message' => $message,
            'logs_url' => $logsUrl,
            'errors' => $errors,
            'details' => [
                'success' => $successCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
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
     * @param int $productId
     *
     * @return bool
     */
    protected function ensureProductQueuedRowForYuju($productId)
    {
        $productId = (int) $productId;
        $product = new Product($productId, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }

        $existing = Db::getInstance()->getValue(
            'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
            WHERE prestashop_product_id = ' . $productId
        );

        if (!$existing) {
            Db::getInstance()->insert('yuju_product_status', [
                'prestashop_product_id' => $productId,
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
                SELECT y.`id`, y.`prestashop_product_id`, y.`yuju_product_id`, y.`sync_status`, y.`last_sync_at`, y.`last_error`
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
                return ' AND NOT ' . $inv . ' AND yps.sync_status IN ("error","synced_with_errors") ';
            case 'pending':
                return ' AND NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","queued")) ';
            case 'in_progress':
                return ' AND NOT ' . $inv . ' AND yps.sync_status IN ("syncing","creating_in_yuju","updating_in_yuju","deleting_in_yuju") ';
            case 'synced_ok':
                return ' AND NOT ' . $inv . ' AND ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings") ';
            case 'disabled':
                return ' AND yps.sync_status = "disabled" ';
            case 'invalid':
                return ' AND ' . $inv . ' ';
            case 'orphan_synced':
                return ' AND NOT ' . $inv . ' AND NOT ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings","synced_with_errors") ';
            case 'all':
            default:
                return '';
        }
    }

    /**
     * @param string $search
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getCategoryOverviewRows($search)
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $searchSql = '';
        if ($search !== '') {
            $like = '%' . pSQL($search) . '%';
            $searchSql = ' AND cl.name LIKE "' . $like . '" ';
        }

        $ypsJoin = $this->getYpsJoinSql();
        $inv = $this->sqlInvalidCatalog();
        $vy = $this->sqlValidYujuId();

        $sql = 'SELECT 
                cp.id_category,
                MAX(cl.name) AS category_name,
                MAX(IF(ycm.id IS NOT NULL, 1, 0)) AS mapped_yuju,
                COUNT(DISTINCT p.id_product) AS total,
                SUM(IF(' . $inv . ', 1, 0)) AS cnt_invalid,
                SUM(IF(NOT ' . $inv . ' AND ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings"), 1, 0)) AS cnt_synced_ok,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status IN ("error","synced_with_errors"), 1, 0)) AS cnt_errors,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status IN ("syncing","creating_in_yuju","updating_in_yuju","deleting_in_yuju"), 1, 0)) AS cnt_in_progress,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","queued")), 1, 0)) AS cnt_pending,
                SUM(IF(yps.sync_status = "disabled", 1, 0)) AS cnt_disabled,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings","synced_with_errors"), 1, 0)) AS cnt_orphan_state
            FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = cp.id_product AND p.active = 1
            INNER JOIN ' . _DB_PREFIX_ . 'category c ON c.id_category = cp.id_category AND c.active = 1
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl 
                ON c.id_category = cl.id_category AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop . '
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = p.id_shop_default
            LEFT JOIN ' . _DB_PREFIX_ . 'yuju_category_mapping ycm ON ycm.prestashop_category_id = cp.id_category
            ' . $ypsJoin . '
            WHERE c.id_category NOT IN (' . (int) Configuration::get('PS_ROOT_CATEGORY') . ', ' . (int) Configuration::get('PS_HOME_CATEGORY') . ')
            ' . $searchSql . '
            GROUP BY cp.id_category
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 400';

        $rows = Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
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
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status IN ("error","synced_with_errors"), 1, 0)) AS cnt_errors,
                SUM(IF(NOT ' . $inv . ' AND yps.sync_status IN ("syncing","creating_in_yuju","updating_in_yuju","deleting_in_yuju"), 1, 0)) AS cnt_in_progress,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND (yps.sync_status IS NULL OR yps.sync_status IN ("pending","queued")), 1, 0)) AS cnt_pending,
                SUM(IF(yps.sync_status = "disabled", 1, 0)) AS cnt_disabled,
                SUM(IF(NOT ' . $inv . ' AND NOT ' . $vy . ' AND yps.sync_status IN ("synced","synced_with_warnings","synced_with_errors"), 1, 0)) AS cnt_orphan_state
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
        $cntPend = (int) $row['cnt_pending'];
        $cntProg = (int) $row['cnt_in_progress'];
        $pct = $total > 0 ? round(100 * $cntOk / $total) : 0;
        $bar = ['ok' => 0.0, 'pending' => 0.0, 'err' => 0.0, 'prog' => 0.0, 'inv' => 0.0];
        if ($total > 0) {
            $bar['ok'] = round(100 * $cntOk / $total, 2);
            $bar['pending'] = round(100 * $cntPend / $total, 2);
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
                'cnt_pending' => $cntPend,
                'cnt_disabled' => (int) $row['cnt_disabled'],
                'cnt_orphan_state' => (int) $row['cnt_orphan_state'],
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
            'SELECT prestashop_category_id
             FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
             WHERE prestashop_category_id IN (' . implode(',', array_map('intval', $lineage)) . ')
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

        $listSql = 'SELECT DISTINCT p.id_product, p.reference, pl.name,
                yps.sync_status AS yuju_status,
                yps.yuju_product_id,
                yps.last_sync_at,
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
