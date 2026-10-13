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

        $status_rows = Db::getInstance()->executeS('
            SELECT prestashop_product_id, yuju_product_id, sync_status, sync_enabled, last_sync_at
            FROM ' . _DB_PREFIX_ . 'yuju_product_status
        ');
        if ($status_rows === false) {
            throw new Exception('Could not read Yuju product mappings.');
        }

        $status_by_product = [];
        foreach ($status_rows as $status_row) {
            $status_by_product[(int) $status_row['prestashop_product_id']] = $status_row;
        }

        $contexts = [];
        $sku_owners = [];
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
        foreach ($report_products as $report_product) {
            $yuju_product_id = isset($report_product['id_product']) ? trim((string) $report_product['id_product']) : '';
            if ($yuju_product_id === '') {
                continue;
            }

            $prestashop_product_id = isset($selected_products[$yuju_product_id])
                && !isset($report_index['duplicate_ids'][$yuju_product_id])
                ? (int) $selected_products[$yuju_product_id]
                : null;
            $catalog_status = 'pending';
            $catalog_error = null;
            $catalog_sync_enabled = 1;

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
                $results[$prestashop_product_id] = $result;
            }

            $yuju_catalog_rows[] = [
                'yuju_product_id' => $yuju_product_id,
                'yuju_product_name' => $this->getYujuReportProductName($report_product),
                'yuju_variation_id' => '',
                'yuju_variation_sku' => null,
                'prestashop_product_id' => $prestashop_product_id,
                'sync_status' => $catalog_status,
                'sync_enabled' => $catalog_sync_enabled,
                'last_error' => $catalog_error,
            ];

            $variations = isset($report_product['variations']) && is_array($report_product['variations'])
                ? $report_product['variations']
                : [];
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

                // Stable Yuju variation ID when available; otherwise use its SKU as identity.
                $variation_key = $variation_id !== '' ? $variation_id : 'sku:' . $variation_sku;
                $variation_ps_id = $prestashop_product_id;
                if ($variation_ps_id === null && $variation_sku !== '') {
                    $normalized_sku = YujuProductReportReconciler::normalizeSku($variation_sku);
                    $owners = isset($sku_owners[$normalized_sku]) ? array_keys($sku_owners[$normalized_sku]) : [];
                    if (count($owners) === 1) {
                        $variation_ps_id = (int) $owners[0];
                    }
                }

                $variation_status = $variation_ps_id !== null && isset($results[$variation_ps_id])
                    ? $results[$variation_ps_id]['status']
                    : 'pending';
                $variation_enabled = $variation_ps_id !== null && isset($contexts[$variation_ps_id]['status_row'])
                    && $contexts[$variation_ps_id]['status_row']
                    ? (int) $contexts[$variation_ps_id]['status_row']['sync_enabled']
                    : 1;
                $yuju_catalog_rows[] = [
                    'yuju_product_id' => $yuju_product_id,
                    'yuju_product_name' => $this->getYujuReportProductName($report_product),
                    'yuju_variation_id' => $variation_key,
                    'yuju_variation_sku' => $variation_sku !== '' ? $variation_sku : null,
                    'prestashop_product_id' => $variation_ps_id,
                    'sync_status' => $variation_status,
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

            if (!$db->execute('COMMIT')) {
                throw new Exception('Could not commit Yuju reconciliation state.');
            }
        } catch (Exception $e) {
            $db->execute('ROLLBACK');
            throw $e;
        }

        $report = [
            'total' => count($contexts),
            'yuju_total' => isset($report_index['product_count']) ? (int) $report_index['product_count'] : 0,
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
            SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE yuju_product_id = "' . $yuju_product_id . '"
              AND (yuju_variation_id = "' . $variation_id . '" OR (yuju_variation_id IS NULL AND "' . $variation_id . '" = ""))
            ORDER BY id ASC
        ');

        $data = [
            'prestashop_product_id' => $row['prestashop_product_id'] !== null ? (int) $row['prestashop_product_id'] : null,
            'yuju_product_id' => $yuju_product_id,
            'yuju_product_name' => pSQL((string) $row['yuju_product_name']),
            'yuju_variation_id' => $variation_id,
            'yuju_variation_sku' => $row['yuju_variation_sku'] !== null ? pSQL((string) $row['yuju_variation_sku']) : null,
            'sync_status' => pSQL((string) $row['sync_status']),
            'sync_enabled' => (int) $row['sync_enabled'],
            'last_error' => $row['last_error'] !== null ? pSQL((string) $row['last_error']) : null,
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
            SELECT id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE prestashop_product_id = ' . (int) $prestashop_product_id
        );
        $difference_fields = [];
        foreach ($result['differences'] as $difference) {
            if (isset($difference['field'])) {
                $difference_fields[] = $difference['field'];
            }
        }

        $error = isset($result['message']) ? (string) $result['message'] : null;
        $data = [
            'yuju_product_id' => $yuju_product_id !== null && $yuju_product_id !== '' ? pSQL($yuju_product_id) : null,
            'sync_status' => pSQL($result['status']),
            'last_error' => $error !== null ? pSQL($error) : null,
            'last_sync_data' => pSQL(json_encode([
                'differences' => array_values(array_unique($difference_fields)),
                'uncomparable' => isset($result['uncomparable']) ? $result['uncomparable'] : [],
                'checked_at' => gmdate('Y-m-d H:i:s'),
            ])),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return $db->update('yuju_product_status', $data, 'prestashop_product_id = ' . (int) $prestashop_product_id);
        }

        $data['prestashop_product_id'] = (int) $prestashop_product_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['last_sync_at'] = null;

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
}
