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
        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (a.prestashop_product_id = pl.id_product AND pl.id_lang = ' . (int) $this->context->language->id . ')';

        $this->_orderBy = 'last_sync_at';
        $this->_orderWay = 'DESC';
    }

    public function initContent()
    {
        $this->context->smarty->assign('current_controller', 'AdminYujuProductStatus');
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
        'ajax_url' => $ajax_url,
        'current_index' => self::$currentIndex,
        'token' => $this->token,
        ]);

        $stats_html = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/product_status_stats.tpl');

        return $stats_html;
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
    
    protected function getProductsWithFilters($page = 1, $per_page = 25)
    {
        $store_id = (int)Tools::getValue('store', 0);
        $ps_category_id = (int)Tools::getValue('ps_category', 0);
        $yuju_category_id = (int)Tools::getValue('yuju_category', 0);
        $sync_status = Tools::getValue('status', '');
        $date_from = Tools::getValue('date_from', '');
        $date_to = Tools::getValue('date_to', '');
        $search = pSQL(Tools::getValue('search', ''));
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
        
        // Filter products without reference and/or name
        $where .= ' AND p.reference IS NOT NULL AND p.reference != "" AND pl.name IS NOT NULL AND pl.name != ""';
        
        // Count total products - SIN JOIN con category_product
        $count_sql = 'SELECT COUNT(DISTINCT p.id_product) as total
                      FROM ' . _DB_PREFIX_ . 'product p
                      LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                          ON p.id_product = pl.id_product 
                          AND pl.id_lang = ' . (int)$this->context->language->id . '
                      LEFT JOIN ' . _DB_PREFIX_ . 'yuju_product_status yps 
                          ON p.id_product = yps.prestashop_product_id
                      WHERE ' . $where;
        
        $total_result = Db::getInstance()->getRow($count_sql);
        $total_products = (int)$total_result['total'];
        
        // Calculate pagination
        $total_pages = ceil($total_products / $per_page);
        $page = max(1, min($page, $total_pages ?: 1));
        $offset = ($page - 1) * $per_page;
        $from = $total_products > 0 ? $offset + 1 : 0;
        $to = min($offset + $per_page, $total_products);
        
        // Get products - Usar SOLO la categoría por defecto del producto
        $products_sql = 'SELECT 
                            p.id_product,
                            p.reference,
                            pl.name,
                            cl.name as category_name,
                            i.id_image,
                            yps.sync_status as yuju_status,
                            yps.yuju_product_id,
                            yps.last_sync_at,
                            yps.last_error
                         FROM ' . _DB_PREFIX_ . 'product p
                         LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                             ON p.id_product = pl.id_product 
                             AND pl.id_lang = ' . (int)$this->context->language->id . '
                         LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl 
                             ON p.id_category_default = cl.id_category 
                             AND cl.id_lang = ' . (int)$this->context->language->id . '
                         LEFT JOIN ' . _DB_PREFIX_ . 'yuju_product_status yps 
                             ON p.id_product = yps.prestashop_product_id
                         LEFT JOIN ' . _DB_PREFIX_ . 'image i
                             ON p.id_product = i.id_product
                             AND i.cover = 1
                         WHERE ' . $where . '
                         ORDER BY p.id_product DESC
                         LIMIT ' . (int)$offset . ', ' . (int)$per_page;
        
        $products = Db::getInstance()->executeS($products_sql);
        
        return [
            'products' => $products ?: [],
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
        $_GET['category'] = isset($filters['category']) ? $filters['category'] : '';
        $_GET['status'] = isset($filters['status']) ? $filters['status'] : '';
        $_GET['search'] = isset($filters['search']) ? $filters['search'] : '';
        
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
        
        $product_ids = Tools::getValue('product_ids', []);
        
        if (empty($product_ids)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No se han seleccionado productos'
            ]);
            return;
        }
        
        $product_count = count($product_ids);
        
        // Si son más de 5 productos, agregar a cola
        if ($product_count > 5) {
            $sync_queue = new YujuSyncQueue();
            $queued_count = 0;
            $skipped_count = 0;
            $error_count = 0;
            $errors = [];
            
            foreach ($product_ids as $product_id) {
                try {
                    // Verificar si el producto ya tiene ID de Yuju
                    $status = Db::getInstance()->getRow(
                        'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                        WHERE prestashop_product_id = ' . (int)$product_id
                    );
                    
                    if (!$status || empty($status['yuju_product_id'])) {
                        // Producto no existe en Yuju, preparar datos y agregar a cola
                        $product = new Product((int)$product_id, false, $this->context->language->id);
                        
                        if (Validate::isLoadedObject($product)) {
                            // Verificar si ya existe registro en yuju_product_status
                            $existing = Db::getInstance()->getValue(
                                'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                                WHERE prestashop_product_id = ' . (int)$product_id
                            );
                            
                            if (!$existing) {
                                // Crear registro inicial
                                Db::getInstance()->insert('yuju_product_status', [
                                    'prestashop_product_id' => (int)$product_id,
                                    'sync_status' => pSQL('queued'),
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                            } else {
                                // Actualizar estado a queued
                                Db::getInstance()->update(
                                    'yuju_product_status',
                                    [
                                        'sync_status' => pSQL('queued'),
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ],
                                    'prestashop_product_id = ' . (int)$product_id
                                );
                            }
                            
                            // Agregar a cola para creación
                            $result = $sync_queue->addToQueue(
                                (int)$product_id,
                                'create',
                                'normal',
                                [] // Los datos se generarán al procesar
                            );
                            
                            if ($result) {
                                $queued_count++;
                            } else {
                                $error_count++;
                                $errors[] = "Error agregando producto ID: $product_id a la cola";
                            }
                        }
                    } else {
                        // Producto ya existe en Yuju
                        $skipped_count++;
                    }
                } catch (Exception $e) {
                    $error_count++;
                    $errors[] = "Producto ID $product_id: " . $e->getMessage();
                }
            }
            
            $message = sprintf(
                '%d producto(s) agregado(s) a la cola. %d omitido(s) (ya en Yuju). %d error(es).',
                $queued_count,
                $skipped_count,
                $error_count
            );
            
            if ($queued_count > 0) {
                $message .= ' Se procesarán en el próximo ciclo del cron (cada 5 minutos).';
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => ($queued_count > 0 || $skipped_count > 0) && $error_count == 0,
                'message' => $message,
                'details' => [
                    'queued' => $queued_count,
                    'skipped' => $skipped_count,
                    'errors' => $error_count,
                    'error_messages' => $errors
                ],
                'reload' => true
            ]);
            return;
        }
        
        // 5 productos o menos: Envío inmediato
        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $errors = [];
        
        foreach ($product_ids as $product_id) {
            try {
                // Verificar si el producto ya tiene ID de Yuju
                $status = Db::getInstance()->getRow(
                    'SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                    WHERE prestashop_product_id = ' . (int)$product_id
                );
                
                if ($status && !empty($status['yuju_product_id'])) {
                    // Producto ya existe en Yuju, no lo enviamos
                    $skipped_count++;
                    continue;
                }
                
                $result = $this->product_manager->sendProductToYuju((int)$product_id);
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Error enviando producto ID: $product_id";
                }
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Producto ID $product_id: " . $e->getMessage();
            }
        }
        
        $message = sprintf(
            '%d producto(s) enviado(s). %d omitido(s) (ya en Yuju). %d error(es).',
            $success_count,
            $skipped_count,
            $error_count
        );
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => ($success_count > 0 || $skipped_count > 0) && $error_count == 0,
            'message' => $message,
            'details' => [
                'success' => $success_count,
                'skipped' => $skipped_count,
                'errors' => $error_count,
                'error_messages' => $errors
            ],
            'reload' => true
        ]);
    }
    
    protected function ajaxProcessSendAllFiltered()
    {
        $filters = Tools::getValue('filters', []);
        
        // Override $_GET for getProductsWithFilters
        $_GET['store'] = isset($filters['store']) ? $filters['store'] : '';
        $_GET['category'] = isset($filters['category']) ? $filters['category'] : '';
        $_GET['status'] = isset($filters['status']) ? $filters['status'] : '';
        $_GET['search'] = isset($filters['search']) ? $filters['search'] : '';
        
        // Get all filtered products (without pagination)
        $result = $this->getProductsWithFilters(1, 999999);
        $products = $result['products'];
        
        if (empty($products)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No hay productos que coincidan con los filtros'
            ]);
            return;
        }
        
        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        
        foreach ($products as $product) {
            try {
                // Verificar si el producto ya tiene ID de Yuju
                if (!empty($product['yuju_product_id'])) {
                    // Producto ya existe en Yuju, no lo enviamos
                    $skipped_count++;
                    continue;
                }
                
                $result = $this->product_manager->sendProductToYuju((int)$product['id_product']);
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => ($success_count > 0 || $skipped_count > 0) && $error_count == 0,
            'message' => sprintf(
                'Proceso completado: %d enviado(s), %d omitido(s) (ya en Yuju), %d error(es).',
                $success_count,
                $skipped_count,
                $error_count
            ),
            'details' => [
                'total' => count($products),
                'success' => $success_count,
                'skipped' => $skipped_count,
                'errors' => $error_count
            ]
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

            $results = $this->product_manager->syncSpecificProducts([$status['prestashop_product_id']]);

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
        LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON ps.prestashop_product_id = pl.id_product
        WHERE pl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
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
            
            // Obtener total de registros
            $total_records = (int) Db::getInstance()->getValue('
                SELECT COUNT(*) 
                FROM ' . _DB_PREFIX_ . 'yuju_product_sync_history 
                WHERE prestashop_product_id = ' . (int)$product_id
            );
            
            $total_pages = ceil($total_records / $limit);
            
            // Obtener historial con paginación
            $history = $this->product_manager->getProductSyncHistory($product_id, $limit, $offset);
            $stats = $this->product_manager->getProductSyncStatistics($product_id);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'history' => $history,
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_records' => $total_records,
                    'limit' => $limit
                ]
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


