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
            'YUJU_BATCH_SIZE' => 100,
            'YUJU_BATCH_FREQUENCY' => 60, // seconds
            'YUJU_MAX_DAILY_SYNCS' => 2, // products-offer-report: máx. cada 12h (= 2/día)
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
            // Reportar progreso de creación de órdenes a Yuju (orders/outbounds)
            'YUJU_ORDER_OUTBOUND_ENABLED' => 1,
            // JSON: { "13": "mercadolibre", "4301": "shopify", ... }
            'YUJU_CHANNEL_MARKETPLACE_MAP' => '{}',

            // Offer audit settings (PS is source of truth → push fixes to Yuju only)
            'YUJU_AUDIT_ENABLED' => 0,
            'YUJU_AUDIT_UI_ENABLED' => 1,
            'YUJU_AUDIT_STOCK' => 1,
            'YUJU_AUDIT_PRICE' => 1,
            'YUJU_AUDIT_IMAGES' => 0,
            'YUJU_AUDIT_SCHEDULE' => 'daily',
            'YUJU_AUDIT_TIMES_PER_PERIOD' => 1,
            'YUJU_AUDIT_LAST_RUN_AT' => '',
            'YUJU_AUDIT_CHUNK_SIZE' => 200,
            // Segundos máx. de trabajo por petición AJAX (varios lotes internos)
            'YUJU_AUDIT_REQUEST_BUDGET' => 25,

            // Category Bulk: botón masivo «Enviar de nuevo» (≥1h sin webhook). Off por defecto.
            'YUJU_ENABLE_BULK_RESEND_PENDING' => 0,

            // products-gral-report (info general + imágenes): máx. 2/día UTC — docs Yuju
            'YUJU_GRAL_REPORT_ENABLED' => 0,
            'YUJU_GRAL_REPORT_MAX_DAILY' => 2,
            // Días ISO-8601: 1=lun … 7=dom (coma-separados). Por defecto todos.
            'YUJU_GRAL_REPORT_WEEKDAYS' => '1,2,3,4,5,6,7',
            'YUJU_GRAL_REPORT_LAST_REQUEST_AT' => '',
            'YUJU_GRAL_REPORT_LAST_COMPLETED_AT' => '',

            // products-offer-report (sku/stock/precio): cada 12h — docs Yuju
            'YUJU_OFFER_REPORT_LAST_REQUEST_AT' => '',
            'YUJU_OFFER_REPORT_LAST_COMPLETED_AT' => '',
            'YUJU_OFFER_REPORT_UNLOCK_AT' => '',
            // Cache GET /account (cuenta, tienda, canales)
            'YUJU_ACCOUNT_INFO_CACHE' => '',
            'YUJU_ACCOUNT_INFO_UPDATED_AT' => '',

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
     * Uses ps_yuju_configuration table instead of PrestaShop's Configuration
     */
    public static function get($key, $default = null)
    {
        try {
            $sql = 'SELECT `config_value`, `config_type` 
                    FROM `' . _DB_PREFIX_ . 'yuju_configuration` 
                    WHERE `config_key` = \'' . pSQL($key) . '\'';
            
            $result = Db::getInstance()->getRow($sql);
            
            if (!$result) {
                // Fallback to defaults
                $defaults = self::getDefaults();
                return isset($defaults[$key]) ? $defaults[$key] : $default;
            }
            
            // Convert value based on type
            $value = $result['config_value'];
            $type = $result['config_type'];
            
            switch ($type) {
                case 'integer':
                    return (int)$value;
                case 'boolean':
                    return (bool)$value;
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        } catch (Exception $e) {
            error_log('[YujuConfig] Error getting config key "' . $key . '": ' . $e->getMessage());
            $defaults = self::getDefaults();
            return isset($defaults[$key]) ? $defaults[$key] : $default;
        }
    }

    /**
     * Set configuration value.
     * Uses ps_yuju_configuration table instead of PrestaShop's Configuration
     */
    public static function set($key, $value, $type = 'string', $description = null)
    {
        try {
            // Convert value to string based on type
            $stringValue = $value;
            
            switch ($type) {
                case 'integer':
                    $stringValue = (string)(int)$value;
                    break;
                case 'boolean':
                    $stringValue = $value ? '1' : '0';
                    break;
                case 'json':
                    $stringValue = json_encode($value);
                    break;
            }
            
            // Check if key exists
            $exists = Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_configuration` 
                 WHERE `config_key` = \'' . pSQL($key) . '\''
            );
            
            if ($exists) {
                // Update existing
                return Db::getInstance()->update(
                    'yuju_configuration',
                    array(
                        'config_value' => pSQL($stringValue),
                        'config_type' => pSQL($type),
                        'updated_at' => date('Y-m-d H:i:s')
                    ),
                    '`config_key` = \'' . pSQL($key) . '\''
                );
            } else {
                // Insert new
                return Db::getInstance()->insert(
                    'yuju_configuration',
                    array(
                        'config_key' => pSQL($key),
                        'config_value' => pSQL($stringValue),
                        'config_type' => pSQL($type),
                        'description' => $description ? pSQL($description) : null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    )
                );
            }
        } catch (Exception $e) {
            error_log('[YujuConfig] Error setting config key "' . $key . '": ' . $e->getMessage());
            return false;
        }
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
            self::set($key, $value, 'string');
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
                self::set($key, $value, 'string');
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
     * Estados oficiales documentados por Yuju.
     *
     * @see https://api-docs.yuju.io/docs/estados-de-un-pedido
     *
     * @return array<string, string> code => label
     */
    public static function getDocumentedYujuOrderStatuses()
    {
        return [
            'paid' => 'Pagado',
            'ready_to_ship' => 'Confirmada / Lista para enviar',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'canceled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'with_mediation' => 'Con mediador',
        ];
    }

    /**
     * Estados oficiales Yuju (docs) + aliases que llegan en webhooks/API.
     *
     * @return array<string, string>
     */
    public static function getOfficialYujuOrderStatuses()
    {
        return array_merge(self::getDocumentedYujuOrderStatuses(), [
            // Aliases / estados frecuentes en payloads
            'cancelled' => 'Cancelado (alias UK)',
            'pending' => 'Pendiente / Pago pendiente',
            'open' => 'Abierta',
            'processing' => 'En proceso',
            'error' => 'Error',
        ]);
    }

    /**
     * Defaults Yuju → PrestaShop usando constantes PS_OS_* (fuente para seed UI / OrderManager).
     * Incluye todos los estados documentados + aliases.
     *
     * @return array<string, int>
     */
    public static function getDefaultYujuToPsMappings()
    {
        $payment = (int) Configuration::get('PS_OS_PAYMENT');
        $preparation = (int) Configuration::get('PS_OS_PREPARATION');
        $shipping = (int) Configuration::get('PS_OS_SHIPPING');
        $delivered = (int) Configuration::get('PS_OS_DELIVERED');
        $canceled = (int) Configuration::get('PS_OS_CANCELED');
        $refund = (int) Configuration::get('PS_OS_REFUND');
        $error = (int) Configuration::get('PS_OS_ERROR');
        $cheque = (int) Configuration::get('PS_OS_CHEQUE');
        if ($cheque <= 0) {
            $cheque = $payment > 0 ? $payment : $preparation;
        }

        return [
            // Documentados (api-docs.yuju.io/docs/estados-de-un-pedido)
            'paid' => $payment,
            'ready_to_ship' => $preparation,
            'shipped' => $shipping,
            'delivered' => $delivered,
            'canceled' => $canceled,
            'refunded' => $refund,
            'with_mediation' => $error > 0 ? $error : $preparation,
            // Aliases
            'cancelled' => $canceled,
            'pending' => $cheque,
            'open' => $cheque,
            'processing' => $preparation,
            'error' => $error > 0 ? $error : $preparation,
        ];
    }

    /**
     * PrestaShop to Yuju order status mapping.
     */
    public static function getOrderStatusMapping()
    {
        $reverse = [];
        foreach (self::getDefaultYujuToPsMappings() as $yuju => $ps) {
            if ($ps > 0 && !isset($reverse[$ps])) {
                $reverse[$ps] = $yuju;
            }
        }

        return $reverse;
    }

    /**
     * Yuju to PrestaShop order status mapping (defaults).
     */
    public static function getYujuOrderStatusMapping()
    {
        return self::getDefaultYujuToPsMappings();
    }

    /**
     * Mapa por defecto id_channel → slug de marketplace (email {ref}@{slug}.com).
     *
     * @return array<string, string>
     */
    public static function getDefaultChannelMarketplaceMap()
    {
        return [
            '13' => 'mercadolibre',
            '1901' => 'shopify',
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
