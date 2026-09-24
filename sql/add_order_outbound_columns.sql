-- Columnas outbound / canal en mapping de órdenes (tiendas ya instaladas)
-- Idempotente: ejecutar solo si la columna no existe (o usar YujuSchemaRepair).

ALTER TABLE `PREFIX_yuju_order_mapping`
    ADD COLUMN `id_channel` varchar(64) DEFAULT NULL AFTER `yuju_order_id`;

ALTER TABLE `PREFIX_yuju_order_mapping`
    ADD COLUMN `outbound_external_pk` varchar(64) DEFAULT NULL AFTER `id_channel`;

ALTER TABLE `PREFIX_yuju_order_mapping`
    ADD COLUMN `outbound_status` varchar(32) DEFAULT NULL AFTER `outbound_external_pk`;

ALTER TABLE `PREFIX_yuju_order_mapping`
    ADD COLUMN `outbound_updated_at` datetime DEFAULT NULL AFTER `outbound_status`;
