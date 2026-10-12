<?php

define('_PS_VERSION_', 'test');
define('_PS_MODULE_DIR_', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once dirname(__DIR__) . '/classes/YujuProductReportFileStore.php';

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuju-report-test-' . bin2hex(random_bytes(5));
$jsonl = implode("\n", [
    json_encode([
        'id_product' => 101,
        'sku' => 'TSHIRT',
        'name' => 'T-shirt',
        'variations' => [
            ['id_variation' => 202, 'sku' => 'TSHIRT-M', 'size' => 'M', 'color' => 'blue', 'price' => 19.5, 'stock' => 4],
        ],
    ]),
    json_encode(['id_product' => 303, 'sku_simple' => 'MUG', 'name' => 'Mug']),
    '',
]);
$downloads = [];
$resolver = function ($host) {
    return $host === 'internal.test' ? ['127.0.0.1'] : ['93.184.216.34'];
};
$transport = function ($url, $path, $ip, $max_bytes) use (&$downloads, $jsonl) {
    $downloads[] = $url;

    if (strpos($url, '/redirect') !== false) {
        return ['status' => 302, 'location' => 'http://reports.test/private.jsonl'];
    }

    file_put_contents($path, $jsonl);

    return ['status' => 200, 'location' => null];
};

$store = new YujuProductReportFileStore($directory, $resolver, $transport, 1048576);
$download = $store->downloadAndValidate('https://reports.test/catalog.jsonl?signature=not-real');
assertSameValue(2, $download['product_count'], 'The JSONL report should be counted line by line.');
$products = iterator_to_array($store->readProducts($download['cache_id']));
assertSameValue('101', $products[0]['id_product'], 'The parser should normalize the Yuju parent product ID.');
assertSameValue('TSHIRT-M', $products[0]['variations'][0]['sku'], 'The parser should preserve variation data.');
assertSameValue(true, $products[0]['_yuju_report_has_variations'], 'The parser should preserve whether the source row actually included variation data.');
assertSameValue('MUG', $products[1]['sku_simple'], 'The parser should preserve simple product data.');

try {
    $store->downloadAndValidate('http://reports.test/catalog.jsonl');
    throw new Exception('Non-HTTPS report URLs must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue(1, count($downloads), 'Reject insecure URLs before making a request.');
}

try {
    $store->downloadAndValidate('https://internal.test/catalog.jsonl');
    throw new Exception('Private IP report hosts must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue(1, count($downloads), 'Reject private DNS results before making a request.');
}

try {
    $store->downloadAndValidate('https://reports.test/redirect');
    throw new Exception('Redirects to non-HTTPS URLs must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue(2, count($downloads), 'The downloader should stop at an unsafe redirect.');
}

$store->deleteReport($download['cache_id']);
assertSameValue(false, $store->reportExists($download['cache_id']), 'The cached report should be removable by its validated cache ID.');
rmdir($directory);

$invalid_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuju-report-invalid-' . bin2hex(random_bytes(5));
$invalid_transport = function ($url, $path) {
    file_put_contents($path, '{"id_product":');

    return ['status' => 200, 'location' => null];
};
$invalid_store = new YujuProductReportFileStore($invalid_directory, $resolver, $invalid_transport, 1024);

try {
    $invalid_store->downloadAndValidate('https://reports.test/bad.jsonl');
    throw new Exception('Malformed JSONL must be rejected before it can be used for comparison.');
} catch (RuntimeException $exception) {
    assertSameValue([], glob($invalid_directory . DIRECTORY_SEPARATOR . '*.jsonl'), 'Invalid report files must be deleted.');
}
rmdir($invalid_directory);

$empty_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuju-report-empty-' . bin2hex(random_bytes(5));
$empty_transport = function ($url, $path) {
    file_put_contents($path, '');

    return ['status' => 200, 'location' => null];
};
$empty_store = new YujuProductReportFileStore($empty_directory, $resolver, $empty_transport, 1024);

try {
    $empty_store->downloadAndValidate('https://reports.test/empty.jsonl');
    throw new Exception('An empty report must not be accepted as a completed product cache.');
} catch (RuntimeException $exception) {
    assertSameValue([], glob($empty_directory . DIRECTORY_SEPARATOR . '*.jsonl'), 'Empty report files must be deleted instead of marked cached.');
}
rmdir($empty_directory);

echo "Product report file store tests passed.\n";
