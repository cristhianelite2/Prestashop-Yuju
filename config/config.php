<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// Yuju API Configuration
define('YUJU_API_VERSION', 'v1');
define('YUJU_SANDBOX_URL', 'https://sandbox-api.yuju.com');
define('YUJU_PRODUCTION_URL', 'https://api.yuju.com');
define('YUJU_OAUTH_AUTHORIZE_URL', '/oauth/authorize');
define('YUJU_OAUTH_TOKEN_URL', '/oauth/token');
define('YUJU_OAUTH_REVOKE_URL', '/oauth/revoke');

// API Endpoints
define('YUJU_ENDPOINT_PRODUCTS', '/api/v1/products');
define('YUJU_ENDPOINT_CATEGORIES', '/api/v1/categories');
define('YUJU_ENDPOINT_ORDERS', '/api/v1/orders');
define('YUJU_ENDPOINT_STOCK', '/api/v1/stock');
define('YUJU_ENDPOINT_PRICES', '/api/v1/prices');
define('YUJU_ENDPOINT_ATTRIBUTES', '/api/v1/attributes');
define('YUJU_ENDPOINT_WEBHOOKS', '/api/v1/webhooks');
define('YUJU_ENDPOINT_CUSTOMERS', '/api/v1/customers');
define('YUJU_ENDPOINT_MANUFACTURERS', '/api/v1/manufacturers');

// Module Configuration
define('YUJU_MODULE_NAME', 'prestashopyuju');
define('YUJU_MODULE_VERSION', '1.0.0');
define('YUJU_LOG_DIR', _PS_MODULE_DIR_ . 'prestashopyuju/logs/');
define('YUJU_CONFIG_DIR', _PS_MODULE_DIR_ . 'prestashopyuju/config/');

// Default Configuration Values
class YujuConfig
{
    // API Configuration
    public const DEFAULT_TIMEOUT = 30;
    public const DEFAULT_CONNECT_TIMEOUT = 10;
    public const DEFAULT_RETRIES = 3;
    public const DEFAULT_RETRY_DELAY = 1; // seconds

    // Sync Configuration
    public const DEFAULT_BATCH_SIZE = 50;
    public const DEFAULT_SYNC_FREQUENCY = 3600; // 1 hour
    public const DEFAULT_MAX_RETRY_ATTEMPTS = 3;
    public const DEFAULT_SYNC_TIMEOUT = 300; // 5 minutes

    // Log Configuration
    public const DEFAULT_LOG_LEVEL = 'info';
    public const DEFAULT_LOG_RETENTION_DAYS = 30;
    public const DEFAULT_MAX_LOG_FILE_SIZE = 10485760; // 10MB
    public const DEFAULT_MAX_LOG_FILES = 5;

    // Webhook Configuration
    public const DEFAULT_WEBHOOK_TIMEOUT = 30;
    public const DEFAULT_WEBHOOK_RETRIES = 3;

    /**
     * Get all default configuration values.
     */
    public static function getDefaults()
    {
        return [
            // API Settings
            'YUJU_API_ENVIRONMENT' => 'sandbox',
            'YUJU_API_TIMEOUT' => self::DEFAULT_TIMEOUT,
            'YUJU_API_CONNECT_TIMEOUT' => self::DEFAULT_CONNECT_TIMEOUT,
            'YUJU_API_RETRIES' => self::DEFAULT_RETRIES,
            'YUJU_API_RETRY_DELAY' => self::DEFAULT_RETRY_DELAY,

            // OAuth Settings
            'YUJU_CLIENT_ID' => '',
            'YUJU_CLIENT_SECRET' => '',
            'YUJU_REDIRECT_URI' => '',
            'YUJU_SCOPE' => 'read write',

            // Sync Settings
            'YUJU_SYNC_BATCH_SIZE' => self::DEFAULT_BATCH_SIZE,
            'YUJU_SYNC_FREQUENCY' => self::DEFAULT_SYNC_FREQUENCY,
            'YUJU_MAX_RETRY_ATTEMPTS' => self::DEFAULT_MAX_RETRY_ATTEMPTS,
            'YUJU_SYNC_TIMEOUT' => self::DEFAULT_SYNC_TIMEOUT,
            'YUJU_ENABLE_AUTO_SYNC' => true,
            'YUJU_ENABLE_STOCK_SYNC' => true,
            'YUJU_ENABLE_PRICE_SYNC' => true,
            'YUJU_ENABLE_CATEGORY_SYNC' => true,
            'YUJU_ENABLE_PRODUCT_SYNC' => true,
            'YUJU_ENABLE_ORDER_SYNC' => true,

            // Notification Settings
            'YUJU_ENABLE_EMAIL_NOTIFICATIONS' => true,
            'YUJU_NOTIFICATION_EMAIL' => '',
            'YUJU_NOTIFICATION_LEVEL' => 'error',

            // Log Settings
            'YUJU_LOG_LEVEL' => self::DEFAULT_LOG_LEVEL,
            'YUJU_LOG_RETENTION_DAYS' => self::DEFAULT_LOG_RETENTION_DAYS,
            'YUJU_MAX_LOG_FILE_SIZE' => self::DEFAULT_MAX_LOG_FILE_SIZE,
            'YUJU_MAX_LOG_FILES' => self::DEFAULT_MAX_LOG_FILES,
            'YUJU_ENABLE_DEBUG_LOGGING' => false,
            'YUJU_LOG_TO_DATABASE' => true,

            // Webhook Settings
            'YUJU_WEBHOOK_SECRET' => '',
            'YUJU_WEBHOOK_TIMEOUT' => self::DEFAULT_WEBHOOK_TIMEOUT,
            'YUJU_WEBHOOK_RETRIES' => self::DEFAULT_WEBHOOK_RETRIES,
            'YUJU_ENABLE_WEBHOOKS' => true,

            // Advanced Settings
            'YUJU_ENABLE_COMPRESSION' => true,
            'YUJU_ENABLE_CACHE' => true,
            'YUJU_CACHE_TTL' => 300, // 5 minutes
            'YUJU_ENABLE_RATE_LIMITING' => true,
            'YUJU_RATE_LIMIT_REQUESTS' => 100,
            'YUJU_RATE_LIMIT_WINDOW' => 60, // 1 minute
        ];
    }

    /**
     * Get configuration value with fallback to default.
     */
    public static function get($key, $default = null)
    {
        $value = Configuration::get($key);

        if ($value === false || $value === '') {
            $defaults = self::getDefaults();

            return isset($defaults[$key]) ? $defaults[$key] : $default;
        }

        return $value;
    }

    /**
     * Set configuration value.
     */
    public static function set($key, $value)
    {
        return Configuration::updateValue($key, $value);
    }

    /**
     * Get API base URL based on environment.
     */
    public static function getApiBaseUrl()
    {
        $environment = self::get('YUJU_API_ENVIRONMENT', 'sandbox');

        return $environment === 'production' ? YUJU_PRODUCTION_URL : YUJU_SANDBOX_URL;
    }

    /**
     * Get OAuth URLs.
     */
    public static function getOAuthUrls()
    {
        $base_url = self::getApiBaseUrl();

        return [
            'authorize' => $base_url . YUJU_OAUTH_AUTHORIZE_URL,
            'token' => $base_url . YUJU_OAUTH_TOKEN_URL,
            'revoke' => $base_url . YUJU_OAUTH_REVOKE_URL,
        ];
    }

    /**
     * Get API endpoints.
     */
    public static function getApiEndpoints()
    {
        $base_url = self::getApiBaseUrl();

        return [
            'products' => $base_url . YUJU_ENDPOINT_PRODUCTS,
            'categories' => $base_url . YUJU_ENDPOINT_CATEGORIES,
            'orders' => $base_url . YUJU_ENDPOINT_ORDERS,
            'stock' => $base_url . YUJU_ENDPOINT_STOCK,
            'prices' => $base_url . YUJU_ENDPOINT_PRICES,
            'attributes' => $base_url . YUJU_ENDPOINT_ATTRIBUTES,
            'webhooks' => $base_url . YUJU_ENDPOINT_WEBHOOKS,
            'customers' => $base_url . YUJU_ENDPOINT_CUSTOMERS,
            'manufacturers' => $base_url . YUJU_ENDPOINT_MANUFACTURERS,
        ];
    }

    /**
     * Validate configuration.
     */
    public static function validate()
    {
        $errors = [];

        // Check required OAuth settings
        if (empty(self::get('YUJU_CLIENT_ID'))) {
            $errors[] = 'Client ID is required';
        }

        if (empty(self::get('YUJU_CLIENT_SECRET'))) {
            $errors[] = 'Client Secret is required';
        }

        // Check numeric values
        $numeric_fields = [
            'YUJU_API_TIMEOUT',
            'YUJU_SYNC_BATCH_SIZE',
            'YUJU_SYNC_FREQUENCY',
            'YUJU_LOG_RETENTION_DAYS',
        ];

        foreach ($numeric_fields as $field) {
            $value = self::get($field);
            if (!is_numeric($value) || $value < 0) {
                $errors[] = $field . ' must be a positive number';
            }
        }

        // Check email format
        $email = self::get('YUJU_NOTIFICATION_EMAIL');
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid notification email format';
        }

        return $errors;
    }

    /**
     * Reset configuration to defaults.
     */
    public static function resetToDefaults()
    {
        $defaults = self::getDefaults();

        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return true;
    }

    /**
     * Export configuration.
     */
    public static function export($include_sensitive = false)
    {
        $config = [];
        $defaults = self::getDefaults();

        $sensitive_keys = [
            'YUJU_CLIENT_SECRET',
            'YUJU_WEBHOOK_SECRET',
        ];

        foreach ($defaults as $key => $default_value) {
            if (!$include_sensitive && in_array($key, $sensitive_keys)) {
                continue;
            }

            $config[$key] = self::get($key, $default_value);
        }

        return $config;
    }

    /**
     * Import configuration.
     */
    public static function import($config)
    {
        $imported = 0;
        $defaults = self::getDefaults();

        foreach ($config as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                Configuration::updateValue($key, $value);
                ++$imported;
            }
        }

        return $imported;
    }
}

// Status mappings
class YujuStatusMappings
{
    /**
     * PrestaShop to Yuju order status mapping.
     */
    public static function getOrderStatusMapping()
    {
        return [
            1 => 'pending',           // Awaiting check payment
            2 => 'processing',        // Payment accepted
            3 => 'processing',        // Preparation in progress
            4 => 'shipped',           // Shipped
            5 => 'delivered',         // Delivered
            6 => 'cancelled',         // Canceled
            7 => 'refunded',          // Refunded
            8 => 'error',             // Payment error
            9 => 'pending',           // On backorder (paid)
            10 => 'pending',          // Awaiting bank wire payment
            11 => 'pending',          // Remote payment accepted
            12 => 'processing',       // On backorder (not paid)
            13 => 'pending',           // Awaiting Cash On Delivery validation
        ];
    }

    /**
     * Yuju to PrestaShop order status mapping.
     */
    public static function getYujuOrderStatusMapping()
    {
        return [
            'pending' => 1,           // Awaiting check payment
            'processing' => 2,        // Payment accepted
            'shipped' => 4,           // Shipped
            'delivered' => 5,         // Delivered
            'cancelled' => 6,         // Canceled
            'refunded' => 7,          // Refunded
            'error' => 8,              // Payment error
        ];
    }

    /**
     * Product sync status mapping.
     */
    public static function getProductSyncStatus()
    {
        return [
            'pending' => 'Pending synchronization',
            'syncing' => 'Synchronization in progress',
            'synced' => 'Successfully synchronized',
            'error' => 'Synchronization error',
            'disabled' => 'Synchronization disabled',
        ];
    }

    /**
     * Sync direction mapping.
     */
    public static function getSyncDirections()
    {
        return [
            'prestashop_to_yuju' => 'PrestaShop → Yuju',
            'yuju_to_prestashop' => 'Yuju → PrestaShop',
            'bidirectional' => 'Bidirectional',
        ];
    }
}

// Field type mappings
class YujuFieldTypes
{
    /**
     * Available field types.
     */
    public static function getFieldTypes()
    {
        return [
            'string' => 'Text',
            'integer' => 'Integer',
            'decimal' => 'Decimal',
            'boolean' => 'Boolean',
            'date' => 'Date',
            'datetime' => 'Date/Time',
            'array' => 'Array',
            'object' => 'Object',
        ];
    }

    /**
     * PrestaShop product fields.
     */
    public static function getPrestaShopProductFields()
    {
        return [
            'name' => 'Product Name',
            'description' => 'Description',
            'description_short' => 'Short Description',
            'price' => 'Price',
            'wholesale_price' => 'Wholesale Price',
            'reference' => 'Reference',
            'ean13' => 'EAN13',
            'upc' => 'UPC',
            'weight' => 'Weight',
            'width' => 'Width',
            'height' => 'Height',
            'depth' => 'Depth',
            'active' => 'Active',
            'quantity' => 'Quantity',
            'minimal_quantity' => 'Minimal Quantity',
            'low_stock_threshold' => 'Low Stock Threshold',
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta Description',
            'meta_keywords' => 'Meta Keywords',
            'link_rewrite' => 'Friendly URL',
            'available_now' => 'Available Now Text',
            'available_later' => 'Available Later Text',
        ];
    }

    /**
     * Yuju product fields.
     */
    public static function getYujuProductFields()
    {
        return [
            'name' => 'Product Name',
            'description' => 'Description',
            'short_description' => 'Short Description',
            'price' => 'Price',
            'cost_price' => 'Cost Price',
            'sku' => 'SKU',
            'ean' => 'EAN',
            'upc' => 'UPC',
            'weight' => 'Weight',
            'width' => 'Width',
            'height' => 'Height',
            'depth' => 'Depth',
            'active' => 'Active',
            'stock_quantity' => 'Stock Quantity',
            'min_quantity' => 'Minimum Quantity',
            'low_stock_alert' => 'Low Stock Alert',
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'seo_keywords' => 'SEO Keywords',
            'slug' => 'URL Slug',
            'availability_text' => 'Availability Text',
            'backorder_text' => 'Backorder Text',
        ];
    }
}
