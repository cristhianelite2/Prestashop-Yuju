<?php

define('_PS_VERSION_', 'test');
define('_PS_MODULE_DIR_', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('_DB_PREFIX_', 'ps_');

function pSQL($value, $html = false)
{
    return addslashes((string) $value);
}

class Configuration
{
    public static function get($key, $default = null)
    {
        return $key === 'PS_LANG_DEFAULT' ? 1 : $default;
    }
}

class Product
{
    public static $fixtures = [];
    public $id;
    public $reference;
    public $name;
    public $description;
    public $price;
    public $id_manufacturer;
    public $id_category_default;
    public $combinations = [];

    public function __construct($id)
    {
        $this->id = (int) $id;
        foreach (self::$fixtures[$this->id] as $field => $value) {
            $this->{$field} = $value;
        }
    }

    public function getAttributeCombinations($id_lang)
    {
        return isset(self::$fixtures[$this->id]['combinations']) ? self::$fixtures[$this->id]['combinations'] : [];
    }
}

class TestDb
{
    public static $instance;
    public $products = [];
    public $statuses = [];

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function executeS($query)
    {
        if (strpos($query, 'FROM ps_yuju_product_status') !== false) {
            return array_values($this->statuses);
        }

        if (strpos($query, 'FROM ps_product p') !== false) {
            return $this->products;
        }

        throw new Exception('Unexpected query in test: ' . $query);
    }

    public function execute($query)
    {
        return true;
    }

    public function getRow($query)
    {
        if (strpos(ltrim($query), 'SELECT id FROM ps_yuju_product_status') === 0
            && preg_match('/prestashop_product_id\s*=\s*(\d+)/', $query, $match)
        ) {
            return isset($this->statuses[(int) $match[1]]) ? $this->statuses[(int) $match[1]] : false;
        }

        return false;
    }

    public function update($table, $data, $where)
    {
        preg_match('/prestashop_product_id\s*=\s*(\d+)/', $where, $match);
        $id = (int) $match[1];
        $this->statuses[$id] = array_merge($this->statuses[$id], $data);

        return true;
    }

    public function insert($table, $data)
    {
        $this->statuses[(int) $data['prestashop_product_id']] = $data;

        return true;
    }
}

class Db extends TestDb
{
}

class TestLogger
{
    public function log($message, $level = 'info')
    {
    }
}

require_once dirname(__DIR__) . '/classes/YujuProductManager.php';

class TestableYujuProductManager extends YujuProductManager
{
    public function __construct($mappings)
    {
        parent::__construct(new stdClass(), new TestLogger(), new stdClass(), $mappings);
    }

    public function updateStatus($product_id, $status, $error, $yuju_id)
    {
        return parent::updateProductStatus($product_id, $status, $error, $yuju_id);
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$db = TestDb::getInstance();
$db->products = [
    ['id_product' => 11, 'reference' => 'SKU-11', 'active' => 1],
    ['id_product' => 12, 'reference' => 'SKU-12', 'active' => 1],
    ['id_product' => 13, 'reference' => 'SKU-13', 'active' => 1],
    ['id_product' => 14, 'reference' => 'VAR-PARENT', 'active' => 1],
];
Product::$fixtures = [
    11 => ['reference' => 'SKU-11', 'name' => [1 => 'Shop name'], 'description' => [1 => 'Description'], 'price' => 15, 'id_manufacturer' => 0, 'id_category_default' => 0],
    12 => ['reference' => 'SKU-12', 'name' => [1 => 'Unmatched'], 'description' => [1 => 'Description'], 'price' => 5, 'id_manufacturer' => 0, 'id_category_default' => 0],
    13 => ['reference' => 'SKU-13', 'name' => [1 => 'Clean'], 'description' => [1 => 'Description'], 'price' => 7, 'id_manufacturer' => 0, 'id_category_default' => 0],
    14 => [
        'reference' => 'VAR-PARENT',
        'name' => [1 => 'Variations'],
        'description' => [1 => 'Description'],
        'price' => 10,
        'id_manufacturer' => 0,
        'id_category_default' => 0,
        'combinations' => [
            ['id_product_attribute' => 1401, 'reference' => 'VAR-M', 'price' => 0, 'quantity' => 2, 'group_name' => 'Talla', 'attribute_name' => 'M'],
            ['id_product_attribute' => 1402, 'reference' => 'VAR-L', 'price' => 1, 'quantity' => 1, 'group_name' => 'Talla', 'attribute_name' => 'L'],
        ],
    ],
];
$mappings = [
    ['prestashop_field' => 'name', 'yuju_field' => 'name', 'field_type' => 'string', 'transformation_rule' => 'none', 'sync_direction' => 'bidirectional', 'is_active' => 1],
    ['prestashop_field' => 'reference', 'yuju_field' => 'sku', 'field_type' => 'string', 'transformation_rule' => 'none', 'sync_direction' => 'bidirectional', 'is_active' => 1],
    ['prestashop_field' => 'price', 'yuju_field' => 'price', 'field_type' => 'decimal', 'transformation_rule' => 'none', 'sync_direction' => 'ps_to_yuju', 'is_active' => 1],
];
$report_rows = [
    ['id_product' => 101, 'sku_simple' => 'SKU-11', 'sku' => 'SKU-11', 'name' => 'Yuju name', 'description' => 'Description', 'price' => '15.00', 'variations' => [], '_yuju_report_has_variations' => true],
    ['id_product' => 103, 'sku_simple' => 'SKU-13', 'sku' => 'SKU-13', 'name' => 'Clean', 'description' => 'Description', 'price' => 7, 'variations' => [], '_yuju_report_has_variations' => true],
    ['id_product' => 104, 'sku_simple' => 'VAR-PARENT', 'sku' => 'VAR-PARENT', 'name' => 'Variations', 'description' => 'Description', 'price' => 10, 'variations' => [
        ['id_variation' => 1041, 'sku' => 'VAR-M', 'size' => 'M', 'price' => 10, 'stock' => 2],
    ], '_yuju_report_has_variations' => true],
];

$manager = new TestableYujuProductManager($mappings);
$report_index = YujuProductReportReconciler::indexReportRows($report_rows);
$result = $manager->reconcileYujuProductReport($report_index, $report_rows);

assertSameValue('101', $db->statuses[11]['yuju_product_id'], 'The report Yuju ID should be backfilled for a unique SKU match.');
assertSameValue('pending', $db->statuses[11]['sync_status'], 'A mapped value difference should leave the product pending.');
assertSameValue('101', $result['unsynced_products'][0]['yuju_product_id'], 'The report should list the mismatched linked product.');
assertSameValue(null, $db->statuses[12]['yuju_product_id'], 'A PrestaShop-only product must not be created in Yuju.');
assertSameValue('pending', $db->statuses[12]['sync_status'], 'A product without a Yuju match should be marked pending.');
assertSameValue('synced', $db->statuses[13]['sync_status'], 'Equal outbound values should be marked synced.');
assertSameValue('pending', $db->statuses[14]['sync_status'], 'A missing PrestaShop variation should keep its parent unsynchronized.');
assertSameValue('104', $db->statuses[14]['yuju_product_id'], 'The matched parent Yuju ID should be saved for a product with variations.');
assertSameValue(true, $manager->updateStatus(11, 'synced', null, '101'), 'Existing status rows should update by the schema primary key.');
assertSameValue(null, $db->statuses[11]['last_error'], 'Status updates should use the schema last_error column.');
assertSameValue(false, isset($db->statuses[11]['error_message']), 'Status updates must not write a nonexistent error_message column.');

echo "Product report status reconciliation tests passed.\n";
