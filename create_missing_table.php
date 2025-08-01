<?php

// Simple script to create missing table without requiring full PrestaShop config
// We'll use the same approach as the module's installDb() method

// Define constants if not already defined
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}

// Database connection parameters (adjust these for your setup)
$db_server = 'localhost';
$db_name = 'prestashop';
$db_user = 'root';
$db_password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$db_server;dbname=$db_name;charset=utf8", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Create the missing yuju_attribute_value_mapping table
    $sql = '
    CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yuju_attribute_value_mapping` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `attribute_mapping_id` int(11) NOT NULL,
        `prestashop_attribute_value_id` int(11) NOT NULL,
        `yuju_value_id` varchar(255) NOT NULL,
        `yuju_value_name` varchar(255),
        `sync_direction` enum(\'prestashop_to_yuju\', \'yuju_to_prestashop\', \'bidirectional\') DEFAULT \'bidirectional\',
        `is_active` tinyint(1) DEFAULT 1,
        `last_sync_at` datetime,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_mapping` (`attribute_mapping_id`, `prestashop_attribute_value_id`),
        KEY `idx_attribute_mapping` (`attribute_mapping_id`),
        KEY `idx_prestashop_value` (`prestashop_attribute_value_id`),
        KEY `idx_yuju_value` (`yuju_value_id`),
        KEY `idx_active` (`is_active`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;
    ';
    
    $result = $pdo->exec($sql);
    
    echo "Table 'yuju_attribute_value_mapping' created successfully!\n";
    echo "SQL executed: $sql\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

?>