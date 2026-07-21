-- Varias categorías PrestaShop pueden compartir la misma categoría Yuju.
-- Ejecutar una vez en tiendas ya instaladas (o se aplica automáticamente al guardar un mapeo).

ALTER TABLE `PREFIX_yuju_category_mapping` DROP INDEX `unique_yuju_category`;
ALTER TABLE `PREFIX_yuju_category_mapping` ADD INDEX `idx_yuju_category` (`yuju_category_id`);
