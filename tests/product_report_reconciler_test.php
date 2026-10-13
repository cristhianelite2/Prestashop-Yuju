<?php

define('_PS_VERSION_', 'test');
require_once dirname(__DIR__) . '/classes/YujuProductReportReconciler.php';

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$rows = [
    [
        'id_product' => 101,
        'sku_simple' => 'TSHIRT',
        'name' => 'T-shirt',
        'variations' => [['id_variation' => 201, 'sku' => 'TSHIRT-M', 'size' => 'M', 'stock' => 4]],
    ],
    ['id_product' => 202, 'sku_simple' => 'MUG', 'name' => 'Mug', 'variations' => []],
];
$index = YujuProductReportReconciler::indexReportRows($rows);

$missingReportFields = YujuProductReportReconciler::compareMappedFields(
    ['name' => 'Nombre desde PrestaShop', 'price' => 100, 'stock' => 5],
    ['name' => 'Nombre en Yuju'],
    [
        ['yuju_field' => 'name', 'field_type' => 'string', 'sync_direction' => 'prestashop_to_yuju', 'is_active' => 1],
        ['yuju_field' => 'price', 'field_type' => 'decimal', 'sync_direction' => 'prestashop_to_yuju', 'is_active' => 1],
        ['yuju_field' => 'stock', 'field_type' => 'integer', 'sync_direction' => 'prestashop_to_yuju', 'is_active' => 1],
    ]
);
assertSameValue(['name'], array_column($missingReportFields['differences'], 'field'), 'The name mapping must still be compared when the report omits price and stock.');
assertSameValue([], $missingReportFields['uncomparable'], 'Fields omitted by Yuju report must not be reported as uncomparable.');

$byId = YujuProductReportReconciler::matchProduct($index, '101', ['WRONG-SKU']);
assertSameValue('matched', $byId['status'], 'A persisted Yuju ID should take precedence over SKU.');
assertSameValue('101', $byId['yuju_product_id'], 'The persisted ID should resolve its report row.');

$bySku = YujuProductReportReconciler::matchProduct($index, null, [' mug ']);
assertSameValue('matched', $bySku['status'], 'A unique SKU match should be case/space normalized.');
assertSameValue('202', $bySku['yuju_product_id'], 'The SKU should map to the expected Yuju parent.');

$byVariationSku = YujuProductReportReconciler::matchProduct($index, null, ['TSHIRT-M']);
assertSameValue('matched', $byVariationSku['status'], 'A PrestaShop combination SKU should match its Yuju parent.');
assertSameValue('101', $byVariationSku['yuju_product_id'], 'A variation SKU should resolve its parent Yuju ID.');

$ambiguousIndex = YujuProductReportReconciler::indexReportRows([
    ['id_product' => 1, 'sku_simple' => 'DUPLICATE', 'variations' => []],
    ['id_product' => 2, 'sku_simple' => 'duplicate', 'variations' => []],
]);
$ambiguous = YujuProductReportReconciler::matchProduct($ambiguousIndex, null, ['duplicate']);
assertSameValue('ambiguous', $ambiguous['status'], 'Duplicate SKUs must never be linked by guessing.');

$duplicateIdIndex = YujuProductReportReconciler::indexReportRows([
    ['id_product' => 9, 'sku_simple' => 'SKU-A', 'variations' => []],
    ['id_product' => 9, 'sku_simple' => 'SKU-B', 'variations' => []],
]);
assertSameValue(
    'ambiguous',
    YujuProductReportReconciler::matchProduct($duplicateIdIndex, null, ['SKU-A'])['status'],
    'A duplicated Yuju product ID must not be linked through a seemingly unique SKU.'
);

$comparison = YujuProductReportReconciler::compareMappedFields(
    ['sku' => 'MUG-1', 'price' => '10.00', 'stock' => 4, 'description' => ' current '],
    ['sku' => 'MUG-1', 'price' => 12, 'stock' => 4],
    [
        ['yuju_field' => 'sku', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_active' => 1],
        ['yuju_field' => 'price', 'field_type' => 'decimal', 'sync_direction' => 'ps_to_yuju', 'is_active' => 1],
        ['yuju_field' => 'stock', 'field_type' => 'integer', 'sync_direction' => 'ps_to_yuju', 'is_active' => 1],
        ['yuju_field' => 'description', 'field_type' => 'string', 'sync_direction' => 'ps_to_yuju', 'is_active' => 1],
        ['yuju_field' => 'internal', 'field_type' => 'string', 'sync_direction' => 'yuju_to_ps', 'is_active' => 1],
        ['yuju_field' => 'inactive_field', 'field_type' => 'string', 'sync_direction' => 'ps_to_yuju', 'is_active' => 0],
    ]
);
assertSameValue(['price'], array_column($comparison['differences'], 'field'), 'Only changed outbound fields should be listed.');
assertSameValue(['description'], $comparison['uncomparable'], 'Missing fields in the report must not be called synchronized.');

$mapped_name_comparison = YujuProductReportReconciler::compareMappedFields(
    ['name' => 'Nombre desde PrestaShop', 'stock' => 7],
    ['name' => 'Nombre en Yuju', 'stock' => 5],
    [
        ['yuju_field' => 'name', 'field_type' => 'string', 'sync_direction' => 'bidirectional', 'is_active' => 1],
        ['yuju_field' => 'stock', 'field_type' => 'integer', 'sync_direction' => 'prestashop_to_yuju', 'is_active' => 1],
    ]
);
assertSameValue(['name', 'stock'], array_column($mapped_name_comparison['differences'], 'field'), 'Product name and canonical PrestaShop-to-Yuju fields must be compared using Yuju API names.');

$variation_comparison = YujuProductReportReconciler::compareVariations(
    [
        ['sku' => 'TEE-M', 'size' => 'M', 'color' => 'blue', 'price' => 20, 'stock' => 3],
        ['sku' => 'TEE-L', 'size' => 'L', 'color' => 'blue', 'price' => 20, 'stock' => 1],
    ],
    [
        ['id_variation' => 901, 'sku' => 'TEE-M', 'size' => 'M', 'color' => 'blue', 'price' => 19, 'stock' => 3],
    ]
);
assertSameValue('901', $variation_comparison['matched']['TEE-M']['id_variation'], 'The Yuju variation ID must be retained for safe updates.');
assertSameValue(['price'], array_column($variation_comparison['differences']['TEE-M'], 'field'), 'Variation differences should be reported by field.');
assertSameValue(['TEE-L'], array_column($variation_comparison['to_create'], 'sku'), 'Missing Yuju variations should be identified for the linked parent.');
assertSameValue([], $variation_comparison['uncomparable'], 'Absent optional fields on both sides should not block comparison.');

$no_id_comparison = YujuProductReportReconciler::compareVariations(
    [['sku' => 'TEE-M', 'price' => 20, 'stock' => 3]],
    [['sku' => 'TEE-M', 'price' => 19, 'stock' => 3]]
);
assertSameValue(true, $no_id_comparison['cannot_update']['TEE-M'], 'Do not guess an ID to update an existing Yuju variation.');

echo "Product report reconciler tests passed.\n";
