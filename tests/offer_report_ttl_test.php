<?php
/**
 * The stock/price (offer) report is reused for 12 hours and its temporary URL is never kept.
 */
define('_PS_VERSION_', 'test');
define('_PS_MODULE_DIR_', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('_DB_PREFIX_', 'ps_');

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

class Db
{
    public static function getInstance()
    {
        return new self();
    }

    public function execute($sql)
    {
        return true;
    }

    public function getValue($sql)
    {
        return 0;
    }

    public function insert($table, $row, $null = false)
    {
        return true;
    }

    public function update($table, $row, $where)
    {
        return true;
    }
}

function bqSQL($value)
{
    return $value;
}

function pSQL($value)
{
    return $value;
}

require_once dirname(__DIR__) . '/classes/YujuProductReportManager.php';

class FakeOfferApi
{
    public $offerCalls = 0;

    public function getProductOfferReport()
    {
        ++$this->offerCalls;

        return ['success' => true, 'http_code' => 200, 'data' => ['url' => 'https://example.test/tmp/1m/offer.json']];
    }
}

class FakeOfferStore
{
    public $downloads = 0;
    public $rows = [['id_product' => 1, 'sku' => 'A', 'stock' => 3, 'price' => 10.5]];

    public function discoverLatestValidOfferReport()
    {
        return null;
    }

    public function readOfferProducts($cache_id)
    {
        return $this->rows;
    }

    public function downloadRawReportFile($url)
    {
        ++$this->downloads;

        return [
            'cache_id' => str_repeat('b', 32),
            'relative_path' => 'var/reports/' . str_repeat('b', 32) . '.json',
            'size_bytes' => 100,
            'saved_at' => time(),
        ];
    }

    public function deleteJsonPayload($cache_id)
    {
        return true;
    }
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

$cache_id = str_repeat('a', 32);
$key = YujuProductReportManager::OFFER_STATE_CONFIG_KEY;

// 1) A file downloaded 2 hours ago is reused: no new request to Yuju.
Configuration::$values = [$key => json_encode(['cache_id' => $cache_id, 'report_downloaded_at' => time() - 7200])];
$api = new FakeOfferApi();
$store = new FakeOfferStore();
$result = (new YujuProductReportManager($api, $store))->downloadOfferReport();
assertTrue($result['success'] && !empty($result['reused']), 'A 2-hour-old offer file must be reused.');
assertTrue($api->offerCalls === 0 && $store->downloads === 0, 'A fresh offer file must not call Yuju.');

// 2) A file older than 12 hours is replaced, even if an old URL was stored.
Configuration::$values = [$key => json_encode([
    'cache_id' => $cache_id,
    'report_downloaded_at' => time() - 13 * 3600,
    'download_url' => 'https://example.test/old-expired.json',
    'download_url_saved_at' => time() - 13 * 3600,
])];
$api = new FakeOfferApi();
$store = new FakeOfferStore();
$result = (new YujuProductReportManager($api, $store))->downloadOfferReport();
assertTrue($result['success'] && empty($result['reused']), 'A 13-hour-old offer file must be downloaded again.');
assertTrue($api->offerCalls === 1 && $store->downloads === 1, 'A stale offer file must request and download a new report.');

// 3) The temporary URL is not kept after a successful download.
$state = json_decode(Configuration::get($key), true);
assertTrue(!isset($state['download_url']) && isset($state['report_downloaded_at']), 'The temporary URL must not be stored after downloading.');

echo "Offer report TTL tests passed.\n";
