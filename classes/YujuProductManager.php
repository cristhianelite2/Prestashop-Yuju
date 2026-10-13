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

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuCategoryManager.php';
require_once dirname(__FILE__) . '/YujuProductReportReconciler.php';

class YujuProductManager
{
    private $api_client;
    private $logger;
    private $category_manager;
    private $field_mappings;

    public function __construct($api_client = null, $logger = null, $category_manager = null, $field_mappings = null)
    {
        $this->api_client = $api_client ?: new YujuApiClient();
        $this->logger = $logger ?: new YujuLogger();
        $this->category_manager = $category_manager ?: new YujuCategoryManager();

        if ($field_mappings === null) {
            $this->loadFieldMappings();
        } else {
            $this->field_mappings = $field_mappings;
        }
    }

    /**
     * Load active field mappings.
     */
    protected function loadFieldMappings()
    {
        // Repair only known legacy defaults. Never overwrite an existing valid/custom mapping.
        $legacy_mappings = [
            ['name', 'title', 'name'],
            ['ean13', 'barcode', 'ean'],
            ['quantity', 'stock_quantity', 'stock'],
            ['width', 'width', 'shipping_width'],
            ['height', 'height', 'shipping_height'],
            ['depth', 'length', 'shipping_depth'],
            ['depth', 'depth', 'shipping_depth'],
        ];
        $all_mappings = Db::getInstance()->executeS('
            SELECT id_mapping, prestashop_field, yuju_field
            FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
        ');
        $mapping_targets = [];
        if (is_array($all_mappings)) {
            foreach ($all_mappings as $row) {
                $mapping_targets[$row['prestashop_field'] . '|' . $row['yuju_field']] = (int) $row['id_mapping'];
            }
            foreach ($all_mappings as $row) {
                foreach ($legacy_mappings as $legacy) {
                    list($ps_field, $old_yuju_field, $new_yuju_field) = $legacy;
                    if ($row['prestashop_field'] !== $ps_field || $row['yuju_field'] !== $old_yuju_field) {
                        continue;
                    }
                    $target_key = $ps_field . '|' . $new_yuju_field;
                    if (!isset($mapping_targets[$target_key]) || $mapping_targets[$target_key] === (int) $row['id_mapping']) {
                        Db::getInstance()->update(
                            'yuju_product_mapping',
                            ['yuju_field' => pSQL($new_yuju_field), 'updated_at' => date('Y-m-d H:i:s')],
                            'id_mapping = ' . (int) $row['id_mapping']
                        );
                        $mapping_targets[$target_key] = (int) $row['id_mapping'];
                    }
                }
            }
        }
        // If a valid mapping already existed, disable rather than compare a stale legacy alias.
        Db::getInstance()->execute('
            UPDATE ' . _DB_PREFIX_ . 'yuju_product_mapping
            SET is_active = 0, updated_at = NOW()
            WHERE (prestashop_field = "name" AND yuju_field = "title")
               OR (prestashop_field = "ean13" AND yuju_field = "barcode")
               OR (prestashop_field = "quantity" AND yuju_field = "stock_quantity")
               OR (prestashop_field = "width" AND yuju_field = "width")
               OR (prestashop_field = "height" AND yuju_field = "height")
               OR (prestashop_field = "depth" AND yuju_field IN ("depth", "length"))
        ');

        $this->field_mappings = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping
            WHERE is_active = 1
            ORDER BY `prestashop_field`
        ');
    }

    /**
     * Synchronize products from Yuju to PrestaShop.
     */
    public function syncProductsFromYuju($product_ids = null, $force_update = false)
    {
        try {
            $this->logger->log('Starting product synchronization from Yuju', 'info');

            $filters = [];

            if ($product_ids && is_array($product_ids)) {
                $filters['ids'] = $product_ids;
            }

            $yuju_products = self::extractYujuProducts($this->api_client->getProducts($filters));

            $synced_count = 0;
            $created_count = 0;
            $updated_count = 0;
            $errors = [];

            foreach ($yuju_products as $yuju_product) {
                try {
                    $result = $this->syncSingleProductFromYuju($yuju_product, $force_update);

                    if ($result) {
                        ++$synced_count;

                        if ($result['action'] === 'created') {
                            ++$created_count;
                        } else {
                            ++$updated_count;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Product ' . $yuju_product['id'] . ': ' . $e->getMessage();
                    $this->logger->log('Error syncing product ' . $yuju_product['id'] . ': ' . $e->getMessage(), 'error');
                }
            }

            $this->logger->log('Product synchronization from Yuju completed. Synced: ' . $synced_count . ' (Created: ' . $created_count . ', Updated: ' . $updated_count . '), Errors: ' . count($errors), 'info');

            YujuMonitor::tally('sync.product', (int) $synced_count, count($errors), [
                'direction' => 'from_yuju',
            ]);

            return [
                'success' => true,
                'synced_count' => $synced_count,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->log('Product synchronization from Yuju failed: ' . $e->getMessage(), 'error');

            YujuMonitor::tally('sync.error', 0, 1, [
                'direction' => 'from_yuju',
                'level' => 'error',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Compare Yuju products with the IDs stored by this module.
     */
    public function getYujuProductSyncReport($report_index = null, $report_products = null)
    {
        if (!is_array($report_index) || (!is_array($report_products) && !($report_products instanceof Traversable))) {
            throw new InvalidArgumentException('A validated Yuju general report is required for reconciliation.');
        }

        return $this->reconcileYujuProductReport($report_index, $report_products);
    }

    /**
     * Reconcile active PrestaShop products against the cached Yuju report.
     * This method only reads the report; it never imports Yuju values to PS.
     *
     * @param array $report_index Compact ID/SKU index built from the cached report
     * @param iterable $report_products A fresh streaming pass over the report rows
     *
     * @return array
     */
    public function reconcileYujuProductReport($report_index, $report_products)
    {
        $products = $this->getProductsForSync();
        if ($products === false) {
            throw new Exception('Could not read active PrestaShop products.');
        }

        // The self-updater copies module files but older updater versions did not
        // execute upgrade scripts. Repair the module-owned local status table before
        // querying columns introduced by the migration. Never access Yuju's external DB.
        $status_table = _DB_PREFIX_ . 'yuju_product_status';
        $status_columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($status_table) . '`');
        if (!is_array($status_columns)) {
            throw new Exception('No se pudo verificar la estructura de la tabla local ' . $status_table . '.');
        }
        $status_column_names = [];
        foreach ($status_columns as $status_column) {
            $status_column_names[$status_column['Field']] = true;
        }
        $required_status_columns = ['prestashop_product_attribute_id', 'yuju_product_name', 'yuju_variation_id', 'yuju_variation_sku'];
        $missing_status_columns = array_diff($required_status_columns, array_keys($status_column_names));
        if (!empty($missing_status_columns)) {
            $migration_file = _PS_MODULE_DIR_ . 'prestashopyuju/upgrade/upgrade-1.1.7.php';
            if (!is_file($migration_file)) {
                throw new Exception('Falta la migración de la tabla local de estados Yuju. Actualice el módulo a la versión 1.1.8.');
            }
            require_once $migration_file;
            if (!function_exists('upgrade_module_1_1_7') || !upgrade_module_1_1_7(Module::getInstanceByName('prestashopyuju'))) {
                throw new Exception('No se pudo migrar la tabla local ' . $status_table . '. No se modificaron tablas externas de Yuju.');
            }
        }

        $status_rows = Db::getInstance()->executeS('
            SELECT prestashop_product_id, yuju_product_id, yuju_variation_id, sync_status, sync_enabled, last_sync_at
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');
        if ($status_rows === false) {
            throw new Exception('Could not read Yuju product mappings.');
        }

        $status_by_product = [];
        $status_by_yuju_item = [];
        foreach ($status_rows as $status_row) {
            if (!empty($status_row['prestashop_product_id'])) {
                $status_by_product[(int) $status_row['prestashop_product_id']] = $status_row;
            }
            if (!empty($status_row['yuju_product_id'])) {
                $variation_key = isset($status_row['yuju_variation_id']) ? (string) $status_row['yuju_variation_id'] : '';
                $status_by_yuju_item[json_encode([(string) $status_row['yuju_product_id'], $variation_key])] = $status_row;
            }
        }

        $contexts = [];
        $sku_owners = [];
        $sku_owner_attributes = [];
        foreach ($products as $product_row) {
            $prestashop_product_id = (int) $product_row['id_product'];
            $product = new Product($prestashop_product_id);
            if (empty($product->id)) {
                continue;
            }

            $payload = $this->prepareProductDataForYuju($product);
            if (class_exists('StockAvailable')) {
                $payload['stock'] = (int) StockAvailable::getQuantityAvailableByProduct($prestashop_product_id, 0);
            }
            $variations = $this->getProductVariationsForYuju($product);
            $skus = [];
            if (!empty($product->reference)) {
                $skus[] = $product->reference;
            }
            foreach ($variations as $variation) {
                if (!empty($variation['sku'])) {
                    $skus[] = $variation['sku'];
                }
            }
            $skus = array_values(array_unique(array_filter(array_map('trim', $skus), 'strlen')));

            $context = [
                'id' => $prestashop_product_id,
                'product' => $product,
                'payload' => $payload,
                'variations' => $variations,
                'skus' => $skus,
                'display_sku' => !empty($payload['sku']) ? (string) $payload['sku'] : (isset($skus[0]) ? $skus[0] : ''),
                'status_row' => isset($status_by_product[$prestashop_product_id]) ? $status_by_product[$prestashop_product_id] : null,
            ];
            $contexts[$prestashop_product_id] = $context;

            foreach ($skus as $sku) {
                $normalized_sku = YujuProductReportReconciler::normalizeSku($sku);
                if ($normalized_sku !== '') {
                    $sku_owners[$normalized_sku][$prestashop_product_id] = true;
                }
            }
            foreach ($variations as $variation) {
                $normalized_sku = YujuProductReportReconciler::normalizeSku(isset($variation['sku']) ? $variation['sku'] : '');
                if ($normalized_sku !== '') {
                    $sku_owner_attributes[$normalized_sku][$prestashop_product_id] = isset($variation['prestashop_product_attribute_id'])
                        ? (int) $variation['prestashop_product_attribute_id']
                        : null;
                }
            }
        }

        $results = [];
        $candidate_groups = [];
        foreach ($contexts as $prestashop_product_id => $context) {
            $status_row = $context['status_row'];
            $existing_yuju_id = $status_row && !empty($status_row['yuju_product_id'])
                ? (string) $status_row['yuju_product_id']
                : null;
            $unique_skus = [];
            $duplicate_skus = [];

            foreach ($context['skus'] as $sku) {
                $normalized_sku = YujuProductReportReconciler::normalizeSku($sku);
                $owners = isset($sku_owners[$normalized_sku]) ? array_keys($sku_owners[$normalized_sku]) : [];
                if (count($owners) === 1) {
                    $unique_skus[] = $sku;
                } else {
                    $duplicate_skus[] = $sku;
                }
            }

            $match = YujuProductReportReconciler::matchProduct($report_index, $existing_yuju_id, $unique_skus);
            $results[$prestashop_product_id] = $this->buildPendingProductReportRow(
                $context,
                $existing_yuju_id,
                $match,
                $duplicate_skus
            );

            if ($match['status'] === 'matched') {
                $candidate_groups[$match['yuju_product_id']][$prestashop_product_id] = $match['matched_by'];
            }
        }

        $selected_products = [];
        foreach ($candidate_groups as $yuju_product_id => $candidates) {
            if (count($candidates) === 1) {
                $selected_products[$yuju_product_id] = (int) key($candidates);
                continue;
            }

            $existing_id_matches = [];
            foreach ($candidates as $prestashop_product_id => $matched_by) {
                $status_row = $contexts[$prestashop_product_id]['status_row'];
                if ($matched_by === 'yuju_id' && $status_row
                    && (string) $status_row['yuju_product_id'] === (string) $yuju_product_id
                ) {
                    $existing_id_matches[] = (int) $prestashop_product_id;
                }
            }

            if (count($existing_id_matches) === 1) {
                $winner = $existing_id_matches[0];
                $selected_products[(string) $yuju_product_id] = $winner;
                foreach ($candidates as $prestashop_product_id => $matched_by) {
                    if ((int) $prestashop_product_id !== $winner) {
                        $results[(int) $prestashop_product_id]['reconciliation_status'] = 'ambiguous';
                        $results[(int) $prestashop_product_id]['message'] = 'La relación SKU apunta a un producto Yuju ya vinculado a otro producto PrestaShop.';
                    }
                }
            } else {
                foreach ($candidates as $prestashop_product_id => $matched_by) {
                    $results[(int) $prestashop_product_id]['reconciliation_status'] = 'ambiguous';
                    $results[(int) $prestashop_product_id]['message'] = 'La relación entre SKU de PrestaShop y producto Yuju es ambigua.';
                }
            }
        }

        // Build the status catalog from Yuju's report, not from the PrestaShop catalog.
        // Parent products and each nested variation receive their own row; an unmatched
        // Yuju item remains visible with prestashop_product_id = NULL.
        $yuju_catalog_rows = [];
        $seen_report_product_ids = [];
        foreach ($report_products as $report_product) {
            $yuju_product_id = isset($report_product['id_product']) ? trim((string) $report_product['id_product']) : '';
            if ($yuju_product_id === '') {
                throw new InvalidArgumentException('The Yuju report contains a product with an empty ID. Catalog reconciliation was cancelled.');
            }

            // Store each parent once. indexReportRows has already rejected conflicting
            // copies of the same ID and this pass ignores exact repeated rows.
            if (isset($seen_report_product_ids[$yuju_product_id])) {
                continue;
            }
            $seen_report_product_ids[$yuju_product_id] = true;

            $prestashop_product_id = isset($selected_products[$yuju_product_id])
                && !isset($report_index['duplicate_ids'][$yuju_product_id])
                ? (int) $selected_products[$yuju_product_id]
                : null;
            $report_downloaded_at = isset($report_product['_report_downloaded_at'])
                ? (int) $report_product['_report_downloaded_at']
                : 0;
            $catalog_status = 'pending';
            $catalog_error = null;
            $parent_status_key = json_encode([$yuju_product_id, '']);
            $catalog_sync_enabled = isset($status_by_yuju_item[$parent_status_key])
                ? (int) $status_by_yuju_item[$parent_status_key]['sync_enabled']
                : 1;

            if ($prestashop_product_id !== null && isset($contexts[$prestashop_product_id])) {
                $context = $contexts[$prestashop_product_id];
                $result = $this->compareProductWithYujuReport($context, $report_product);
                $result['prestashop_product_id'] = $prestashop_product_id;
                $result['yuju_product_id'] = $yuju_product_id;
                $result['sku'] = $context['display_sku'];
                $result['name'] = $this->getProductDisplayName($context['product']);

                $status_row = $context['status_row'];
                if ($status_row && empty($status_row['sync_enabled'])) {
                    $result['status'] = 'disabled';
                    $result['reconciliation_status'] = 'disabled';
                    $result['syncable'] = false;
                    $result['message'] = 'La sincronización de este producto está deshabilitada en el módulo.';
                }
                $catalog_status = isset($result['status']) ? $result['status'] : 'pending';
                $catalog_error = isset($result['message']) ? $result['message'] : null;
                $catalog_sync_enabled = $status_row && isset($status_row['sync_enabled']) ? (int) $status_row['sync_enabled'] : 1;

                $yuju_status_row = isset($status_by_yuju_item[$parent_status_key])
                    ? $status_by_yuju_item[$parent_status_key]
                    : null;
                if ($catalog_status === 'pending' && $catalog_sync_enabled
                    && $report_downloaded_at > 0 && $yuju_status_row
                    && isset($yuju_status_row['sync_status'], $yuju_status_row['last_sync_at'])
                    && $yuju_status_row['sync_status'] === 'synced'
                    && !empty($yuju_status_row['last_sync_at'])
                    && strtotime($yuju_status_row['last_sync_at']) > $report_downloaded_at
                ) {
                    // A report older than the successful write cannot prove the item is still pending.
                    $catalog_status = 'synced';
                    $catalog_error = null;
                    $result['status'] = 'synced';
                    $result['reconciliation_status'] = 'synced';
                    $result['message'] = null;
                    $result['syncable'] = false;
                }

                $results[$prestashop_product_id] = $result;
            }

            $yuju_catalog_rows[] = [
                'yuju_product_id' => $yuju_product_id,
                'yuju_product_name' => $this->getYujuReportProductName($report_product),
                'yuju_variation_id' => '',
                'yuju_variation_sku' => null,
                'prestashop_product_attribute_id' => null,
                'prestashop_product_id' => $prestashop_product_id,
                'sync_status' => $catalog_status,
                'report_downloaded_at' => $report_downloaded_at,
                'sync_enabled' => $catalog_sync_enabled,
                'last_error' => $catalog_error,
            ];

            $variations = isset($report_product['variations']) && is_array($report_product['variations'])
                ? $report_product['variations']
                : [];
            $seen_variation_keys = [];
            foreach ($variations as $variation) {
                if (!is_array($variation)) {
                    continue;
                }
                $variation_sku = isset($variation['sku']) && is_scalar($variation['sku'])
                    ? trim((string) $variation['sku'])
                    : '';
                $variation_id = isset($variation['id_variation']) ? trim((string) $variation['id_variation'])
                    : (isset($variation['variation_id']) ? trim((string) $variation['variation_id']) : '');
                if ($variation_sku === '' && $variation_id === '') {
                    continue;
                }

                // Ignore repeated copies of the same variation within one parent.
                // Use Yuju's stable variation ID when available; otherwise its normalized SKU.
                $variation_key = $variation_id !== ''
                    ? 'id:' . $variation_id
                    : 'sku:' . YujuProductReportReconciler::normalizeSku($variation_sku);
                if (isset($seen_variation_keys[$variation_key])) {
                    continue;
                }
                $seen_variation_keys[$variation_key] = true;

                $variation_ps_id = $prestashop_product_id;
                $variation_ps_attribute_id = null;
                if ($variation_sku !== '') {
                    $normalized_sku = YujuProductReportReconciler::normalizeSku($variation_sku);
                    $owners = isset($sku_owners[$normalized_sku]) ? array_keys($sku_owners[$normalized_sku]) : [];
                    if ($variation_ps_id === null && count($owners) === 1) {
                        $variation_ps_id = (int) $owners[0];
                    }
                    if ($variation_ps_id !== null && isset($sku_owner_attributes[$normalized_sku][$variation_ps_id])) {
                        $variation_ps_attribute_id = $sku_owner_attributes[$normalized_sku][$variation_ps_id];
                    }
                }

                $variation_status = $variation_ps_id !== null && isset($results[$variation_ps_id])
                    ? $results[$variation_ps_id]['status']
                    : 'pending';
                $variation_status_key = json_encode([$yuju_product_id, $variation_key]);
                $variation_enabled = isset($status_by_yuju_item[$variation_status_key])
                    ? (int) $status_by_yuju_item[$variation_status_key]['sync_enabled']
                    : (($variation_ps_id !== null && isset($contexts[$variation_ps_id]['status_row'])
                        && $contexts[$variation_ps_id]['status_row'])
                        ? (int) $contexts[$variation_ps_id]['status_row']['sync_enabled']
                        : 1);
                $yuju_catalog_rows[] = [
                    'yuju_product_id' => $yuju_product_id,
                    'yuju_product_name' => $this->getYujuReportProductName($report_product),
                    'yuju_variation_id' => $variation_key,
                    'yuju_variation_sku' => $variation_sku !== '' ? $variation_sku : null,
                    'prestashop_product_attribute_id' => $variation_ps_attribute_id,
                    'prestashop_product_id' => $variation_ps_id,
                    'sync_status' => $variation_status,
                    'report_downloaded_at' => $report_downloaded_at,
                    'sync_enabled' => $variation_enabled,
                    'last_error' => null,
                ];
            }
        }

        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            throw new Exception('Could not start transaction to save Yuju reconciliation state.');
        }

        try {
            foreach ($yuju_catalog_rows as $catalog_row) {
                if (!$this->saveYujuCatalogStatus($catalog_row)) {
                    throw new Exception('Could not save Yuju catalog status for product ' . $catalog_row['yuju_product_id'] . '.');
                }
            }
            if (!$this->pruneYujuCatalogStatuses($yuju_catalog_rows)) {
                throw new Exception('Could not remove stale entries from the Yuju product status catalog.');
            }

            if (!$db->execute('COMMIT')) {
                throw new Exception('Could not commit Yuju reconciliation state.');
            }
        } catch (Exception $e) {
            $db->execute('ROLLBACK');
            throw $e;
        }

        $report = [
            'total' => count($contexts),
            // Count parent products plus each nested Yuju variation shown in the catalog table.
            'yuju_total' => count($yuju_catalog_rows),
            'synced_count' => 0,
            'unsynced_count' => 0,
            'disabled_count' => 0,
            'syncable_count' => 0,
            'all_products' => [],
            'unsynced_products' => [],
        ];

        foreach ($results as $result) {
            // Keep every PrestaShop product for the table; actions use the separate pending subset.
            $report['all_products'][] = $result;

            if ($result['status'] === 'synced') {
                ++$report['synced_count'];
            } elseif ($result['status'] === 'disabled') {
                ++$report['disabled_count'];
            } else {
                ++$report['unsynced_count'];
            }

            // Never queue disabled products or products without a match for PS→Yuju writes.
            if ($result['status'] !== 'synced' && $result['status'] !== 'disabled') {
                $report['unsynced_products'][] = $result;
            }

            if (!empty($result['syncable'])) {
                ++$report['syncable_count'];
            }
        }

        return $report;
    }

    protected function buildPendingProductReportRow($context, $yuju_product_id, $match, $duplicate_skus)
    {
        $status_row = $context['status_row'];
        $message = null;

        if ($match['status'] === 'ambiguous' || !empty($duplicate_skus)) {
            $message = 'SKU duplicado o ambiguo; no se asignó un ID Yuju automáticamente.';
        } elseif ($match['status'] === 'id_not_found') {
            $message = 'El ID Yuju guardado no aparece en el reporte actual; no se sustituyó automáticamente.';
        } else {
            $message = 'No se encontró un producto Yuju con un SKU único para enlazar.';
        }

        $reconciliation_status = 'not_in_yuju';
        if ($match['status'] === 'ambiguous' || !empty($duplicate_skus)) {
            $reconciliation_status = 'ambiguous';
        } elseif ($match['status'] === 'matched') {
            $reconciliation_status = 'needs_review';
        }

        $status = $status_row && empty($status_row['sync_enabled']) ? 'disabled' : 'pending';
        if ($status === 'disabled') {
            $reconciliation_status = 'disabled';
        }

        return [
            'prestashop_product_id' => (int) $context['id'],
            'yuju_product_id' => $yuju_product_id,
            'sku' => $context['display_sku'],
            'name' => $this->getProductDisplayName($context['product']),
            'status' => $status,
            'reconciliation_status' => $reconciliation_status,
            'differences' => [],
            'uncomparable' => [],
            'message' => $message,
            'syncable' => false,
        ];
    }

    protected function compareProductWithYujuReport($context, $report_product)
    {
        $yuju_data = $report_product;
        if (!isset($yuju_data['sku']) && isset($yuju_data['sku_simple'])) {
            $yuju_data['sku'] = $yuju_data['sku_simple'];
        }

        $mappings = is_array($this->field_mappings) ? $this->field_mappings : [];
        $outbound_mapping_count = 0;
        $has_stock_mapping = false;
        foreach ($mappings as $mapping) {
            if (empty($mapping['is_active'])
                || !in_array($mapping['sync_direction'], ['ps_to_yuju', 'prestashop_to_yuju', 'bidirectional'], true)
            ) {
                continue;
            }
            ++$outbound_mapping_count;
            if ($mapping['yuju_field'] === 'stock') {
                $has_stock_mapping = true;
            }
        }

        $comparison_mappings = $mappings;
        if (!$has_stock_mapping && isset($context['payload']['stock'])) {
            $comparison_mappings[] = [
                'yuju_field' => 'stock',
                'field_type' => 'integer',
                'sync_direction' => 'ps_to_yuju',
                'is_active' => 1,
            ];
            ++$outbound_mapping_count;
        }

        $parent_comparison = YujuProductReportReconciler::compareMappedFields(
            $context['payload'],
            $yuju_data,
            $comparison_mappings
        );
        $differences = $parent_comparison['differences'];
        $uncomparable = $parent_comparison['uncomparable'];

        if ($outbound_mapping_count === 0) {
            $uncomparable[] = 'No active PrestaShop-to-Yuju field mappings are configured.';
        }

        $ps_variations = $context['variations'];
        $has_report_variations = array_key_exists('_yuju_report_has_variations', $report_product)
            ? (bool) $report_product['_yuju_report_has_variations']
            : array_key_exists('variations', $report_product);

        if (!empty($ps_variations) && !$has_report_variations) {
            $uncomparable[] = 'Yuju report omitted variation data.';
            $variation_comparison = [
                'matched' => [],
                'differences' => [],
                'uncomparable' => [],
                'ambiguous' => [],
                'cannot_update' => [],
                'to_create' => [],
            ];
        } else {
            $variation_comparison = YujuProductReportReconciler::compareVariations(
                $ps_variations,
                isset($report_product['variations']) && is_array($report_product['variations'])
                    ? $report_product['variations']
                    : []
            );
        }

        foreach ($variation_comparison['differences'] as $sku => $variant_differences) {
            foreach ($variant_differences as $difference) {
                $differences[] = [
                    'field' => 'variations.' . $sku . '.' . $difference['field'],
                    'prestashop' => $difference['prestashop'],
                    'yuju' => $difference['yuju'],
                ];
            }
        }

        foreach ($variation_comparison['to_create'] as $variation) {
            $differences[] = [
                'field' => 'variations.' . $variation['sku'],
                'prestashop' => 'present',
                'yuju' => 'missing',
            ];
        }

        foreach ($variation_comparison['uncomparable'] as $sku => $fields) {
            $uncomparable[] = 'Variation ' . $sku . ': ' . implode(', ', $fields);
        }

        foreach ($variation_comparison['ambiguous'] as $sku) {
            $uncomparable[] = 'Variation SKU is ambiguous: ' . $sku;
        }

        foreach (array_keys($variation_comparison['cannot_update']) as $sku) {
            $uncomparable[] = 'Yuju report has no stable variation ID for update: ' . $sku;
        }

        $uncomparable = array_values(array_unique($uncomparable));
        $has_differences = !empty($differences);
        $status = !$has_differences && empty($uncomparable) ? 'synced' : 'pending';
        $reconciliation_status = $has_differences ? 'differences' : (empty($uncomparable) ? 'synced' : 'needs_review');
        $syncable = ($has_differences && empty($uncomparable));
        $message = null;

        if (!empty($uncomparable)) {
            $message = 'No se puede confirmar o actualizar todo el producto: ' . implode('; ', $uncomparable);
        } elseif ($has_differences) {
            $message = 'Diferencias detectadas: ' . implode(', ', array_column($differences, 'field'));
        }

        return [
            'status' => $status,
            'reconciliation_status' => $reconciliation_status,
            'differences' => $differences,
            'uncomparable' => $uncomparable,
            'message' => $message,
            'syncable' => $syncable,
            'variation_comparison' => $variation_comparison,
        ];
    }

    protected function getProductVariationsForYuju($product)
    {
        if (!method_exists($product, 'getAttributeCombinations')) {
            return [];
        }

        $language_id = (int) Configuration::get('PS_LANG_DEFAULT');
        $rows = $product->getAttributeCombinations($language_id);
        if (!is_array($rows)) {
            return [];
        }

        $variations = [];
        foreach ($rows as $row) {
            if (empty($row['id_product_attribute'])) {
                continue;
            }

            $id_product_attribute = (int) $row['id_product_attribute'];
            if (!isset($variations[$id_product_attribute])) {
                $variations[$id_product_attribute] = [
                    'prestashop_product_attribute_id' => $id_product_attribute,
                    'sku' => isset($row['reference']) ? trim((string) $row['reference']) : '',
                    'stock' => isset($row['quantity']) ? (int) $row['quantity'] : 0,
                    'price' => (float) $product->price + (isset($row['price']) ? (float) $row['price'] : 0),
                ];

                if (!empty($row['ean13'])) {
                    $variations[$id_product_attribute]['ean'] = (string) $row['ean13'];
                } elseif (!empty($row['upc'])) {
                    $variations[$id_product_attribute]['upc'] = (string) $row['upc'];
                }
            }

            $group_name = isset($row['group_name']) ? trim((string) $row['group_name']) : '';
            $attribute_name = isset($row['attribute_name']) ? trim((string) $row['attribute_name']) : '';
            if ($group_name === '' || $attribute_name === '') {
                continue;
            }

            $group = function_exists('mb_strtolower') ? mb_strtolower($group_name, 'UTF-8') : strtolower($group_name);
            if (in_array($group, ['size', 'talla', 'tamaño'], true)) {
                $variations[$id_product_attribute]['size'] = $attribute_name;
            } elseif (in_array($group, ['color', 'colour'], true)) {
                $variations[$id_product_attribute]['color'] = strtolower($attribute_name);
                $variations[$id_product_attribute]['color_text'] = $attribute_name;
            } elseif (in_array($group, ['secondary color', 'secondary_color', 'color secundario'], true)) {
                $variations[$id_product_attribute]['secondary_color'] = strtolower($attribute_name);
            } else {
                if (!isset($variations[$id_product_attribute]['custom_variation_values'])) {
                    $variations[$id_product_attribute]['custom_variation_values'] = [];
                }
                $variations[$id_product_attribute]['custom_variation_values'][$group_name] = $attribute_name;
            }
        }

        foreach ($variations as &$variation) {
            if (!empty($variation['custom_variation_values'])) {
                $custom_values = [];
                foreach ($variation['custom_variation_values'] as $group => $value) {
                    $custom_values[] = $group . ': ' . $value;
                }
                $variation['custom_variation_value'] = implode('; ', $custom_values);
                unset($variation['custom_variation_values']);
            }
        }
        unset($variation);

        return array_values($variations);
    }

    protected function getProductDisplayName($product)
    {
        if (!isset($product->name)) {
            return '';
        }

        if (is_scalar($product->name)) {
            return (string) $product->name;
        }

        $language_id = (int) Configuration::get('PS_LANG_DEFAULT');

        return isset($product->name[$language_id]) && is_scalar($product->name[$language_id])
            ? (string) $product->name[$language_id]
            : '';
    }

    /**
     * Remove obsolete PrestaShop-only rows and duplicate/stale Yuju catalog rows.
     * The report is the source of truth for this table.
     */
    protected function pruneYujuCatalogStatuses($catalog_rows)
    {
        $valid = [];
        foreach ($catalog_rows as $row) {
            $valid[json_encode([(string) $row['yuju_product_id'], (string) $row['yuju_variation_id']])] = true;
        }

        $db = Db::getInstance();
        $rows = $db->executeS('
            SELECT id, yuju_product_id, yuju_variation_id
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
            ORDER BY id ASC
        ');
        if (!is_array($rows)) {
            return false;
        }

        $seen = [];
        $delete_ids = [];
        foreach ($rows as $row) {
            $yuju_id = isset($row['yuju_product_id']) ? (string) $row['yuju_product_id'] : '';
            $variation_id = isset($row['yuju_variation_id']) ? (string) $row['yuju_variation_id'] : '';
            $key = json_encode([$yuju_id, $variation_id]);
            if ($yuju_id === '' || !isset($valid[$key]) || isset($seen[$key])) {
                $delete_ids[] = (int) $row['id'];
                continue;
            }
            $seen[$key] = true;
        }

        foreach (array_chunk($delete_ids, 400) as $batch) {
            if (!$db->execute('DELETE FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE id IN (' . implode(',', array_map('intval', $batch)) . ')')) {
                return false;
            }
        }

        return true;
    }

    protected function getYujuReportProductName($product)
    {
        foreach (['name', 'title', 'product_name'] as $field) {
            if (isset($product[$field]) && is_scalar($product[$field]) && trim((string) $product[$field]) !== '') {
                return trim((string) $product[$field]);
            }
        }

        return '';
    }

    /**
     * Upsert one Yuju parent product or variation in the Yuju-centric status catalog.
     */
    protected function saveYujuCatalogStatus($row)
    {
        $db = Db::getInstance();
        $yuju_product_id = pSQL((string) $row['yuju_product_id']);
        $variation_id = pSQL((string) $row['yuju_variation_id']);
        $existing = $db->getRow('
            SELECT id, sync_status, last_sync_at
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE yuju_product_id = "' . $yuju_product_id . '"
              AND (yuju_variation_id = "' . $variation_id . '" OR (yuju_variation_id IS NULL AND "' . $variation_id . '" = ""))
            ORDER BY id ASC
        ');

        $incoming_status = (string) $row['sync_status'];
        $report_downloaded_at = isset($row['report_downloaded_at']) ? (int) $row['report_downloaded_at'] : 0;
        if ($existing && $report_downloaded_at > 0 && $incoming_status === 'pending'
            && (string) $existing['sync_status'] === 'synced'
            && !empty($existing['last_sync_at'])
            && strtotime($existing['last_sync_at']) > $report_downloaded_at
        ) {
            // Do not let a cached report downloaded before the successful API write
            // downgrade the newer sync result back to pending.
            $incoming_status = 'synced';
        }

        $data = [
            'prestashop_product_id' => $row['prestashop_product_id'] !== null ? (int) $row['prestashop_product_id'] : null,
            'prestashop_product_attribute_id' => isset($row['prestashop_product_attribute_id']) && $row['prestashop_product_attribute_id'] !== null
                ? (int) $row['prestashop_product_attribute_id']
                : null,
            'yuju_product_id' => $yuju_product_id,
            'yuju_product_name' => pSQL((string) $row['yuju_product_name']),
            'yuju_variation_id' => $variation_id,
            'yuju_variation_sku' => $row['yuju_variation_sku'] !== null ? pSQL((string) $row['yuju_variation_sku']) : null,
            'sync_status' => pSQL($incoming_status),
            'sync_enabled' => (int) $row['sync_enabled'],
            'last_error' => $incoming_status === 'synced' ? null : ($row['last_error'] !== null ? pSQL((string) $row['last_error']) : null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return $db->update('yuju_product_status', $data, 'id = ' . (int) $existing['id']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['last_sync_at'] = null;
        return $db->insert('yuju_product_status', $data);
    }

    protected function saveProductReportStatus($prestashop_product_id, $yuju_product_id, $result)
    {
        $db = Db::getInstance();
        $existing = $db->getRow('
            SELECT id, last_sync_at FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . (int) $prestashop_product_id
        );
        $difference_fields = [];
        foreach ($result['differences'] as $difference) {
            if (isset($difference['field'])) {
                $difference_fields[] = $difference['field'];
            }
        }

        $uncomparable = isset($result['uncomparable']) && is_array($result['uncomparable'])
            ? $result['uncomparable']
            : [];
        $verified_synced = isset($result['status'])
            && $result['status'] === 'synced'
            && empty($difference_fields)
            && empty($uncomparable);

        $error = isset($result['message']) ? (string) $result['message'] : null;
        $data = [
            'yuju_product_id' => $yuju_product_id !== null && $yuju_product_id !== '' ? pSQL($yuju_product_id) : null,
            'sync_status' => pSQL($result['status']),
            'last_error' => $error !== null ? pSQL($error) : null,
            'last_sync_data' => pSQL(json_encode([
                'differences' => array_values(array_unique($difference_fields)),
                'uncomparable' => $uncomparable,
                'checked_at' => gmdate('Y-m-d H:i:s'),
            ])),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Record the time only when reconciliation verifies that all comparable fields match.
        // A report with differences or uncomparable fields must not look like a successful sync.
        if ($verified_synced) {
            $data['last_sync_at'] = date('Y-m-d H:i:s');
        }

        if ($existing) {
            return $db->update('yuju_product_status', $data, 'prestashop_product_id = ' . (int) $prestashop_product_id);
        }

        $data['prestashop_product_id'] = (int) $prestashop_product_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        if (!$verified_synced) {
            $data['last_sync_at'] = null;
        }

        return $db->insert('yuju_product_status', $data);
    }

    /**
     * Build a report from Yuju's response and the module's product mappings.
     *
     * @param array $response API response from YujuApiClient::getProducts()
     * @param array $mappings Product status rows containing Yuju IDs
     *
     * @return array
     */
    protected static function buildYujuProductSyncReport($response, $mappings)
    {
        $products = self::extractYujuProducts($response);

        $mapped_products = [];

        foreach ($mappings as $mapping) {
            if (!empty($mapping['yuju_product_id']) && (int) $mapping['prestashop_product_id'] > 0) {
                $mapped_products[(string) $mapping['yuju_product_id']] = $mapping;
            }
        }

        $report = [
            'total' => count($products),
            'synced_count' => 0,
            'unsynced_count' => 0,
            'unsynced_products' => [],
        ];

        foreach ($products as $product) {
            $yuju_product_id = (string) $product['id'];

            if (isset($mapped_products[$yuju_product_id])) {
                ++$report['synced_count'];
                continue;
            }

            ++$report['unsynced_count'];
            $report['unsynced_products'][] = [
                'id' => $yuju_product_id,
                'name' => isset($product['name']) && is_scalar($product['name']) ? (string) $product['name'] : '',
                'sku' => isset($product['sku']) && is_scalar($product['sku']) ? (string) $product['sku'] : '',
            ];
        }

        return $report;
    }

    /**
     * Normalize Yuju's product collection response and reject malformed items.
     *
     * @param array $response API response from YujuApiClient::getProducts()
     *
     * @return array
     */
    protected static function extractYujuProducts($response)
    {
        if (empty($response['success'])) {
            $message = isset($response['message']) ? $response['message'] : 'Could not retrieve products from Yuju.';
            throw new InvalidArgumentException($message);
        }

        $products = isset($response['data']) ? $response['data'] : null;

        for ($depth = 0; $depth < 4 && is_array($products); ++$depth) {
            if (isset($products['data']) && is_array($products['data'])) {
                $products = $products['data'];
            } elseif (isset($products['products']) && is_array($products['products'])) {
                $products = $products['products'];
            } else {
                break;
            }
        }

        if (!is_array($products)) {
            throw new InvalidArgumentException('Yuju returned an unexpected products response.');
        }

        foreach ($products as $product) {
            if (!is_array($product) || !isset($product['id']) || (string) $product['id'] === '') {
                throw new InvalidArgumentException('Yuju returned a product without an ID; synchronization was stopped.');
            }
        }

        return $products;
    }

    /**
     * Synchronize products from PrestaShop to Yuju.
     */
    public function syncProductsToYuju($product_ids = null, $category_ids = null)
    {
        try {
            $this->logger->log('Starting product synchronization to Yuju', 'info');

            $products = $this->getProductsForSync($product_ids, $category_ids);

            if (empty($products)) {
                throw new Exception('No products found for synchronization');
            }

            $synced_count = 0;
            $created_count = 0;
            $updated_count = 0;
            $errors = [];

            foreach ($products as $product_data) {
                try {
                    $result = $this->syncSingleProductToYuju($product_data);

                    if ($result) {
                        ++$synced_count;

                        if ($result['action'] === 'created') {
                            ++$created_count;
                        } else {
                            ++$updated_count;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Product ' . $product_data['id_product'] . ': ' . $e->getMessage();
                    $this->logger->log('Error syncing product ' . $product_data['id_product'] . ': ' . $e->getMessage(), 'error');
                }
            }

            $this->logger->log('Product synchronization to Yuju completed. Synced: ' . $synced_count . ' (Created: ' . $created_count . ', Updated: ' . $updated_count . '), Errors: ' . count($errors), 'info');

            YujuMonitor::tally('sync.product', (int) $synced_count, count($errors), [
                'direction' => 'to_yuju',
            ]);

            return [
                'success' => true,
                'synced_count' => $synced_count,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            $this->logger->log('Product synchronization to Yuju failed: ' . $e->getMessage(), 'error');

            YujuMonitor::tally('sync.error', 0, 1, [
                'direction' => 'to_yuju',
                'level' => 'error',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync single product from Yuju to PrestaShop.
     */
    protected function syncSingleProductFromYuju($yuju_product, $force_update = false)
    {
        // Check if product already exists in PrestaShop
        $existing_product = $this->findProductByYujuId($yuju_product['id']);

        if ($existing_product) {
            // Update existing product
            if (!$force_update && !$this->isProductUpdateNeeded($existing_product, $yuju_product)) {
                return false;
            }

            $product = new Product($existing_product['id_product']);
            $this->updateProductFromYujuData($product, $yuju_product);

            if ($product->save()) {
                $this->updateProductStatus($existing_product['id_product'], 'synced', null, $yuju_product['id']);
                $this->logger->log('Product updated from Yuju: ' . $yuju_product['id'] . ' -> ' . $product->id, 'info');

                return ['action' => 'updated', 'product_id' => $product->id];
            } else {
                throw new Exception('Failed to save updated product');
            }
        } else {
            // Create new product
            $product = new Product();
            $this->updateProductFromYujuData($product, $yuju_product);

            if ($product->save()) {
                // Create product status record
                $this->createProductStatus($product->id, 'synced', null, $yuju_product['id']);
                $this->logger->log('Product created from Yuju: ' . $yuju_product['id'] . ' -> ' . $product->id, 'info');

                return ['action' => 'created', 'product_id' => $product->id];
            } else {
                throw new Exception('Failed to save new product');
            }
        }
    }

    /**
     * Sync single product from PrestaShop to Yuju.
     */
    protected function syncSingleProductToYuju($product_data)
    {
        $product = new Product($product_data['id_product']);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception('PrestaShop product not found: ' . $product_data['id_product']);
        }

        // Prepare product data for Yuju
        $yuju_data = $this->prepareProductDataForYuju($product);

        // Check if product exists in Yuju
        $yuju_product_id = $this->getYujuProductId($product->id);

        if ($yuju_product_id) {
            // Update existing product in Yuju
            $result = $this->api_client->updateProduct($yuju_product_id, $yuju_data);
            $action = 'updated';
        } else {
            // Create new product in Yuju
            $result = $this->api_client->createProduct($yuju_data);
            $action = 'created';

            if ($result && isset($result['id'])) {
                $yuju_product_id = $result['id'];
            }
        }

        if ($result) {
            $this->updateProductStatus($product->id, 'synced', null, $yuju_product_id);
            $this->logger->log('Product synced to Yuju: ' . $product->id . ' -> ' . $yuju_product_id, 'info');

            return ['action' => $action, 'yuju_product_id' => $yuju_product_id];
        } else {
            throw new Exception('Failed to sync product to Yuju');
        }
    }

    /**
     * Update PrestaShop product with Yuju data.
     */
    protected function updateProductFromYujuData($product, $yuju_product)
    {
        $languages = Language::getLanguages(false);
        $default_lang = Configuration::get('PS_LANG_DEFAULT');

        foreach ($this->field_mappings as $mapping) {
            if (in_array($mapping['sync_direction'], ['ps_to_yuju', 'prestashop_to_yuju'], true)) {
                continue; // Skip fields that only sync from PS to Yuju
            }

            $yuju_field = $mapping['yuju_field'];
            $ps_field = $mapping['prestashop_field'];

            if (!isset($yuju_product[$yuju_field])) {
                if ($mapping['is_required'] && !empty($mapping['default_value'])) {
                    $value = $mapping['default_value'];
                } else {
                    continue;
                }
            } else {
                $value = $yuju_product[$yuju_field];
            }

            // Apply transformation
            $value = $this->applyTransformation($value, $mapping);

            // Set field value based on type
            if (in_array($ps_field, ['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords', 'link_rewrite', 'available_now', 'available_later'])) {
                // Multilingual fields
                foreach ($languages as $language) {
                    $product->{$ps_field}[$language['id_lang']] = $value;
                }
            } else {
                // Single value fields
                $product->{$ps_field} = $value;
            }
        }

        // Set category if mapped
        $yuju_category_id = isset($yuju_product['id_category'])
            ? $yuju_product['id_category']
            : (isset($yuju_product['category_id']) ? $yuju_product['category_id'] : null);
        if ($yuju_category_id !== null) {
            $category_mapping = $this->getCategoryMappingByYujuId($yuju_category_id);

            if ($category_mapping) {
                $product->id_category_default = $category_mapping['prestashop_category_id'];
            }
        }

        // Set manufacturer if available
        if (isset($yuju_product['brand']) && !empty($yuju_product['brand'])) {
            $manufacturer = $this->getOrCreateManufacturer($yuju_product['brand']);

            if ($manufacturer) {
                $product->id_manufacturer = $manufacturer->id;
            }
        }
    }

    /**
     * Build a read-only debug view of the payload produced by the active mappings.
     * This method never calls Yuju or changes product data.
     *
     * @param int $product_id
     *
     * @return array
     */
    public function getMappedProductPayloadForDebug($product_id)
    {
        $product_id = (int) $product_id;
        $product = new Product($product_id);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception('PrestaShop product not found: ' . $product_id);
        }

        return [
            'prestashop_product_id' => $product_id,
            'yuju_product_id' => $this->getYujuProductId($product_id),
            'product_name' => $this->getProductDisplayName($product),
            'mapped_payload' => $this->prepareProductDataForYuju($product),
            'variations' => $this->getProductVariationsForYuju($product),
        ];
    }

    /**
     * Prepare PrestaShop product data for Yuju.
     */
    protected function prepareProductDataForYuju($product)
    {
        $default_lang = Configuration::get('PS_LANG_DEFAULT');
        $data = [];

        foreach ($this->field_mappings as $mapping) {
            if (in_array($mapping['sync_direction'], ['yuju_to_ps', 'yuju_to_prestashop'], true)) {
                continue; // Skip fields that only sync from Yuju to PS
            }

            $ps_field = $mapping['prestashop_field'];
            $yuju_field = $mapping['yuju_field'];

            // StockAvailable is the source of truth for product quantity in PrestaShop.
            if ($ps_field === 'quantity') {
                $value = class_exists('StockAvailable')
                    ? (int) StockAvailable::getQuantityAvailableByProduct((int) $product->id, 0)
                    : 0;
            } elseif ($ps_field === 'manufacturer') {
                $value = '';
                if (!empty($product->id_manufacturer)) {
                    $manufacturer = new Manufacturer((int) $product->id_manufacturer);
                    if (Validate::isLoadedObject($manufacturer)) {
                        $value = (string) $manufacturer->name;
                    }
                }
            } elseif (in_array($ps_field, ['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords', 'link_rewrite', 'available_now', 'available_later'], true)) {
                $value = isset($product->{$ps_field}[$default_lang]) ? $product->{$ps_field}[$default_lang] : '';
            } else {
                $value = isset($product->{$ps_field}) ? $product->{$ps_field} : '';
            }

            // Use default value if empty and required
            if (empty($value) && $mapping['is_required'] && !empty($mapping['default_value'])) {
                $value = $mapping['default_value'];
            }

            // Apply transformation
            $value = $this->applyTransformation($value, $mapping);

            $data[$yuju_field] = $value;
        }

        // Add category mapping
        if ($product->id_category_default) {
            $category_mapping = $this->getCategoryMappingByPrestashopId($product->id_category_default);

            if ($category_mapping) {
                $data['id_category'] = (int) $category_mapping['yuju_category_id'];
            }
        }

        // Add manufacturer
        if ($product->id_manufacturer) {
            $manufacturer = new Manufacturer($product->id_manufacturer);

            if (Validate::isLoadedObject($manufacturer)) {
                $data['brand'] = $manufacturer->name;
            }
        }

        return $data;
    }

    /**
     * Apply field transformation.
     */
    protected function applyTransformation($value, $mapping)
    {
        switch ($mapping['transformation_rule']) {
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'capitalize':
                return ucwords($value);
            case 'strip_html':
                return strip_tags($value);
            case 'currency_convert':
                // Implement currency conversion logic
                return $value;
            case 'date_format':
                if ($value && strtotime($value)) {
                    return date('Y-m-d H:i:s', strtotime($value));
                }
                return $value;
            case 'custom':
                if (!empty($mapping['custom_transformation'])) {
                    // Execute custom PHP code (be careful with security)
                    return $this->executeCustomTransformation($value, $mapping['custom_transformation']);
                }
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Execute custom transformation (with security considerations).
     */
    protected function executeCustomTransformation($value, $code)
    {
        // This is a simplified implementation
        // In production, you should implement proper sandboxing

        try {
            $result = eval('return ' . $code . ';');

            return $result !== false ? $result : $value;
        } catch (Exception $e) {
            $this->logger->log('Custom transformation error: ' . $e->getMessage(), 'error');

            return $value;
        }
    }

    /**
     * Find product by Yuju ID.
     */
    protected function findProductByYujuId($yuju_product_id)
    {
        return Db::getInstance()->getRow('
            SELECT ps.*, p.id_product
            FROM ' . _DB_PREFIX_ . 'yuju_product_status ps
            JOIN ' . _DB_PREFIX_ . 'product p ON ps.prestashop_product_id = p.id_product
            WHERE ps.yuju_product_id = "' . pSQL($yuju_product_id) . '"
        ');
    }

    /**
     * Get Yuju product ID for PrestaShop product.
     */
    protected function getYujuProductId($prestashop_product_id)
    {
        $result = Db::getInstance()->getRow('
            SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . (int) $prestashop_product_id . '
        ');

        return $result ? $result['yuju_product_id'] : null;
    }

    /**
     * Check if product update is needed.
     */
    protected function isProductUpdateNeeded($existing_product, $yuju_product)
    {
        if (isset($yuju_product['updated_at'])) {
            $yuju_updated = strtotime($yuju_product['updated_at']);
            $ps_updated = strtotime($existing_product['date_upd']);

            return $yuju_updated > $ps_updated;
        }

        return true;
    }

    /**
     * Get products for synchronization.
     */
    protected function getProductsForSync($product_ids = null, $category_ids = null)
    {
        $sql = 'SELECT p.* FROM ' . _DB_PREFIX_ . 'product p WHERE p.active = 1';

        if ($product_ids && is_array($product_ids)) {
            $sql .= ' AND p.id_product IN (' . implode(',', array_map('intval', $product_ids)) . ')';
        }

        if ($category_ids && is_array($category_ids)) {
            $sql .= ' AND p.id_category_default IN (' . implode(',', array_map('intval', $category_ids)) . ')';
        }

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Create product status record.
     */
    protected function createProductStatus($prestashop_product_id, $sync_status, $error_message = null, $yuju_product_id = null)
    {
        $data = [
            'prestashop_product_id' => (int) $prestashop_product_id,
            'sync_status' => pSQL($sync_status),
            'last_sync_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($error_message) {
            $data['last_error'] = pSQL($error_message);
        }

        if ($yuju_product_id) {
            $data['yuju_product_id'] = pSQL($yuju_product_id);
        }

        return Db::getInstance()->insert('yuju_product_status', $data);
    }

    /**
     * Update product status.
     */
    protected function updateProductStatus($prestashop_product_id, $sync_status, $error_message = null, $yuju_product_id = null)
    {
        $data = [
            'sync_status' => pSQL($sync_status),
            'last_sync_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($error_message) {
            $data['last_error'] = pSQL($error_message);
        } else {
            $data['last_error'] = null;
        }

        if ($yuju_product_id) {
            $data['yuju_product_id'] = pSQL($yuju_product_id);
        }

        $existing = Db::getInstance()->getRow('
            SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . (int) $prestashop_product_id . '
        ');

        if ($existing) {
            return Db::getInstance()->update(
                'yuju_product_status',
                $data,
                'prestashop_product_id = ' . (int) $prestashop_product_id
            );
        } else {
            $data['prestashop_product_id'] = (int) $prestashop_product_id;
            $data['created_at'] = date('Y-m-d H:i:s');

            return Db::getInstance()->insert('yuju_product_status', $data);
        }
    }

    /**
     * Get category mapping by Yuju ID.
     */
    protected function getCategoryMappingByYujuId($yuju_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE yuju_category_id = "' . pSQL($yuju_category_id) . '" AND sync_enabled = 1
        ');
    }

    /**
     * Get category mapping by PrestaShop ID.
     */
    protected function getCategoryMappingByPrestashopId($prestashop_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE prestashop_category_id = ' . (int) $prestashop_category_id . ' AND sync_enabled = 1
        ');
    }

    /**
     * Get or create manufacturer.
     */
    protected function getOrCreateManufacturer($brand_name)
    {
        $manufacturer = Manufacturer::getIdByName($brand_name);

        if ($manufacturer) {
            return new Manufacturer($manufacturer);
        }

        // Create new manufacturer
        $manufacturer = new Manufacturer();
        $manufacturer->name = $brand_name;
        $manufacturer->active = true;

        if ($manufacturer->save()) {
            return $manufacturer;
        }

        return null;
    }

    /**
     * Get product synchronization statistics.
     */
    public function getProductSyncStats()
    {
        $stats = [];

        $stats['total_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product WHERE active = 1
        ');

        $stats['synced_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_status = \'synced\'
        ');

        $stats['error_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_status = \'error\'
        ');

        $stats['pending_products'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_status WHERE sync_status = \'pending\'
        ');

        $stats['last_sync'] = Db::getInstance()->getValue('
            SELECT MAX(last_sync_at) FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');

        return $stats;
    }

    /**
     * Push PrestaShop → Yuju differences for already-linked products.
     * This is called after reconciliation when the user clicks "Sincronizar".
     * Only products with a Yuju ID and syncable=true are processed.
     *
     * @param array $report The reconciliation report from reconcileYujuProductReport
     *
     * @return array Result with counts and errors
     */
    public function pushDifferencesToYuju($report, $selected_product_ids = [], $urgent_only = true)
    {
        if (!is_array($report) || !isset($report['unsynced_products'])) {
            throw new InvalidArgumentException('Invalid reconciliation report.');
        }
        $selected_product_ids = array_values(array_unique(array_filter(array_map('intval', (array) $selected_product_ids), function($id) { return $id > 0; })));
        if (empty($selected_product_ids)) {
            throw new InvalidArgumentException('At least one explicitly selected PrestaShop product is required.');
        }
        $selected_lookup = array_fill_keys($selected_product_ids, true);
        $is_urgent_field = function($field) {
            $parts = explode('.', strtolower((string) $field));
            $name = preg_replace('/[\\s-]+/', '_', end($parts));
            return in_array($name, ['stock', 'price'], true);
        };

        $results = [
            'synced' => 0,
            'failed' => 0,
            'products' => [],
        ];

        foreach ($report['unsynced_products'] as $unsynced) {
            $prestashop_product_id = isset($unsynced['prestashop_product_id']) ? (int) $unsynced['prestashop_product_id'] : 0;
            if (!$prestashop_product_id || !isset($selected_lookup[$prestashop_product_id])) continue;
            if ($urgent_only) {
                $urgent_parent_differences = array_filter(isset($unsynced['differences']) ? $unsynced['differences'] : [], function($difference) use ($is_urgent_field) {
                    return isset($difference['field']) && strpos($difference['field'], 'variations.') !== 0 && $is_urgent_field($difference['field']);
                });
                $urgent_variation_differences = false;
                $comparison = isset($unsynced['variation_comparison']['differences']) ? $unsynced['variation_comparison']['differences'] : [];
                foreach ($comparison as $variant_differences) {
                    foreach ($variant_differences as $difference) {
                        if (isset($difference['field']) && $is_urgent_field($difference['field'])) { $urgent_variation_differences = true; break 2; }
                    }
                }
                if (empty($urgent_parent_differences) && !$urgent_variation_differences) {
                    $results['products'][] = ['prestashop_product_id' => $prestashop_product_id, 'status' => 'skipped', 'reason' => 'The selected product has no urgent stock or price differences.'];
                    continue;
                }
            }

            // Only process products that are linked to Yuju and have actionable differences
            if (empty($unsynced['yuju_product_id']) || (!$urgent_only && empty($unsynced['syncable']))) {
                $results['products'][] = [
                    'prestashop_product_id' => (int) $unsynced['prestashop_product_id'],
                    'status' => 'skipped',
                    'reason' => 'No Yuju ID or not syncable (missing match, ambiguous SKU, or disabled).',
                ];
                continue;
            }

            $yuju_product_id = (string) $unsynced['yuju_product_id'];

            try {
                $product = new Product($prestashop_product_id);
                if (!Validate::isLoadedObject($product)) {
                    throw new Exception('PrestaShop product not found.');
                }

                $payload = $this->prepareProductDataForYuju($product);
                $variations = $this->getProductVariationsForYuju($product);

                // Build the diff payload: only send fields that actually differ
                $diff_payload = [];
                foreach ($unsynced['differences'] as $difference) {
                    $field = $difference['field'];
                    if (strpos($field, 'variations.') !== 0 && (!$urgent_only || $is_urgent_field($field))) {
                        $diff_payload[$field] = $difference['prestashop'];
                    }
                }

                // Push parent product differences if any
                if (!empty($diff_payload)) {
                    $update_result = $this->api_client->updateProduct($yuju_product_id, $diff_payload);
                    if (empty($update_result['success'])) {
                        throw new Exception('Failed to update Yuju product: ' . ($update_result['message'] ?? 'Unknown error'));
                    }
                }

                // Push variation differences
                $variation_comparison = $unsynced['variation_comparison'] ?? [
                    'matched' => [],
                    'differences' => [],
                    'to_create' => [],
                    'cannot_update' => [],
                ];

                // Update existing variations that have differences
                foreach ($variation_comparison['differences'] as $sku => $variant_differences) {
                    $matched = $variation_comparison['matched'][$sku] ?? null;
                    if (!$matched || empty($matched['id_variation'])) {
                        $results['products'][] = [
                            'prestashop_product_id' => $prestashop_product_id,
                            'status' => 'skipped_variation',
                            'sku' => $sku,
                            'reason' => 'No stable Yuju variation ID to update.',
                        ];
                        continue;
                    }

                    $variation_id = (string) $matched['id_variation'];
                    $variation_payload = [];
                    foreach ($variant_differences as $difference) {
                        if (!$urgent_only || (isset($difference['field']) && $is_urgent_field($difference['field']))) {
                            $variation_payload[$difference['field']] = $difference['prestashop'];
                        }
                    }

                    if (!empty($variation_payload)) {
                        $variation_result = $this->api_client->updateProductVariation($yuju_product_id, $variation_id, $variation_payload);
                        if (empty($variation_result['success'])) {
                            throw new Exception('Failed to update Yuju variation ' . $sku . ': ' . ($variation_result['message'] ?? 'Unknown error'));
                        }
                    }
                }

                // Creating missing variations is a structural change, never part of urgent-only updates.
                foreach (($urgent_only ? [] : $variation_comparison['to_create']) as $variation) {
                    $variation_payload = $variation;
                    if (!isset($variation_payload['sku'])) {
                        continue;
                    }

                    $create_result = $this->api_client->createProductVariations($yuju_product_id, [$variation_payload]);
                    if (empty($create_result['success'])) {
                        throw new Exception('Failed to create Yuju variation ' . $variation_payload['sku'] . ': ' . ($create_result['message'] ?? 'Unknown error'));
                    }
                }

                // A successful API update is a successful sync operation. The next fresh
                // reconciliation can flag remaining field differences independently.
                $this->updateProductStatus($prestashop_product_id, 'synced', null, $yuju_product_id);

                $results['synced']++;
                $results['products'][] = [
                    'prestashop_product_id' => $prestashop_product_id,
                    'yuju_product_id' => $yuju_product_id,
                    'status' => 'synced',
                    'parent_updated' => !empty($diff_payload),
                    'variations_updated' => count($variation_comparison['differences']),
                    'variations_created' => count($variation_comparison['to_create']),
                ];

            } catch (Exception $e) {
                $this->updateProductStatus($prestashop_product_id, 'error', $e->getMessage(), $yuju_product_id);
                $results['failed']++;
                $results['products'][] = [
                    'prestashop_product_id' => $prestashop_product_id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }


    /* Funciones recuperadas de belltec. */

protected function sanitizeYujuProductPayload(array $data)
    {
        $forbidden = [
            'active',
            'cost_price',
            'stock_quantity',
            'quantity',
            'wholesale_price',
            'short_description',
            'description_short',
            // Dimensiones PS crudas: Yuju usa shipping_width/height/depth
            'width',
            'height',
            'depth',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'link_rewrite',
            'id_product',
            'prestashop_product_id',
        ];

        foreach ($forbidden as $key) {
            unset($data[$key]);
        }

        // Asegurar stock canónico
        if (isset($data['stock'])) {
            $data['stock'] = (int) $data['stock'];
        }

        return $data;
    }

public function normalizeYujuProductId($id)
    {
        $id = trim((string) $id);
        if ($id === '' || strtolower($id) === 'null' || $id === '0') {
            return '';
        }

        return $id;
    }

public function extractYujuIdFromApiSuccess($successItem, $responseData = null)
    {
        if (is_array($successItem)) {
            foreach (['id_product', 'id', 'product_id', 'yuju_product_id'] as $key) {
                $id = $this->normalizeYujuProductId($successItem[$key] ?? '');
                if ($id !== '') {
                    return $id;
                }
            }
        }
        if (is_array($responseData)) {
            foreach (['id_product', 'id', 'product_id'] as $key) {
                $id = $this->normalizeYujuProductId($responseData[$key] ?? '');
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return '';
    }

public function resolveExistingYujuProductId($prestashop_product_id)
    {
        $prestashop_product_id = (int) $prestashop_product_id;
        if ($prestashop_product_id <= 0) {
            return '';
        }

        $status_row = $this->getProductYujuStatusRow($prestashop_product_id);
        $found = '';

        if ($status_row) {
            $found = $this->normalizeYujuProductId($status_row['yuju_product_id'] ?? '');
            if ($found === '' && !empty($status_row['last_sync_data'])) {
                $meta = json_decode((string) $status_row['last_sync_data'], true);
                if (is_array($meta)) {
                    $inner = (isset($meta['_meta']) && is_array($meta['_meta'])) ? $meta['_meta'] : $meta;
                    $found = $this->normalizeYujuProductId($inner['api_yuju_id'] ?? ($meta['api_yuju_id'] ?? ''));
                }
            }
        }

        if ($found === '' && $this->isProductSyncHistoryTablePresent()) {
            // getRow() ya añade LIMIT 1 en PrestaShop — no duplicarlo
            $row = Db::getInstance()->getRow(
                'SELECT `yuju_product_id`, `response_data`
                 FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                 WHERE `prestashop_product_id` = ' . $prestashop_product_id . '
                   AND `status` = \'success\'
                   AND TRIM(IFNULL(`yuju_product_id`, \'\')) NOT IN (\'\', \'0\', \'null\', \'NULL\')
                 ORDER BY `id` DESC'
            );
            if ($row) {
                $found = $this->normalizeYujuProductId($row['yuju_product_id'] ?? '');
            }
            if ($found === '') {
                $hist = $this->getLastValidCreateHistory($prestashop_product_id);
                if ($hist) {
                    $found = $this->normalizeYujuProductId($hist['yuju_product_id'] ?? '');
                    if ($found === '' && !empty($hist['response_data'])) {
                        $resp = json_decode((string) $hist['response_data'], true);
                        $first = null;
                        if (is_array($resp) && !empty($resp['success'][0]) && is_array($resp['success'][0])) {
                            $first = $resp['success'][0];
                        }
                        $found = $this->extractYujuIdFromApiSuccess($first, is_array($resp) ? $resp : null);
                    }
                }
            }
        }

        // Re-persistir si el status local no lo tenía
        if ($found !== '' && $status_row) {
            $current = $this->normalizeYujuProductId($status_row['yuju_product_id'] ?? '');
            if ($current === '') {
                Db::getInstance()->update(
                    'yuju_product_status',
                    [
                        'yuju_product_id' => pSQL($found),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'prestashop_product_id = ' . $prestashop_product_id
                );
            }
        }

        return $found;
    }

public function productAlreadyExistsInYuju($product_id, $status_row = null)
    {
        if ($this->resolveExistingYujuProductId((int) $product_id) !== '') {
            return true;
        }
        if ($status_row === null) {
            $status_row = $this->getProductYujuStatusRow((int) $product_id);
        }
        if ($status_row) {
            $st = (string) ($status_row['sync_status'] ?? '');
            if (in_array($st, ['synced', 'synced_with_warnings', 'updating_in_yuju', 'creating_in_yuju'], true)) {
                if ($st === 'creating_in_yuju' && $this->hasValidPendingCreateEvidence((int) $product_id, $status_row)) {
                    return true;
                }
                if (in_array($st, ['synced', 'synced_with_warnings', 'updating_in_yuju'], true)) {
                    // Sin ID es inconsistente, pero no recrear a ciegas
                    return $this->getLastValidCreateHistory((int) $product_id) !== null;
                }
            }
        }

        return $this->getLastValidCreateHistory((int) $product_id) !== null;
    }

public function getLastSuccessfulSyncPayload($product_id)
    {
        $product_id = (int) $product_id;
        $status_row = $this->getProductYujuStatusRow($product_id);
        if ($status_row && !empty($status_row['last_sync_data'])) {
            $decoded = json_decode((string) $status_row['last_sync_data'], true);
            if (is_array($decoded)) {
                if (isset($decoded['_meta'])) {
                    unset($decoded['_meta']);
                }
                // Meta-only viejo (webhook_pending sin payload de producto)
                $looksLikeMetaOnly = !empty($decoded['webhook_pending'])
                    && !isset($decoded['sku'])
                    && !isset($decoded['name'])
                    && !isset($decoded['price'])
                    && !isset($decoded['stock']);
                if (!$looksLikeMetaOnly && count($decoded) > 0) {
                    return $decoded;
                }
            }
        }

        if (!$this->isProductSyncHistoryTablePresent()) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `action`, `request_data`
             FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
             WHERE `prestashop_product_id` = ' . $product_id . '
               AND `status` = \'success\'
               AND `action` IN (\'create\', \'update\')
               AND `request_data` IS NOT NULL
               AND TRIM(`request_data`) NOT IN (\'\', \'[]\', \'{}\', \'null\')
             ORDER BY `id` DESC
             LIMIT 25'
        );
        if (!is_array($rows) || !$rows) {
            return [];
        }

        $fallback = [];
        foreach ($rows as $row) {
            $req = json_decode((string) ($row['request_data'] ?? ''), true);
            if (!is_array($req) || count($req) === 0) {
                continue;
            }
            if (isset($req['_meta'])) {
                unset($req['_meta']);
            }
            // Ignorar registros que solo tenían sku (errores/reinyecciones viejas)
            $keys = array_keys($req);
            if (empty(array_diff($keys, ['sku', 'sku_simple']))) {
                continue;
            }
            if ($fallback === []) {
                $fallback = $req;
            }
            // Preferir payload “rico” (create o update con varios campos de producto)
            if ($this->payloadLooksLikeFullProductSnapshot($req)
                || (isset($row['action']) && $row['action'] === 'create')
            ) {
                return $req;
            }
        }

        return $fallback;
    }

protected function payloadLooksLikeFullProductSnapshot(array $payload)
    {
        $markers = ['name', 'price', 'stock', 'description', 'id_category', 'images', 'sku'];
        $hits = 0;
        foreach ($markers as $key) {
            if (array_key_exists($key, $payload)) {
                ++$hits;
            }
        }

        return $hits >= 3;
    }

public function buildYujuPayloadDiff(array $newPayload, array $oldPayload)
    {
        $newN = $this->normalizePayloadForDiffCompare($newPayload);
        $oldN = $this->normalizePayloadForDiffCompare($oldPayload);
        $diff = [];
        foreach ($newPayload as $key => $value) {
            if ($key === '_meta') {
                continue;
            }
            $newCmp = array_key_exists($key, $newN) ? $newN[$key] : $value;
            if (!array_key_exists($key, $oldN) && !array_key_exists($key, $oldPayload)) {
                $diff[$key] = $value;
                continue;
            }
            $oldCmp = array_key_exists($key, $oldN) ? $oldN[$key] : $oldPayload[$key];
            if (json_encode($oldCmp) !== json_encode($newCmp)) {
                $diff[$key] = $value;
            }
        }

        return $diff;
    }

protected function normalizePayloadForDiffCompare(array $payload)
    {
        unset($payload['_meta']);
        if (isset($payload['images']) && is_array($payload['images'])) {
            $norm = [];
            foreach ($payload['images'] as $url) {
                $url = (string) $url;
                $path = parse_url($url, PHP_URL_PATH);
                $norm[] = $path !== null && $path !== '' ? $path : preg_replace('/\?.*$/', '', $url);
            }
            sort($norm);
            $payload['images'] = $norm;
        }
        foreach (['price', 'weight', 'shipping_width', 'shipping_height', 'shipping_depth', 'stock'] as $numKey) {
            if (isset($payload[$numKey]) && is_numeric($payload[$numKey])) {
                $payload[$numKey] = $numKey === 'stock'
                    ? (int) $payload[$numKey]
                    : round((float) $payload[$numKey], 4);
            }
        }

        return $payload;
    }

protected function persistLastSyncPayload($product_id, array $payload, array $meta = [])
    {
        $store = $payload;
        unset($store['_meta']);
        if (!empty($meta)) {
            $store['_meta'] = $meta;
        }
        $json = json_encode($store, JSON_UNESCAPED_UNICODE);
        Db::getInstance()->update(
            'yuju_product_status',
            [
                'last_sync_data' => pSQL($json, true),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'prestashop_product_id = ' . (int) $product_id
        );
    }

public function getProductYujuStatusRow($prestashop_product_id)
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
             WHERE prestashop_product_id = ' . (int) $prestashop_product_id
        );

        return $row ?: null;
    }

public function isNonEmptyJsonPayload($json)
    {
        $raw = trim((string) $json);
        if ($raw === '' || $raw === '[]' || $raw === '{}' || $raw === 'null' || strtolower($raw) === 'null') {
            return false;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return count($decoded) > 0;
        }

        return strlen($raw) > 2;
    }

public function getLastValidCreateHistory($product_id)
    {
        if (!$this->isProductSyncHistoryTablePresent()) {
            return null;
        }
        $product_id = (int) $product_id;
        $rows = Db::getInstance()->executeS(
            'SELECT `id`, `yuju_product_id`, `http_status_code`, `request_data`, `response_data`,
                    `error_message`, `status`, `created_at`, `sync_duration`
             FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
             WHERE `prestashop_product_id` = ' . $product_id . '
               AND `action` = \'create\'
             ORDER BY `id` DESC
             LIMIT 20'
        );
        if (!is_array($rows)) {
            return null;
        }
        // Preferir success con request/response con datos
        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'success') {
                continue;
            }
            if ($this->isNonEmptyJsonPayload($row['request_data'] ?? '')
                || $this->isNonEmptyJsonPayload($row['response_data'] ?? '')) {
                return $row;
            }
        }
        foreach ($rows as $row) {
            if ($this->isNonEmptyJsonPayload($row['request_data'] ?? '')
                || $this->isNonEmptyJsonPayload($row['response_data'] ?? '')) {
                return $row;
            }
        }

        return null;
    }

public function hasValidPendingCreateEvidence($product_id, $status_row = null)
    {
        if ($status_row === null) {
            $status_row = $this->getProductYujuStatusRow($product_id);
        }
        if (!$status_row || ($status_row['sync_status'] ?? '') !== 'creating_in_yuju') {
            return false;
        }
        if (!empty($status_row['yuju_product_id'])) {
            return true;
        }
        if (!empty($status_row['last_sync_data'])) {
            $meta = json_decode((string) $status_row['last_sync_data'], true);
            if (is_array($meta)) {
                $inner = (isset($meta['_meta']) && is_array($meta['_meta'])) ? $meta['_meta'] : $meta;
                if (!empty($inner['api_yuju_id']) || !empty($inner['webhook_pending'])
                    || !empty($meta['api_yuju_id']) || !empty($meta['webhook_pending'])) {
                    if (!empty($inner['api_yuju_id']) || !empty($meta['api_yuju_id'])) {
                        return true;
                    }
                }
            }
        }

        return $this->getLastValidCreateHistory((int) $product_id) !== null;
    }

public function confirmProductCreatedFromWebhook($resource_id, $sku, $sku_simple, $parent_id = null)
    {
        $resource_id_raw = trim((string) $resource_id);
        $resource_id = $this->normalizeWebhookResourceId($resource_id_raw);
        $sku = trim((string) $sku);
        $sku_simple = trim((string) $sku_simple);
        $parent_id_norm = $this->normalizeWebhookResourceId((string) $parent_id);

        if ($resource_id === '') {
            return ['success' => false, 'message' => 'resource_id vacío'];
        }

        // Solo tratar como variación cuando parent_id es realmente informativo (no "0"/"null"/vacío).
        if ($parent_id_norm !== '') {
            $this->logger->log('Yuju webhook product-created: variación (parent_id=' . $parent_id . '), sin actualizar estado PS padre', 'info');

            return ['success' => true, 'message' => 'Variación ignorada para estado PrestaShop'];
        }

        $matched = null;

        // 1) Match directo por id Yuju ya guardado (más confiable si hubo reprocesos/retentos).
        $q = new DbQuery();
        $q->select('*')
            ->from('yuju_product_status')
            ->where('TRIM(IFNULL(yuju_product_id, "")) = "' . pSQL($resource_id) . '"')
            ->limit(1);
        $rows = Db::getInstance()->executeS($q);
        if (!empty($rows)) {
            $matched = $rows[0];
        }

        $candidates = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
             WHERE sync_status = \'creating_in_yuju\''
        ) ?: [];

        if (!$matched) {
            foreach ($candidates as $c) {
                $meta = json_decode($c['last_sync_data'] ?? '{}', true) ?: [];
                $inner = (isset($meta['_meta']) && is_array($meta['_meta'])) ? $meta['_meta'] : $meta;
                $meta_id = $this->normalizeWebhookResourceId((string) ($inner['api_yuju_id'] ?? ($meta['api_yuju_id'] ?? '')));
                if ($meta_id !== '' && $meta_id === $resource_id) {
                    $matched = $c;
                    break;
                }
            }
        }

        if (!$matched && $sku !== '') {
            $q = new DbQuery();
            $q->select('yps.*')
                ->from('yuju_product_status', 'yps')
                ->innerJoin('product', 'p', 'p.id_product = yps.prestashop_product_id')
                ->where('yps.sync_status = "creating_in_yuju"')
                ->where('TRIM(IFNULL(p.reference, "")) = "' . pSQL($sku) . '"');
            $rows = Db::getInstance()->executeS($q);
            $matched = !empty($rows) ? $rows[0] : false;
        }

        if (!$matched && $sku !== '' && !empty($candidates)) {
            foreach ($candidates as $c) {
                $meta = json_decode($c['last_sync_data'] ?? '{}', true) ?: [];
                $inner = (isset($meta['_meta']) && is_array($meta['_meta'])) ? $meta['_meta'] : $meta;
                $meta_sku = trim((string) ($inner['sku'] ?? ($meta['sku'] ?? '')));
                if ($meta_sku !== '' && strcasecmp($meta_sku, $sku) === 0) {
                    $matched = $c;
                    break;
                }
            }
        }

        if (!$matched && $sku_simple !== '' && $sku_simple !== $sku) {
            $q = new DbQuery();
            $q->select('yps.*')
                ->from('yuju_product_status', 'yps')
                ->innerJoin('product', 'p', 'p.id_product = yps.prestashop_product_id')
                ->where('yps.sync_status = "creating_in_yuju"')
                ->where('TRIM(IFNULL(p.reference, "")) = "' . pSQL($sku_simple) . '"');
            $rows = Db::getInstance()->executeS($q);
            $matched = !empty($rows) ? $rows[0] : false;
        }

        if (!$matched) {
            // 2) Rescate por historial (casos donde el estado quedó desfasado pero sí hubo create exitoso).
            try {
                $h = Db::getInstance()->getRow(
                    'SELECT `prestashop_product_id`
                     FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                     WHERE `action` = "create"
                       AND TRIM(IFNULL(`yuju_product_id`, "")) = "' . pSQL($resource_id) . '"
                     ORDER BY `id` DESC'
                );
                if (!empty($h['prestashop_product_id'])) {
                    $pid_hist = (int) $h['prestashop_product_id'];
                    $row_hist = Db::getInstance()->getRow(
                        'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                         WHERE `prestashop_product_id` = ' . $pid_hist
                    );
                    if ($row_hist) {
                        $matched = $row_hist;
                    }
                }
            } catch (Exception $e) {
                // No interrumpir el flujo de webhook por fallas de tabla/consulta de historial.
            }
        }

        if (!$matched) {
            $this->logger->log('product-created webhook sin fila PS en creating_in_yuju (resource=' . $resource_id_raw . ', resource_norm=' . $resource_id . ', sku=' . $sku . ')', 'warning');

            return ['success' => true, 'message' => 'Sin fila pendiente local; evento registrado'];
        }

        $pid = (int) $matched['prestashop_product_id'];
        // Confirmación definitiva: webhook product-created → creado/sincronizado.
        // No guardar el mensaje de éxito en last_error (esa columna es para errores reales).
        $this->updateProductStatus($pid, 'synced', null, $resource_id, null);
        $this->confirmPendingCreateHistory(
            $pid,
            $resource_id,
            'Creado en Yuju (confirmado por webhook).'
        );

        $this->logger->log('Webhook product-created confirmado: PS ' . $pid . ' -> Yuju ' . $resource_id, 'info');

        return ['success' => true, 'prestashop_product_id' => $pid, 'yuju_product_id' => $resource_id];
    }

protected function confirmPendingCreateHistory($productId, $yujuProductId, $message)
    {
        $productId = (int) $productId;
        if ($productId <= 0 || !$this->isProductSyncHistoryTablePresent()) {
            return;
        }

        $row = Db::getInstance()->getRow(
            'SELECT `id`
             FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
             WHERE `prestashop_product_id` = ' . (int) $productId . '
               AND `action` = "create"
               AND `status` = "success"
               AND (
                    `error_message` LIKE "%Pendiente confirmaci%"
                 OR `error_message` LIKE "%webhook product-created%"
                 OR `error_message` LIKE "%Esperando confirmaci%"
                 OR TRIM(IFNULL(`error_message`, "")) = ""
               )
             ORDER BY `id` DESC'
        );
        if (empty($row['id'])) {
            // Fallback: último create exitoso del producto
            $row = Db::getInstance()->getRow(
                'SELECT `id`
                 FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                 WHERE `prestashop_product_id` = ' . (int) $productId . '
                   AND `action` = "create"
                   AND `status` = "success"
                 ORDER BY `id` DESC'
            );
        }
        if (empty($row['id'])) {
            return;
        }

        $data = [
            'error_message' => pSQL((string) $message),
        ];
        $yujuProductId = trim((string) $yujuProductId);
        if ($yujuProductId !== '') {
            $data['yuju_product_id'] = pSQL($yujuProductId);
        }

        Db::getInstance()->update(
            'yuju_product_sync_history',
            $data,
            'id = ' . (int) $row['id']
        );
    }

public function findAndLinkProductCreatedWebhook($productId, $autoApply = true)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return ['success' => false, 'message' => 'Producto inválido.'];
        }

        $status = $this->getProductYujuStatusRow($productId);
        if (!$status || (string) ($status['sync_status'] ?? '') !== 'creating_in_yuju') {
            return [
                'success' => false,
                'message' => 'El producto no está en «En espera de respuesta». Solo aplica a ese estado.',
            ];
        }

        $existingId = trim((string) ($status['yuju_product_id'] ?? ''));
        if ($existingId !== '' && strtolower($existingId) !== 'null' && $existingId !== '0') {
            return [
                'success' => true,
                'already_linked' => true,
                'message' => 'El producto ya tiene ID Yuju vinculado: ' . $existingId,
                'yuju_product_id' => $existingId,
            ];
        }

        $sentSkus = [];
        $createAfter = null;
        $lastCreate = null;

        if ($this->isProductSyncHistoryTablePresent()) {
            $lastCreate = Db::getInstance()->getRow(
                'SELECT `id`, `created_at`, `request_data`, `response_data`, `status`, `http_status_code`, `yuju_product_id`
                 FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                 WHERE `prestashop_product_id` = ' . (int) $productId . '
                   AND `action` = "create"
                 ORDER BY `id` DESC'
            );
        }

        if ($lastCreate && !empty($lastCreate['created_at'])) {
            $createAfter = (string) $lastCreate['created_at'];
        } elseif (!empty($status['updated_at'])) {
            $createAfter = (string) $status['updated_at'];
        } elseif (!empty($status['last_sync_at'])) {
            $createAfter = (string) $status['last_sync_at'];
        }

        if ($lastCreate && $this->isNonEmptyJsonPayload($lastCreate['request_data'] ?? '')) {
            $req = json_decode((string) $lastCreate['request_data'], true);
            if (is_array($req)) {
                foreach (['sku', 'sku_simple', 'reference'] as $k) {
                    $v = isset($req[$k]) ? trim((string) $req[$k]) : '';
                    if ($v !== '') {
                        $sentSkus[$v] = true;
                    }
                }
            }
        }

        if (!empty($status['last_sync_data'])) {
            $meta = json_decode((string) $status['last_sync_data'], true);
            if (is_array($meta)) {
                $inner = (isset($meta['_meta']) && is_array($meta['_meta'])) ? $meta['_meta'] : $meta;
                foreach (['sku', 'sku_simple'] as $k) {
                    $v = isset($inner[$k]) ? trim((string) $inner[$k]) : '';
                    if ($v !== '') {
                        $sentSkus[$v] = true;
                    }
                }
            }
        }

        $product = new Product($productId);
        if (Validate::isLoadedObject($product) && trim((string) $product->reference) !== '') {
            $sentSkus[trim((string) $product->reference)] = true;
        }

        $sentSkuList = array_keys($sentSkus);
        if ($sentSkuList === []) {
            return [
                'success' => false,
                'message' => 'No se encontró el SKU enviado (historial/referencia) para buscar en webhooks.',
            ];
        }

        // Margen amplio: en algunos logs received_at llega desfasado respecto a sent_at
        $fromSql = $createAfter
            ? date('Y-m-d H:i:s', max(0, strtotime($createAfter) - 3600))
            : date('Y-m-d H:i:s', time() - 86400 * 7);

        $likeParts = [];
        foreach ($sentSkuList as $sku) {
            $likeParts[] = 'payload LIKE "%' . pSQL($sku) . '%"';
            $likeParts[] = 'entity_id = "' . pSQL($sku) . '"';
        }

        $sql = 'SELECT `id`, `event_type`, `entity_id`, `payload`, `status`, `received_at`, `processed_at`, `error_message`
                FROM `' . _DB_PREFIX_ . 'yuju_webhook_logs`
                WHERE `event_type` IN ("product-created", "product_created")
                  AND `received_at` >= "' . pSQL($fromSql) . '"
                  AND (' . implode(' OR ', $likeParts) . ')
                ORDER BY `received_at` ASC
                LIMIT 50';
        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            $rows = [];
        }

        $matches = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }
            // Algunos wrappers guardan el body en data/payload
            if (isset($payload['data']) && is_array($payload['data']) && !isset($payload['sku']) && !isset($payload['resource_id'])) {
                $payload = $payload['data'];
            }

            $whSku = trim((string) ($payload['sku'] ?? ''));
            $whSkuSimple = trim((string) ($payload['sku_simple'] ?? ''));
            $resourceId = $this->normalizeWebhookResourceId(
                (string) ($payload['resource_id'] ?? ($payload['id'] ?? ($row['entity_id'] ?? '')))
            );

            $matchedSku = '';
            foreach ($sentSkuList as $sent) {
                if ($whSku !== '' && strcasecmp($whSku, $sent) === 0) {
                    $matchedSku = $sent;
                    break;
                }
                if ($whSkuSimple !== '' && strcasecmp($whSkuSimple, $sent) === 0) {
                    $matchedSku = $sent;
                    break;
                }
            }
            if ($matchedSku === '') {
                continue;
            }

            $matches[] = [
                'webhook_log_id' => (int) ($row['id'] ?? 0),
                'received_at' => (string) ($row['received_at'] ?? ''),
                'webhook_status' => (string) ($row['status'] ?? ''),
                'resource_id' => $resourceId,
                'sku' => $whSku,
                'sku_simple' => $whSkuSimple,
                'matched_sent_sku' => $matchedSku,
                'event' => (string) ($payload['event'] ?? ($payload['topic'] ?? 'product-created')),
                'shop_id' => isset($payload['shop_id']) ? (string) $payload['shop_id'] : '',
                'webhook_id' => isset($payload['webhook_id']) ? (string) $payload['webhook_id'] : '',
                'payload' => $payload,
            ];
        }

        if ($matches === []) {
            return [
                'success' => true,
                'found' => false,
                'message' => 'No se encontró un webhook product-created con el SKU enviado ('
                    . implode(', ', $sentSkuList) . ') después de '
                    . ($createAfter ?: $fromSql) . '.',
                'sent_skus' => $sentSkuList,
                'search_from' => $fromSql,
                'last_create_at' => $createAfter,
                'matches' => [],
            ];
        }

        // Preferir el más cercano después del create; si todos son anteriores, el más reciente
        $best = $matches[0];
        $createTs = $createAfter ? strtotime($createAfter) : 0;
        $bestScore = PHP_INT_MAX;
        foreach ($matches as $m) {
            $ts = !empty($m['received_at']) ? strtotime($m['received_at']) : 0;
            $delta = $createTs > 0 ? abs($ts - $createTs) : (time() - $ts);
            if ($m['resource_id'] === '') {
                $delta += 100000;
            }
            if ($delta < $bestScore) {
                $bestScore = $delta;
                $best = $m;
            }
        }

        $result = [
            'success' => true,
            'found' => true,
            'sent_skus' => $sentSkuList,
            'search_from' => $fromSql,
            'last_create_at' => $createAfter,
            'match' => $best,
            'matches_count' => count($matches),
            'matches' => $matches,
            'applied' => false,
            'message' => 'Webhook encontrado. SKU enviado: ' . $best['matched_sent_sku']
                . ' | SKU webhook: ' . ($best['sku'] ?: $best['sku_simple'])
                . ' | ID Yuju: ' . ($best['resource_id'] ?: '—'),
        ];

        if (!$autoApply) {
            return $result;
        }

        if ($best['resource_id'] === '') {
            $result['success'] = false;
            $result['message'] = 'Se encontró webhook con el SKU, pero sin resource_id/id para vincular.';

            return $result;
        }

        $apply = $this->confirmProductCreatedFromWebhook(
            $best['resource_id'],
            $best['sku'] !== '' ? $best['sku'] : $best['matched_sent_sku'],
            $best['sku_simple'],
            null
        );

        if (!empty($apply['success']) && !empty($apply['prestashop_product_id'])) {
            $result['applied'] = true;
            $result['yuju_product_id'] = $best['resource_id'];
            $result['message'] = 'Webhook encontrado y vinculado. SKU coincidente: '
                . $best['matched_sent_sku'] . ' → ID Yuju ' . $best['resource_id'] . '. Estado: Sincronizado.';
        } elseif (!empty($apply['success'])) {
            // confirm no encontró fila (raro): forzar vínculo local
            $this->updateProductStatus(
                $productId,
                'synced',
                null,
                $best['resource_id'],
                null
            );
            $this->confirmPendingCreateHistory(
                $productId,
                $best['resource_id'],
                'Creado en Yuju (vinculado manualmente desde búsqueda de webhook).'
            );
            $result['applied'] = true;
            $result['yuju_product_id'] = $best['resource_id'];
            $result['message'] = 'Webhook encontrado. Se vinculó localmente el ID Yuju '
                . $best['resource_id'] . ' por coincidencia de SKU.';
        } else {
            $result['success'] = false;
            $result['message'] = 'Webhook encontrado pero no se pudo vincular: '
                . (string) ($apply['message'] ?? 'error desconocido');
        }

        return $result;
    }

public function getPendingCreateWaitInfo($productId, $status = null)
    {
        $productId = (int) $productId;
        if ($status === null) {
            $status = $this->getProductYujuStatusRow($productId);
        }
        $since = null;
        if ($this->isProductSyncHistoryTablePresent()) {
            $lastCreate = Db::getInstance()->getRow(
                'SELECT `created_at`
                 FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                 WHERE `prestashop_product_id` = ' . (int) $productId . '
                   AND `action` = "create"
                 ORDER BY `id` DESC'
            );
            if ($lastCreate && !empty($lastCreate['created_at'])) {
                $since = (string) $lastCreate['created_at'];
            }
        }
        if ($since === null && is_array($status)) {
            if (!empty($status['updated_at'])) {
                $since = (string) $status['updated_at'];
            } elseif (!empty($status['last_sync_at'])) {
                $since = (string) $status['last_sync_at'];
            }
        }
        $ts = $since ? strtotime($since) : 0;
        $seconds = $ts > 0 ? max(0, time() - $ts) : 0;

        return [
            'seconds' => $seconds,
            'since' => $since,
            'can_resend' => ($ts > 0 && $seconds >= 3600),
        ];
    }

public function resendPendingCreate($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return ['success' => false, 'message' => 'Producto inválido.'];
        }

        $status = $this->getProductYujuStatusRow($productId);
        if (!$status || (string) ($status['sync_status'] ?? '') !== 'creating_in_yuju') {
            return [
                'success' => false,
                'message' => 'El producto no está en «En espera de respuesta».',
            ];
        }

        $existingId = trim((string) ($status['yuju_product_id'] ?? ''));
        if ($existingId !== '' && strtolower($existingId) !== 'null' && $existingId !== '0') {
            return [
                'success' => false,
                'message' => 'El producto ya tiene ID Yuju (' . $existingId . '). Use actualizar, no reenviar creación.',
                'yuju_product_id' => $existingId,
            ];
        }

        $wait = $this->getPendingCreateWaitInfo($productId, $status);
        if (empty($wait['can_resend'])) {
            $mins = (int) floor(($wait['seconds'] ?: 0) / 60);
            $left = max(0, 60 - $mins);

            return [
                'success' => false,
                'too_early' => true,
                'wait_seconds' => $wait['seconds'],
                'wait_since' => $wait['since'],
                'message' => 'Aún no ha pasado 1 hora desde el último envío'
                    . ($wait['since'] ? ' (' . $wait['since'] . ')' : '')
                    . '. Quedan ~' . $left . ' min. Primero pruebe «Buscar webhook».',
            ];
        }

        // Liberar el bloqueo de espera para permitir un CREATE forzado.
        $this->updateProductStatus(
            $productId,
            'pending',
            'Reenvío manual tras >1h sin webhook product-created.',
            null,
            null
        );

        $result = $this->sendProductToYuju($productId, ['force_resend_create' => true]);
        if (!is_array($result)) {
            $result = ['success' => false, 'message' => 'Error al reenviar el producto.'];
        }
        $result['resent'] = true;
        $result['wait_since'] = $wait['since'];
        if (empty($result['message'])) {
            $result['message'] = !empty($result['success'])
                ? 'Producto reenviado a Yuju. Queda en espera del webhook product-created.'
                : 'No se pudo reenviar el producto.';
        }

        return $result;
    }

protected function normalizeWebhookResourceId($value)
    {
        $v = trim((string) $value);
        $v_l = strtolower($v);
        if (
            $v === ''
            || $v === '0'
            || $v_l === 'null'
            || $v_l === 'none'
            || $v_l === 'n/a'
            || $v_l === 'na'
            || $v_l === 'false'
        ) {
            return '';
        }
        if (preg_match('/(\d+)(?!.*\d)/', $v, $m)) {
            return (string) $m[1];
        }

        return $v;
    }

public function confirmProductDeletedFromWebhook($resource_id, $sku, $parent_id = null)
    {
        $resource_id = trim((string) $resource_id);
        $sku = trim((string) $sku);

        if (!empty($parent_id)) {
            $this->logger->log('Yuju webhook product-deleted: variación (parent_id=' . $parent_id . ')', 'info');

            return ['success' => true, 'message' => 'Variación ignorada'];
        }

        $q = new DbQuery();
        $q->select('*')
            ->from('yuju_product_status')
            ->where('yuju_product_id = "' . pSQL($resource_id) . '"')
            ->limit(1);
        $rows = Db::getInstance()->executeS($q);
        $row = !empty($rows) ? $rows[0] : false;

        if (!$row && $sku !== '') {
            $q = new DbQuery();
            $q->select('yps.*')
                ->from('yuju_product_status', 'yps')
                ->innerJoin('product', 'p', 'p.id_product = yps.prestashop_product_id')
                ->where('TRIM(IFNULL(p.reference, "")) = "' . pSQL($sku) . '"')
                ->limit(1);
            $rows = Db::getInstance()->executeS($q);
            $row = !empty($rows) ? $rows[0] : false;
        }

        if (!$row) {
            return ['success' => true, 'message' => 'Producto no encontrado en estado local'];
        }

        $pid = (int) $row['prestashop_product_id'];
        Db::getInstance()->delete('yuju_product_status', 'prestashop_product_id = ' . $pid);

        $this->logger->log('Webhook product-deleted: eliminado vínculo local PS ' . $pid . ' (Yuju ' . $resource_id . ')', 'info');

        return ['success' => true, 'prestashop_product_id' => $pid];
    }

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

protected function resolveMappedYujuCategoryAlongLineage($prestashop_category_id)
    {
        $lineage = $this->getCategoryLineageIds((int) $prestashop_category_id);
        foreach ($lineage as $idx => $cid) {
            $map = $this->getCategoryMappingByPrestashopId((int) $cid);
            if (!$map || empty($map['yuju_category_id'])) {
                continue;
            }
            $yuju_cat_id = trim((string) $map['yuju_category_id']);
            if (!is_numeric($yuju_cat_id) || (int) $yuju_cat_id <= 0) {
                continue;
            }

            return [
                'prestashop_category_id' => (int) $cid,
                'yuju_category_id' => (string) ((int) $yuju_cat_id),
                'from_ancestor' => $idx > 0,
                'source_category_id' => (int) $prestashop_category_id,
            ];
        }

        return [];
    }

protected function getDuplicateCheckLanguageId()
    {
        $ctx = Context::getContext();
        if ($ctx && isset($ctx->language) && $ctx->language && (int) $ctx->language->id > 0) {
            return (int) $ctx->language->id;
        }

        return (int) Configuration::get('PS_LANG_DEFAULT');
    }

public function getGloballyDuplicatePrestaShopReferenceSet()
    {
        if (self::$globalDuplicatePrestaShopReferenceSetCache !== null) {
            return self::$globalDuplicatePrestaShopReferenceSetCache;
        }

        $dup_ref_rows = Db::getInstance()->executeS(
            'SELECT t.ref
             FROM (
                SELECT TRIM(p.reference) AS ref, p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                WHERE TRIM(IFNULL(p.reference, \'\')) != \'\'
                UNION ALL
                SELECT TRIM(pa.reference) AS ref, pa.id_product
                FROM ' . _DB_PREFIX_ . 'product_attribute pa
                WHERE TRIM(IFNULL(pa.reference, \'\')) != \'\'
             ) t
             GROUP BY t.ref
             HAVING COUNT(DISTINCT t.id_product) > 1'
        );

        $global_dup_refs = [];
        if ($dup_ref_rows) {
            foreach ($dup_ref_rows as $r) {
                $ref = trim((string) $r['ref']);
                if ($ref !== '') {
                    $global_dup_refs[$ref] = true;
                }
            }
        }

        self::$globalDuplicatePrestaShopReferenceSetCache = $global_dup_refs;

        return $global_dup_refs;
    }

public function getDuplicateSkuConflictsForProductIds(array $product_ids)
    {
        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($product_ids)) {
            return [];
        }

        $id_lang = $this->getDuplicateCheckLanguageId();
        $ids_sql = implode(',', $product_ids);

        $global_dup_refs = $this->getGloballyDuplicatePrestaShopReferenceSet();

        // 2) Referencias que usa el lote (producto + combinaciones del lote)
        $used_ref_rows = Db::getInstance()->executeS(
            'SELECT DISTINCT TRIM(p.reference) AS ref
             FROM ' . _DB_PREFIX_ . 'product p
             WHERE p.id_product IN (' . $ids_sql . ')
             AND TRIM(IFNULL(p.reference, \'\')) != \'\'
             UNION
             SELECT DISTINCT TRIM(pa.reference) AS ref
             FROM ' . _DB_PREFIX_ . 'product_attribute pa
             WHERE pa.id_product IN (' . $ids_sql . ')
             AND TRIM(IFNULL(pa.reference, \'\')) != \'\''
        );

        $conflicts = [];
        $seen_conflict_keys = [];

        if ($used_ref_rows) {
            foreach ($used_ref_rows as $row) {
                $ref = (string) $row['ref'];
                if ($ref === '' || !isset($global_dup_refs[$ref]) || isset($seen_conflict_keys[$ref])) {
                    continue;
                }
                $seen_conflict_keys[$ref] = true;

                $list = $this->getProductIdsAndNamesSharingReferenceValue($ref, $id_lang);
                if (count($list) > 1) {
                    $conflicts[] = [
                        'reference' => $ref,
                        'products' => $list,
                    ];
                }
            }
        }

        // 3) SKU efectivo: usar reference PS (sin re-preparar payload completo; eso era muy lento en cola)
        foreach ($product_ids as $pid) {
            $product = new Product((int) $pid, false, $id_lang);
            if (!Validate::isLoadedObject($product)) {
                continue;
            }
            $sku = trim((string) ($product->reference ?? ''));
            if ($sku === '') {
                continue;
            }
            $skip_eff = preg_match('/^PS-(\d+)$/', $sku, $m_eff) && (int) $m_eff[1] === (int) $pid;
            if ($skip_eff) {
                continue;
            }
            if (isset($seen_conflict_keys[$sku])) {
                continue;
            }
            $eff_dup = $this->getDuplicateSkuConflictsForEffectiveSku($sku);
            if (empty($eff_dup)) {
                continue;
            }
            $seen_conflict_keys[$sku] = true;
            foreach ($eff_dup as $block) {
                $conflicts[] = $block;
            }
        }

        return $conflicts;
    }

protected function getProductIdsAndNamesSharingReferenceValue($ref_trimmed, $id_lang)
    {
        $ref_trimmed = trim((string) $ref_trimmed);
        if ($ref_trimmed === '') {
            return [];
        }
        $ref_esc = pSQL($ref_trimmed);
        $rows = Db::getInstance()->executeS(
            'SELECT t.id_product, pl.name
             FROM (
                SELECT DISTINCT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                WHERE TRIM(IFNULL(p.reference, \'\')) = \'' . $ref_esc . '\'
                  AND TRIM(IFNULL(p.reference, \'\')) != \'\'
                UNION
                SELECT DISTINCT pa.id_product
                FROM ' . _DB_PREFIX_ . 'product_attribute pa
                WHERE TRIM(IFNULL(pa.reference, \'\')) = \'' . $ref_esc . '\'
                  AND TRIM(IFNULL(pa.reference, \'\')) != \'\'
             ) t
             INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON (t.id_product = pl.id_product AND pl.id_lang = ' . (int) $id_lang . ')
             ORDER BY t.id_product ASC'
        );
        if (!$rows) {
            return [];
        }
        $list = [];
        $seen_pid = [];
        foreach ($rows as $pr) {
            $pid = (int) $pr['id_product'];
            if (isset($seen_pid[$pid])) {
                continue;
            }
            $seen_pid[$pid] = true;
            $list[] = [
                'id_product' => $pid,
                'name' => (string) $pr['name'],
            ];
        }

        return $list;
    }

public function getDuplicateSkuConflictsForEffectiveSku($sku_trimmed)
    {
        $sku_trimmed = trim((string) $sku_trimmed);
        if ($sku_trimmed === '') {
            return [];
        }

        $id_lang = $this->getDuplicateCheckLanguageId();
        $esc = pSQL($sku_trimmed);

        $rows = Db::getInstance()->executeS(
            'SELECT p.id_product, pl.name
             FROM ' . _DB_PREFIX_ . 'product p
             INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int) $id_lang . ')
             WHERE TRIM(IFNULL(p.reference, \'\')) = \'' . $esc . '\'
                OR TRIM(IFNULL(p.ean13, \'\')) = \'' . $esc . '\'
                OR TRIM(IFNULL(p.upc, \'\')) = \'' . $esc . '\'
                OR TRIM(IFNULL(p.isbn, \'\')) = \'' . $esc . '\'
             ORDER BY p.id_product ASC'
        );

        $rows_attr = Db::getInstance()->executeS(
            'SELECT DISTINCT p.id_product, pl.name
             FROM ' . _DB_PREFIX_ . 'product_attribute pa
             INNER JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = pa.id_product
             INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int) $id_lang . ')
             WHERE TRIM(IFNULL(pa.reference, \'\')) = \'' . $esc . '\'
             ORDER BY p.id_product ASC'
        );
        if ($rows_attr) {
            if (!$rows) {
                $rows = [];
            }
            $rows = array_merge($rows, $rows_attr);
        }

        if (!$rows) {
            return [];
        }

        $list = [];
        $seen_pid = [];
        foreach ($rows as $pr) {
            $pid = (int) $pr['id_product'];
            if (isset($seen_pid[$pid])) {
                continue;
            }
            $seen_pid[$pid] = true;
            $list[] = [
                'id_product' => $pid,
                'name' => (string) $pr['name'],
            ];
        }

        if (count($list) <= 1) {
            return [];
        }

        return [
            [
                'reference' => $sku_trimmed,
                'products' => $list,
            ],
        ];
    }

public function validateProductForYujuCreate($product_id, array $options = [], array $preparedPayload = null)
    {
        $product_id = (int) $product_id;
        $errors = [];

        $product = new Product($product_id);
        if (!Validate::isLoadedObject($product)) {
            return [
                'success' => false,
                'errors' => ['Producto no encontrado en PrestaShop (ID: ' . $product_id . ').'],
            ];
        }

        // Reutilizar payload ya preparado (cola/envío) para no duplicar trabajo pesado
        $yuju_data = (is_array($preparedPayload) && !empty($preparedPayload))
            ? $preparedPayload
            : $this->prepareProductDataForYuju($product, $options);

        $sku = trim((string) ($yuju_data['sku'] ?? ''));
        if ($sku === '') {
            $errors[] = 'El SKU está vacío. Revise el mapeo de campos (normalmente debe mapear a referencia).';
        } else {
            $dup = $this->getDuplicateSkuConflictsForEffectiveSku($sku);
            if (!empty($dup)) {
                $errors[] = $this->formatDuplicateSkuExceptionMessage($dup);
            }
        }

        foreach ($this->field_mappings as $mapping) {
            if ($mapping['sync_direction'] === 'yuju_to_ps') {
                continue;
            }
            if (empty($mapping['is_required'])) {
                continue;
            }
            $yuju_field = trim((string) ($mapping['yuju_field'] ?? ''));
            if ($yuju_field === '' || $yuju_field === 'id_category' || $yuju_field === 'category_id') {
                continue;
            }
            if (!array_key_exists($yuju_field, $yuju_data)) {
                $errors[] = 'Falta campo obligatorio para Yuju: ' . $yuju_field;
                continue;
            }
            $value = $yuju_data[$yuju_field];
            if ((is_string($value) && trim($value) === '') || $value === null || $value === []) {
                $errors[] = 'Campo obligatorio vacío para Yuju: ' . $yuju_field;
            }
        }

        if ((int) $product->id_category_default <= 0) {
            $errors[] = 'El producto no tiene categoría por defecto en PrestaShop.';
        } else {
            $preferredCategoryId = isset($options['preferred_ps_category_id']) ? (int) $options['preferred_ps_category_id'] : 0;
            $resolved = $this->resolveMappedYujuCategoryForProduct((int) $product->id, (int) $product->id_category_default, $preferredCategoryId);
            if (empty($resolved['yuju_category_id'])) {
                $candidateIds = $this->getProductCategoryIdsForMappingLookup((int) $product->id, (int) $product->id_category_default);
                if ($preferredCategoryId > 0 && !in_array($preferredCategoryId, $candidateIds, true)) {
                    array_unshift($candidateIds, $preferredCategoryId);
                }
                $labels = [];
                foreach ($candidateIds as $cid) {
                    $labels[] = $this->getPrestashopCategoryLabelById((int) $cid);
                }
                $errors[] = 'Ninguna de las categorías del producto está mapeada con Yuju (tampoco por herencia de padre). '
                    . 'Categorías revisadas: ' . implode(' | ', $labels)
                    . '. Configure el Mapeo de Categorías antes de enviar (la categoría o un padre debe estar mapeado y con sync habilitado).';
            } else {
                $yuju_cat_id = trim((string) $resolved['yuju_category_id']);
                if (!is_numeric($yuju_cat_id) || (int) $yuju_cat_id <= 0) {
                    $errors[] = 'El mapeo de categoría tiene un ID de Yuju inválido: "' . $yuju_cat_id . '".';
                }
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors,
        ];
    }

protected function getProductCategoryIdsForMappingLookup($product_id, $default_category_id)
    {
        $product_id = (int) $product_id;
        $default_category_id = (int) $default_category_id;
        if ($product_id <= 0) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT id_category FROM `' . _DB_PREFIX_ . 'category_product` WHERE id_product = ' . $product_id
        );
        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $cid = isset($r['id_category']) ? (int) $r['id_category'] : 0;
                if ($cid > 0) {
                    $ids[] = $cid;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($default_category_id > 0) {
            $ids = array_values(array_diff($ids, [$default_category_id]));
            array_unshift($ids, $default_category_id);
        }

        return $ids;
    }

protected function resolveMappedYujuCategoryForProduct($product_id, $default_category_id, $preferred_category_id = 0)
    {
        $preferred_category_id = (int) $preferred_category_id;
        if ($preferred_category_id > 0) {
            $resolved = $this->resolveMappedYujuCategoryAlongLineage($preferred_category_id);
            if ($resolved !== []) {
                return [
                    'prestashop_category_id' => (int) $resolved['prestashop_category_id'],
                    'yuju_category_id' => (string) $resolved['yuju_category_id'],
                ];
            }

            // Contexto Bulk/preferido sin mapeo activo: no usar otra categoría del producto
            return [];
        }

        $categoryIds = $this->getProductCategoryIdsForMappingLookup((int) $product_id, (int) $default_category_id);
        foreach ($categoryIds as $cid) {
            $resolved = $this->resolveMappedYujuCategoryAlongLineage((int) $cid);
            if ($resolved !== []) {
                return [
                    'prestashop_category_id' => (int) $resolved['prestashop_category_id'],
                    'yuju_category_id' => (string) $resolved['yuju_category_id'],
                ];
            }
        }

        return [];
    }

protected function formatDuplicateSkuExceptionMessage(array $conflicts)
    {
        $parts = ['La misma referencia/código (SKU) está duplicada en varios productos de PrestaShop. Corrija el catálogo antes de sincronizar con Yuju.'];
        foreach ($conflicts as $c) {
            $label = isset($c['reference']) ? (string) $c['reference'] : '';
            $ids = [];
            foreach ($c['products'] as $p) {
                $ids[] = '#' . (int) $p['id_product'] . ' — ' . (string) $p['name'];
            }
            $parts[] = 'Código "' . $label . '": ' . implode(' | ', $ids);
        }

        return implode(' ', $parts);
    }

protected function getPrestashopCategoryLabelById($category_id)
    {
        $category_id = (int) $category_id;
        if ($category_id <= 0) {
            return 'ID 0';
        }

        $id_lang = 0;
        $ctx = Context::getContext();
        if ($ctx && isset($ctx->language) && $ctx->language) {
            $id_lang = (int) $ctx->language->id;
        }
        if ($id_lang <= 0) {
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        }

        $name = '';
        try {
            $row = Db::getInstance()->getRow(
                'SELECT cl.name
                 FROM `' . _DB_PREFIX_ . 'category_lang` cl
                 WHERE cl.id_category = ' . $category_id . '
                   AND cl.id_lang = ' . (int) $id_lang . '
                 ORDER BY cl.id_shop ASC'
            );
            if ($row && isset($row['name'])) {
                $name = trim((string) $row['name']);
            }
        } catch (Exception $e) {
            $name = '';
        }

        if ($name === '') {
            return 'ID ' . $category_id;
        }

        return 'ID ' . $category_id . ' — ' . $name;
    }

public function sendProductToYuju($product_id, array $options = [])
    {
        $start_time = microtime(true);
        $tNow = static function () {
            return microtime(true);
        };
        $markMs = static function (array &$timings, $key, $from) use ($tNow) {
            $timings[$key] = round(($tNow() - $from) * 1000, 1);
        };
        $timings = [
            'resolve_id_ms' => 0.0,
            'dup_check_ms' => 0.0,
            'prepare_ms' => 0.0,
            'validate_ms' => 0.0,
            'diff_ms' => 0.0,
            'api_ms' => 0.0,
            'persist_ms' => 0.0,
            'total_ms' => 0.0,
        ];
        $diff_field_count = 0;
        $had_baseline = false;
        $product_id = (int) $product_id;
        $existing_yuju_id = null;
        $action = 'create';
        $request_data = '';
        $yuju_data = [];
        $result = [];

        if (Module::isInstalled('prestashopyuju')) {
            $m = Module::getInstanceByName('prestashopyuju');
            if ($m && method_exists($m, 'ensureProductStatusIntermediateWebhookStates')) {
                $m->ensureProductStatusIntermediateWebhookStates();
            }
        }

        try {
            $product = new Product($product_id);
            if (!Validate::isLoadedObject($product)) {
                throw new Exception('Product not found: ' . $product_id);
            }

            $status_row = $this->getProductYujuStatusRow($product_id);
            $forceResendCreate = !empty($options['force_resend_create']);
            if ($status_row && !empty($status_row['sync_status'])) {
                $pendingStatus = (string) $status_row['sync_status'];
                // Solo bloquear reenvío en create/delete (esperan webhook).
                // updating_in_yuju se confirma en ESTE mismo request por respuesta API;
                // no soft-return (la cola marca updating justo antes de llamar aquí).
                if (!$forceResendCreate && in_array($pendingStatus, ['creating_in_yuju', 'deleting_in_yuju'], true)) {
                    $updatedAt = strtotime((string) ($status_row['updated_at'] ?? ''));
                    $isStale = !$updatedAt || (time() - $updatedAt) > 900; // 15 min
                    $hasValidCreate = ($pendingStatus !== 'creating_in_yuju')
                        || $this->hasValidPendingCreateEvidence((int) $product_id, $status_row);

                    if (!$isStale && $hasValidCreate) {
                        $yujuIdPending = !empty($status_row['yuju_product_id'])
                            ? (string) $status_row['yuju_product_id']
                            : null;
                        $waitLabels = [
                            'creating_in_yuju' => 'En espera de respuesta de Yuju. El producto ya fue enviado; no se puede volver a crear hasta que llegue el webhook de confirmación.',
                            'deleting_in_yuju' => 'En espera de respuesta de Yuju. Eliminación en curso; esperando webhook de confirmación.',
                        ];
                        $waitMsg = $waitLabels[$pendingStatus] ?? 'Operación en espera de confirmación de Yuju.';
                        if ($pendingStatus === 'creating_in_yuju') {
                            $this->updateProductStatus(
                                $product_id,
                                'creating_in_yuju',
                                $waitMsg,
                                $yujuIdPending,
                                false
                            );
                        }

                        $timings['total_ms'] = round(($tNow() - $start_time) * 1000, 1);

                        return [
                            'success' => true,
                            'message' => $waitMsg,
                            'yuju_product_id' => $yujuIdPending,
                            'action' => $pendingStatus === 'creating_in_yuju' ? 'create' : 'delete',
                            'awaiting_webhook' => true,
                            'already_pending' => true,
                            'timings' => $timings,
                        ];
                    }
                }
            }

            // Resolver ID Yuju UNA sola vez (antes del prepare / validaciones)
            $t0 = $tNow();
            $existing_yuju_id = $this->resolveExistingYujuProductId($product_id);
            $action = $existing_yuju_id !== '' ? 'update' : 'create';

            if ($action === 'create' && !$forceResendCreate && $this->productAlreadyExistsInYuju($product_id, $status_row)) {
                $existing_yuju_id = $this->resolveExistingYujuProductId($product_id);
                if ($existing_yuju_id !== '') {
                    $action = 'update';
                } else {
                    // Ya se envió / hay evidencia de create: no recrear ni marcar error.
                    // Queda en espera del webhook de Yuju.
                    $waitMsg = 'En espera de respuesta de Yuju. No se puede volver a enviar '
                        . 'porque el producto ya fue enviado/creado y estamos esperando la confirmación (webhook).';
                    $this->updateProductStatus(
                        $product_id,
                        'creating_in_yuju',
                        $waitMsg,
                        null,
                        false
                    );
                    $timings['total_ms'] = round(($tNow() - $start_time) * 1000, 1);

                    return [
                        'success' => true,
                        'message' => $waitMsg,
                        'yuju_product_id' => null,
                        'action' => 'create',
                        'awaiting_webhook' => true,
                        'already_pending' => true,
                        'timings' => $timings,
                    ];
                }
            }
            $markMs($timings, 'resolve_id_ms', $t0);

            // Check de SKU duplicados: pesado. Solo en CREATE.
            if ($action === 'create') {
                $t0 = $tNow();
                $ref_dup = $this->getDuplicateSkuConflictsForProductIds([$product_id]);
                if (!empty($ref_dup)) {
                    throw new Exception($this->formatDuplicateSkuExceptionMessage($ref_dup));
                }
                $markMs($timings, 'dup_check_ms', $t0);
            }

            $t0 = $tNow();
            $yuju_data = $this->prepareProductDataForYuju($product, $options);
            $markMs($timings, 'prepare_ms', $t0);

            $eff = trim((string) ($yuju_data['sku'] ?? ''));
            if ($action === 'create') {
                $t0 = $tNow();
                $skip_eff = ($eff !== '' && preg_match('/^PS-(\d+)$/', $eff, $m_eff) && (int) $m_eff[1] === (int) $product_id);
                if ($eff !== '' && !$skip_eff) {
                    $eff_dup = $this->getDuplicateSkuConflictsForEffectiveSku($eff);
                    if (!empty($eff_dup)) {
                        throw new Exception($this->formatDuplicateSkuExceptionMessage($eff_dup));
                    }
                }
                $validation = $this->validateProductForYujuCreate($product_id, $options, $yuju_data);
                if (empty($validation['success'])) {
                    throw new Exception(implode(' ', $validation['errors']));
                }
                $markMs($timings, 'validate_ms', $t0);
            }

            $full_yuju_data = $yuju_data;
            $request_data = json_encode($full_yuju_data, JSON_PRETTY_PRINT);

            if ($existing_yuju_id !== '') {
                $t0 = $tNow();
                // Update: solo diferencias vs el último payload enviado (nunca reenviar sku/sku_simple)
                $currentSku = trim((string) ($full_yuju_data['sku'] ?? ''));
                $oldPayload = $this->getLastSuccessfulSyncPayload($product_id);
                $had_baseline = !empty($oldPayload);
                $lockedSku = '';
                if (!empty($oldPayload['sku_simple'])) {
                    $lockedSku = trim((string) $oldPayload['sku_simple']);
                } elseif (!empty($oldPayload['sku'])) {
                    $lockedSku = trim((string) $oldPayload['sku']);
                }
                if ($lockedSku === '') {
                    $lockedSku = $this->getLockedSkuForExistingYujuProduct($product_id, $currentSku);
                }
                // Snapshot local: conservar SKU bloqueado (Yuju no permite editar sku_simple)
                if ($lockedSku !== '') {
                    $full_yuju_data['sku'] = $lockedSku;
                    $full_yuju_data['sku_simple'] = $lockedSku;
                } elseif ($currentSku !== '') {
                    $full_yuju_data['sku_simple'] = $currentSku;
                }

                if (!empty($oldPayload)) {
                    $yuju_data = $this->buildYujuPayloadDiff($full_yuju_data, $oldPayload);
                } else {
                    // Sin baseline: update completo salvo identificadores inmutables
                    $yuju_data = $full_yuju_data;
                }

                // Nunca enviar sku/sku_simple en UPDATE: Yuju los bloquea y provocan falso error
                unset($yuju_data['sku'], $yuju_data['sku_simple']);

                $diff_field_count = is_array($yuju_data) ? count($yuju_data) : 0;
                $markMs($timings, 'diff_ms', $t0);

                // Sin cambios reales (vacío o solo quedaban claves de SKU)
                if (empty($yuju_data)) {
                    $t0 = $tNow();
                    $this->persistLastSyncPayload($product_id, $full_yuju_data);
                    $this->updateProductStatus(
                        $product_id,
                        'synced',
                        null,
                        $existing_yuju_id
                    );
                    $markMs($timings, 'persist_ms', $t0);
                    $timings['total_ms'] = round(($tNow() - $start_time) * 1000, 1);

                    return [
                        'success' => true,
                        'message' => 'Sin cambios vs el último envío. Estado: Sincronizado.',
                        'yuju_product_id' => $existing_yuju_id,
                        'action' => 'update',
                        'skipped_no_diff' => true,
                        'awaiting_webhook' => false,
                        'timings' => $timings,
                        'diff_fields' => 0,
                        'had_baseline' => $had_baseline,
                    ];
                }

                $request_data = $this->buildHistoryRequestPayload($yuju_data, [
                    'origin' => $options['origin'] ?? $this->detectSyncOrigin(),
                    'changed_fields' => array_keys($yuju_data),
                    'action' => 'update',
                ]);
                $this->updateProductStatus(
                    $product_id,
                    'updating_in_yuju',
                    'Actualizando en Yuju… (solo diferencias vs último envío).',
                    $existing_yuju_id
                );
                $t0 = $tNow();
                $result = $this->api_client->updateProduct($existing_yuju_id, $yuju_data);
                $markMs($timings, 'api_ms', $t0);
                if (!empty($result['curl_time'])) {
                    $timings['curl_s'] = round((float) $result['curl_time'], 3);
                }
            } else {
                $this->updateProductStatus(
                    $product_id,
                    'creating_in_yuju',
                    'Creando en Yuju… Pendiente de confirmación vía webhook (product-created).',
                    null
                );
                $t0 = $tNow();
                $result = $this->api_client->createProduct($full_yuju_data);
                $markMs($timings, 'api_ms', $t0);
                if (!empty($result['curl_time'])) {
                    $timings['curl_s'] = round((float) $result['curl_time'], 3);
                }
                $yuju_data = $full_yuju_data;
                $request_data = $this->buildHistoryRequestPayload($full_yuju_data, [
                    'origin' => $options['origin'] ?? $this->detectSyncOrigin(),
                    'changed_fields' => ['create'],
                    'action' => 'create',
                ]);
            }

            $duration = microtime(true) - $start_time;

            $response_data = $result['data'] ?? [];
            $http_code = $result['http_code'] ?? 500;

            $successful_products = isset($response_data['success']) && is_array($response_data['success'])
                ? $response_data['success']
                : [];

            $error_products = isset($response_data['errors']) && is_array($response_data['errors'])
                ? $response_data['errors']
                : [];

            if (!empty($successful_products) && count($successful_products) > 0) {
                $first_success = $successful_products[0];
                $returned_yuju_id = $this->extractYujuIdFromApiSuccess($first_success, $response_data);
                if ($returned_yuju_id === '') {
                    $returned_yuju_id = null;
                }

                $warnings = isset($first_success['warning']) && is_array($first_success['warning'])
                    ? $first_success['warning']
                    : [];

                $has_warnings = !empty($warnings);
                $warning_message = $has_warnings ? implode('; ', $warnings) : null;

                if ($action === 'update') {
                    $final_yuju_id = $returned_yuju_id ?: $existing_yuju_id;
                    if (!$final_yuju_id) {
                        throw new Exception('Actualización en Yuju sin ID de producto');
                    }
                    $t0 = $tNow();
                    $sync_status = $has_warnings ? 'synced_with_warnings' : 'synced';
                    $this->updateProductStatus($product_id, $sync_status, $warning_message, $final_yuju_id);
                    // Snapshot del payload COMPLETO (no solo el diff) para futuros diffs
                    $this->persistLastSyncPayload($product_id, isset($full_yuju_data) ? $full_yuju_data : $yuju_data);

                    $history_id = $this->logSyncHistory([
                        'prestashop_product_id' => $product_id,
                        'yuju_product_id' => $final_yuju_id,
                        'sync_direction' => 'to_yuju',
                        'action' => $action,
                        'status' => 'success',
                        'http_status_code' => $http_code,
                        'request_data' => $request_data,
                        'response_data' => json_encode($response_data, JSON_PRETTY_PRINT),
                        'error_message' => $warning_message,
                        'sync_duration' => $duration,
                        'created_by' => $options['origin'] ?? $this->detectSyncOrigin(),
                    ]);
                    $markMs($timings, 'persist_ms', $t0);
                    $timings['total_ms'] = round(($tNow() - $start_time) * 1000, 1);

                    $message = 'Producto actualizado en Yuju (solo diferencias)';
                    if ($has_warnings) {
                        $message .= ' con advertencias';
                    }

                    return [
                        'success' => true,
                        'message' => $message,
                        'yuju_product_id' => $final_yuju_id,
                        'action' => $action,
                        'history_id' => $history_id,
                        'warnings' => $warnings,
                        'has_warnings' => $has_warnings,
                        'awaiting_webhook' => false,
                        'timings' => $timings,
                        'diff_fields' => $diff_field_count,
                        'had_baseline' => $had_baseline,
                    ];
                }

                if ($action === 'create') {
                    $pending_meta = [
                        'webhook_pending' => true,
                        'pending_action' => 'create',
                        'api_yuju_id' => $returned_yuju_id ? (string) $returned_yuju_id : null,
                        'sku' => $eff,
                        'api_response_at' => date('Y-m-d H:i:s'),
                    ];
                    // Envío OK → "Creando / en espera". Solo el webhook product-created confirma "creado" (synced).
                    $t0 = $tNow();
                    $this->updateProductStatus(
                        $product_id,
                        'creating_in_yuju',
                        'Enviado a Yuju. Creando… Esperando confirmación por webhook (product-created).',
                        $returned_yuju_id ? (string) $returned_yuju_id : null
                    );
                    $this->persistLastSyncPayload(
                        $product_id,
                        isset($full_yuju_data) ? $full_yuju_data : $yuju_data,
                        $pending_meta
                    );

                    $history_id = $this->logSyncHistory([
                        'prestashop_product_id' => $product_id,
                        'yuju_product_id' => $returned_yuju_id ? (string) $returned_yuju_id : '',
                        'sync_direction' => 'to_yuju',
                        'action' => $action,
                        'status' => 'success',
                        'http_status_code' => $http_code,
                        'request_data' => $request_data,
                        'response_data' => json_encode($response_data, JSON_PRETTY_PRINT),
                        'error_message' => $has_warnings
                            ? $warning_message
                            : 'Enviado. Pendiente confirmación webhook product-created',
                        'sync_duration' => $duration,
                        'created_by' => $options['origin'] ?? $this->detectSyncOrigin(),
                    ]);
                    $markMs($timings, 'persist_ms', $t0);
                    $timings['total_ms'] = round(($tNow() - $start_time) * 1000, 1);

                    return [
                        'success' => true,
                        'message' => 'Producto enviado a Yuju. Estado: Creando… Se marcará como creado cuando llegue el webhook de confirmación.',
                        'yuju_product_id' => $returned_yuju_id ? (string) $returned_yuju_id : null,
                        'action' => $action,
                        'history_id' => $history_id,
                        'warnings' => $warnings,
                        'has_warnings' => $has_warnings,
                        'awaiting_webhook' => true,
                        'timings' => $timings,
                    ];
                }
            }

            $error_message = 'Error desconocido';

            if (!empty($error_products)) {
                $first_error = $error_products[0];

                if (isset($first_error['message']) && is_array($first_error['message'])) {
                    $error_message = implode('; ', $first_error['message']);
                } elseif (isset($first_error['message'])) {
                    $error_message = $first_error['message'];
                }
            } elseif (isset($result['message'])) {
                $error_message = $result['message'];
            } elseif (empty($successful_products) && $http_code == 200) {
                $error_message = 'API returned empty result or missing ID';
            }

            throw new Exception($error_message);
        } catch (Exception $e) {
            $duration = microtime(true) - $start_time;
            $error_message = $e->getMessage();
            if ($error_message === '' || strtolower(trim($error_message)) === 'error') {
                $error_message = 'Error desconocido al sincronizar con Yuju';
            }

            // Si ya tiene ID Yuju: no ocultar que está en Yuju; el fallo es de la operación (casi siempre update).
            $persistStatus = 'error';
            if ($existing_yuju_id !== '') {
                $persistStatus = 'synced_with_errors';
                $opLabel = ($action === 'update') ? 'actualizar' : (($action === 'create') ? 'crear' : 'sincronizar');
                $error_message = 'El producto SÍ está en Yuju (ID ' . $existing_yuju_id
                    . '). Falló al ' . $opLabel . ': ' . $error_message;
            } elseif ($action === 'create') {
                $error_message = 'Error al crear en Yuju: ' . $error_message;
            }

            $this->updateProductStatus($product_id, $persistStatus, $error_message, $existing_yuju_id);

            $history_id = $this->logSyncHistory([
                'prestashop_product_id' => $product_id,
                'yuju_product_id' => $existing_yuju_id ?: '',
                'sync_direction' => 'to_yuju',
                'action' => $action,
                'status' => 'error',
                'http_status_code' => isset($result['http_code']) ? $result['http_code'] : 500,
                'request_data' => $request_data !== ''
                    ? $request_data
                    : $this->buildHistoryRequestPayload($yuju_data ?? [], [
                        'origin' => $options['origin'] ?? $this->detectSyncOrigin(),
                        'action' => $action,
                    ]),
                'response_data' => isset($result['data']) ? json_encode($result['data'], JSON_PRETTY_PRINT) : null,
                'error_message' => $error_message,
                'sync_duration' => $duration,
                'created_by' => $options['origin'] ?? $this->detectSyncOrigin(),
            ]);

            $this->logger->log('Failed to send product to Yuju: ' . $product_id . ' - ' . $error_message, 'error');
            $timings['total_ms'] = round($duration * 1000, 1);

            return [
                'success' => false,
                'message' => $error_message,
                'error' => $error_message,
                'history_id' => $history_id,
                'timings' => $timings,
                'action' => $action,
                'yuju_product_id' => $existing_yuju_id !== '' ? (string) $existing_yuju_id : null,
                'in_yuju' => $existing_yuju_id !== '',
            ];
        }
    }

protected function getLockedSkuForExistingYujuProduct($product_id, $fallbackSku = '')
    {
        $product_id = (int) $product_id;
        $fallbackSku = trim((string) $fallbackSku);
        if ($product_id <= 0) {
            return $fallbackSku;
        }

        // 1) Intentar extraer de la última petición exitosa guardada en historial.
        try {
            // getRow() ya añade LIMIT 1 en PrestaShop — no duplicarlo
            $row = Db::getInstance()->getRow(
                'SELECT request_data
                 FROM `' . _DB_PREFIX_ . 'yuju_product_sync_history`
                 WHERE prestashop_product_id = ' . $product_id . '
                   AND status = "success"
                   AND request_data IS NOT NULL
                   AND request_data != ""
                 ORDER BY id DESC'
            );
            if ($row && !empty($row['request_data'])) {
                $req = json_decode((string) $row['request_data'], true);
                if (is_array($req)) {
                    $skuSimple = isset($req['sku_simple']) ? trim((string) $req['sku_simple']) : '';
                    $sku = isset($req['sku']) ? trim((string) $req['sku']) : '';
                    if ($skuSimple !== '') {
                        return $skuSimple;
                    }
                    if ($sku !== '') {
                        return $sku;
                    }
                }
            }
        } catch (Exception $e) {
            // Continuar con fallback.
        }

        // 2) Fallback: usar SKU actual calculado.
        return $fallbackSku;
    }

public function isProductSyncHistoryTablePresent()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $rows = Db::getInstance()->executeS(
                'SHOW TABLES LIKE "' . _DB_PREFIX_ . 'yuju_product_sync_history"'
            );
            $cache = !empty($rows);
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

public function logSyncHistory($data)
    {
        try {
            $created_by = isset($data['created_by']) ? trim((string) $data['created_by']) : '';
            if ($created_by === '') {
                $created_by = $this->detectSyncOrigin();
            }

            $insert_data = [
                'prestashop_product_id' => (int) $data['prestashop_product_id'],
                'yuju_product_id' => pSQL($data['yuju_product_id'] ?? ''),
                'sync_direction' => pSQL($data['sync_direction'] ?? 'to_yuju'),
                'action' => pSQL($data['action']),
                'status' => pSQL($data['status']),
                'http_status_code' => (int) ($data['http_status_code'] ?? 0),
                'request_data' => pSQL($data['request_data'] ?? '', true),
                'response_data' => pSQL($data['response_data'] ?? '', true),
                'error_message' => pSQL($data['error_message'] ?? '', true),
                'sync_duration' => (float) ($data['sync_duration'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => pSQL($created_by),
            ];

            if (Db::getInstance()->insert('yuju_product_sync_history', $insert_data)) {
                return Db::getInstance()->Insert_ID();
            }

            $db_err = Db::getInstance()->getMsgError();
            $this->logger->log(
                'No se pudo insertar en yuju_product_sync_history: ' . $db_err . ' | producto=' . ($data['prestashop_product_id'] ?? ''),
                'error'
            );

            return false;
        } catch (\Throwable $e) {
            $this->logger->log(
                'logSyncHistory excepción: ' . $e->getMessage() . ' | producto=' . ($data['prestashop_product_id'] ?? ''),
                'error'
            );

            return false;
        }
    }

public function detectSyncOrigin()
    {
        if (!empty($GLOBALS['yuju_sync_origin'])) {
            return preg_replace('/[^a-z0-9_\-]/i', '', (string) $GLOBALS['yuju_sync_origin']) ?: 'system';
        }

        if (PHP_SAPI === 'cli') {
            return 'cron';
        }

        $ctx = Context::getContext();
        if (isset($ctx->employee) && !empty($ctx->employee->id)) {
            return 'prestashop';
        }

        return 'system';
    }

public function buildHistoryRequestPayload($body, array $meta = [])
    {
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : ['_raw' => $body];
        }
        if (!is_array($body)) {
            $body = [];
        }

        $changed = [];
        if (!empty($meta['changed_fields']) && is_array($meta['changed_fields'])) {
            $changed = $meta['changed_fields'];
        } else {
            $changed = array_keys($body);
        }

        $envelope = [
            '_meta' => [
                'origin' => $meta['origin'] ?? $this->detectSyncOrigin(),
                'changed_fields' => array_values($changed),
                'action' => $meta['action'] ?? 'update',
                'old_values' => $meta['old_values'] ?? null,
                'new_values' => $meta['new_values'] ?? null,
                'priority' => $meta['priority'] ?? null,
            ],
            'body' => $body,
        ];

        return json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

public function enrichSyncHistoryRecordForUi(array $record)
    {
        $requestRaw = $record['request_data'] ?? '';
        $responseRaw = $record['response_data'] ?? '';
        $req = [];
        if (is_string($requestRaw) && $requestRaw !== '') {
            $tmp = json_decode($requestRaw, true);
            $req = is_array($tmp) ? $tmp : [];
        } elseif (is_array($requestRaw)) {
            $req = $requestRaw;
        }

        $meta = [];
        $body = $req;
        if (isset($req['_meta']) && is_array($req['_meta'])) {
            $meta = $req['_meta'];
            $body = isset($req['body']) && is_array($req['body']) ? $req['body'] : [];
        } elseif (isset($req['body']) && is_array($req['body'])) {
            // saveProductUpdateHistory legacy shape
            $body = $req['body'];
            $meta = [
                'changed_fields' => $req['changed_fields'] ?? array_keys($body),
                'old_values' => $req['old_values'] ?? null,
                'new_values' => $req['new_values'] ?? null,
                'priority' => $req['priority'] ?? null,
                'origin' => $req['origin'] ?? null,
            ];
        }

        $origin = $meta['origin'] ?? ($record['created_by'] ?? 'system');
        $changedFields = [];
        if (!empty($meta['changed_fields']) && is_array($meta['changed_fields'])) {
            $changedFields = $meta['changed_fields'];
        } elseif (!empty($body) && is_array($body)) {
            $changedFields = array_keys($body);
        }

        $resp = null;
        if (is_string($responseRaw) && $responseRaw !== '') {
            $tmp = json_decode($responseRaw, true);
            $resp = is_array($tmp) ? $tmp : $responseRaw;
        } elseif (is_array($responseRaw)) {
            $resp = $responseRaw;
        }

        $responseSummary = $this->summarizeYujuHistoryResponse($resp, $record);

        $record['ui'] = [
            'origin' => (string) $origin,
            'origin_label' => self::getSyncOriginLabel($origin),
            'changed_fields' => $changedFields,
            'changed_fields_labels' => self::getChangedFieldLabels($changedFields),
            'sent_body' => $body,
            'sent_summary' => $this->summarizeSentBody($body, $meta),
            'response_summary' => $responseSummary,
            'old_values' => $meta['old_values'] ?? null,
            'new_values' => $meta['new_values'] ?? null,
        ];

        return $record;
    }

protected function summarizeSentBody(array $body, array $meta = [])
    {
        if (empty($body)) {
            return 'Sin payload enviado';
        }
        $parts = [];
        foreach ($body as $k => $v) {
            if ($k === '_raw') {
                continue;
            }
            if (is_array($v) || is_object($v)) {
                $parts[] = $k . ': [' . (is_array($v) ? count($v) . ' ítems' : 'objeto') . ']';
            } else {
                $sv = (string) $v;
                if (strlen($sv) > 80) {
                    $sv = substr($sv, 0, 77) . '…';
                }
                $parts[] = $k . ': ' . $sv;
            }
            if (count($parts) >= 8) {
                $parts[] = '…';
                break;
            }
        }

        return implode(' · ', $parts);
    }

protected function summarizeYujuHistoryResponse($resp, array $record)
    {
        $status = (string) ($record['status'] ?? '');
        $http = (int) ($record['http_status_code'] ?? 0);
        $err = trim((string) ($record['error_message'] ?? ''));

        if ($status === 'error') {
            return $err !== '' ? ('Error: ' . $err) : ('Error HTTP ' . ($http ?: '?'));
        }

        if (!is_array($resp)) {
            if (is_string($resp) && $resp !== '') {
                return strlen($resp) > 120 ? substr($resp, 0, 117) . '…' : $resp;
            }
            return $status === 'success' ? 'OK' : ($status ?: 'Sin respuesta');
        }

        // Formato API Yuju { success: [...], errors: [...] }
        if (!empty($resp['errors']) && is_array($resp['errors'])) {
            $msgs = [];
            foreach ($resp['errors'] as $e) {
                if (isset($e['message'])) {
                    $msgs[] = is_array($e['message']) ? implode('; ', $e['message']) : (string) $e['message'];
                }
            }
            if ($msgs) {
                return 'Yuju errores: ' . implode(' | ', array_slice($msgs, 0, 3));
            }
        }

        if (!empty($resp['success']) && is_array($resp['success'])) {
            $first = $resp['success'][0];
            $id = '';
            if (is_array($first)) {
                $id = (string) ($first['id_product'] ?? $first['id'] ?? '');
                if (!empty($first['warning']) && is_array($first['warning'])) {
                    return 'OK' . ($id !== '' ? ' (ID ' . $id . ')' : '') . ' · avisos: ' . implode('; ', $first['warning']);
                }
            }
            return 'OK — Yuju aceptó la actualización' . ($id !== '' ? ' (ID ' . $id . ')' : '');
        }

        if (isset($resp['success']) && $resp['success'] === false) {
            return 'Error: ' . ($resp['message'] ?? $err ?: 'respuesta fallida');
        }

        if (isset($resp['message']) && is_string($resp['message'])) {
            return $resp['message'];
        }

        return $status === 'success' ? 'OK — respuesta recibida de Yuju' : 'Respuesta recibida';
    }

public function logValidationErrorHistory($prestashop_product_id, $error_message, $action = 'create')
    {
        $prestashop_product_id = (int) $prestashop_product_id;
        $action = in_array($action, ['create', 'update', 'delete'], true) ? $action : 'create';

        return $this->logSyncHistory([
            'prestashop_product_id' => $prestashop_product_id,
            'yuju_product_id' => '',
            'sync_direction' => 'to_yuju',
            'action' => $action,
            'status' => 'error',
            'http_status_code' => 422,
            'request_data' => null,
            'response_data' => null,
            'error_message' => (string) $error_message,
            'sync_duration' => 0,
            'created_by' => $this->detectSyncOrigin(),
        ]);
    }

public function getProductSyncHistory($product_id, $limit = 10, $offset = 0)
    {
        if (!$this->isProductSyncHistoryTablePresent()) {
            return [];
        }

        $product_id = (int) $product_id;
        $limit = (int) $limit;
        $offset = (int) $offset;

        $query = new DbQuery();
        $query->select('*');
        $query->from('yuju_product_sync_history');
        $query->where('prestashop_product_id = ' . (int) $product_id);
        $query->orderBy('created_at DESC');
        $query->limit($limit, $offset);

        $rows = Db::getInstance()->executeS($query) ?: [];
        foreach ($rows as &$row) {
            $row = $this->enrichSyncHistoryRecordForUi($row);
        }
        unset($row);

        return $rows;
    }

public function getProductSyncStatistics($product_id)
    {
        $product_id = (int) $product_id;
        $empty_stats = [
            'total_syncs' => 0,
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'last_sync' => null,
            'average_duration' => 0.0,
        ];

        if (!$this->isProductSyncHistoryTablePresent()) {
            return $empty_stats;
        }

        $stats = [];
        $last_sql = '';

        try {
            // Total syncs
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $stats['total_syncs'] = (int) Db::getInstance()->getValue($query);

            // Successful syncs
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->where('status = "success"');
            $stats['successful_syncs'] = (int) Db::getInstance()->getValue($query);

            // Failed syncs
            $query = new DbQuery();
            $query->select('COUNT(*)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->where('status = "error"');
            $stats['failed_syncs'] = (int) Db::getInstance()->getValue($query);

            // Last sync - Use executeS and get first result instead of getRow with LIMIT
            $query = new DbQuery();
            $query->select('*');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->orderBy('id DESC');
            $query->limit(1);
            $last_sql = str_replace(["\n", "\r"], ' ', $query->build());
            $result = Db::getInstance()->executeS($query);
            $stats['last_sync'] = !empty($result) ? $result[0] : null;

            // Average duration
            $query = new DbQuery();
            $query->select('AVG(sync_duration)');
            $query->from('yuju_product_sync_history');
            $query->where('prestashop_product_id = ' . (int)$product_id);
            $query->where('status = "success"');
            $stats['average_duration'] = (float) Db::getInstance()->getValue($query);
        } catch (Exception $e) {
            $this->logger->log('SQL Error in getProductSyncStatistics: ' . $e->getMessage() . ' | SQL: ' . $last_sql, 'error');

            return $empty_stats;
        }

        return $stats;
    }

public function productNeedsYujuUpdate($product_id, array $options = [])
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return false;
        }

        $existing_yuju_id = $this->resolveExistingYujuProductId($product_id);
        if ($existing_yuju_id === '') {
            return true;
        }

        try {
            $full = $this->prepareProductDataForYuju($product_id, $options);
        } catch (Exception $e) {
            return true;
        }
        if (!is_array($full) || empty($full)) {
            return true;
        }

        $currentSku = trim((string) ($full['sku'] ?? ''));
        $oldPayload = $this->getLastSuccessfulSyncPayload($product_id);
        $lockedSku = '';
        if (!empty($oldPayload['sku_simple'])) {
            $lockedSku = trim((string) $oldPayload['sku_simple']);
        } elseif (!empty($oldPayload['sku'])) {
            $lockedSku = trim((string) $oldPayload['sku']);
        }
        if ($lockedSku === '') {
            $lockedSku = $this->getLockedSkuForExistingYujuProduct($product_id, $currentSku);
        }
        if ($lockedSku !== '') {
            $full['sku'] = $lockedSku;
            $full['sku_simple'] = $lockedSku;
        } elseif ($currentSku !== '') {
            $full['sku_simple'] = $currentSku;
        }

        if (empty($oldPayload)) {
            // Sin baseline: sí hay que actualizar (payload completo sin sku inmutable)
            return true;
        }

        $diff = $this->buildYujuPayloadDiff($full, $oldPayload);
        unset($diff['sku'], $diff['sku_simple']);

        return !empty($diff);
    }

public function buildProductPayloadForYujuQueue($product_id, array $options = [])
    {
        $product_id = (int) $product_id;
        $product = new Product($product_id, false, (int) Context::getContext()->language->id);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        // Solo opciones de contexto (preferred). El payload completo se regenera al procesar la cola.
        $queueOptions = [];
        if (!empty($options['preferred_ps_category_id'])) {
            $queueOptions['preferred_ps_category_id'] = (int) $options['preferred_ps_category_id'];
        }
        if (!empty($options['force_resend_create'])) {
            $queueOptions['force_resend_create'] = true;
        }
        if (!empty($options['origin'])) {
            $queueOptions['origin'] = preg_replace('/[^a-z0-9_\-]/i', '', (string) $options['origin']) ?: 'cola';
        }

        return $queueOptions;
    }

public function removeProductFromYujuAndLocalStatus($prestashop_product_id)
    {
        $prestashop_product_id = (int) $prestashop_product_id;
        $row = Db::getInstance()->getRow(
            '
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . $prestashop_product_id
        );

        if (!$row) {
            return ['success' => true, 'skipped' => true, 'message' => 'Sin registro local'];
        }

        $yuju_product_id = isset($row['yuju_product_id']) ? trim((string) $row['yuju_product_id']) : '';
        $has_yuju = $yuju_product_id !== ''
            && strtolower($yuju_product_id) !== 'null'
            && $yuju_product_id !== '0';

        if (!$has_yuju) {
            Db::getInstance()->delete('yuju_product_status', 'prestashop_product_id = ' . $prestashop_product_id);

            return ['success' => true, 'skipped' => false, 'message' => 'Registro local eliminado (sin ID Yuju)'];
        }

        if (Module::isInstalled('prestashopyuju')) {
            $m = Module::getInstanceByName('prestashopyuju');
            if ($m && method_exists($m, 'ensureProductStatusIntermediateWebhookStates')) {
                $m->ensureProductStatusIntermediateWebhookStates();
            }
        }

        $pending_meta = json_encode([
            'delete_pending' => true,
            'requested_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        $this->updateProductStatus(
            $prestashop_product_id,
            'deleting_in_yuju',
            'Eliminando en Yuju… Pendiente de confirmación vía webhook (product-deleted).',
            $yuju_product_id,
            $pending_meta
        );

        $result = $this->api_client->deleteProduct($yuju_product_id);
        $deleted_from_yuju = !empty($result['success']);

        if (!$deleted_from_yuju) {
            $this->updateProductStatus(
                $prestashop_product_id,
                'synced_with_errors',
                $result['message'] ?? 'Error al eliminar en Yuju',
                $yuju_product_id,
                null
            );

            return [
                'success' => false,
                'skipped' => false,
                'message' => $result['message'] ?? 'Error API eliminar',
            ];
        }

        return [
            'success' => true,
            'skipped' => false,
            'message' => 'Eliminación solicitada en Yuju. Estado: eliminando… Confirmación cuando llegue el webhook product-deleted.',
            'awaiting_webhook' => true,
        ];
    }
}
