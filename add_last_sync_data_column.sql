-- Agregar columna last_sync_data a yuju_product_status
-- Si la columna ya existe, simplemente ignora el error
ALTER TABLE `ps_yuju_product_status` 
ADD COLUMN `last_sync_data` TEXT NULL AFTER `last_sync_at`;
