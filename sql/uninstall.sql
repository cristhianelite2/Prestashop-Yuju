-- Disable foreign key checks to avoid constraint errors
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables with foreign key dependencies first
DROP TABLE IF EXISTS `PREFIX_yuju_attribute_value_mapping`;
DROP TABLE IF EXISTS `PREFIX_yuju_attribute_mapping`;
DROP TABLE IF EXISTS `PREFIX_yuju_product_status`;
DROP TABLE IF EXISTS `PREFIX_yuju_category_mapping`;
DROP TABLE IF EXISTS `PREFIX_yuju_product_mapping`;
DROP TABLE IF EXISTS `PREFIX_yuju_sync_logs`;
DROP TABLE IF EXISTS `PREFIX_yuju_webhook_logs`;
DROP TABLE IF EXISTS `PREFIX_yuju_webhook_registrations`;
DROP TABLE IF EXISTS `PREFIX_yuju_order_status_mapping`;
DROP TABLE IF EXISTS `PREFIX_yuju_order_mapping`;
DROP TABLE IF EXISTS `PREFIX_yuju_oauth_tokens`;
DROP TABLE IF EXISTS `PREFIX_yuju_attributes_cache`;
DROP TABLE IF EXISTS `PREFIX_yuju_attribute_values_cache`;
DROP TABLE IF EXISTS `PREFIX_yuju_categories_cache`;
DROP TABLE IF EXISTS `PREFIX_yuju_sync_queue`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;