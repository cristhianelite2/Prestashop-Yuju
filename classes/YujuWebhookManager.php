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

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuOrderManager.php';
require_once dirname(__FILE__) . '/YujuProductManager.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuWebhookManager
{
    private $api_client;
    private $logger;
    private $order_manager;
    private $product_manager;
    private $webhook_secret;

    public function __construct()
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->order_manager = new YujuOrderManager();
        $this->product_manager = new YujuProductManager();
        $this->webhook_secret = YujuConfig::get('YUJU_WEBHOOK_SECRET');
    }

    /**
     * Process incoming webhook.
     */
    public function processWebhook($payload, $headers)
    {
        $this->logger->log('Processing webhook with payload length: ' . strlen($payload), 'info');

        /** @var int|false|null $webhook_id */
        $webhook_id = null;

        try {
            // Verify webhook signature (solo si hay payload y secret configurado)
            if (!empty($payload) && !$this->verifyWebhookSignature($payload, $headers)) {
                throw new Exception('Invalid webhook signature');
            }

            // Parse webhook data
            // Yuju webhooks pueden venir sin body (solo headers)
            $webhook_data = [];
            
            if (!empty($payload)) {
                $webhook_data = json_decode($payload, true);
                if (!$webhook_data) {
                    $this->logger->log('Invalid JSON payload, will use headers only', 'warning');
                    $webhook_data = [];
                }
            }
            
            // Si no hay datos en el payload, construir desde headers
            if (empty($webhook_data)) {
                $webhook_data = $this->buildWebhookDataFromHeaders($headers);
                $this->logger->log('Webhook data built from headers: ' . json_encode($webhook_data), 'info');
            }
            
            // Para webhooks de órdenes, hacer fetch automático de los detalles completos
            $topic = $webhook_data['topic'] ?? null;
            if ($topic && strpos($topic, 'order') !== false) {
                $this->logger->log('Order webhook detected, fetching full order details', 'info');
                $webhook_data = $this->enrichOrderWebhookData($webhook_data, $headers);
            }

            // Log webhook reception
            $webhook_id = $this->logWebhookReception($webhook_data, $headers);

            // Process webhook based on topic from headers
            $result = $this->processWebhookEvent($webhook_data, $headers);

            // Update webhook log with result
            $this->updateWebhookLog($webhook_id, $result);
            
            // Incluir los datos enriquecidos en el resultado para guardarlos en storage
            $result['enriched_data'] = $webhook_data;

            return $result;
        } catch (Exception $e) {
            $this->logger->log('Webhook processing failed: ' . $e->getMessage(), 'error');

            // Log failed webhook if webhook_id was successfully created
            if (is_numeric($webhook_id) && $webhook_id > 0) {
                $this->updateWebhookLog($webhook_id, [
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Verify webhook signature.
     */
    protected function verifyWebhookSignature($payload, $headers)
    {
        if (empty($this->webhook_secret)) {
            $this->logger->log('Webhook secret not configured, skipping signature verification', 'warning');

            return true;
        }

        $signature_header = null;

        // Look for signature in headers (case-insensitive)
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-yuju-signature') {
                $signature_header = $value;
                break;
            }
        }

        if (!$signature_header) {
            $this->logger->log('No signature header found in webhook', 'error');

            return false;
        }

        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $payload, $this->webhook_secret);

        // Compare signatures
        return hash_equals($expected_signature, $signature_header);
    }

    /**
     * Build webhook data structure from Yuju headers.
     * Yuju webhooks send all data in headers, not in body.
     */
    protected function buildWebhookDataFromHeaders($headers)
    {
        $data = [
            'event' => null,
            'topic' => null,
            'resource_id' => null,
            'id' => null,
        ];
        
        // Extraer headers de Yuju (case-insensitive)
        foreach ($headers as $key => $value) {
            $key_lower = strtolower($key);
            
            if ($key_lower === 'x-yuju-topic') {
                $data['topic'] = $value;
                $data['event'] = $value; // Usar topic como event también
            } elseif ($key_lower === 'x-yuju-resource') {
                $data['resource_id'] = $value;
                $data['id'] = $value; // Usar resource como id
            } elseif ($key_lower === 'x-yuju-id') {
                $data['webhook_id'] = $value;
            } elseif ($key_lower === 'x-yuju-id-account') {
                $data['account_id'] = $value;
            } elseif ($key_lower === 'x-yuju-id-shop') {
                $data['shop_id'] = $value;
            } elseif ($key_lower === 'x-yuju-id-channel') {
                $data['channel_id'] = $value;
            } elseif ($key_lower === 'x-yuju-attempts') {
                $data['attempts'] = (int)$value;
            } elseif ($key_lower === 'x-yuju-received') {
                $data['received_at'] = $value;
            } elseif ($key_lower === 'x-yuju-send') {
                $data['sent_at'] = $value;
            } elseif ($key_lower === 'x-yuju-sku') {
                $data['sku'] = $value;
            } elseif ($key_lower === 'x-yuju-sku-simple') {
                $data['sku_simple'] = $value;
            } elseif ($key_lower === 'x-yuju-id-parent') {
                $data['parent_id'] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Enriquecer datos de webhook de orden haciendo fetch a la API.
     * Obtiene los detalles completos de la orden desde Yuju API.
     * 
     * @param array $webhook_data Datos básicos del webhook
     * @param array $headers Headers del webhook
     * @return array Datos enriquecidos con información completa de la orden
     */
    protected function enrichOrderWebhookData($webhook_data, $headers)
    {
        try {
            $order_id = $webhook_data['resource_id'] ?? $webhook_data['id'] ?? null;
            $channel_id = $webhook_data['channel_id'] ?? null;
            
            if (!$order_id) {
                $this->logger->log('No order ID found in webhook data, cannot fetch details', 'warning');
                return $webhook_data;
            }
            
            $this->logger->log('Fetching order details from API', [
                'order_id' => $order_id,
                'channel_id' => $channel_id
            ]);
            
            // Hacer petición a la API de Yuju para obtener detalles completos
            $api_response = $this->api_client->getOrder($order_id, $channel_id);
            
            if (isset($api_response['success']) && $api_response['success'] === false) {
                $this->logger->log('API returned error fetching order', [
                    'order_id' => $order_id,
                    'error' => $api_response['message'] ?? 'Unknown error'
                ]);
                
                // Agregar info de que el fetch falló pero mantener datos básicos
                $webhook_data['fetch_status'] = 'failed';
                $webhook_data['fetch_error'] = $api_response['message'] ?? 'Unknown error';
                return $webhook_data;
            }
            
            // Si la API retorna los datos directamente (sin wrapper success/data)
            // Yuju puede retornar directamente el objeto de la orden
            $order_details = $api_response;
            
            // Si viene en un wrapper "data"
            if (isset($api_response['data'])) {
                $order_details = $api_response['data'];
            }
            
            $this->logger->log('Successfully fetched order details from API', [
                'order_id' => $order_id,
                'has_items' => isset($order_details['items']),
                'items_count' => isset($order_details['items']) ? count($order_details['items']) : 0
            ]);
            
            // Combinar datos básicos del webhook con los detalles completos
            $enriched_data = array_merge($webhook_data, [
                'order_details' => $order_details,
                'fetch_status' => 'success',
                'fetch_timestamp' => date('Y-m-d H:i:s'),
                
                // Mantener campos importantes en el nivel raíz para compatibilidad
                'id_order' => $order_details['id_order'] ?? $order_id,
                'reference' => $order_details['reference'] ?? null,
                'status' => $order_details['status'] ?? null,
                'items' => $order_details['items'] ?? [],
                'customer' => $order_details['customer'] ?? null,
                'shipping_address' => $order_details['shipping_address'] ?? null,
                'billing_address' => $order_details['billing_address'] ?? null,
                'total' => $order_details['total'] ?? null,
                'currency' => $order_details['currency'] ?? null,
            ]);
            
            return $enriched_data;
            
        } catch (Exception $e) {
            $this->logger->log('Exception fetching order details from API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, retornar datos básicos con indicador de fallo
            $webhook_data['fetch_status'] = 'exception';
            $webhook_data['fetch_error'] = $e->getMessage();
            return $webhook_data;
        }
    }

    /**
     * Process webhook event based on type.
     */
    protected function processWebhookEvent($webhook_data, $headers)
    {
        // Obtener el topic desde los headers de Yuju
        $topic = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-yuju-topic') {
                $topic = $value;
                break;
            }
        }

        if (!$topic) {
            throw new Exception('No x-yuju-topic header found in webhook');
        }

        $this->logger->log('Processing webhook topic: ' . $topic, 'info');

        // Mapear topics de Yuju a tipos de entidad
        // Topics: new-order, updated-order, new-std-order, updated-std-order, 
        //         product-created, product-deleted, etc.
        
        if (strpos($topic, 'order') !== false) {
            // Todos los topics relacionados con órdenes
            return $this->processOrderWebhook($webhook_data, $topic);
        } elseif (strpos($topic, 'product') !== false) {
            // Todos los topics relacionados con productos
            return $this->processProductWebhook($webhook_data, $topic);
        } elseif (strpos($topic, 'stock') !== false) {
            return $this->processStockWebhook($webhook_data, $topic);
        } elseif (strpos($topic, 'price') !== false) {
            return $this->processPriceWebhook($webhook_data, $topic);
        } elseif (strpos($topic, 'category') !== false || strpos($topic, 'categorizer') !== false) {
            return $this->processCategoryWebhook($webhook_data, $topic);
        } else {
            // Para otros topics que no procesamos aún, retornar éxito con log
            $this->logger->log('Webhook topic not implemented yet: ' . $topic . '. Payload stored but not processed.', 'warning');
            return [
                'success' => true,
                'message' => 'Webhook received but topic not implemented yet',
                'topic' => $topic,
                'stored' => true,
            ];
        }
    }

    /**
     * Process order webhook.
     */
    protected function processOrderWebhook($webhook_data, $topic)
    {
        return $this->order_manager->processWebhookOrder($webhook_data, $topic);
    }

    /**
     * Process product webhook.
     */
    protected function processProductWebhook($webhook_data, $topic = null)
    {
        $event_type = $webhook_data['event'];
        $product_data = $webhook_data['data'];

        switch ($event_type) {
            case 'product.created':
            case 'product.updated':
                return $this->product_manager->syncSingleProductFromYuju($product_data, true);

            case 'product.deleted':
                return $this->handleProductDeletion($product_data);

            default:
                throw new Exception('Unknown product webhook event: ' . $event_type);
        }
    }

    /**
     * Process stock webhook.
     */
    protected function processStockWebhook($webhook_data, $topic = null)
    {
        $stock_data = $webhook_data['data'];

        // Find PrestaShop product
        $product_id = $this->findProductByYujuId($stock_data['product_id']);

        if (!$product_id) {
            throw new Exception('Product not found for Yuju ID: ' . $stock_data['product_id']);
        }

        // Update stock
        StockAvailable::setQuantity(
            $product_id,
            0, // id_product_attribute
            (int) $stock_data['quantity']
        );

        $this->logger->log('Updated stock for product ' . $product_id . ': ' . $stock_data['quantity'], 'info');

        return [
            'success' => true,
            'product_id' => $product_id,
            'new_quantity' => $stock_data['quantity'],
        ];
    }

    /**
     * Process price webhook.
     */
    protected function processPriceWebhook($webhook_data, $topic = null)
    {
        $price_data = $webhook_data['data'];

        // Find PrestaShop product
        $product_id = $this->findProductByYujuId($price_data['product_id']);

        if (!$product_id) {
            throw new Exception('Product not found for Yuju ID: ' . $price_data['product_id']);
        }

        // Update price
        $product = new Product($product_id);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception('Product not found in PrestaShop: ' . $product_id);
        }

        $product->price = (float) $price_data['price'];

        if (isset($price_data['cost_price'])) {
            $product->wholesale_price = (float) $price_data['cost_price'];
        }

        if (!$product->save()) {
            throw new Exception('Failed to save product price');
        }

        $this->logger->log('Updated price for product ' . $product_id . ': ' . $price_data['price'], 'info');

        return [
            'success' => true,
            'product_id' => $product_id,
            'new_price' => $price_data['price'],
        ];
    }

    /**
     * Process category webhook.
     */
    protected function processCategoryWebhook($webhook_data, $topic = null)
    {
        $event_type = $webhook_data['event'];
        $category_data = $webhook_data['data'];

        switch ($event_type) {
            case 'category.created':
            case 'category.updated':
                return $this->syncCategoryFromWebhook($category_data);

            case 'category.deleted':
                return $this->handleCategoryDeletion($category_data);

            default:
                throw new Exception('Unknown category webhook event: ' . $event_type);
        }
    }

    /**
     * Handle product deletion.
     */
    protected function handleProductDeletion($product_data)
    {
        $product_id = $this->findProductByYujuId($product_data['id']);

        if (!$product_id) {
            return [
                'success' => true,
                'message' => 'Product not found in PrestaShop',
            ];
        }

        // Update product status instead of deleting
        $this->product_manager->updateProductStatus($product_id, 'disabled', 'Product deleted in Yuju');

        $this->logger->log('Disabled product due to Yuju deletion: ' . $product_id, 'info');

        return [
            'success' => true,
            'product_id' => $product_id,
            'action' => 'disabled',
        ];
    }

    /**
     * Handle category deletion.
     */
    protected function handleCategoryDeletion($category_data)
    {
        $category_mapping = $this->findCategoryMappingByYujuId($category_data['id']);

        if (!$category_mapping) {
            return [
                'success' => true,
                'message' => 'Category not found in PrestaShop',
            ];
        }

        // Disable category mapping instead of deleting
        Db::getInstance()->update(
            'yuju_category_mapping',
            ['sync_enabled' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $category_mapping['id']
        );

        $this->logger->log('Disabled category mapping due to Yuju deletion: ' . $category_mapping['prestashop_category_id'], 'info');

        return [
            'success' => true,
            'category_id' => $category_mapping['prestashop_category_id'],
            'action' => 'mapping_disabled',
        ];
    }

    /**
     * Sync category from webhook.
     */
    protected function syncCategoryFromWebhook($category_data)
    {
        // Check if category mapping exists
        $mapping = $this->findCategoryMappingByYujuId($category_data['id']);

        if ($mapping) {
            // Update existing category
            $category = new Category($mapping['prestashop_category_id']);

            if (Validate::isLoadedObject($category)) {
                $languages = Language::getLanguages(false);

                foreach ($languages as $language) {
                    $category->name[$language['id_lang']] = $category_data['name'];
                    $category->description[$language['id_lang']] = isset($category_data['description']) ? $category_data['description'] : '';
                }

                $category->active = isset($category_data['active']) ? (bool) $category_data['active'] : true;
                $category->update();

                return [
                    'success' => true,
                    'category_id' => $category->id,
                    'action' => 'updated',
                ];
            }
        }

        // Create new category if mapping doesn't exist
        $category = new Category();
        $category->id_parent = (int) Configuration::get('PS_HOME_CATEGORY');
        $category->active = isset($category_data['active']) ? (bool) $category_data['active'] : true;

        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            $category->name[$language['id_lang']] = $category_data['name'];
            $category->description[$language['id_lang']] = isset($category_data['description']) ? $category_data['description'] : '';
            $category->link_rewrite[$language['id_lang']] = Tools::link_rewrite($category_data['name']);
        }

        if ($category->add()) {
            // Create category mapping
            Db::getInstance()->insert('yuju_category_mapping', [
                'prestashop_category_id' => $category->id,
                'yuju_category_id' => pSQL($category_data['id']),
                'yuju_category_name' => pSQL($category_data['name']),
                'sync_enabled' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'category_id' => $category->id,
                'action' => 'created',
            ];
        }

        throw new Exception('Failed to create category from webhook');
    }

    /**
     * Log webhook reception.
     *
     * @return int|false The inserted webhook log ID or false on failure
     */
    protected function logWebhookReception($webhook_data, $headers)
    {
        // Obtener event_type desde headers o webhook_data
        $event_type = 'unknown';
        if (isset($headers['x-yuju-topic'])) {
            $event_type = $headers['x-yuju-topic'];
        } elseif (isset($webhook_data['event'])) {
            $event_type = $webhook_data['event'];
        }
        
        $log_data = [
            'event_type' => pSQL($event_type),
            'entity_id' => isset($webhook_data['data']['id']) ? pSQL($webhook_data['data']['id']) : (isset($webhook_data['id_order']) ? pSQL($webhook_data['id_order']) : ''),
            'payload' => pSQL(json_encode($webhook_data)),
            'headers' => pSQL(json_encode($headers)),
            'status' => 'processing',
            'received_at' => date('Y-m-d H:i:s'),
        ];

        Db::getInstance()->insert('yuju_webhook_logs', $log_data);

        return Db::getInstance()->Insert_ID();
    }

    /**
     * Update webhook log with processing result.
     */
    protected function updateWebhookLog($webhook_id, $result)
    {
        $update_data = [
            'status' => $result['success'] ? 'processed' : 'failed',
            'response' => pSQL(json_encode($result)),
            'processed_at' => date('Y-m-d H:i:s'),
        ];

        if (!$result['success'] && isset($result['error'])) {
            $update_data['error_message'] = pSQL($result['error']);
        }

        Db::getInstance()->update('yuju_webhook_logs', $update_data, 'id = ' . (int) $webhook_id);
    }

    /**
     * Register webhooks with Yuju.
     */
    public function registerWebhooks()
    {
        $webhook_url = $this->getWebhookUrl();

        $webhooks_to_register = [
            'order.created',
            'order.updated',
            'order.status_changed',
            'order.cancelled',
            'product.created',
            'product.updated',
            'product.deleted',
            'stock.updated',
            'price.updated',
            'category.created',
            'category.updated',
            'category.deleted',
        ];

        $registered_webhooks = [];

        foreach ($webhooks_to_register as $event_type) {
            try {
                $webhook_data = [
                    'url' => $webhook_url,
                    'event' => $event_type,
                    'active' => true,
                ];

                $response = $this->api_client->registerWebhook($webhook_data);

                if ($response && isset($response['id'])) {
                    $registered_webhooks[] = [
                        'event_type' => $event_type,
                        'webhook_id' => $response['id'],
                        'status' => 'registered',
                    ];

                    // Store webhook registration
                    $this->storeWebhookRegistration($event_type, $response['id']);
                }
            } catch (Exception $e) {
                $this->logger->log('Failed to register webhook for ' . $event_type . ': ' . $e->getMessage(), 'error');

                $registered_webhooks[] = [
                    'event_type' => $event_type,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $registered_webhooks;
    }

    /**
     * Unregister webhooks from Yuju.
     */
    public function unregisterWebhooks()
    {
        $registered_webhooks = $this->getRegisteredWebhooks();
        $unregistered_count = 0;

        foreach ($registered_webhooks as $webhook) {
            try {
                $this->api_client->unregisterWebhook($webhook['yuju_webhook_id']);

                // Remove from database
                Db::getInstance()->delete(
                    'yuju_webhook_registrations',
                    'id = ' . (int) $webhook['id']
                );

                ++$unregistered_count;
            } catch (Exception $e) {
                $this->logger->log('Failed to unregister webhook ' . $webhook['yuju_webhook_id'] . ': ' . $e->getMessage(), 'error');
            }
        }

        return $unregistered_count;
    }

    /**
     * Get webhook URL.
     */
    protected function getWebhookUrl()
    {
        $shop_url = Configuration::get('PS_SHOP_DOMAIN');
        $ssl = Configuration::get('PS_SSL_ENABLED');

        $protocol = $ssl ? 'https://' : 'http://';

        return $protocol . $shop_url . '/modules/prestashopyuju/webhook.php';
    }

    /**
     * Store webhook registration.
     */
    protected function storeWebhookRegistration($event_type, $webhook_id)
    {
        return Db::getInstance()->insert('yuju_webhook_registrations', [
            'event_type' => pSQL($event_type),
            'yuju_webhook_id' => pSQL($webhook_id),
            'webhook_url' => pSQL($this->getWebhookUrl()),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get registered webhooks.
     */
    protected function getRegisteredWebhooks()
    {
        return Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_webhook_registrations
            WHERE is_active = 1
        ');
    }

    /**
     * Helper methods.
     */
    protected function findProductByYujuId($yuju_product_id)
    {
        $mapping = Db::getInstance()->getRow('
            SELECT prestashop_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status
            WHERE yuju_product_id = "' . pSQL($yuju_product_id) . '"
        ');

        return $mapping ? $mapping['prestashop_product_id'] : null;
    }

    protected function findCategoryMappingByYujuId($yuju_category_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping
            WHERE yuju_category_id = "' . pSQL($yuju_category_id) . '"
        ');
    }

    /**
     * Get webhook statistics.
     */
    public function getWebhookStats()
    {
        $stats = [];

        // Total webhooks received
        $stats['total_received'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs
        ');

        // Webhooks by status
        $stats['by_status'] = Db::getInstance()->executeS('
            SELECT status, COUNT(*) as count
            FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs
            GROUP BY status
        ');

        // Webhooks by event type
        $stats['by_event_type'] = Db::getInstance()->executeS('
            SELECT event_type, COUNT(*) as count
            FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs
            GROUP BY event_type
            ORDER BY `count` DESC
        ');

        // Recent webhooks
        $stats['recent_webhooks'] = Db::getInstance()->executeS('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs
            ORDER BY `received_at` DESC
            LIMIT 10
        ');

        // Registered webhooks
        $stats['registered_webhooks'] = $this->getRegisteredWebhooks();

        return $stats;
    }

    /**
     * Clean old webhook logs.
     */
    public function cleanOldWebhookLogs($days = 30)
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));

        return Db::getInstance()->delete(
            'yuju_webhook_logs',
            'received_at < "' . pSQL($cutoff_date) . '"'
        );
    }
}
