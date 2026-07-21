CREATE TABLE IF NOT EXISTS `PREFIX_yuju_oauth_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_id` varchar(255) DEFAULT NULL,
    `client_secret` varchar(255) DEFAULT NULL,
    `access_token` varchar(500) DEFAULT NULL,
    `refresh_token` varchar(500) DEFAULT NULL,
    `token_type` varchar(50) DEFAULT NULL,
    `expires_in` int(11) DEFAULT NULL,
    `expires_at` datetime DEFAULT NULL,
    `token_expires` datetime DEFAULT NULL,
    `scope` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_category_mapping` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_product_mapping` (
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
    UNIQUE KEY `unique_mapping` (`prestashop_field`, `yuju_field`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_attribute_mapping` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_product_status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `yuju_product_id` varchar(255),
    `sync_status` enum('pending', 'syncing', 'synced', 'synced_with_warnings', 'synced_with_errors', 'error', 'disabled', 'queued', 'creating_in_yuju', 'updating_in_yuju', 'deleting_in_yuju') DEFAULT 'pending',
    `sync_direction` enum('prestashop_to_yuju', 'yuju_to_prestashop', 'bidirectional') DEFAULT 'bidirectional',
    `last_sync_at` datetime,
    `last_sync_data` text,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_sync_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `action` enum('create','update','delete') NOT NULL,
    `priority` enum('high','normal') NOT NULL DEFAULT 'normal',
    `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `data` TEXT NOT NULL COMMENT 'JSON con los datos a sincronizar',
    `attempts` int(11) NOT NULL DEFAULT 0,
    `max_attempts` int(11) NOT NULL DEFAULT 3,
    `error_message` TEXT DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `last_attempt_at` datetime DEFAULT NULL,
    `processed_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `prestashop_product_id` (`prestashop_product_id`),
    KEY `status` (`status`),
    KEY `priority` (`priority`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_sync_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_webhook_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_webhook_registrations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_configuration` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_order_mapping` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_order_id` int(11) NOT NULL,
    `yuju_order_id` varchar(255) NOT NULL,
    `id_channel` varchar(64) DEFAULT NULL,
    `outbound_external_pk` varchar(64) DEFAULT NULL,
    `outbound_status` varchar(32) DEFAULT NULL,
    `outbound_updated_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_prestashop_order` (`prestashop_order_id`),
    UNIQUE KEY `unique_yuju_order` (`yuju_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_order_status_mapping` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_categories_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `yuju_category_id` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `parent_id` varchar(255) DEFAULT NULL,
    `level` int(11) DEFAULT 0,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_yuju_category` (`yuju_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_attributes_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `yuju_attribute_id` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `type` varchar(50) DEFAULT 'select',
    `required` tinyint(1) DEFAULT 0,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_yuju_attribute` (`yuju_attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_attribute_values_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `yuju_attribute_id` varchar(255) NOT NULL,
    `yuju_value_id` varchar(255) NOT NULL,
    `value_name` varchar(255) NOT NULL,
    `value_code` varchar(255) DEFAULT '',
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_yuju_attribute_value` (`yuju_attribute_id`, `yuju_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_attribute_value_mapping` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `attribute_mapping_id` int(11) NOT NULL,
    `prestashop_attribute_value_id` int(11) NOT NULL,
    `yuju_value_id` varchar(255) NOT NULL,
    `yuju_value_name` varchar(255),
    `sync_direction` enum('prestashop_to_yuju', 'yuju_to_prestashop', 'bidirectional') DEFAULT 'bidirectional',
    `is_active` tinyint(1) DEFAULT 1,
    `last_sync_at` datetime,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_mapping` (`attribute_mapping_id`, `prestashop_attribute_value_id`),
    KEY `idx_attribute_mapping` (`attribute_mapping_id`),
    KEY `idx_prestashop_value` (`prestashop_attribute_value_id`),
    KEY `idx_yuju_value` (`yuju_value_id`),
    KEY `idx_active` (`is_active`),
    FOREIGN KEY (`attribute_mapping_id`) REFERENCES `PREFIX_yuju_attribute_mapping` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_product_sync_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `yuju_product_id` varchar(255) DEFAULT NULL,
    `sync_direction` enum('to_yuju', 'from_yuju') NOT NULL DEFAULT 'to_yuju',
    `action` enum('create', 'update', 'delete') NOT NULL,
    `status` enum('success', 'error', 'warning') NOT NULL,
    `http_status_code` int(11) DEFAULT NULL,
    `request_data` longtext,
    `response_data` longtext,
    `error_message` text,
    `sync_duration` decimal(10,3) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_prestashop_product` (`prestashop_product_id`),
    KEY `idx_yuju_product` (`yuju_product_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_audit_runs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `trigger` enum('scheduled','manual') NOT NULL DEFAULT 'manual',
    `mode` enum('silent','visual') NOT NULL DEFAULT 'silent',
    `audit_stock` tinyint(1) NOT NULL DEFAULT 1,
    `audit_price` tinyint(1) NOT NULL DEFAULT 1,
    `audit_images` tinyint(1) NOT NULL DEFAULT 0,
    `status` enum('pending','running','paused','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    `total` int(11) NOT NULL DEFAULT 0,
    `processed` int(11) NOT NULL DEFAULT 0,
    `matched` int(11) NOT NULL DEFAULT 0,
    `diff_found` int(11) NOT NULL DEFAULT 0,
    `fixed` int(11) NOT NULL DEFAULT 0,
    `errors` int(11) NOT NULL DEFAULT 0,
    `not_found` int(11) NOT NULL DEFAULT 0,
    `started_at` datetime DEFAULT NULL,
    `finished_at` datetime DEFAULT NULL,
    `duration_seconds` int(11) DEFAULT NULL,
    `error_message` text,
    `summary_json` longtext,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_started_at` (`started_at`),
    KEY `idx_trigger` (`trigger`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_audit_run_details` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_audit_run` int(11) NOT NULL,
    `prestashop_product_id` int(11) DEFAULT NULL,
    `yuju_product_id` varchar(255) DEFAULT NULL,
    `sku` varchar(255) DEFAULT NULL,
    `product_name` varchar(512) DEFAULT NULL,
    `ps_stock` int(11) DEFAULT NULL,
    `yuju_stock` int(11) DEFAULT NULL,
    `ps_price` decimal(20,6) DEFAULT NULL,
    `yuju_price` decimal(20,6) DEFAULT NULL,
    `ps_images_count` int(11) DEFAULT NULL,
    `yuju_images_count` int(11) DEFAULT NULL,
    `diffs_json` text,
    `result` enum('matched','diff_fixed','diff_found','diff_error','not_found') NOT NULL DEFAULT 'matched',
    `message` text,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_run` (`id_audit_run`),
    KEY `idx_result` (`result`),
    KEY `idx_sku` (`sku`),
    KEY `idx_prestashop_product` (`prestashop_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_yuju_product_reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_task` varchar(64) DEFAULT NULL,
    `report_type` varchar(32) NOT NULL DEFAULT 'gral',
    `trigger` enum('scheduled','manual','webhook') NOT NULL DEFAULT 'manual',
    `status` enum('pending','processing','completed','failed','rejected','limit_reached') NOT NULL DEFAULT 'pending',
    `products_count` int(11) NOT NULL DEFAULT 0,
    `file_path` varchar(512) DEFAULT NULL,
    `file_size` int(11) NOT NULL DEFAULT 0,
    `download_url` text,
    `error_message` text,
    `meta_json` longtext,
    `requested_at` datetime DEFAULT NULL,
    `completed_at` datetime DEFAULT NULL,
    `utc_day` char(10) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_id_task` (`id_task`),
    KEY `idx_status` (`status`),
    KEY `idx_utc_day` (`utc_day`),
    KEY `idx_report_type` (`report_type`),
    KEY `idx_requested_at` (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Los datos por defecto ya se insertan arriba después de la creación de la tabla

-- Insert default product field mappings (ignore if already exist)
-- Solo campos aceptados por POST /products (docs Yuju create único / con variaciones)
INSERT IGNORE INTO `PREFIX_yuju_product_mapping` (`prestashop_field`, `yuju_field`, `field_type`, `sync_direction`, `transformation_rule`, `is_required`, `is_active`, `created_at`, `updated_at`) VALUES
('name', 'name', 'string', 'bidirectional', 'none', 1, 1, NOW(), NOW()),
('description', 'description', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('price', 'price', 'decimal', 'bidirectional', 'none', 1, 1, NOW(), NOW()),
('reference', 'sku', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('ean13', 'ean', 'string', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('weight', 'weight', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('width', 'shipping_width', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('height', 'shipping_height', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW()),
('depth', 'shipping_depth', 'decimal', 'bidirectional', 'none', 0, 1, NOW(), NOW());

-- Desactivar mapeos inválidos en instalaciones existentes (API Yuju no acepta estos campos)
UPDATE `PREFIX_yuju_product_mapping`
SET `is_active` = 0, `updated_at` = NOW()
WHERE `yuju_field` IN ('cost_price', 'active', 'stock_quantity', 'short_description', 'width', 'height', 'depth')
   OR (`prestashop_field` = 'wholesale_price' AND `yuju_field` = 'cost_price')
   OR (`prestashop_field` = 'quantity' AND `yuju_field` = 'stock_quantity')
   OR (`prestashop_field` = 'active' AND `yuju_field` = 'active')
   OR (`prestashop_field` = 'description_short' AND `yuju_field` = 'short_description');
