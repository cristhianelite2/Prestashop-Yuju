-- Agregar estados 'synced_with_warnings' y 'synced_with_errors' al enum de sync_status
ALTER TABLE `PREFIX_yuju_product_status` 
MODIFY COLUMN `sync_status` ENUM('pending', 'syncing', 'synced', 'synced_with_warnings', 'synced_with_errors', 'error', 'disabled') DEFAULT 'pending';
