<?php

define('_PS_VERSION_', 'test');
define('_PS_MODULE_DIR_', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once dirname(__DIR__) . '/classes/YujuProductManager.php';

class TestableYujuProductManager extends YujuProductManager
{
    public static function buildReport($response, $mappings)
    {
        return parent::buildYujuProductSyncReport($response, $mappings);
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$report = TestableYujuProductManager::buildReport(
    [
        'success' => true,
        'data' => [
            ['id' => 'Y-100', 'name' => 'Linked item', 'sku' => 'A-100'],
            ['id' => 'Y-200', 'name' => 'Missing item', 'sku' => 'B-200'],
        ],
    ],
    [
        ['yuju_product_id' => 'Y-100', 'prestashop_product_id' => 42, 'sync_status' => 'error'],
        ['yuju_product_id' => '', 'prestashop_product_id' => 43, 'sync_status' => 'synced'],
    ]
);

assertSameValue(2, $report['total'], 'The report should include every Yuju product.');
assertSameValue(1, $report['synced_count'], 'A Yuju ID assigned in the module table is linked.');
assertSameValue(1, $report['unsynced_count'], 'Products without an assigned Yuju ID should be unsynced.');
assertSameValue('Y-200', $report['unsynced_products'][0]['id'], 'The unlinked product should be listed for synchronization.');

$wrappedReport = TestableYujuProductManager::buildReport(
    ['success' => true, 'data' => ['data' => ['products' => [['id' => 'Y-300', 'name' => 'Wrapped item']]]]],
    []
);
assertSameValue(1, $wrappedReport['total'], 'The report should support nested Yuju response wrappers.');

try {
    TestableYujuProductManager::buildReport(['success' => false, 'message' => 'API unavailable'], []);
    throw new Exception('An unsuccessful API response must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue('API unavailable', $exception->getMessage(), 'The API error should be preserved.');
}

try {
    TestableYujuProductManager::buildReport(['success' => true, 'data' => [['name' => 'Missing ID']]], []);
    throw new Exception('Products without an ID must not be counted as synchronized or missing.');
} catch (InvalidArgumentException $exception) {
    assertSameValue(true, strpos($exception->getMessage(), 'without an ID') !== false, 'Malformed products should stop the report.');
}

echo "Product sync report tests passed.\n";
