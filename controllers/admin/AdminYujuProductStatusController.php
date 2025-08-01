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
        'pending' => 'Pendiente',
        'error' => 'Error',
        'disabled' => 'Deshabilitado',
        ];

        // Add statistics
        $stats = $this->getProductStatusStats();

        $this->context->smarty->assign([
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

        // Active vs inactive
        $stats['active'] = (int) Db::getInstance()->getValue('
        SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_enabled = 1
        ');

        $stats['inactive'] = $stats['total'] - $stats['active'];

        // With errors
        $stats['with_errors'] = (int) Db::getInstance()->getValue('
        SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE error_count > 0
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
}
