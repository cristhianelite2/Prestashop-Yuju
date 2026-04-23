-- Tabla para historial de intentos de sincronización de productos
CREATE TABLE IF NOT EXISTS `PREFIX_yuju_product_sync_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `yuju_product_id` varchar(255),
    `sync_direction` enum('to_yuju', 'from_yuju') NOT NULL DEFAULT 'to_yuju',
    `action` enum('create', 'update', 'delete') NOT NULL,
    `status` enum('success', 'error', 'warning') NOT NULL,
    `http_status_code` int(11),
    `request_data` longtext,
    `response_data` longtext,
    `error_message` text,
    `sync_duration` decimal(10,3),
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` varchar(100),
    PRIMARY KEY (`id`),
    KEY `idx_prestashop_product` (`prestashop_product_id`),
    KEY `idx_yuju_product` (`yuju_product_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
