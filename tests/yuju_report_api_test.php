<?php

define('_PS_VERSION_', 'test');
define('_PS_MODULE_DIR_', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once dirname(__DIR__) . '/classes/YujuApiClient.php';

class TestableYujuApiClient extends YujuApiClient
{
    public $requests = [];

    public function __construct()
    {
    }

    protected function makeRequest($method, $endpoint, $data = null, $params = [], $max_retries = null)
    {
        $this->requests[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
            'params' => $params,
            'max_retries' => $max_retries,
        ];

        return ['success' => true, 'data' => ['id_task' => 'report-task-1']];
    }

    public function headersForLog($headers)
    {
        return parent::sanitizeHeadersForLog($headers);
    }

    public function responseForLog($endpoint, $response)
    {
        return parent::sanitizeResponseForLog($endpoint, $response);
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$api = new TestableYujuApiClient();
$api->createProductGeneralReport();
assertSameValue('POST', $api->requests[0]['method'], 'General report generation must use POST.');
assertSameValue('products-gral-report', $api->requests[0]['endpoint'], 'Use Yuju’s documented general report endpoint.');
assertSameValue(0, $api->requests[0]['max_retries'], 'Do not retry report creation and consume the daily quota twice.');

$api->getProductGeneralReportStatus('task-123');
assertSameValue('GET', $api->requests[1]['method'], 'Report polling must use GET.');
assertSameValue('products-gral-report/task-123', $api->requests[1]['endpoint'], 'Poll the report by task ID.');

try {
    $api->getProductGeneralReportStatus('../account');
    throw new Exception('Unsafe task IDs must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSameValue(2, count($api->requests), 'Reject an unsafe task ID before sending a request.');
}

$safe_headers = $api->headersForLog([
    'Authorization: secret-token',
    'Accept: application/json',
]);
assertSameValue('Authorization: [REDACTED]', $safe_headers[0], 'API authorization tokens must never be written to logs.');

$safe_response = $api->responseForLog('products-gral-report/task-123', [
    'success' => true,
    'data' => ['status' => 'COMPLETED', 'url' => 'https://report.example/signed?signature=secret'],
]);
assertSameValue('[REDACTED]', $safe_response['data']['url'], 'Signed report URLs must never be written to logs.');
assertSameValue('COMPLETED', $safe_response['data']['status'], 'Safe report status can remain in logs.');

echo "Yuju report API tests passed.\n";
