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
        'prestashop_product_attribute_id' => [
        'title' => 'ID Combinación PS',
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
        'yuju_variation_id' => [
        'title' => 'Clave de Variación Yuju',
        'align' => 'center',
        'class' => 'fixed-width-sm',
        ],
        'yuju_variation_sku' => [
        'title' => 'SKU Variación',
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

        $this->_select = 'pl.name as product_name, a.yuju_product_name as yuju_product_name';
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
        'pending' => 'Pendiente',
        'error' => 'Error',
        'disabled' => 'Deshabilitado',
        ];

        // Add statistics
        $stats = $this->getProductStatusStats();

        // The statistics template consumes the "stats" structure. Keep the
        // legacy variable as an alias for compatibility with any custom templates.
        $this->context->smarty->assign([
            'stats' => $stats,
            'product_status_stats' => $stats,
            'sync_running' => $this->sync_manager->isSyncRunning(),
        ]);

        $stats_html = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/product_status_stats.tpl');

        return $stats_html . parent::renderList();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitBulkenableSync')) {
            $this->processBulkEnableSync();
        } elseif (Tools::isSubmit('submitBulkdisableSync')) {
            $this->processBulkDisableSync();
        } elseif (Tools::isSubmit('submitBulksyncSelected')) {
            $this->processBulkSyncSelected();
        } elseif (Tools::isSubmit('submitBulkresetErrors')) {
            $this->processBulkResetErrors();
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

                if ($status && !empty($status['prestashop_product_id'])) {
                    $prestashop_product_ids[] = (int) $status['prestashop_product_id'];
                }
            }

            $prestashop_product_ids = array_values(array_unique($prestashop_product_ids));
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
            } else {
                $this->errors[] = 'Los registros seleccionados no tienen un producto enlazado en PrestaShop.';
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

            if (empty($status['prestashop_product_id'])) {
                $this->errors[] = 'Este producto o variación de Yuju todavía no está enlazado con PrestaShop. Enlázalo por SKU antes de sincronizarlo.';

                return;
            }

            $results = $this->product_manager->syncSpecificProducts([(int) $status['prestashop_product_id']]);

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
        $db = Db::getInstance();
        $stats = [];

        // Build one synchronization status per PrestaShop product so combinations
        // do not inflate the headline or per-category product counts.
        $productStatusSql = '
            SELECT
                prestashop_product_id,
                CASE
                    WHEN SUM(sync_status IN ("error", "synced_with_errors")) > 0 THEN "error"
                    WHEN SUM(sync_status IN ("synced", "synced_with_warnings")) > 0 THEN "synced"
                    WHEN SUM(sync_status = "disabled") > 0 THEN "disabled"
                    ELSE "pending"
                END AS product_sync_status,
                MAX(last_sync_at) AS last_sync_at,
                MAX(error_count) AS error_count,
                MAX(last_error) AS last_error
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id IS NOT NULL
              AND prestashop_product_id > 0
            GROUP BY prestashop_product_id
        ';

        $stats['total_products'] = (int) $db->getValue('
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product p
            WHERE p.active = 1
        ');

        $stats['synced_products'] = (int) $db->getValue('
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product p
            INNER JOIN (' . $productStatusSql . ') ps
                ON ps.prestashop_product_id = p.id_product
            WHERE p.active = 1
              AND ps.product_sync_status = "synced"
        ');

        $stats['pending_products'] = (int) $db->getValue('
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN (' . $productStatusSql . ') ps
                ON ps.prestashop_product_id = p.id_product
            WHERE p.active = 1
              AND (ps.prestashop_product_id IS NULL OR ps.product_sync_status = "pending")
        ');

        $stats['error_products'] = (int) $db->getValue('
            SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product p
            INNER JOIN (' . $productStatusSql . ') ps
                ON ps.prestashop_product_id = p.id_product
            WHERE p.active = 1
              AND ps.product_sync_status = "error"
        ');

        $stats['last_sync_date'] = $db->getValue('
            SELECT MAX(last_sync_at)
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');

        // Category membership is taken from category_product, not only the
        // default category, so products appear in every category they belong to.
        $stats['category_stats'] = $db->executeS('
            SELECT
                c.id_category AS category_id,
                cl.name AS category_name,
                COUNT(DISTINCT p.id_product) AS total,
                COUNT(DISTINCT CASE
                    WHEN ps.product_sync_status = "synced" THEN p.id_product
                    ELSE NULL
                END) AS synced,
                COUNT(DISTINCT CASE
                    WHEN ps.prestashop_product_id IS NULL
                      OR ps.product_sync_status = "pending" THEN p.id_product
                    ELSE NULL
                END) AS pending,
                COUNT(DISTINCT CASE
                    WHEN ps.product_sync_status = "error" THEN p.id_product
                    ELSE NULL
                END) AS errors
            FROM ' . _DB_PREFIX_ . 'category_product cp
            INNER JOIN ' . _DB_PREFIX_ . 'product p
                ON p.id_product = cp.id_product AND p.active = 1
            INNER JOIN ' . _DB_PREFIX_ . 'category c
                ON c.id_category = cp.id_category
            INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl
                ON cl.id_category = c.id_category
               AND cl.id_lang = ' . (int) $this->context->language->id . '
               AND cl.id_shop = ' . (int) $this->context->shop->id . '
            LEFT JOIN (' . $productStatusSql . ') ps
                ON ps.prestashop_product_id = p.id_product
            GROUP BY c.id_category, cl.name
            ORDER BY cl.name ASC
        ');
        if (!is_array($stats['category_stats'])) {
            $stats['category_stats'] = [];
        }

        // Recent activity data is an array, as expected by the template.
        $stats['recent_syncs'] = $db->executeS('
            SELECT
                ps.prestashop_product_id AS product_id,
                COALESCE(pl.name, ps.yuju_product_name, CONCAT("Producto #", ps.prestashop_product_id)) AS product_name,
                ps.sync_status,
                ps.last_sync_at AS last_sync_attempt
            FROM ' . _DB_PREFIX_ . 'yuju_product_status ps
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON pl.id_product = ps.prestashop_product_id
               AND pl.id_lang = ' . (int) $this->context->language->id . '
               AND pl.id_shop = ' . (int) $this->context->shop->id . '
            WHERE ps.last_sync_at IS NOT NULL
            ORDER BY ps.last_sync_at DESC
            LIMIT 10
        ');
        if (!is_array($stats['recent_syncs'])) {
            $stats['recent_syncs'] = [];
        }

        $stats['recent_errors'] = $db->executeS('
            SELECT
                ps.prestashop_product_id AS product_id,
                COALESCE(pl.name, ps.yuju_product_name, CONCAT("Producto #", ps.prestashop_product_id)) AS product_name,
                COALESCE(NULLIF(ps.last_error, ""), "Error de sincronización (sin detalle)") AS error_message,
                ps.last_sync_at AS error_date
            FROM ' . _DB_PREFIX_ . 'yuju_product_status ps
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON pl.id_product = ps.prestashop_product_id
               AND pl.id_lang = ' . (int) $this->context->language->id . '
               AND pl.id_shop = ' . (int) $this->context->shop->id . '
            WHERE ps.error_count > 0
               OR ps.sync_status IN ("error", "synced_with_errors")
            ORDER BY ps.last_sync_at DESC
            LIMIT 10
        ');
        if (!is_array($stats['recent_errors'])) {
            $stats['recent_errors'] = [];
        }

        // Preserve the previous summary keys in case another module template uses them.
        $stats['total'] = $stats['total_products'];
        $stats['active'] = (int) $db->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_enabled = 1
        ');
        $stats['inactive'] = max(0, $stats['total'] - $stats['active']);
        $stats['with_errors'] = $stats['error_products'];
        $stats['by_status'] = $db->executeS('
            SELECT sync_status, COUNT(*) AS count
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
            GROUP BY sync_status
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
        'PrestaShop Combination ID',
        'Product Name',
        'Yuju Product ID',
        'Yuju Product Name',
        'Yuju Variation ID',
        'Yuju Variation SKU',
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
        LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (ps.prestashop_product_id = pl.id_product AND pl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . ')
        ORDER BY ps.created_at DESC
        ');

        foreach ($records as $record) {
            fputcsv($handle, [
            $record['prestashop_product_id'],
            $record['prestashop_product_attribute_id'],
            $record['product_name'],
            $record['yuju_product_id'],
            $record['yuju_product_name'],
            $record['yuju_variation_id'],
            $record['yuju_variation_sku'],
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

        if (empty($row['prestashop_product_id'])) {
            $name = !empty($row['yuju_product_name']) ? $row['yuju_product_name'] : 'Producto Yuju sin enlazar';
            if (!empty($row['yuju_variation_sku'])) {
                $name .= ' — Variación ' . $row['yuju_variation_sku'];
            }

            return $name;
        }

        $product = new Product((int) $row['prestashop_product_id'], false, $this->context->language->id);

        return Validate::isLoadedObject($product) ? $product->name : 'Producto no encontrado';
    }

    public function displaySyncStatus($value, $row)
    {
        $status_colors = [
        'synced' => 'success',
        'pending' => 'warning',
        'error' => 'danger',
        'disabled' => 'default',
        ];

        $color = isset($status_colors[$value]) ? $status_colors[$value] : 'default';

        return '<span class="label label-' . $color . '">' . ucfirst($value) . '</span>';
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
        'required' => false,
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
}
