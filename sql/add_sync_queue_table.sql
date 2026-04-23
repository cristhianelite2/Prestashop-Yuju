-- Tabla para cola de sincronización por lotes
CREATE TABLE IF NOT EXISTS `ps_yuju_sync_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `action` enum('create', 'update') NOT NULL,
    `priority` enum('high', 'normal') DEFAULT 'normal',
    `status` enum('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `data` text,
    `attempts` int(11) DEFAULT 0,
    `max_attempts` int(11) DEFAULT 3,
    `last_attempt_at` datetime,
    `error_message` text,
    `created_at` datetime NOT NULL,
    `processed_at` datetime,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_priority` (`priority`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_product_action` (`prestashop_product_id`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
