-- Claims pendientes usan prestashop_order_id NEGATIVO (nunca NULL).
-- Opcional: normalizar filas legacy con 0 o NULL.

-- Ejemplo (ajusta PREFIX):
-- UPDATE `PREFIX_yuju_order_mapping`
-- SET `prestashop_order_id` = -ABS(CRC32(`yuju_order_id`))
-- WHERE `prestashop_order_id` IS NULL OR `prestashop_order_id` = 0;
