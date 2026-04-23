<?php
/**
 * Fix existing orders with id_shop = 0
 */

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

$shop_id = Context::getContext()->shop->id;
$shop_group_id = Context::getContext()->shop->id_shop_group;

// Update orders with id_shop = 0
$result = Db::getInstance()->execute('
    UPDATE ' . _DB_PREFIX_ . 'orders 
    SET id_shop = ' . (int)$shop_id . ', 
        id_shop_group = ' . (int)$shop_group_id . '
    WHERE id_shop = 0
');

if ($result) {
    $affected = Db::getInstance()->Affected_Rows();
    echo "✅ Updated $affected orders with shop ID $shop_id and shop group ID $shop_group_id\n";
} else {
    echo "❌ Error updating orders\n";
}
