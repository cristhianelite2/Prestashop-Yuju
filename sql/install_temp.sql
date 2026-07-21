CREATE TABLE IF NOT EXISTS `ps_yuju_oauth_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `access_token` text NOT NULL,
    `refresh_token` text,
    `token_type` varchar(50) DEFAULT 'Bearer',
    `expires_in` int(11),
    `expires_at` datetime,
    `scope` text,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_category_mapping` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_category_id` int(11) NOT NULL,
    `yuju_category_id` varchar(255) NOT NULL,
    `yuju_category_name` varchar(255),
    `sync_enabled` tinyint(1) DEFAULT 1,
    `last_sync_at` datetime,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_prestashop_category` (`prestashop_category_id`),
    KEY `idx_yuju_category` (`yuju_category_id`),
    KEY `idx_sync_enabled` (`sync_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_product_mapping` (
    `id_mapping` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_field` varchar(100) NOT NULL,
    `yuju_field` varchar(100) NOT NULL,
    `field_type` enum('string', 'integer', 'decimal', 'boolean', 'date', 'array', 'object') DEFAULT 'string',
    `sync_direction` enum('prestashop_to_yuju', 'yuju_to_prestashop', 'bidirectional') DEFAULT 'bidirectional',
    `transformation_rule` varchar(255) DEFAULT 'none',
    `custom_transformation` text,
    `default_value` varchar(255),
    `is_required` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id_mapping`),
    UNIQUE KEY `unique_mapping` (`prestashop_field`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_attribute_mapping` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_attribute_id` int(11),
    `prestashop_attribute_name` varchar(255),
    `yuju_attribute_id` varchar(255),
    `yuju_attribute_name` varchar(255),
    `attribute_type` enum('color', 'size', 'material', 'custom') DEFAULT 'custom',
    `sync_direction` enum('prestashop_to_yuju', 'yuju_to_prestashop', 'bidirectional') DEFAULT 'bidirectional',
    `auto_create_values` tinyint(1) DEFAULT 1,
    `is_active` tinyint(1) DEFAULT 1,
    `last_sync_at` datetime,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_prestashop_attribute` (`prestashop_attribute_id`),
    KEY `idx_yuju_attribute` (`yuju_attribute_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_product_status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `yuju_product_id` varchar(255),
    `sync_status` enum('pending', 'syncing', 'synced', 'error', 'disabled') DEFAULT 'pending',
    `sync_direction` enum('prestashop_to_yuju', 'yuju_to_prestashop', 'bidirectional') DEFAULT 'bidirectional',
    `last_sync_at` datetime,
    `last_error` text,
    `error_count` int(11) DEFAULT 0,
    `sync_enabled` tinyint(1) DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_prestashop_product` (`prestashop_product_id`),
    KEY `idx_yuju_product` (`yuju_product_id`),
    KEY `idx_sync_status` (`sync_status`),
    KEY `idx_sync_enabled` (`sync_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_sync_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sync_type` enum('full', 'incremental', 'manual') DEFAULT 'manual',
    `entity_type` enum('categories', 'products', 'stock', 'prices', 'orders', 'attributes') NOT NULL,
    `sync_direction` enum('prestashop_to_yuju', 'yuju_to_prestashop', 'bidirectional') DEFAULT 'bidirectional',
    `status` enum('started', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'started',
    `total_items` int(11) DEFAULT 0,
    `processed_items` int(11) DEFAULT 0,
    `success_items` int(11) DEFAULT 0,
    `error_items` int(11) DEFAULT 0,
    `start_time` datetime NOT NULL,
    `end_time` datetime,
    `duration` int(11),
    `error_message` text,
    `details` longtext,
    `created_by` varchar(100),
    PRIMARY KEY (`id`),
    KEY `idx_sync_type` (`sync_type`),
    KEY `idx_entity_type` (`entity_type`),
    KEY `idx_status` (`status`),
    KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `level` enum('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
    `message` text NOT NULL,
    `context` longtext,
    `created_at` datetime NOT NULL,
    `ip_address` varchar(45),
    `user_agent` text,
    `user_id` int(11),
    PRIMARY KEY (`id`),
    KEY `idx_level` (`level`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_webhook_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `event_type` varchar(100) NOT NULL,
    `entity_id` varchar(255),
    `payload` longtext NOT NULL,
    `headers` text,
    `status` enum('processing', 'processed', 'failed') DEFAULT 'processing',
    `response` text,
    `error_message` text,
    `received_at` datetime NOT NULL,
    `processed_at` datetime,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_entity_id` (`entity_id`),
    KEY `idx_status` (`status`),
    KEY `idx_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_webhook_registrations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `event_type` varchar(100) NOT NULL,
    `yuju_webhook_id` varchar(255) NOT NULL,
    `webhook_url` varchar(500) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_event_type` (`event_type`),
    KEY `idx_yuju_webhook_id` (`yuju_webhook_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_configuration` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `config_key` varchar(100) NOT NULL,
    `config_value` longtext,
    `config_type` enum('string', 'integer', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    `is_sensitive` tinyint(1) DEFAULT 0,
    `description` text,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_order_mapping` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_order_id` int(11) NOT NULL,
    `yuju_order_id` varchar(255) NOT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_prestashop_order` (`prestashop_order_id`),
    UNIQUE KEY `unique_yuju_order` (`yuju_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_categories_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `yuju_category_id` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `parent_id` varchar(255) DEFAULT NULL,
    `level` int(11) DEFAULT 0,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_yuju_category` (`yuju_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_attributes_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `yuju_attribute_id` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `type` varchar(50) DEFAULT 'select',
    `required` tinyint(1) DEFAULT 0,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_yuju_attribute` (`yuju_attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ps_yuju_attribute_values_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `yuju_attribute_id` varchar(255) NOT NULL,
    `yuju_value_id` varchar(255) NOT NULL,
    `value_name` varchar(255) NOT NULL,
    `value_code` varchar(255) DEFAULT '',
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_yuju_attribute_value` (`yuju_attribute_id`, `yuju_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default product field mappings
INSERT INTO `ps_yuju_product_mapping` (`prestashop_field`, `yuju_field`, `field_type`, `sync_direction`, `transformation_rule`, `is_required`, `is_active`, `created_at`, `updated_at`) VALUES
('name', 'name', 'string', 'bidirectional', 'none', 1, 1, NOW(), NOW()),
('description', 'description', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('description_short', 'short_description', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('price', 'price', 'decimal', 'bidirectional', 'none', 1, 1, NOW(), NOW()),
('wholesale_price', 'cost_price', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('reference', 'sku', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('ean13', 'ean', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('weight', 'weight', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('width', 'width', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('height', 'height', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('depth', 'depth', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('active', 'active', 'boolean', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('quantity', 'stock_quantity', 'integer', 'bidirectional', 'none', 0, 1, NOW(), NOW());

-- Insert default configuration values
INSERT INTO `ps_yuju_configuration` (`config_key`, `config_value`, `config_type`, `description`, `created_at`, `updated_at`) VALUES
('sync_batch_size', '50', 'integer', 'Number of items to process in each sync batch', NOW(), NOW()),
('sync_frequency', '3600', 'integer', 'Sync frequency in seconds (3600 = 1 hour)', NOW(), NOW()),
('enable_email_notifications', '1', 'boolean', 'Enable email notifications for sync errors', NOW(), NOW()),
('notification_email', '', 'string', 'Email address for notifications', NOW(), NOW()),
('webhook_secret', '', 'encrypted', 'Secret key for webhook signature verification', NOW(), NOW()),
('log_retention_days', '30', 'integer', 'Number of days to keep logs', NOW(), NOW()),
('enable_debug_logging', '0', 'boolean', 'Enable debug level logging', NOW(), NOW()),
('max_retry_attempts', '3', 'integer', 'Maximum number of retry attempts for failed syncs', NOW(), NOW()),
('sync_timeout', '300', 'integer', 'Sync timeout in seconds', NOW(), NOW()),
('enable_stock_sync', '1', 'boolean', 'Enable automatic stock synchronization', NOW(), NOW()),
('enable_price_sync', '1', 'boolean', 'Enable automatic price synchronization', NOW(), NOW()),
('enable_category_sync', '1', 'boolean', 'Enable automatic category synchronization', NOW(), NOW());
