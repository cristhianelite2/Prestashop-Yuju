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
    private $next_status_id = 1;

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
        if (preg_match('/DELETE FROM ps_yuju_product_status WHERE id IN \\(([^)]+)\\)/', $query, $match)) {
            foreach (explode(',', $match[1]) as $id) {
                unset($this->statuses[(int) $id]);
            }
        }

        return true;
    }

    public function getRow($query)
    {
        if (preg_match('/yuju_product_id = "([^"]*)"/', $query, $yuju_match)) {
            $yuju_id = stripslashes($yuju_match[1]);
            $variation_id = '';
            if (preg_match('/yuju_variation_id = "([^"]*)"/', $query, $variation_match)) {
                $variation_id = stripslashes($variation_match[1]);
            }
            foreach ($this->statuses as $row) {
                $stored_variation = isset($row['yuju_variation_id']) ? (string) $row['yuju_variation_id'] : '';
                if ((string) ($row['yuju_product_id'] ?? '') === $yuju_id && $stored_variation === $variation_id) {
                    return ['id' => $row['id']];
                }
            }
            return false;
        }

        if (preg_match('/prestashop_product_id\\s*=\\s*(\\d+)/', $query, $match)) {
            foreach ($this->statuses as $row) {
                if ((int) ($row['prestashop_product_id'] ?? 0) === (int) $match[1]) {
                    return ['id' => $row['id']];
                }
            }
        }

        return false;
    }

    public function update($table, $data, $where)
    {
        if (preg_match('/\\bid\\s*=\\s*(\\d+)/', $where, $match)) {
            $id = (int) $match[1];
            if (!isset($this->statuses[$id])) {
                return false;
            }
            $this->statuses[$id] = array_merge($this->statuses[$id], $data);
            return true;
        }

        if (preg_match('/prestashop_product_id\\s*=\\s*(\\d+)/', $where, $match)) {
            $found = false;
            foreach ($this->statuses as $id => $row) {
                if ((int) ($row['prestashop_product_id'] ?? 0) === (int) $match[1]) {
                    $this->statuses[$id] = array_merge($row, $data);
                    $found = true;
                }
            }
            return $found;
        }

        return false;
    }

    public function insert($table, $data)
    {
        $id = $this->next_status_id++;
        $data['id'] = $id;
        $this->statuses[$id] = $data;

        return true;
    }

    public function findStatusByYujuId($yuju_id, $variation_id = '')
    {
        foreach ($this->statuses as $row) {
            $stored_variation = isset($row['yuju_variation_id']) ? (string) $row['yuju_variation_id'] : '';
            if ((string) ($row['yuju_product_id'] ?? '') === (string) $yuju_id && $stored_variation === (string) $variation_id) {
                return $row;
            }
        }

        return null;
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

$status101 = $db->findStatusByYujuId('101');
assertSameValue(11, (int) $status101['prestashop_product_id'], 'A unique SKU match should link the Yuju row to PrestaShop.');
assertSameValue('pending', $status101['sync_status'], 'A mapped value difference should leave the product pending.');
assertSameValue('101', $result['unsynced_products'][0]['yuju_product_id'], 'The report should list the mismatched linked product.');
assertSameValue(4, count($result['all_products']), 'The reconciliation table data must include every active PrestaShop product.');
assertSameValue('differences', $result['all_products'][0]['reconciliation_status'], 'Products with field differences must be categorized for the table.');
assertSameValue('not_in_yuju', $result['all_products'][1]['reconciliation_status'], 'Products without a Yuju match must be shown as missing in Yuju.');
assertSameValue('synced', $result['all_products'][2]['reconciliation_status'], 'Products with equal mapped fields must be shown as synchronized.');
assertSameValue(null, $db->findStatusByYujuId('101', 'missing'), 'Unknown Yuju variations should not be fabricated.');
$status103 = $db->findStatusByYujuId('103');
$status104 = $db->findStatusByYujuId('104');
assertSameValue('synced', $status103['sync_status'], 'Equal outbound values should be marked synced.');
assertSameValue('pending', $status104['sync_status'], 'A missing PrestaShop variation should keep its parent unsynchronized.');
assertSameValue('104', $status104['yuju_product_id'], 'The matched parent Yuju ID should be saved for a product with variations.');
assertSameValue(null, $db->findStatusByYujuId('', ''), 'PrestaShop-only products must not be inserted into the Yuju catalog.');
assertSameValue(true, $manager->updateStatus(11, 'synced', null, '101'), 'Existing linked status rows should update by the PrestaShop product ID.');
$status101 = $db->findStatusByYujuId('101');
assertSameValue(null, $status101['last_error'], 'Status updates should use the schema last_error column.');
assertSameValue(false, isset($status101['error_message']), 'Status updates must not write a nonexistent error_message column.');
assertSameValue('VAR-M', $db->findStatusByYujuId('104', 'id:1041')['yuju_variation_sku'], 'Each reported Yuju variation should have its own status row.');
assertSameValue(4, $result['yuju_total'], 'The Yuju catalog total should include parent products and nested variations.');

echo "Product report status reconciliation tests passed.\n";
