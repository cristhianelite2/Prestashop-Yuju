<?php

define('_PS_VERSION_', 'test');
define('_PS_MODULE_DIR_', dirname(__DIR__) . DIRECTORY_SEPARATOR);

class Configuration
{
    public static $values = [];

    public static function get($key)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : false;
    }

    public static function updateValue($key, $value)
    {
        self::$values[$key] = $value;

        return true;
    }
}

require_once dirname(__DIR__) . '/classes/YujuProductReportManager.php';

class FakeYujuReportApiClient
{
    public $createCalls = 0;
    public $statusCalls = 0;
    public $createResponse = ['success' => true, 'data' => ['id_task' => 'task-123']];
    public $statusResponses = [];

    public function createProductGeneralReport()
    {
        ++$this->createCalls;

        return $this->createResponse;
    }

    public function getProductGeneralReportStatus($task_id)
    {
        ++$this->statusCalls;

        return array_shift($this->statusResponses);
    }
}

class FakeYujuProductReportFileStore
{
    public $downloadedUrls = [];
    public $cachedRows = [];
    public $failDownloads = false;

    public function downloadAndValidate($url)
    {
        $this->downloadedUrls[] = $url;

        if ($this->failDownloads) {
            $this->failDownloads = false;
            throw new RuntimeException('unsafe report URL');
        }

        return [
            'cache_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'product_count' => count($this->cachedRows),
            'size_bytes' => 123,
            'downloaded_at' => 1000,
        ];
    }

    public function reportExists($cache_id)
    {
        return $cache_id === 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    }

    public function readProducts($cache_id)
    {
        return new ArrayIterator($this->cachedRows);
    }

    public function deleteReport($cache_id)
    {
        return true;
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

Configuration::$values = [];
$api = new FakeYujuReportApiClient();
$file_store = new FakeYujuProductReportFileStore();
$file_store->cachedRows = [['id_product' => '101'], ['id_product' => '303']];
$manager = new YujuProductReportManager($api, $file_store);

$started = $manager->startOrResumeReport();
assertSameValue(true, $started['success'], 'A report should start when no report is active.');
assertSameValue('CREATED', $started['status'], 'The returned task should be resumable.');
assertSameValue(1, $api->createCalls, 'The first scan should create one report task.');

$resumed = $manager->startOrResumeReport();
assertSameValue('task-123', $resumed['task_id'], 'Repeated starts should reuse the active task.');
assertSameValue(1, $api->createCalls, 'A repeated click must not create another report.');

$api->statusResponses = [
    ['success' => true, 'data' => ['status' => 'PROCESSING']],
    ['success' => true, 'data' => ['status' => 'COMPLETED', 'url' => 'https://reports.example/file.jsonl?signature=secret']],
];
$processing = $manager->pollReport();
assertSameValue('PROCESSING', $processing['status'], 'Processing tasks should remain pending.');
$complete = $manager->pollReport();
assertSameValue('COMPLETED', $complete['status'], 'A completed task should expose its URL to the server-side downloader.');
assertSameValue(['https://reports.example/file.jsonl?signature=secret'], $file_store->downloadedUrls, 'The signed URL should be downloaded on the server.');
assertSameValue(false, isset($complete['report_url']), 'The signed URL must not be returned to the browser.');
assertSameValue(2, count(iterator_to_array($manager->getCachedReportProducts())), 'The completed report should be available from private cache.');
$manager->pollReport();
assertSameValue(2, $api->statusCalls, 'A cached report should not require another status request.');

$quotaState = ['date_utc' => gmdate('Y-m-d'), 'count' => 2];
Configuration::updateValue(YujuProductReportManager::QUOTA_CONFIG_KEY, json_encode($quotaState));
Configuration::updateValue(YujuProductReportManager::STATE_CONFIG_KEY, '');
$blocked = $manager->startOrResumeReport();
assertSameValue(false, $blocked['success'], 'The daily report limit should be enforced before another POST.');
assertSameValue(1, $api->createCalls, 'A rate-limited click must not call Yuju.');

Configuration::$values = [];
$failed_api = new FakeYujuReportApiClient();
$failed_api->createResponse = ['success' => false, 'message' => 'Gateway timeout'];
$failed_manager = new YujuProductReportManager($failed_api);
$failed_manager->startOrResumeReport();
$unknown = $failed_manager->startOrResumeReport();
assertSameValue('UNKNOWN', $unknown['status'], 'A timed-out create should be treated as an unknown result.');
assertSameValue(1, $failed_api->createCalls, 'An unknown POST result must not be retried automatically.');

Configuration::$values = [];
$retry_api = new FakeYujuReportApiClient();
$retry_api->statusResponses = [
    ['success' => true, 'data' => ['status' => 'COMPLETED', 'url' => 'https://reports.example/file.jsonl?signature=secret']],
    ['success' => true, 'data' => ['status' => 'COMPLETED', 'url' => 'https://reports.example/file.jsonl?signature=secret']],
];
$retry_store = new FakeYujuProductReportFileStore();
$retry_store->failDownloads = true;
$retry_manager = new YujuProductReportManager($retry_api, $retry_store);
$retry_manager->startOrResumeReport();
assertSameValue('DOWNLOAD_ERROR', $retry_manager->pollReport()['status'], 'A failed download should be recorded without starting another report.');
assertSameValue('DOWNLOAD_ERROR', $retry_manager->pollReport()['status'], 'Polling should not retry a failed download automatically.');
assertSameValue('COMPLETED', $retry_manager->pollReport(true)['status'], 'The owner can explicitly retry downloading the completed task.');
assertSameValue(1, $retry_api->createCalls, 'Retrying download must not create another quota-limited report.');
assertSameValue(2, $retry_api->statusCalls, 'Only the explicit download retry should recheck task status.');

echo "Product report lifecycle tests passed.\n";
