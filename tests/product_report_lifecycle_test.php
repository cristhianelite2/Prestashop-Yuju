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

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

Configuration::$values = [];
$api = new FakeYujuReportApiClient();
$manager = new YujuProductReportManager($api);

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
assertSameValue('https://reports.example/file.jsonl?signature=secret', $complete['report_url'], 'The returned URL should not be lost before download.');

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

echo "Product report lifecycle tests passed.\n";
