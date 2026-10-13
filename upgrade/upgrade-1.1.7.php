<?php
/**
 * Actualización 1.1.6 -> 1.1.7
 * Asegura que el catálogo de estados sea Yuju-céntrico y que PrestaShop sea opcional.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_7($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'yuju_product_status';

    $columns = $db->executeS('SHOW COLUMNS FROM ' . bqSQL($table));
    if (!is_array($columns)) {
        return false;
    }

    $column_names = [];
    foreach ($columns as $column) {
        $column_names[$column['Field']] = true;
    }

    if (isset($column_names['prestashop_product_id'])
        && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' MODIFY prestashop_product_id int(11) DEFAULT NULL')) {
        return false;
    }

    $required_columns = [
        'prestashop_product_attribute_id' => 'int(11) DEFAULT NULL AFTER prestashop_product_id',
        'yuju_product_name' => 'varchar(512) DEFAULT NULL AFTER yuju_product_id',
        'yuju_variation_id' => 'varchar(255) DEFAULT NULL AFTER yuju_product_name',
        'yuju_variation_sku' => 'varchar(255) DEFAULT NULL AFTER yuju_variation_id',
    ];

    foreach ($required_columns as $name => $definition) {
        if (!isset($column_names[$name])
            && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' ADD ' . bqSQL($name) . ' ' . $definition)) {
            return false;
        }
    }

    $indexes = $db->executeS('SHOW INDEX FROM ' . bqSQL($table));
    if (!is_array($indexes)) {
        return false;
    }

    $index_names = [];
    foreach ($indexes as $index) {
        $index_names[$index['Key_name']] = true;
    }

    if (isset($index_names['unique_prestashop_product'])
        && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' DROP INDEX unique_prestashop_product')) {
        return false;
    }

    if (isset($column_names['sync_direction'])
        && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' DROP COLUMN sync_direction')) {
        return false;
    }

    if (!isset($index_names['idx_prestashop_product'])
        && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' ADD KEY idx_prestashop_product (prestashop_product_id)')) {
        return false;
    }
    if (!isset($index_names['idx_yuju_product'])
        && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' ADD KEY idx_yuju_product (yuju_product_id)')) {
        return false;
    }
    if (!isset($index_names['idx_yuju_variation_sku'])
        && !$db->execute('ALTER TABLE ' . bqSQL($table) . ' ADD KEY idx_yuju_variation_sku (yuju_variation_sku)')) {
        return false;
    }

    return true;
}
