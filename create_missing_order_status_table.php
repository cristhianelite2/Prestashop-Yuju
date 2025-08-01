<?php
/**
 * Script to create the missing yuju_order_status_mapping table
 */

require_once dirname(__FILE__) . '/../../config/config.inc.php';

try {
    $sql = '
    CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yuju_order_status_mapping` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `prestashop_status_id` int(11) NOT NULL,
        `yuju_status_name` varchar(100) NOT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_status_mapping` (`prestashop_status_id`, `yuju_status_name`),
        KEY `idx_prestashop_status` (`prestashop_status_id`),
        KEY `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ';
    
    $result = Db::getInstance()->execute($sql);
    
    if ($result) {
        echo "SUCCESS: Table ps_yuju_order_status_mapping created successfully.\n";
    } else {
        echo "ERROR: Failed to create table ps_yuju_order_status_mapping.\n";
        echo "SQL Error: " . Db::getInstance()->getMsgError() . "\n";
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "Script completed.\n";
?>