-- ============================================
-- Script de actualización para sistema de cola
-- Para PRUEBAS en base de datos existente
-- ============================================
-- Este script agrega el sistema de cola por lotes
-- sin afectar la estructura existente
-- ============================================

-- 1. Modificar yuju_product_status para agregar estado 'queued'
ALTER TABLE `ps_yuju_product_status` 
MODIFY COLUMN `sync_status` enum('pending', 'syncing', 'synced', 'synced_with_warnings', 'synced_with_errors', 'error', 'disabled', 'queued') DEFAULT 'pending';

-- 2. Crear tabla de cola de sincronización
CREATE TABLE IF NOT EXISTS `ps_yuju_sync_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prestashop_product_id` int(11) NOT NULL,
    `action` enum('create','update') NOT NULL,
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

-- 3. Agregar configuraciones de lotes (si no existen)
INSERT IGNORE INTO `ps_configuration` (`name`, `value`, `date_add`, `date_upd`) 
VALUES 
    ('YUJU_BATCH_SIZE', '100', NOW(), NOW()),
    ('YUJU_BATCH_FREQUENCY', '300', NOW(), NOW());

-- ============================================
-- Verificar cambios
-- ============================================
SELECT 'Tabla yuju_sync_queue creada' as status;
SELECT COUNT(*) as total_en_cola FROM `ps_yuju_sync_queue`;

SELECT 'Configuración de lotes' as status;
SELECT `name`, `value` FROM `ps_configuration` 
WHERE `name` IN ('YUJU_BATCH_SIZE', 'YUJU_BATCH_FREQUENCY');

SELECT 'Estados disponibles en yuju_product_status' as status;
SHOW COLUMNS FROM `ps_yuju_product_status` WHERE Field = 'sync_status';
