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
require_once dirname(__FILE__) . '/YujuOrderOutbound.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuOrderManager
{
    private $api_client;
    private $logger;
    private $status_mapping;
    private $context;

    /** @var array<string,int> Contador de locks anidados por yuju_order_id (GET_LOCK no es reentrante) */
    private $orderLocksHeld = [];

    /**
     * Cuando true, hookActionUpdateQuantity no vuelve a empujar a Yuju
     * (el push lo hace explícitamente applyOrderStockDecrementAndPushToYuju).
     *
     * @var bool
     */
    public static $suppressStockHookToYuju = false;

    /** @var bool */
    private $orderMappingSchemaReady = false;

    public function __construct($context = null)
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->context = $context ?: Context::getContext();
        $this->loadStatusMapping();
    }

    /**
     * Lock público para serializar todo el pipeline del webhook (enrich + create/update).
     *
     * @param string|int $yuju_order_id
     * @param int $timeoutSeconds
     * @return bool
     */
    public function acquireOrderLockPublic($yuju_order_id, $timeoutSeconds = 45)
    {
        return $this->acquireOrderLock($yuju_order_id, $timeoutSeconds);
    }

    /**
     * @param string|int $yuju_order_id
     * @return void
     */
    public function releaseOrderLockPublic($yuju_order_id)
    {
        $this->releaseOrderLock($yuju_order_id);
    }

    /**
     * Nombre corto y estable para MySQL GET_LOCK (máx. 64 chars).
     *
     * @param string|int $yuju_order_id
     * @return string
     */
    protected function orderLockName($yuju_order_id)
    {
        return 'yuju_ord_' . substr(md5((string) $yuju_order_id), 0, 40);
    }

    /**
     * Serializa creación/actualización de la misma orden Yuju entre requests concurrentes.
     *
     * @param string|int $yuju_order_id
     * @param int $timeoutSeconds
     * @return bool
     */
    protected function acquireOrderLock($yuju_order_id, $timeoutSeconds = 45)
    {
        $key = (string) $yuju_order_id;
        if ($key === '') {
            return false;
        }
        if (!empty($this->orderLocksHeld[$key])) {
            $this->orderLocksHeld[$key]++;
            return true;
        }

        $lockName = $this->orderLockName($key);
        $got = (int) Db::getInstance()->getValue(
            'SELECT GET_LOCK("' . pSQL($lockName) . '", ' . (int) $timeoutSeconds . ')'
        );
        if ($got === 1) {
            $this->orderLocksHeld[$key] = 1;
            return true;
        }

        $this->logger->log('Could not acquire order lock for Yuju ID ' . $key . ' (result=' . $got . ')', 'warning');
        return false;
    }

    /**
     * @param string|int $yuju_order_id
     * @return void
     */
    protected function releaseOrderLock($yuju_order_id)
    {
        $key = (string) $yuju_order_id;
        if ($key === '' || empty($this->orderLocksHeld[$key])) {
            return;
        }
        $this->orderLocksHeld[$key]--;
        if ($this->orderLocksHeld[$key] > 0) {
            return;
        }
        unset($this->orderLocksHeld[$key]);
        $lockName = $this->orderLockName($key);
        Db::getInstance()->execute('SELECT RELEASE_LOCK("' . pSQL($lockName) . '")');
    }

    /**
     * Placeholder negativo único por yuju_order_id (claim pendiente).
     * Nunca usamos NULL: funciona con columnas NOT NULL y UNIQUE.
     *
     * @param string $yuju_order_id
     * @return int < 0
     */
    protected function pendingMappingPlaceholder($yuju_order_id)
    {
        $h = sprintf('%u', crc32((string) $yuju_order_id));
        $n = (int) ($h % 2000000000);
        if ($n <= 0) {
            $n = 1;
        }
        return -1 * $n;
    }

    /**
     * ¿El valor de mapping es claim pendiente (aún sin orden PS real)?
     *
     * @param mixed $prestashop_order_id
     * @return bool
     */
    protected function isPendingMappingPsId($prestashop_order_id)
    {
        if ($prestashop_order_id === null || $prestashop_order_id === '') {
            return true;
        }
        return (int) $prestashop_order_id <= 0;
    }

    /**
     * Normaliza filas legacy con prestashop_order_id = 0 (rompe UNIQUE si hay varias).
     * No escribe NULL nunca.
     *
     * @return void
     */
    protected function ensureOrderMappingSchema()
    {
        if ($this->orderMappingSchemaReady) {
            return;
        }
        $this->orderMappingSchemaReady = true;

        try {
            $table = _DB_PREFIX_ . 'yuju_order_mapping';
            $this->ensureOrderMappingOutboundColumns($table);

            $rows = Db::getInstance()->executeS(
                'SELECT `id`, `yuju_order_id` FROM `' . $table . '`
                 WHERE `prestashop_order_id` IS NULL OR `prestashop_order_id` = 0'
            );
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $row) {
                $yujuId = isset($row['yuju_order_id']) ? (string) $row['yuju_order_id'] : '';
                if ($yujuId === '') {
                    continue;
                }
                $placeholder = (int) $this->pendingMappingPlaceholder($yujuId);
                Db::getInstance()->execute(
                    'UPDATE `' . $table . '`
                     SET `prestashop_order_id` = ' . $placeholder . ',
                         `updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"
                     WHERE `id` = ' . (int) $row['id'] . '
                     AND (`prestashop_order_id` IS NULL OR `prestashop_order_id` = 0)'
                );
            }
        } catch (Exception $e) {
            $this->logger->log('ensureOrderMappingSchema failed: ' . $e->getMessage(), 'warning');
        } catch (Throwable $e) {
            $this->logger->log('ensureOrderMappingSchema failed: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Asegura columnas outbound en yuju_order_mapping (tiendas ya instaladas).
     *
     * @param string $table full table name with prefix
     * @return void
     */
    protected function ensureOrderMappingOutboundColumns($table)
    {
        $needed = [
            'id_channel' => 'varchar(64) DEFAULT NULL',
            'outbound_external_pk' => 'varchar(64) DEFAULT NULL',
            'outbound_status' => 'varchar(32) DEFAULT NULL',
            'outbound_updated_at' => 'datetime DEFAULT NULL',
        ];
        foreach ($needed as $col => $definition) {
            try {
                $exists = Db::getInstance()->executeS(
                    'SHOW COLUMNS FROM `' . pSQL($table) . '` LIKE "' . pSQL($col) . '"'
                );
                if (!empty($exists)) {
                    continue;
                }
                // pSQL no es ideal para identificadores; validar nombre de columna
                if (!preg_match('/^[a-z0-9_]+$/i', $col)) {
                    continue;
                }
                Db::getInstance()->execute(
                    'ALTER TABLE `' . $table . '` ADD COLUMN `' . $col . '` ' . $definition
                );
            } catch (Exception $e) {
                $this->logger->log(
                    'ensureOrderMappingOutboundColumns ' . $col . ': ' . $e->getMessage(),
                    'warning'
                );
            } catch (Throwable $e) {
                $this->logger->log(
                    'ensureOrderMappingOutboundColumns ' . $col . ': ' . $e->getMessage(),
                    'warning'
                );
            }
        }
    }

    /**
     * Load order status mapping configuration.
     */
    protected function loadStatusMapping()
    {
        $defaults = YujuStatusMappings::getDefaultYujuToPsMappings();
        $this->status_mapping = [
            'ps_to_yuju' => [],
            'yuju_to_ps' => [],
        ];

        foreach ($defaults as $yuju_status => $ps_status_id) {
            $ps_status_id = (int) $ps_status_id;
            if ($ps_status_id <= 0) {
                continue;
            }
            $this->status_mapping['yuju_to_ps'][$yuju_status] = (string) $ps_status_id;
            if (!isset($this->status_mapping['ps_to_yuju'][(string) $ps_status_id])) {
                $this->status_mapping['ps_to_yuju'][(string) $ps_status_id] = $yuju_status;
            }
        }

        // Custom DB mappings override defaults (solo activos)
        try {
            $custom_mappings = Db::getInstance()->executeS('
                SELECT prestashop_status_id, yuju_status_name
                FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping
                WHERE is_active = 1
            ');
        } catch (Exception $e) {
            $custom_mappings = [];
        }

        if (is_array($custom_mappings)) {
            foreach ($custom_mappings as $mapping) {
                $ps_id = (string) (int) $mapping['prestashop_status_id'];
                $yuju_name = (string) $mapping['yuju_status_name'];
                if ($yuju_name === '' || (int) $ps_id <= 0) {
                    continue;
                }
                $this->status_mapping['ps_to_yuju'][$ps_id] = $yuju_name;
                $this->status_mapping['yuju_to_ps'][$yuju_name] = $ps_id;
            }
        }
    }

    /**
     * Expone la creación/obtención del carrier "Yuju" (install/upgrade).
     *
     * @return int
     */
    public function ensureYujuCarrierPublic()
    {
        return (int) $this->getCarrierId('Yuju');
    }

    /**
     * Process webhook order data.
     */
    public function processWebhookOrder($webhook_data, $topic = null)
    {
        $this->logger->log('Processing webhook order with topic: ' . $topic, 'info');

        try {
            // Los datos ya vienen enriquecidos desde YujuWebhookManager
            // Obtener datos de la orden desde order_details si existen (enriquecidos)
            // o desde el nivel raíz si vienen directamente
            $order_data = isset($webhook_data['order_details']) ? $webhook_data['order_details'] : $webhook_data;
            
            // Si hay un wrapper "data", extraerlo
            if (isset($order_data['data']) && is_array($order_data['data'])) {
                $order_data = $order_data['data'];
            }
            
            // Determinar el tipo de evento basado en el topic
            // Topics: new-order, updated-order, new-std-order, updated-std-order
            $event_type = $topic ?? (isset($webhook_data['event']) ? $webhook_data['event'] : null);
            
            if (!$event_type) {
                throw new Exception('No event type or topic found in webhook data');
            }
            
            $this->logger->log('Order webhook event/topic: ' . $event_type, 'info');
            
            // Log si los datos fueron enriquecidos con fetch
            if (isset($webhook_data['fetch_status'])) {
                $this->logger->log('Order data fetch status: ' . $webhook_data['fetch_status'], 'info');
            }

            // Mapear topics de Yuju a acciones
            if (strpos($event_type, 'new-order') !== false || strpos($event_type, 'new-std-order') !== false || $event_type === 'order.created') {
                // Para nuevas órdenes, intentar crear automáticamente con force=false (validar duplicados)
                $this->logger->log('Attempting to create order automatically from webhook', 'info');
                
                try {
                    $creation_result = $this->createOrderFromYuju($order_data, false);
                    
                    // Agregar información adicional al resultado
                    $creation_result['auto_created'] = true;
                    $creation_result['webhook_topic'] = $event_type;
                    
                    $this->logger->log('Order creation result: ' . ($creation_result['success'] ? 'SUCCESS' : 'FAILED'), 'info');
                    
                    return $creation_result;
                } catch (Exception $e) {
                    // Si falla la creación, retornar error pero no lanzar excepción
                    $this->logger->log('Order creation failed: ' . $e->getMessage(), 'error');
                    
                    return [
                        'success' => false,
                        'auto_created' => true,
                        'webhook_topic' => $event_type,
                        'message' => 'Failed to create order from webhook',
                        'error' => $e->getMessage(),
                        'details' => [
                            'exception' => $e->getMessage(),
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine(),
                        ]
                    ];
                }
            } elseif (strpos($event_type, 'updated-order') !== false || strpos($event_type, 'updated-std-order') !== false || $event_type === 'order.updated') {
                return $this->updateOrderFromYuju($order_data);
            } elseif ($event_type === 'order.status_changed') {
                return $this->updateOrderStatus($order_data);
            } elseif ($event_type === 'order.cancelled') {
                return $this->cancelOrder($order_data);
            } else {
                // Para topics que no procesamos aún, retornar éxito con log
                $this->logger->log('Order webhook topic not implemented yet: ' . $event_type . '. Data received but not processed.', 'warning');
                return [
                    'success' => true,
                    'message' => 'Order webhook received but not processed yet',
                    'topic' => $event_type,
                    'order_id' => isset($order_data['id']) ? $order_data['id'] : 'unknown',
                ];
            }
        } catch (Exception $e) {
            $this->logger->log('Webhook order processing failed: ' . $e->getMessage(), 'error');
            
            // Retornar error en lugar de lanzar excepción
            return [
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
                'details' => [
                    'exception' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ]
            ];
        }
    }

    /**
     * Adapta los datos de orden de Yuju al formato esperado por PrestaShop.
     *
     * @param array $yuju_data Datos originales de Yuju (estructura normal)
     * @return array Datos adaptados
     */
    protected function adaptYujuOrderData($yuju_data)
    {
        $this->logger->log('adaptYujuOrderData - ORIGINAL DATA STRUCTURE: Has order_details=' . (isset($yuju_data['order_details']) ? 'YES' : 'NO') .
            ' | Has customer in root=' . (isset($yuju_data['customer']) ? 'YES' : 'NO') .
            ' | Has shipping_address in root=' . (isset($yuju_data['shipping_address']) ? 'YES' : 'NO'), 'debug');

        if (isset($yuju_data['order_details'])) {
            $order_details = $yuju_data['order_details'];
            $yuju_data = array_merge($order_details, [
                'customer' => $yuju_data['customer'] ?? $order_details['customer'] ?? [],
                'shipping_address' => $yuju_data['shipping_address'] ?? $order_details['shipping_address'] ?? [],
                'billing_address' => $yuju_data['billing_address'] ?? $order_details['billing_address'] ?? null,
                'items' => $yuju_data['items'] ?? $order_details['items'] ?? [],
                'id_channel' => $yuju_data['id_channel'] ?? $order_details['id_channel'] ?? null,
                'progress' => $yuju_data['progress'] ?? $order_details['progress'] ?? null,
            ]);
        }

        $country_map = [
            'México' => 'MX', 'Mexico' => 'MX',
            'Estados Unidos' => 'US', 'United States' => 'US',
            'Colombia' => 'CO', 'Chile' => 'CL',
            'Argentina' => 'AR', 'Perú' => 'PE', 'Peru' => 'PE',
            'Brasil' => 'BR', 'Brazil' => 'BR',
        ];

        $shipping = isset($yuju_data['shipping_address']) && is_array($yuju_data['shipping_address'])
            ? $yuju_data['shipping_address'] : [];
        $billing_raw = isset($yuju_data['billing_address']) && is_array($yuju_data['billing_address'])
            ? $yuju_data['billing_address'] : null;

        $country_name = isset($shipping['country']) ? $shipping['country'] : '';
        $country_code = isset($country_map[$country_name]) ? $country_map[$country_name] : 'MX';

        $customer_first_name = !empty($yuju_data['customer']['first_name']) ? trim($yuju_data['customer']['first_name']) : 'Cliente';
        $customer_last_name = !empty($yuju_data['customer']['last_name']) ? trim($yuju_data['customer']['last_name']) : 'Yuju';

        $address_first_name = $customer_first_name;
        $address_last_name = $customer_last_name;
        if (!empty($shipping['first_name']) && !empty($shipping['last_name'])) {
            $address_first_name = trim($shipping['first_name']);
            $address_last_name = trim($shipping['last_name']);
        }

        $reference = (string) ($yuju_data['reference'] ?? $yuju_data['id_order'] ?? '');
        $id_channel = $yuju_data['id_channel'] ?? null;
        $marketplace_email = $this->buildMarketplaceEmail($reference, $id_channel);

        $shipping_address = [
            'first_name' => $address_first_name,
            'last_name' => $address_last_name,
            'company' => '',
            'address_line_1' => !empty($shipping['address'])
                ? $shipping['address']
                : ((!empty($shipping['street_name']) ? $shipping['street_name'] : 'Not Found')
                    . (!empty($shipping['street_number']) ? ' ' . $shipping['street_number'] : '')),
            'address_line_2' => (!empty($shipping['neighborhood']) ? $shipping['neighborhood'] . '. ' : '')
                . (!empty($shipping['reference']) ? $shipping['reference'] : ''),
            'city' => !empty($shipping['city']) ? $shipping['city'] : 'Not Found',
            'postal_code' => !empty($shipping['postal_code']) ? $shipping['postal_code'] : '00000',
            'country_code' => $country_code,
            'state_code' => '',
            'phone' => $shipping['phone'] ?? $yuju_data['customer']['phone'] ?? '000000000',
            'dni' => $yuju_data['customer']['doc_number'] ?? ($billing_raw['taxid'] ?? '00000000'),
        ];

        $billing_address = $shipping_address;
        if ($billing_raw) {
            $billing_country = isset($billing_raw['country']) ? $billing_raw['country'] : $country_name;
            $billing_country_code = isset($country_map[$billing_country]) ? $country_map[$billing_country] : $country_code;
            $billing_line = !empty($billing_raw['address'])
                ? $billing_raw['address']
                : ((!empty($billing_raw['street_name']) ? $billing_raw['street_name'] : '')
                    . (!empty($billing_raw['street_number']) ? ' ' . $billing_raw['street_number'] : ''));
            if (trim($billing_line) !== '') {
                $billing_address = [
                    'first_name' => !empty($billing_raw['name']) ? trim($billing_raw['name']) : $address_first_name,
                    'last_name' => $address_last_name,
                    'company' => '',
                    'address_line_1' => $billing_line,
                    'address_line_2' => (!empty($billing_raw['neighborhood']) ? $billing_raw['neighborhood'] . '. ' : '')
                        . (!empty($billing_raw['reference']) ? $billing_raw['reference'] : ''),
                    'city' => !empty($billing_raw['city']) ? $billing_raw['city'] : $shipping_address['city'],
                    'postal_code' => !empty($billing_raw['postal_code']) ? $billing_raw['postal_code'] : $shipping_address['postal_code'],
                    'country_code' => $billing_country_code,
                    'state_code' => '',
                    'phone' => $billing_raw['phone'] ?? $shipping_address['phone'],
                    'dni' => $billing_raw['taxid'] ?? $shipping_address['dni'],
                ];
            }
        }

        $products_total = floatval($yuju_data['total'] ?? 0);
        $shipping_cost = floatval($yuju_data['shipping_cost'] ?? 0);
        $paid_total = isset($yuju_data['paid_total'])
            ? floatval($yuju_data['paid_total'])
            : ($products_total + $shipping_cost);

        $adapted = [
            'id' => $yuju_data['id_order'] ?? $reference ?: uniqid('yuju_'),
            'reference' => $reference,
            'id_channel' => $id_channel,
            'marketplace_slug' => $this->resolveMarketplaceSlug($id_channel),
            'status' => $yuju_data['status'] ?? 'open',
            'progress' => $yuju_data['progress'] ?? null,
            'currency' => strtoupper($yuju_data['currency'] ?? 'MXN'),
            'payment_method' => $yuju_data['payment_method'] ?? 'Yuju',
            'shipping_method' => 'Yuju',
            'created_at' => isset($yuju_data['order_created_at'])
                ? date('Y-m-d H:i:s', strtotime($yuju_data['order_created_at']))
                : date('Y-m-d H:i:s'),
            'total_amount' => $paid_total,
            'total_amount_tax_excl' => $paid_total,
            'products_total' => $products_total,
            'shipping_cost' => $shipping_cost,
            'customer' => [
                'email' => $marketplace_email,
                'first_name' => $customer_first_name,
                'last_name' => $customer_last_name,
                'phone' => $yuju_data['customer']['phone'] ?? '000000000',
                'doc_type' => $yuju_data['customer']['doc_type'] ?? null,
                'doc_number' => $yuju_data['customer']['doc_number'] ?? null,
            ],
            'shipping_address' => $shipping_address,
            'billing_address' => $billing_address,
            'items' => [],
        ];

        if (!empty($yuju_data['items']) && is_array($yuju_data['items'])) {
            foreach ($yuju_data['items'] as $item) {
                $sku = $item['sku'] ?? $item['channel_sku'] ?? $item['product_id'] ?? $item['id_item'] ?? null;
                $qty = intval($item['quantity'] ?? 1);
                $unit_price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
                $tracking = $item['tracking_code'] ?? null;
                $adapted['items'][] = [
                    'product_id' => $item['id_product'] ?? $item['product_id'] ?? null,
                    'sku' => $sku,
                    'channel_sku' => $item['channel_sku'] ?? null,
                    'name' => $item['name'] ?? $item['product_name'] ?? 'Unknown Product',
                    'quantity' => $qty,
                    'unit_price' => $unit_price,
                    'total_price' => floatval($item['total_price'] ?? $item['total'] ?? ($unit_price * $qty)),
                    'product_attribute_id' => $item['product_attribute_id'] ?? null,
                    'tracking_code' => $tracking,
                    'status' => $item['status'] ?? null,
                ];
            }
            $this->logger->log('Processed ' . count($adapted['items']) . ' items from Yuju order', 'info');
        } else {
            $this->logger->log('No items found in Yuju order data', 'warning');
        }

        $this->logger->log('Adapted Yuju order data with email ' . $marketplace_email . ' and ' . count($adapted['items']) . ' items', 'debug');

        return $adapted;
    }

    /**
     * Email sintético: {reference}@{marketplace}.com
     *
     * @param string $reference
     * @param mixed $id_channel
     * @return string
     */
    protected function buildMarketplaceEmail($reference, $id_channel)
    {
        $ref = preg_replace('/[^a-zA-Z0-9._+-]/', '', (string) $reference);
        if ($ref === '') {
            $ref = 'order' . substr(md5((string) microtime(true)), 0, 8);
        }
        // Local-part max ~64 chars for common email limits
        $ref = substr($ref, 0, 64);
        $marketplace = $this->resolveMarketplaceSlug($id_channel);

        return strtolower($ref) . '@' . $marketplace . '.com';
    }

    /**
     * Resuelve slug de marketplace desde id_channel.
     *
     * @param mixed $id_channel
     * @return string
     */
    protected function resolveMarketplaceSlug($id_channel)
    {
        $map = YujuConfig::get('YUJU_CHANNEL_MARKETPLACE_MAP', []);
        if (is_string($map)) {
            $decoded = json_decode($map, true);
            $map = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($map) || empty($map)) {
            $map = YujuStatusMappings::getDefaultChannelMarketplaceMap();
        }

        $key = (string) $id_channel;
        if ($key !== '' && isset($map[$key]) && $map[$key] !== '') {
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $map[$key]));
            if ($slug !== '') {
                return $slug;
            }
        }

        if ($key !== '' && $key !== '0') {
            $fallback = preg_replace('/[^a-z0-9]/', '', strtolower($key));

            return 'channel' . ($fallback !== '' ? $fallback : 'yuju');
        }

        return 'marketplace';
    }

    /**
     * Create PrestaShop order from Yuju data.
     * 
     * @param array $order_data Datos de la orden de Yuju
     * @param bool $force Si es true, ignora si ya existe un mapping y intenta crear de nuevo
     */
    public function createOrderFromYuju($order_data, $force = false)
    {
        $this->logger->log('Creating order from Yuju data: ' . json_encode($order_data), 'debug');
        
        $details = [
            'customer_id' => null,
            'customer_error' => null,
            'shipping_address_id' => null,
            'shipping_address_error' => null,
            'billing_address_id' => null,
            'billing_address_error' => null,
            'cart_id' => null,
            'cart_error' => null,
            'cart_products_added' => 0,
            'cart_products_failed' => [],
            'order_id' => null,
            'order_error' => null,
            'mapping_id' => null,
            'mapping_error' => null,
        ];
        
        // Adaptar estructura de datos de Yuju al formato esperado
        $adapted_data = $this->adaptYujuOrderData($order_data);
        $yuju_order_id = (string) ($adapted_data['id'] ?? '');

        if ($yuju_order_id === '') {
            return [
                'success' => false,
                'message' => 'Missing Yuju order id',
                'details' => $details,
            ];
        }

        if (!$this->acquireOrderLock($yuju_order_id)) {
            return [
                'success' => false,
                'message' => 'Another process is creating/updating this order. Retry later.',
                'yuju_order_id' => $yuju_order_id,
                'details' => $details,
                'lock_busy' => true,
            ];
        }

        try {
            return $this->createOrderFromYujuLocked($adapted_data, $order_data, $force, $details);
        } finally {
            $this->releaseOrderLock($yuju_order_id);
        }
    }

    /**
     * Cuerpo de creación con lock ya adquirido.
     *
     * @param array $adapted_data
     * @param array $order_data
     * @param bool $force
     * @param array $details
     * @return array
     */
    protected function createOrderFromYujuLocked(array $adapted_data, array $order_data, $force, array $details)
    {
        $yuju_order_id = (string) $adapted_data['id'];
        $id_channel = $adapted_data['id_channel'] ?? ($order_data['id_channel'] ?? null);
        $yuju_reference = (string) ($adapted_data['reference'] ?? $yuju_order_id);
        $outbound = new YujuOrderOutbound($this->api_client, $this->logger);

        // Check if order already exists (puede ser null claim, 0 legacy, o > 0 orden real)
        // SOLO si NO se está forzando la creación
        if (!$force) {
            $existing_order = $this->findOrderByYujuId($yuju_order_id);

            if ($existing_order !== null && (int) $existing_order > 0) {
                $this->logger->log(
                    'Order already exists with Yuju ID: ' . $yuju_order_id . ', order ID: ' . $existing_order . ' — idempotent sync',
                    'info'
                );
                return $this->syncExistingOrderFromYuju((int) $existing_order, $adapted_data, $details, true);
            }
        } else {
            // Si se está forzando, eliminar mapping existente si hay
            $existing_order = $this->findOrderByYujuId($yuju_order_id);
            if ($existing_order !== null) {
                $this->logger->log('Force mode: Deleting existing mapping for Yuju ID: ' . $yuju_order_id, 'info');
                Db::getInstance()->delete('yuju_order_mapping', 'yuju_order_id = "' . pSQL($yuju_order_id) . '"');
            }
        }

        // Claim atómico del mapping ANTES de crear customer/cart/order (UNIQUE yuju_order_id)
        if (!$force) {
            $claim = $this->claimOrderMapping($yuju_order_id);
            if (!$claim['claimed']) {
                $psId = isset($claim['prestashop_order_id']) ? (int) $claim['prestashop_order_id'] : 0;
                if ($psId > 0) {
                    $this->logger->log(
                        'Claim lost: order already mapped Yuju ID ' . $yuju_order_id . ' → PS ' . $psId,
                        'info'
                    );
                    return $this->syncExistingOrderFromYuju($psId, $adapted_data, $details, true);
                }
                // Claim de otro proceso aún sin PS id: esperar un momento y re-leer
                $waited = $this->waitForMappedOrder($yuju_order_id, 8);
                if ($waited > 0) {
                    return $this->syncExistingOrderFromYuju($waited, $adapted_data, $details, true);
                }
                return [
                    'success' => true,
                    'message' => 'Order is being created by another process',
                    'yuju_order_id' => $yuju_order_id,
                    'status' => 'creating_elsewhere',
                    'details' => $details,
                ];
            }
            $details['mapping_id'] = $claim['mapping_id'];
            $details['mapping_status'] = 'CLAIMED';
        }

        $outbound->start($id_channel, $yuju_order_id, $yuju_reference, []);

        // LOG: Ver datos adaptados antes de crear cliente
        $this->logger->log('ADAPTED DATA - customer: ' . json_encode($adapted_data['customer']) . 
            ' | shipping_address: ' . json_encode($adapted_data['shipping_address']), 'debug');

        // Get or create customer
        $customer = null;
        try {
            $customer = $this->getOrCreateCustomer($adapted_data['customer']);
            if (!$customer || !$customer->id) {
                throw new Exception('Customer object is null or has no ID');
            }
            $details['customer_id'] = $customer->id;
            
            // Verificar si ya existía
            $existing_customer = Customer::customerExists($adapted_data['customer']['email'], true);
            if ($existing_customer && $existing_customer == $customer->id) {
                $details['customer_status'] = 'EXISTING';
            } else {
                $details['customer_status'] = 'CREATED';
            }
            $custLabel = $details['customer_status'] === 'EXISTING' ? 'existente' : 'nuevo';
            $outbound->step(
                'processing',
                'Cliente ' . $custLabel . ' listo (ID ' . (int) $customer->id . ').',
                [
                    'customer_id' => (int) $customer->id,
                    'customer_status' => $details['customer_status'],
                ],
                'Cliente'
            );
        } catch (Exception $e) {
            $details['customer_error'] = $e->getMessage();
            $this->logger->log('Failed to create customer: ' . $e->getMessage(), 'error');
            $this->releaseClaimedMapping($yuju_order_id);
            $outbound->finishError('Failed to get or create customer: ' . $e->getMessage(), [
                'customer_error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to get or create customer: ' . $e->getMessage(),
                'details' => $details,
            ];
        }

        // Get or create shipping address
        $address = null;
        $address_existed = false;
        try {
            $address_result = $this->getOrCreateAddress($adapted_data['shipping_address'], $customer->id);
            $address = $address_result['address'];
            $address_existed = $address_result['existed'];
            
            if (!$address || !$address->id) {
                throw new Exception('Address object is null or has no ID');
            }
            $details['shipping_address_id'] = $address->id;
            $details['address_status'] = $address_existed ? 'EXISTING' : 'CREATED';
            $addrLabel = $address_existed ? 'ya existía' : 'creada';
            $outbound->step(
                'processing',
                'Dirección de envío ' . $addrLabel . ' (ID ' . (int) $address->id . ').',
                [
                    'shipping_address_id' => (int) $address->id,
                    'address_status' => $details['address_status'],
                ],
                'Dirección'
            );
        } catch (Exception $e) {
            $details['shipping_address_error'] = $e->getMessage();
            $details['billing_address_error'] = $e->getMessage();
            $this->logger->log('Failed to create address: ' . $e->getMessage(), 'error');
            $this->releaseClaimedMapping($yuju_order_id);
            $outbound->finishError('Failed to create shipping address: ' . $e->getMessage(), [
                'shipping_address_error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create shipping address: ' . $e->getMessage(),
                'details' => $details,
            ];
        }

        // Billing address (si difiere del shipping)
        $invoice_address = $address;
        try {
            $billing_data = isset($adapted_data['billing_address']) ? $adapted_data['billing_address'] : $adapted_data['shipping_address'];
            $same_billing = (
                ($billing_data['address_line_1'] ?? '') === ($adapted_data['shipping_address']['address_line_1'] ?? '')
                && ($billing_data['city'] ?? '') === ($adapted_data['shipping_address']['city'] ?? '')
                && ($billing_data['postal_code'] ?? '') === ($adapted_data['shipping_address']['postal_code'] ?? '')
            );
            if (!$same_billing) {
                $billing_result = $this->getOrCreateAddress($billing_data, $customer->id);
                $invoice_address = $billing_result['address'];
                $details['billing_address_status'] = !empty($billing_result['existed']) ? 'EXISTING' : 'CREATED';
            } else {
                $details['billing_address_status'] = 'SAME_AS_SHIPPING';
            }
            $details['billing_address_id'] = $invoice_address->id;
        } catch (Exception $e) {
            $details['billing_address_error'] = $e->getMessage();
            $details['billing_address_id'] = $address->id;
            $invoice_address = $address;
            $this->logger->log('Billing address fallback to shipping: ' . $e->getMessage(), 'warning');
        }

        // Verificar si hay items para crear el carrito y orden completa
        if (empty($adapted_data['items'])) {
            // Webhook de new-order NO incluye items: dejar claim pendiente (NULL) para el siguiente evento
            $this->logger->log(
                'Webhook new-order received without items. Mapping claimed; waiting for full sync. Yuju Order ID: ' . $yuju_order_id,
                'warning'
            );
            $outbound->step(
                'processing',
                'El webhook llegó sin productos. Se espera la sincronización completa del pedido.',
                [
                    'pending_note' => 'Sin ítems en el webhook; pendiente de sync completo',
                ],
                'Esperando ítems'
            );
            
            return [
                'success' => true,
                'message' => 'Order webhook received. Full order sync required to create in PrestaShop.',
                'action_required' => 'fetch_full_order',
                'yuju_order_id' => $yuju_order_id,
                'yuju_reference' => $adapted_data['reference'],
                'status' => 'pending_sync',
                'details' => $details,
            ];
        }

        // Create cart (solo si hay items) — un carrito nuevo solo cuando vamos a crear la orden
        $cart = null;
        $cart_existed = false;
        try {
            $cart_result = $this->createCartFromOrderData($adapted_data, $customer->id, $address->id);
            $cart = $cart_result['cart'];
            $cart_existed = $cart_result['existed'];
            
            if (!$cart || !$cart->id) {
                throw new Exception('Cart object is null or has no ID');
            }
            $details['cart_id'] = $cart->id;
            $details['cart_status'] = $cart_existed ? 'EXISTING' : 'CREATED';
            $cartLabel = $cart_existed ? 'reutilizado' : 'creado';
            $outbound->step(
                'processing',
                'Carrito ' . $cartLabel . ' (ID ' . (int) $cart->id . ').',
                [
                    'cart_id' => (int) $cart->id,
                    'cart_status' => $details['cart_status'],
                ],
                'Carrito'
            );
        } catch (Exception $e) {
            $details['cart_error'] = $e->getMessage();
            $this->logger->log('Failed to create cart: ' . $e->getMessage(), 'error');
            $this->releaseClaimedMapping($yuju_order_id);
            $outbound->finishError('Failed to create cart: ' . $e->getMessage(), [
                'cart_error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create cart: ' . $e->getMessage(),
                'details' => $details,
            ];
        }

        // Create order
        try {
            $order = new Order();
            $order->id_customer = $customer->id;
            $order->id_cart = $cart->id;
            $order->id_currency = $this->getCurrencyId($adapted_data['currency']);
            $order->id_lang = $this->context->language->id;
            $order->id_address_delivery = $address->id;
            $order->id_address_invoice = $invoice_address->id;
            $order->id_carrier = $this->getCarrierId('Yuju');
            $details['carrier_id'] = (int) $order->id_carrier;
            $order->payment = isset($adapted_data['payment_method']) ? $adapted_data['payment_method'] : 'Yuju';
            $order->module = 'prestashopyuju';
            $order->secure_key = $customer->secure_key; // Clave de seguridad del cliente
            $order->total_paid = (float) $adapted_data['total_amount'];
            $order->total_paid_tax_incl = (float) $adapted_data['total_amount'];
            $order->total_paid_tax_excl = (float) $adapted_data['total_amount_tax_excl'];
            $order->total_paid_real = (float) $adapted_data['total_amount']; // Usar total_amount como paid_total
            $order->conversion_rate = 1; // Tasa de conversión (1 = misma moneda que la tienda)
            $order->total_products = (float) $adapted_data['products_total'];
            $order->total_products_wt = (float) $adapted_data['products_total'];
            $order->total_shipping = (float) $adapted_data['shipping_cost'];
            $order->total_shipping_tax_incl = (float) $adapted_data['shipping_cost'];
            $order->total_shipping_tax_excl = (float) $adapted_data['shipping_cost'];
            $order->reference = $this->generateOrderReference($adapted_data);
            $order->current_state = $this->mapYujuStatusToPrestaShop(
                $adapted_data['status'], 
                isset($adapted_data['progress']) ? $adapted_data['progress'] : null
            );
            $order->date_add = isset($adapted_data['created_at']) ? $adapted_data['created_at'] : date('Y-m-d H:i:s');
            
            // CRÍTICO: Asignar shop y shop_group para que la orden sea visible
            $order->id_shop = (int)$this->context->shop->id;
            $order->id_shop_group = (int)$this->context->shop->id_shop_group;

            if (!$order->add()) {
                // Obtener errores de validación de PrestaShop
                $errors = method_exists($order, 'getErrors') ? $order->validateFields(false, true) : false;
                $error_msg = is_string($errors) && $errors !== ''
                    ? 'PrestaShop validation errors: ' . $errors
                    : 'Order->add() returned false. Possible causes: Invalid order state, missing required fields, or database constraint violations.';
                
                // Log detallado de los datos de la orden
                $this->logger->log('Failed to create order: ' . $error_msg, 'error');
                $this->logger->log('Order data: Customer=' . $customer->id . ', Cart=' . $cart->id . ', Currency=' . $order->id_currency . ', State=' . $order->current_state . ', Shop=' . $order->id_shop, 'error');
                
                throw new Exception($error_msg);
            }
            $details['order_id'] = $order->id;
            $details['order_status'] = 'CREATED';
            $details['customer_email'] = $adapted_data['customer']['email'] ?? null;
            $details['marketplace_slug'] = $adapted_data['marketplace_slug'] ?? null;
            $this->logger->log('Order created successfully with ID: ' . $order->id . ' for shop ' . $order->id_shop, 'info');
            $outbound->step(
                'processing',
                'Orden creada en PrestaShop #' . (int) $order->id
                . ' (ref. ' . (string) $order->reference . ').',
                [
                    'prestashop_order_id' => (int) $order->id,
                    'order_reference' => (string) $order->reference,
                ],
                'Orden PrestaShop'
            );
        } catch (Exception $e) {
            $details['order_error'] = $e->getMessage();
            $this->logger->log('Failed to create order: ' . $e->getMessage(), 'error');
            $this->releaseClaimedMapping($yuju_order_id);
            $outbound->finishError('Failed to create order in PrestaShop: ' . $e->getMessage(), [
                'order_error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create order in PrestaShop: ' . $e->getMessage(),
                'details' => $details,
            ];
        }

        // Vincular claim → id real de PrestaShop
        try {
            $mapping_id = $this->bindOrderMapping($order->id, $yuju_order_id);
            $details['mapping_id'] = $mapping_id;
            $details['mapping_status'] = 'BOUND';
        } catch (Exception $e) {
            $details['mapping_error'] = $e->getMessage();
            $this->logger->log('Failed to bind mapping: ' . $e->getMessage(), 'error');
            // Orden ya creada: no borrar claim; intentar upsert de nuevo
            try {
                $this->createOrderMapping($order->id, $yuju_order_id);
            } catch (Exception $e2) {
                $this->logger->log('Mapping upsert fallback failed: ' . $e2->getMessage(), 'error');
            }
        }

        // Add order history - ESTO ES CRÍTICO para que la orden aparezca en el backoffice
        // El OrderHistory también crea los OrderDetail automáticamente desde el carrito
        try {
            $this->addOrderHistory($order->id, $order->current_state, 'Order created from Yuju');
            $this->logger->log('Order history added successfully', 'info');
        } catch (Exception $e) {
            $this->logger->log('Failed to add order history: ' . $e->getMessage(), 'warning');
            // Intentar método alternativo
            try {
                $this->updateOrderState($order->id, $order->current_state);
            } catch (Exception $e2) {
                $this->logger->log('Alternative order state update also failed: ' . $e2->getMessage(), 'error');
            }
        }

        // Transportista visible en BO + costo de envío (0 = gratis, >0 = con costo)
        try {
            $tracking = null;
            if (!empty($adapted_data['tracking_number'])) {
                $tracking = $adapted_data['tracking_number'];
            } elseif (!empty($adapted_data['items']) && is_array($adapted_data['items'])) {
                foreach ($adapted_data['items'] as $it) {
                    if (!empty($it['tracking_code'])) {
                        $tracking = $it['tracking_code'];
                        break;
                    }
                }
            }
            $this->attachOrderCarrier(
                $order,
                isset($adapted_data['shipping_cost']) ? (float) $adapted_data['shipping_cost'] : 0.0,
                $tracking
            );
            $details['carrier_id'] = (int) $order->id_carrier;
            $details['shipping_cost'] = (float) ($adapted_data['shipping_cost'] ?? 0);
        } catch (Exception $e) {
            $this->logger->log('Failed to attach order carrier: ' . $e->getMessage(), 'warning');
        }

        // Ciclo completo: bajar stock en PS → empujar stock nuevo a Yuju
        try {
            $stockSync = $this->applyOrderStockDecrementAndPushToYuju($order);
            $details['stock_sync'] = $stockSync;
            $outbound->step(
                'processing',
                'Stock descontado en PrestaShop y actualizado en Yuju.',
                [
                    'prestashop_order_id' => (int) $order->id,
                    'stock_sync' => $stockSync,
                ],
                'Stock'
            );
        } catch (Exception $e) {
            $details['stock_sync_error'] = $e->getMessage();
            $this->logger->log('Stock cycle after order failed: ' . $e->getMessage(), 'error');
            $outbound->step(
                'processing',
                'Orden creada, pero hubo un problema al sincronizar el stock: ' . $e->getMessage(),
                [
                    'prestashop_order_id' => (int) $order->id,
                    'stock_sync_error' => $e->getMessage(),
                ],
                'Stock'
            );
        }

        $this->logger->log('Created order from Yuju: PS Order ID ' . $order->id . ', Yuju Order ID ' . $yuju_order_id, 'info');

        $outbound->setReference((string) $order->reference);
        $outbound->finishSuccess(
            'Orden creada correctamente en PrestaShop #' . (int) $order->id . '.',
            [
                'prestashop_order_id' => (int) $order->id,
                'order_reference' => (string) $order->reference,
                'carrier_id' => $details['carrier_id'] ?? null,
            ]
        );

        return [
            'success' => true,
            'prestashop_order_id' => $order->id,
            'yuju_order_id' => $yuju_order_id,
            'details' => $details,
        ];
    }

    /**
     * Actualiza estado/tracking de una orden PS ya mapeada (idempotente).
     *
     * @param int $prestashop_order_id
     * @param array $adapted_data
     * @param array $details
     * @param bool $from_duplicate_webhook
     * @return array
     */
    protected function syncExistingOrderFromYuju($prestashop_order_id, array $adapted_data, array $details = [], $from_duplicate_webhook = false)
    {
        $order = new Order((int) $prestashop_order_id);
        if (!Validate::isLoadedObject($order)) {
            return [
                'success' => false,
                'message' => 'Mapped PrestaShop order not found: ' . (int) $prestashop_order_id,
                'yuju_order_id' => $adapted_data['id'] ?? null,
                'prestashop_order_id' => (int) $prestashop_order_id,
                'details' => $details,
            ];
        }

        $details['order_id'] = (int) $order->id;
        $details['order_status'] = 'EXISTING';
        $details['customer_id'] = (int) $order->id_customer;
        $details['shipping_address_id'] = (int) $order->id_address_delivery;
        $details['billing_address_id'] = (int) $order->id_address_invoice;
        $details['cart_id'] = (int) $order->id_cart;

        $new_status = $this->mapYujuStatusToPrestaShop(
            $adapted_data['status'] ?? '',
            isset($adapted_data['progress']) ? $adapted_data['progress'] : null
        );
        $status_changed = false;
        if ($new_status && !$this->orderAlreadyHasState((int) $order->id, (int) $new_status)) {
            try {
                $status_changed = (bool) $this->applyOrderStatusChange(
                    $order,
                    (int) $new_status,
                    'Status synced from Yuju: ' . ($adapted_data['status'] ?? '')
                );
                if ($status_changed) {
                    $details['status_synced_to'] = (int) $new_status;
                } else {
                    $details['status_skipped_same'] = (int) $new_status;
                }
            } catch (Exception $e) {
                $details['status_sync_error'] = $e->getMessage();
                $this->logger->log('Failed to sync status on existing order: ' . $e->getMessage(), 'warning');
            }
        } else {
            $details['status_skipped_same'] = (int) $new_status;
        }

        if (!empty($adapted_data['tracking_number'])) {
            try {
                $order->shipping_number = $adapted_data['tracking_number'];
                $order->update();
                $details['tracking_updated'] = true;
            } catch (Exception $e) {
                $this->logger->log('Failed to update tracking on existing order: ' . $e->getMessage(), 'warning');
            }
        }

        // Asegurar transportista Yuju + fila order_carrier (pedidos creados antes del fix)
        try {
            $yujuCarrierId = $this->getCarrierId('Yuju');
            if ($yujuCarrierId > 0 && (int) $order->id_carrier !== $yujuCarrierId) {
                $order->id_carrier = $yujuCarrierId;
                $order->update();
            }
            $shipping = isset($adapted_data['shipping_cost'])
                ? (float) $adapted_data['shipping_cost']
                : (float) $order->total_shipping_tax_incl;
            $this->attachOrderCarrier(
                $order,
                $shipping,
                !empty($adapted_data['tracking_number']) ? $adapted_data['tracking_number'] : null
            );
            $details['carrier_id'] = (int) $order->id_carrier;
        } catch (Exception $e) {
            $this->logger->log('Failed to sync carrier on existing order: ' . $e->getMessage(), 'warning');
        }

        return [
            'success' => true,
            'already_exists' => true,
            'from_duplicate_webhook' => (bool) $from_duplicate_webhook,
            'status_changed' => $status_changed,
            'message' => $from_duplicate_webhook
                ? 'Order already exists — webhook ignored for creation, status synced if needed'
                : 'Order synced',
            'prestashop_order_id' => (int) $order->id,
            'order_id' => (int) $order->id,
            'yuju_order_id' => $adapted_data['id'] ?? null,
            'details' => $details,
        ];
    }

    /**
     * Update PrestaShop order from Yuju data.
     */
    protected function updateOrderFromYuju($order_data)
    {
        $adapted_data = $this->adaptYujuOrderData($order_data);
        $yuju_order_id = (string) ($adapted_data['id'] ?? '');

        if ($yuju_order_id === '') {
            return [
                'success' => false,
                'message' => 'Missing Yuju order id on updated-order',
            ];
        }

        if (!$this->acquireOrderLock($yuju_order_id)) {
            return [
                'success' => false,
                'message' => 'Another process is handling this order',
                'yuju_order_id' => $yuju_order_id,
                'lock_busy' => true,
            ];
        }

        try {
            $ps_order_id = $this->findOrderByYujuId($yuju_order_id);

            if (!empty($order_data['tracking_number'])) {
                $adapted_data['tracking_number'] = $order_data['tracking_number'];
            }

            if ($ps_order_id === null || (int) $ps_order_id <= 0) {
                // No existe (o solo claim pendiente): crear / completar
                return $this->createOrderFromYujuLocked($adapted_data, $order_data, false, [
                    'customer_id' => null,
                    'shipping_address_id' => null,
                    'billing_address_id' => null,
                    'cart_id' => null,
                    'order_id' => null,
                    'mapping_id' => null,
                ]);
            }

            return $this->syncExistingOrderFromYuju((int) $ps_order_id, $adapted_data, [], false);
        } finally {
            $this->releaseOrderLock($yuju_order_id);
        }
    }

    /**
     * Update order status.
     */
    protected function updateOrderStatus($order_data)
    {
        $adapted_data = $this->adaptYujuOrderData($order_data);
        $yuju_order_id = (string) ($adapted_data['id'] ?? '');

        if ($yuju_order_id === '') {
            throw new Exception('Order not found: missing Yuju order id');
        }

        if (!$this->acquireOrderLock($yuju_order_id)) {
            throw new Exception('Could not lock order for status update: ' . $yuju_order_id);
        }

        try {
            $ps_order_id = $this->findOrderByYujuId($yuju_order_id);
            if (!$ps_order_id || (int) $ps_order_id <= 0) {
                throw new Exception('Order not found with Yuju ID: ' . $yuju_order_id);
            }

            $order = new Order((int) $ps_order_id);
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('PrestaShop order not loaded: ' . (int) $ps_order_id);
            }

            $new_status = $this->mapYujuStatusToPrestaShop(
                $adapted_data['status'] ?? '',
                isset($adapted_data['progress']) ? $adapted_data['progress'] : null
            );

            $changed = false;
            if ($new_status && !$this->orderAlreadyHasState((int) $order->id, (int) $new_status)) {
                $message = 'Status updated from Yuju: ' . ($adapted_data['status'] ?? '');
                if (!empty($order_data['status_message'])) {
                    $message .= ' - ' . $order_data['status_message'];
                }
                $changed = (bool) $this->applyOrderStatusChange($order, (int) $new_status, $message);
            }

            return [
                'success' => true,
                'order_id' => (int) $order->id,
                'new_status' => (int) $new_status,
                'status_changed' => $changed,
                'status_skipped_same' => !$changed,
            ];
        } finally {
            $this->releaseOrderLock($yuju_order_id);
        }
    }

    /**
     * Cancel order.
     */
    protected function cancelOrder($order_data)
    {
        $adapted_data = $this->adaptYujuOrderData($order_data);
        $yuju_order_id = (string) ($adapted_data['id'] ?? '');

        if ($yuju_order_id === '') {
            throw new Exception('Order not found: missing Yuju order id');
        }

        if (!$this->acquireOrderLock($yuju_order_id)) {
            throw new Exception('Could not lock order for cancel: ' . $yuju_order_id);
        }

        try {
            $ps_order_id = $this->findOrderByYujuId($yuju_order_id);
            if (!$ps_order_id || (int) $ps_order_id <= 0) {
                throw new Exception('Order not found with Yuju ID: ' . $yuju_order_id);
            }

            $order = new Order((int) $ps_order_id);
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('PrestaShop order not loaded: ' . (int) $ps_order_id);
            }

            $cancelled_status = (int) Configuration::get('PS_OS_CANCELED');
            $message = 'Order cancelled from Yuju';
            if (!empty($order_data['cancellation_reason'])) {
                $message .= ' - Reason: ' . $order_data['cancellation_reason'];
            }
            $this->applyOrderStatusChange($order, $cancelled_status, $message);

            $this->logger->log('Cancelled order: PS Order ID ' . $order->id, 'info');

            return [
                'success' => true,
                'order_id' => (int) $order->id,
                'status' => 'cancelled',
            ];
        } finally {
            $this->releaseOrderLock($yuju_order_id);
        }
    }

    /**
     * Estado actual real en BD + último del historial.
     *
     * @param int $order_id
     * @return array{current:int,last_history:int}
     */
    protected function getOrderStateSnapshot($order_id)
    {
        $order_id = (int) $order_id;
        $current = (int) Db::getInstance()->getValue(
            'SELECT `current_state` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . $order_id
        );
        $lastHistory = (int) Db::getInstance()->getValue(
            'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_history`
             WHERE `id_order` = ' . $order_id . '
             ORDER BY `id_order_history` DESC'
        );

        return [
            'current' => $current,
            'last_history' => $lastHistory,
        ];
    }

    /**
     * True si la orden ya está (o el último historial ya es) el estado indicado.
     * No registrar otro OrderHistory en ese caso.
     *
     * @param int $order_id
     * @param int $status_id
     * @return bool
     */
    protected function orderAlreadyHasState($order_id, $status_id)
    {
        $status_id = (int) $status_id;
        if ($status_id <= 0) {
            return true;
        }
        $snap = $this->getOrderStateSnapshot($order_id);

        return $snap['current'] === $status_id || $snap['last_history'] === $status_id;
    }

    /**
     * Cambia estado de una orden PS (OrderHistory) solo si el estado realmente cambia.
     * Webhooks repetidos con el mismo estado no deben crear filas duplicadas.
     *
     * @param Order $order
     * @param int $new_status
     * @param string $message
     * @return bool true si cambió; false si se omitió
     */
    protected function applyOrderStatusChange(Order $order, $new_status, $message = '')
    {
        $new_status = (int) $new_status;
        $order_id = (int) $order->id;
        if ($new_status <= 0 || $order_id <= 0) {
            return false;
        }

        $snap = $this->getOrderStateSnapshot($order_id);

        // Ya en ese estado (current o último historial) → no registrar de nuevo
        if ($snap['current'] === $new_status || $snap['last_history'] === $new_status) {
            if ($snap['current'] !== $new_status && $snap['last_history'] === $new_status) {
                // Historial ya tiene el estado; sanear current_state sin nueva fila
                Db::getInstance()->update(
                    'orders',
                    ['current_state' => $new_status],
                    'id_order = ' . $order_id
                );
            }
            $order->current_state = $new_status;
            $this->logger->log(
                'Skip status change (idempotent): PS Order ' . $order_id
                . ' already at state ' . $new_status
                . ' (current=' . $snap['current'] . ', last_history=' . $snap['last_history'] . ')'
                . ($message !== '' ? (' — ' . $message) : ''),
                'info'
            );

            return false;
        }

        $employee = $this->getOrCreateYujuEmployee();
        $employeeId = ($employee && !empty($employee->id)) ? (int) $employee->id : 0;
        if (!isset($this->context->employee) || !$this->context->employee->id) {
            $this->context->employee = $employee;
        }

        $order_history = new OrderHistory();
        $order_history->id_order = $order_id;
        $order_history->id_employee = $employeeId;
        $order_history->changeIdOrderState($new_status, $order);
        $order_history->addWithemail(true, [], $this->context);

        // Asegurar current_state en BD (changeIdOrderState a veces deja el objeto desfasado)
        Db::getInstance()->update(
            'orders',
            ['current_state' => $new_status],
            'id_order = ' . $order_id
        );
        $order->current_state = $new_status;

        $this->logger->log(
            'Updated order status: PS Order ID ' . $order_id
            . ' from ' . $snap['current'] . ' to ' . $new_status
            . ($message !== '' ? (' — ' . $message) : ''),
            'info'
        );

        return true;
    }

    /**
     * Send order to Yuju.
     */
    public function sendOrderToYuju($order_id)
    {
        $order = new Order($order_id);

        if (!Validate::isLoadedObject($order)) {
            throw new Exception('Order not found: ' . $order_id);
        }

        // Check if order is already sent to Yuju
        $existing_mapping = $this->getOrderMapping($order_id);

        if ($existing_mapping) {
            throw new Exception('Order already sent to Yuju: ' . $existing_mapping['yuju_order_id']);
        }

        // Prepare order data for Yuju
        $order_data = $this->prepareOrderDataForYuju($order);

        // Send to Yuju API
        $response = $this->api_client->createOrder($order_data);

        if (!$response || !isset($response['id'])) {
            throw new Exception('Failed to create order in Yuju');
        }

        // Store mapping
        $this->createOrderMapping($order_id, $response['id']);

        $this->logger->log('Sent order to Yuju: PS Order ID ' . $order_id . ', Yuju Order ID ' . $response['id'], 'info');

        return [
            'success' => true,
            'yuju_order_id' => $response['id'],
        ];
    }

    /**
     * Update order status in Yuju.
     */
    public function updateOrderStatusInYuju($order_id, $new_status)
    {
        $mapping = $this->getOrderMapping($order_id);

        if (!$mapping) {
            throw new Exception('Order not found in Yuju mapping');
        }

        $yuju_status = $this->mapPrestaShopStatusToYuju($new_status);

        $response = $this->api_client->updateOrderStatus($mapping['yuju_order_id'], $yuju_status);

        if (!$response) {
            throw new Exception('Failed to update order status in Yuju');
        }

        $this->logger->log('Updated order status in Yuju: Order ID ' . $order_id . ' to status ' . $yuju_status, 'info');

        return [
            'success' => true,
            'yuju_status' => $yuju_status,
        ];
    }

    /**
     * Get or create customer from order data.
     */
    protected function getOrCreateCustomer($customer_data)
    {
        // LOG: Ver qué datos recibe este método
        $this->logger->log('getOrCreateCustomer called with data: ' . json_encode($customer_data), 'debug');
        
        // Validar que las clases de PrestaShop estén disponibles
        if (!class_exists('Customer')) {
            throw new Exception('PrestaShop Customer class not loaded. Check PrestaShop initialization.');
        }
        
        // Try to find existing customer by email
        $customer_id = Customer::customerExists($customer_data['email'], true);

        if ($customer_id) {
            $customer = new Customer($customer_id);
            if (!Validate::isLoadedObject($customer)) {
                throw new Exception('Customer with ID ' . $customer_id . ' exists but could not be loaded');
            }
            $this->logger->log('Customer already exists with ID: ' . $customer_id . ' (email: ' . $customer_data['email'] . ')', 'info');
            return $customer;
        }

        // Create new customer
        $customer = new Customer();
        
        // Validar y sanitizar email
        $email = trim($customer_data['email']);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format: ' . $email);
        }
        
        // Validar y sanitizar nombres (PrestaShop requiere nombres válidos)
        $firstname = trim($customer_data['first_name']);
        $lastname = trim($customer_data['last_name']);
        
        if (empty($firstname)) {
            $firstname = 'Cliente';
        }
        if (empty($lastname)) {
            $lastname = 'Yuju';
        }
        
        // Remover caracteres especiales que PrestaShop no acepta
        $firstname = preg_replace('/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\']/u', '', $firstname);
        $lastname = preg_replace('/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\']/u', '', $lastname);
        
        // Limitar longitud (PrestaShop tiene límites)
        $firstname = substr($firstname, 0, 32);
        $lastname = substr($lastname, 0, 32);
        
        $customer->email = $email;
        $customer->firstname = $firstname;
        $customer->lastname = $lastname;
        $customer->passwd = password_hash(Tools::passwdGen(), PASSWORD_DEFAULT);
        $customer->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $customer->id_lang = $this->context->language->id;
        $customer->active = true;

        if (!$customer->add()) {
            // Carrera: otro request creó el mismo email entre el EXISTS y el add()
            $race_id = Customer::customerExists($email, true);
            if ($race_id) {
                $customer = new Customer((int) $race_id);
                if (Validate::isLoadedObject($customer)) {
                    $this->logger->log('Customer race resolved — reusing ID: ' . $customer->id, 'info');
                    return $customer;
                }
            }

            $errors = method_exists($customer, 'getErrors') ? $customer->validateFields(false, true) : false;
            $error_msg = is_string($errors) && $errors !== ''
                ? 'PrestaShop validation errors: ' . $errors
                : 'Customer->add() returned false without specific error message';
            
            $this->logger->log('Failed to create customer: ' . $error_msg . ' | Data: ' . json_encode($customer_data), 'error');
            throw new Exception($error_msg);
        }

        $this->logger->log('Customer created successfully with ID: ' . $customer->id, 'info');
        return $customer;
    }

    /**
     * Get or create address from order data.
     */
    protected function getOrCreateAddress($address_data, $customer_id)
    {
        // LOG: Ver qué datos recibe este método
        $this->logger->log('getOrCreateAddress called with data: ' . json_encode($address_data) . ' | customer_id: ' . $customer_id, 'debug');
        
        // Validar que las clases de PrestaShop estén disponibles
        if (!class_exists('Address')) {
            throw new Exception('PrestaShop Address class not loaded. Check PrestaShop initialization.');
        }
        
        // Buscar dirección existente del cliente con los mismos datos clave
        // Solo comparamos los campos principales para evitar duplicados por diferencias menores en address2
        $existing_addresses = Db::getInstance()->executeS('
            SELECT id_address FROM ' . _DB_PREFIX_ . 'address
            WHERE id_customer = ' . (int)$customer_id . '
            AND firstname = "' . pSQL(trim($address_data['first_name'])) . '"
            AND lastname = "' . pSQL(trim($address_data['last_name'])) . '"
            AND address1 = "' . pSQL(trim($address_data['address_line_1'])) . '"
            AND city = "' . pSQL(trim($address_data['city'])) . '"
            AND postcode = "' . pSQL(trim($address_data['postal_code'])) . '"
            AND deleted = 0
            ORDER BY id_address DESC
            LIMIT 1
        ');
        
        if (!empty($existing_addresses)) {
            $address_id = $existing_addresses[0]['id_address'];
            $address = new Address($address_id);
            $this->logger->log('Address already exists with ID: ' . $address_id, 'info');
            return [
                'address' => $address,
                'existed' => true
            ];
        }
        
        // Crear nueva dirección si no existe
        $address = new Address();
        $address->id_customer = $customer_id;
        
        // Validar y sanitizar nombres
        $firstname = trim($address_data['first_name']);
        $lastname = trim($address_data['last_name']);
        
        if (empty($firstname)) {
            $firstname = 'Cliente';
        }
        if (empty($lastname)) {
            $lastname = 'Yuju';
        }
        
        // Sanitizar nombres (remover caracteres especiales)
        $firstname = preg_replace('/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\']/u', '', $firstname);
        $lastname = preg_replace('/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\']/u', '', $lastname);
        
        // Limitar longitud
        $firstname = substr($firstname, 0, 32);
        $lastname = substr($lastname, 0, 32);
        
        // Validar y sanitizar dirección
        $address1 = trim($address_data['address_line_1']);
        if (empty($address1) || $address1 === 'Not Found') {
            $address1 = 'Dirección no especificada';
        }
        $address1 = substr($address1, 0, 128);
        
        // Validar ciudad
        $city = trim($address_data['city']);
        if (empty($city) || $city === 'Not Found') {
            $city = 'Ciudad no especificada';
        }
        $city = substr($city, 0, 64);
        
        // Validar código postal
        $postcode = trim($address_data['postal_code']);
        if (empty($postcode) || $postcode === '00000') {
            $postcode = '00000';
        }
        $postcode = substr($postcode, 0, 12);
        
        // Validar teléfono
        $phone = isset($address_data['phone']) ? trim($address_data['phone']) : '';
        if (empty($phone)) {
            $phone = '0000000000';
        }
        $phone = substr($phone, 0, 32);
        
        $address->firstname = $firstname;
        $address->lastname = $lastname;
        $address->company = isset($address_data['company']) ? substr($address_data['company'], 0, 64) : '';
        $address->address1 = $address1;
        $address->address2 = isset($address_data['address_line_2']) ? substr($address_data['address_line_2'], 0, 128) : '';
        $address->postcode = $postcode;
        $address->city = $city;
        $address->id_country = $this->getCountryId($address_data['country_code']);
        $address->id_state = $this->getStateId($address_data['state_code'], $address->id_country);
        $address->phone = $phone;
        $address->dni = isset($address_data['dni']) ? substr($address_data['dni'], 0, 16) : '00000000';
        $address->alias = 'Yuju Address';

        if (!$address->add()) {
            // Obtener errores de validación de PrestaShop
            $errors = $address->getErrors();
            $error_msg = !empty($errors) 
                ? 'PrestaShop validation errors: ' . implode(', ', $errors)
                : 'Address->add() returned false without specific error message';
            
            $this->logger->log('Failed to create address: ' . $error_msg . ' | Data: ' . json_encode($address_data), 'error');
            throw new Exception($error_msg);
        }

        $this->logger->log('Address created successfully with ID: ' . $address->id, 'info');
        return [
            'address' => $address,
            'existed' => false
        ];
    }

    /**
     * Create cart from order data.
     */
    protected function createCartFromOrderData($order_data, $customer_id, $address_id)
    {
        // Validar que las clases de PrestaShop estén disponibles
        if (!class_exists('Cart')) {
            throw new Exception('PrestaShop Cart class not loaded. Check PrestaShop initialization.');
        }
        
        // SIEMPRE crear un carrito nuevo para cada orden
        // No reutilizar carritos existentes para evitar conflictos
        $cart = new Cart();
        $cart->id_customer = $customer_id;
        $cart->id_address_delivery = $address_id;
        $cart->id_address_invoice = $address_id;
        $cart->id_lang = $this->context->language->id;
        $cart->id_currency = $this->getCurrencyId($order_data['currency']);
        $cart->id_carrier = $this->getCarrierId('Yuju');

        if (!$cart->add()) {
            // Obtener errores de validación de PrestaShop
            $errors = $cart->getErrors();
            $error_msg = !empty($errors) 
                ? 'PrestaShop validation errors: ' . implode(', ', $errors)
                : 'Cart->add() returned false without specific error message';
            
            $this->logger->log('Failed to create cart: ' . $error_msg, 'error');
            throw new Exception($error_msg);
        }

        $this->logger->log('Cart created with ID: ' . $cart->id, 'info');
        $cart_existed = false;

        // Add products to cart
        $products_added = 0;
        $products_failed = [];
        
        foreach ($order_data['items'] as $index => $item) {
            // Intentar encontrar producto por mapping de Yuju
            $product_id = $this->findProductByYujuId($item['product_id']);
            
            // Si no existe mapping, buscar por SKU / channel_sku / reference
            if (!$product_id) {
                $sku_candidates = array_filter([
                    $item['sku'] ?? null,
                    $item['channel_sku'] ?? null,
                ]);
                foreach ($sku_candidates as $sku_try) {
                    $product_id = $this->findProductBySKU($sku_try);
                    if ($product_id) {
                        $this->logger->log('Product found by SKU: ' . $sku_try . ' -> ID: ' . $product_id, 'info');
                        break;
                    }
                }
            }

            if (!$product_id) {
                $error_detail = 'Product not found - Yuju ID: ' . ($item['product_id'] ?? 'N/A')
                    . ', SKU: ' . ($item['sku'] ?? 'N/A')
                    . ', channel_sku: ' . ($item['channel_sku'] ?? 'N/A')
                    . ', Name: ' . ($item['name'] ?? 'N/A');
                $products_failed[] = $error_detail;
                $this->logger->log($error_detail, 'error');
                continue;
            }

            // Intentar agregar producto al carrito
            $qty_added = $cart->updateQty(
                (int) $item['quantity'],
                $product_id,
                isset($item['product_attribute_id']) ? (int) $item['product_attribute_id'] : null,
                false,
                'up'
            );
            
            if ($qty_added) {
                $products_added++;
                $this->logger->log('Product added to cart: ID=' . $product_id . ', SKU=' . ($item['sku'] ?? 'N/A') . ', Qty=' . $item['quantity'], 'info');
            } else {
                $error_detail = 'Failed to add product to cart: ID=' . $product_id . ', SKU=' . ($item['sku'] ?? 'N/A') . ' - Cart->updateQty() returned false';
                $products_failed[] = $error_detail;
                $this->logger->log($error_detail, 'error');
            }
        }
        
        // Validar que al menos un producto se agregó
        if ($products_added === 0) {
            $error_summary = 'NO PRODUCTS ADDED TO CART. ';
            $error_summary .= 'Total items in order: ' . count($order_data['items']) . '. ';
            $error_summary .= 'Products failed: ' . count($products_failed) . '. ';
            
            if (!empty($products_failed)) {
                $error_summary .= 'Details: ' . implode(' | ', $products_failed);
            } else {
                $error_summary .= 'All products were skipped (check if items array is populated).';
            }
            
            $this->logger->log($error_summary, 'error');
            throw new Exception($error_summary);
        }

        $this->logger->log($products_added . ' of ' . count($order_data['items']) . ' products added to cart successfully', 'info');

        return [
            'cart' => $cart,
            'existed' => $cart_existed
        ];
    }
    
    /**
     * Find product by SKU (reference field in PrestaShop)
     */
    protected function findProductBySKU($sku)
    {
        if (empty($sku)) {
            return null;
        }
        
        $product_id = Db::getInstance()->getValue('
            SELECT id_product FROM ' . _DB_PREFIX_ . 'product
            WHERE reference = "' . pSQL($sku) . '"
        ');
        
        return $product_id ? (int) $product_id : null;
    }

    /**
     * Add order details.
     */
    protected function addOrderDetails($order, $items)
    {
        foreach ($items as $item) {
            $product_id = $this->findProductByYujuId($item['product_id']);

            if (!$product_id) {
                continue;
            }

            $product = new Product($product_id);

            $order_detail = new OrderDetail();
            $order_detail->id_order = $order->id;
            $order_detail->product_id = $product_id;
            $order_detail->product_attribute_id = isset($item['product_attribute_id']) ? (int) $item['product_attribute_id'] : 0;
            $order_detail->product_name = $item['product_name'];
            $order_detail->product_quantity = (int) $item['quantity'];
            $order_detail->product_price = (float) $item['unit_price'];
            $order_detail->unit_price_tax_incl = (float) $item['unit_price'];
            $order_detail->unit_price_tax_excl = (float) $item['unit_price_tax_excl'];
            $order_detail->total_price_tax_incl = (float) $item['total_price'];
            $order_detail->total_price_tax_excl = (float) $item['total_price_tax_excl'];
            $order_detail->product_reference = $product->reference;
            $order_detail->product_ean13 = $product->ean13;
            $order_detail->product_weight = $product->weight;

            $order_detail->add();
        }
    }

    /**
     * Prepare order data for Yuju API.
     */
    protected function prepareOrderDataForYuju($order)
    {
        $customer = new Customer($order->id_customer);
        $address = new Address($order->id_address_delivery);
        $currency = new Currency($order->id_currency);
        $carrier = new Carrier($order->id_carrier);

        $order_data = [
            'external_id' => $order->id,
            'reference' => $order->reference,
            'status' => $this->mapPrestaShopStatusToYuju($order->current_state),
            'currency' => $currency->iso_code,
            'total_amount' => $order->total_paid,
            'total_amount_tax_excl' => $order->total_paid_tax_excl,
            'products_total' => $order->total_products,
            'shipping_cost' => $order->total_shipping,
            'payment_method' => $order->payment,
            'shipping_method' => $carrier->name,
            'created_at' => $order->date_add,
            'customer' => [
                'email' => $customer->email,
                'first_name' => $customer->firstname,
                'last_name' => $customer->lastname,
            ],
            'shipping_address' => [
                'first_name' => $address->firstname,
                'last_name' => $address->lastname,
                'company' => $address->company,
                'address_line_1' => $address->address1,
                'address_line_2' => $address->address2,
                'city' => $address->city,
                'postal_code' => $address->postcode,
                'country_code' => Country::getIsoById($address->id_country),
                'state_code' => $address->id_state ? State::getNameById($address->id_state) : '',
                'phone' => $address->phone,
            ],
            'items' => [],
        ];

        // Add order items
        $order_details = $order->getOrderDetailList();

        foreach ($order_details as $detail) {
            $yuju_product_id = $this->findYujuProductId($detail['product_id']);

            if ($yuju_product_id) {
                $order_data['items'][] = [
                    'product_id' => $yuju_product_id,
                    'product_name' => $detail['product_name'],
                    'quantity' => (int) $detail['product_quantity'],
                    'unit_price' => (float) $detail['unit_price_tax_incl'],
                    'unit_price_tax_excl' => (float) $detail['unit_price_tax_excl'],
                    'total_price' => (float) $detail['total_price_tax_incl'],
                    'total_price_tax_excl' => (float) $detail['total_price_tax_excl'],
                ];
            }
        }

        return $order_data;
    }

    /**
     * Helper methods.
     */
    protected function findOrderByYujuId($yuju_order_id)
    {
        if ($yuju_order_id === null || $yuju_order_id === '') {
            return null;
        }

        $this->ensureOrderMappingSchema();

        $mapping = Db::getInstance()->getRow('
            SELECT prestashop_order_id FROM ' . _DB_PREFIX_ . 'yuju_order_mapping
            WHERE yuju_order_id = "' . pSQL((string) $yuju_order_id) . '"
        ');

        if (!$mapping) {
            return null;
        }

        // Claim pendiente (NULL / 0 / negativo) → 0; orden real → id > 0
        if ($this->isPendingMappingPsId($mapping['prestashop_order_id'])) {
            return 0;
        }

        return (int) $mapping['prestashop_order_id'];
    }

    /**
     * Inserta claim atómico con placeholder negativo (NUNCA NULL).
     * UNIQUE(yuju_order_id) impide doble claim.
     *
     * @param string $yuju_order_id
     * @return array{claimed:bool,prestashop_order_id:?int,mapping_id:?int}
     */
    protected function claimOrderMapping($yuju_order_id)
    {
        $this->ensureOrderMappingSchema();
        $yuju_order_id = (string) $yuju_order_id;

        $existing = $this->findOrderByYujuId($yuju_order_id);
        if ($existing !== null) {
            if ((int) $existing > 0) {
                return [
                    'claimed' => false,
                    'prestashop_order_id' => (int) $existing,
                    'mapping_id' => null,
                ];
            }
            $mapping_id = (int) Db::getInstance()->getValue(
                'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_order_mapping
                 WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"'
            );
            Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'yuju_order_mapping
                 SET updated_at = "' . pSQL(date('Y-m-d H:i:s')) . '"
                 WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"'
            );
            return [
                'claimed' => true,
                'prestashop_order_id' => 0,
                'mapping_id' => $mapping_id ?: null,
            ];
        }

        $now = date('Y-m-d H:i:s');
        $placeholder = (int) $this->pendingMappingPlaceholder($yuju_order_id);

        try {
            $ok = Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'yuju_order_mapping`
                (`prestashop_order_id`, `yuju_order_id`, `created_at`, `updated_at`)
                VALUES (' . $placeholder . ', "' . pSQL($yuju_order_id) . '", "' . pSQL($now) . '", "' . pSQL($now) . '")'
            );
        } catch (Exception $e) {
            $ok = false;
            $this->logger->log('claimOrderMapping insert failed: ' . $e->getMessage(), 'warning');
        } catch (Throwable $e) {
            $ok = false;
            $this->logger->log('claimOrderMapping insert failed: ' . $e->getMessage(), 'warning');
        }

        if ($ok) {
            return [
                'claimed' => true,
                'prestashop_order_id' => $placeholder,
                'mapping_id' => (int) Db::getInstance()->Insert_ID(),
            ];
        }

        $existing = $this->findOrderByYujuId($yuju_order_id);
        return [
            'claimed' => false,
            'prestashop_order_id' => $existing,
            'mapping_id' => null,
        ];
    }

    /**
     * Vincula el claim al id real de la orden PrestaShop.
     *
     * @param int $prestashop_order_id
     * @param string $yuju_order_id
     * @return int|false mapping id
     */
    protected function bindOrderMapping($prestashop_order_id, $yuju_order_id)
    {
        $this->ensureOrderMappingSchema();
        $prestashop_order_id = (int) $prestashop_order_id;
        $yuju_order_id = (string) $yuju_order_id;
        $now = date('Y-m-d H:i:s');

        if ($prestashop_order_id <= 0) {
            throw new Exception('bindOrderMapping requiere prestashop_order_id > 0');
        }

        $updated = Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'yuju_order_mapping`
             SET prestashop_order_id = ' . $prestashop_order_id . ',
                 updated_at = "' . pSQL($now) . '"
             WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"'
        );

        if ($updated) {
            $id = (int) Db::getInstance()->getValue(
                'SELECT id FROM `' . _DB_PREFIX_ . 'yuju_order_mapping`
                 WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"'
            );
            return $id ?: true;
        }

        return $this->createOrderMapping($prestashop_order_id, $yuju_order_id);
    }

    /**
     * Elimina claim pendiente si falló la creación, para permitir reintento.
     *
     * @param string $yuju_order_id
     * @return void
     */
    protected function releaseClaimedMapping($yuju_order_id)
    {
        $yuju_order_id = (string) $yuju_order_id;
        if ($yuju_order_id === '') {
            return;
        }
        try {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'yuju_order_mapping`
                 WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"
                 AND (prestashop_order_id IS NULL OR prestashop_order_id <= 0)'
            );
        } catch (Exception $e) {
            $this->logger->log('releaseClaimedMapping failed: ' . $e->getMessage(), 'warning');
        } catch (Throwable $e) {
            $this->logger->log('releaseClaimedMapping failed: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Espera a que otro proceso termine de bindear el mapping.
     *
     * @param string $yuju_order_id
     * @param int $seconds
     * @return int PS order id o 0
     */
    protected function waitForMappedOrder($yuju_order_id, $seconds = 8)
    {
        $deadline = microtime(true) + max(1, (int) $seconds);
        while (microtime(true) < $deadline) {
            usleep(250000);
            $psId = $this->findOrderByYujuId($yuju_order_id);
            if ($psId !== null && (int) $psId > 0) {
                return (int) $psId;
            }
        }
        return 0;
    }

    protected function findProductByYujuId($yuju_product_id)
    {
        $mapping = Db::getInstance()->getRow('
        SELECT prestashop_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status
        WHERE yuju_product_id = "' . pSQL($yuju_product_id) . '"
        ');

        return $mapping ? $mapping['prestashop_product_id'] : null;
    }

    protected function findYujuProductId($prestashop_product_id)
    {
        $mapping = Db::getInstance()->getRow('
        SELECT yuju_product_id FROM ' . _DB_PREFIX_ . 'yuju_product_status
        WHERE prestashop_product_id = ' . (int) $prestashop_product_id . '
        ');

        return $mapping ? $mapping['yuju_product_id'] : null;
    }

    protected function createOrderMapping($prestashop_order_id, $yuju_order_id, $extra_data = [])
    {
        $this->ensureOrderMappingSchema();

        $prestashop_order_id = (int) $prestashop_order_id;
        $yuju_order_id = (string) $yuju_order_id;
        $now = date('Y-m-d H:i:s');

        // Nunca NULL ni 0: orden real > 0, o placeholder negativo pendiente
        if ($prestashop_order_id > 0) {
            $psSql = (string) $prestashop_order_id;
        } else {
            $psSql = (string) (int) $this->pendingMappingPlaceholder($yuju_order_id);
        }

        if (!empty($extra_data)) {
            $this->logger->log('Order mapping extra data (not stored in DB): ' . json_encode($extra_data), 'debug');
        }

        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'yuju_order_mapping`
             WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"'
        );

        if ($exists) {
            try {
                $ok = Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'yuju_order_mapping`
                     SET prestashop_order_id = ' . $psSql . ',
                         updated_at = "' . pSQL($now) . '"
                     WHERE id = ' . (int) $exists
                );
            } catch (Exception $e) {
                $ok = false;
                $this->logger->log('createOrderMapping update failed: ' . $e->getMessage(), 'warning');
            } catch (Throwable $e) {
                $ok = false;
                $this->logger->log('createOrderMapping update failed: ' . $e->getMessage(), 'warning');
            }
            return $ok ? (int) $exists : false;
        }

        try {
            $ok = Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'yuju_order_mapping`
                (`prestashop_order_id`, `yuju_order_id`, `created_at`, `updated_at`)
                VALUES (' . $psSql . ', "' . pSQL($yuju_order_id) . '", "' . pSQL($now) . '", "' . pSQL($now) . '")'
            );
        } catch (Exception $e) {
            $ok = false;
            $this->logger->log('createOrderMapping insert failed: ' . $e->getMessage(), 'warning');
        } catch (Throwable $e) {
            $ok = false;
            $this->logger->log('createOrderMapping insert failed: ' . $e->getMessage(), 'warning');
        }

        if ($ok) {
            return (int) Db::getInstance()->Insert_ID();
        }

        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'yuju_order_mapping`
             WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"'
        );
        if ($exists && $prestashop_order_id > 0) {
            try {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'yuju_order_mapping`
                     SET prestashop_order_id = ' . (int) $prestashop_order_id . ',
                         updated_at = "' . pSQL($now) . '"
                     WHERE id = ' . (int) $exists
                );
            } catch (Exception $e) {
                return false;
            } catch (Throwable $e) {
                return false;
            }
            return (int) $exists;
        }

        return false;
    }

    protected function getOrderMapping($prestashop_order_id)
    {
        return Db::getInstance()->getRow('
        SELECT * FROM ' . _DB_PREFIX_ . 'yuju_order_mapping
        WHERE prestashop_order_id = ' . (int) $prestashop_order_id . '
        ');
    }

    protected function mapYujuStatusToPrestaShop($yuju_status, $progress = null)
    {
        // Si hay progress, determinar el estado más avanzado
        if (!empty($progress) && is_array($progress)) {
            $current_status = $this->getStatusFromProgress($progress);
            if ($current_status) {
                $yuju_status = $current_status;
            }
        }

        $yuju_status = strtolower(trim((string) $yuju_status));
        if ($yuju_status === 'cancelled') {
            $yuju_status = 'canceled';
        }

        return isset($this->status_mapping['yuju_to_ps'][$yuju_status])
            ? (int) $this->status_mapping['yuju_to_ps'][$yuju_status]
            : (int) Configuration::get('PS_OS_PREPARATION');
    }
    
    /**
     * Determina el estado actual basándose en el array progress de Yuju
     * 
     * @param array $progress Array de estados con status (done/pending)
     * @return string|null Estado de Yuju (paid, ready_to_ship, shipped, delivered)
     */
    protected function getStatusFromProgress($progress)
    {
        // Buscar el último estado con status "done"
        $last_done = null;
        foreach ($progress as $step) {
            if (isset($step['status']) && $step['status'] === 'done' && isset($step['name'])) {
                $last_done = $step['name'];
            }
        }
        
        // Mapear el estado de progress a un estado de Yuju
        switch ($last_done) {
            case 'paid':
                return 'paid'; // Pagado
            case 'ready_to_ship':
                return 'ready_to_ship'; // Listo para enviar
            case 'shipped':
                return 'shipped'; // Enviado
            case 'delivered':
                return 'delivered'; // Entregado
            default:
                return null; // Usar el estado original
        }
    }

    protected function mapPrestaShopStatusToYuju($ps_status)
    {
        return isset($this->status_mapping['ps_to_yuju'][$ps_status])
            ? $this->status_mapping['ps_to_yuju'][$ps_status]
            : 'processing';
    }

    protected function getCurrencyId($currency_code)
    {
        $currency = Currency::getIdByIsoCode($currency_code);

        return $currency ? $currency : Configuration::get('PS_CURRENCY_DEFAULT');
    }

    protected function getCarrierId($shipping_method)
    {
        // Carrier dedicado marketplace: no tocar carriers nativos de la tienda
        $shipping_method = 'Yuju';

        $configId = (int) Configuration::get('YUJU_CARRIER_ID');
        if ($configId > 0) {
            $carrier = new Carrier($configId);
            if (Validate::isLoadedObject($carrier) && !(int) $carrier->deleted) {
                if (!(int) $carrier->active) {
                    $carrier->active = 1;
                    $carrier->update();
                }
                return (int) $carrier->id;
            }
        }

        $carrier_id = (int) Db::getInstance()->getValue('
            SELECT c.id_carrier FROM `' . _DB_PREFIX_ . 'carrier` c
            WHERE c.name = "' . pSQL($shipping_method) . '"
              AND c.deleted = 0
            ORDER BY c.active DESC, c.id_carrier DESC
        ');

        if ($carrier_id > 0) {
            Configuration::updateValue('YUJU_CARRIER_ID', $carrier_id);
            return $carrier_id;
        }

        $this->logger->log('Creating Yuju carrier...', 'info');
        $carrier_id = (int) $this->createYujuCarrier();

        if ($carrier_id > 0) {
            Configuration::updateValue('YUJU_CARRIER_ID', $carrier_id);
            return $carrier_id;
        }

        $fallback = (int) Configuration::get('PS_CARRIER_DEFAULT');
        $this->logger->log(
            'Yuju carrier unavailable, falling back to PS_CARRIER_DEFAULT=' . $fallback,
            'error'
        );

        return $fallback;
    }

    /**
     * Crea el transportista "Yuju" usable con envío gratis o con costo
     * (el importe real se aplica en order_carrier por pedido).
     *
     * @return int id_carrier o 0
     */
    protected function createYujuCarrier()
    {
        try {
            $carrier = new Carrier();
            $carrier->name = 'Yuju';
            $carrier->active = 1;
            $carrier->deleted = 0;
            $carrier->is_module = 1;
            $carrier->external_module_name = 'prestashopyuju';
            $carrier->shipping_external = 0;
            $carrier->need_range = 1;
            $carrier->shipping_handling = 0;
            $carrier->range_behavior = 0;
            $carrier->is_free = 0;
            // 2 = SHIPPING_METHOD_PRICE (rango por precio; tarifa base 0, costo real en el pedido)
            $carrier->shipping_method = 2;
            $carrier->max_width = 0;
            $carrier->max_height = 0;
            $carrier->max_depth = 0;
            $carrier->max_weight = 0;
            $carrier->grade = 0;
            $carrier->url = '';

            $languages = Language::getLanguages(false);
            if (empty($languages)) {
                $languages = [['id_lang' => (int) Configuration::get('PS_LANG_DEFAULT')]];
            }
            foreach ($languages as $language) {
                $carrier->delay[(int) $language['id_lang']] = 'Envío gestionado por Yuju / marketplace';
            }

            if (!$carrier->add()) {
                $msg = method_exists($carrier, 'validateFields')
                    ? (string) $carrier->validateFields(false, true)
                    : 'Carrier->add() returned false';
                throw new Exception('Failed to create Yuju carrier: ' . $msg);
            }

            // Referencia estable (PS la usa al editar carriers)
            $carrier->id_reference = (int) $carrier->id;
            $carrier->update();

            // Tiendas (multistore)
            $shopIds = Shop::getContextListShopID();
            if (empty($shopIds)) {
                $shopIds = [(int) $this->context->shop->id];
            }
            foreach ($shopIds as $id_shop) {
                Db::getInstance()->execute(
                    'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'carrier_shop`
                    (`id_carrier`, `id_shop`) VALUES (' . (int) $carrier->id . ', ' . (int) $id_shop . ')'
                );
            }

            // Grupos de clientes
            $groups = Group::getGroups((int) Configuration::get('PS_LANG_DEFAULT'));
            if (is_array($groups)) {
                foreach ($groups as $group) {
                    Db::getInstance()->execute(
                        'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'carrier_group`
                        (`id_carrier`, `id_group`) VALUES (' . (int) $carrier->id . ', ' . (int) $group['id_group'] . ')'
                    );
                }
            }

            // Zonas + rango de precio amplio con tarifa 0
            // (0 = gratis por defecto; el costo real del marketplace va en order_carrier)
            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = (int) $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000000';
            if (!$rangePrice->add()) {
                throw new Exception('Failed to create price range for Yuju carrier');
            }

            $zones = Zone::getZones(true);
            if (!is_array($zones) || empty($zones)) {
                $zones = [['id_zone' => (int) Configuration::get('PS_ZONE_DEFAULT') ?: 1]];
            }

            foreach ($zones as $zone) {
                $id_zone = (int) $zone['id_zone'];
                if ($id_zone <= 0) {
                    continue;
                }
                Db::getInstance()->execute(
                    'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'carrier_zone`
                    (`id_carrier`, `id_zone`) VALUES (' . (int) $carrier->id . ', ' . $id_zone . ')'
                );

                // delivery: precio 0 → flexible (gratis o se sobrescribe en el pedido)
                $id_shop = (int) $this->context->shop->id;
                $id_shop_group = (int) $this->context->shop->id_shop_group;
                Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'delivery`
                    (`id_carrier`, `id_range_price`, `id_range_weight`, `id_zone`, `id_shop`, `id_shop_group`, `price`)
                    VALUES (
                        ' . (int) $carrier->id . ',
                        ' . (int) $rangePrice->id . ',
                        NULL,
                        ' . $id_zone . ',
                        ' . $id_shop . ',
                        ' . $id_shop_group . ',
                        0
                    )'
                );
            }

            // Tax rules: sin impuesto forzado (el costo viene de Yuju)
            if (method_exists($carrier, 'setTaxRulesGroup')) {
                try {
                    $carrier->setTaxRulesGroup(0);
                } catch (Exception $e) {
                    // ignore
                }
            }

            Configuration::updateValue('YUJU_CARRIER_ID', (int) $carrier->id);

            $this->logger->log('Yuju carrier created successfully', [
                'carrier_id' => (int) $carrier->id,
                'range_price_id' => (int) $rangePrice->id,
            ]);

            return (int) $carrier->id;
        } catch (Exception $e) {
            $this->logger->log('Error creating Yuju carrier: ' . $e->getMessage(), 'error');
            return 0;
        } catch (Throwable $e) {
            $this->logger->log('Error creating Yuju carrier: ' . $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * Registra el transportista en el pedido (sección Transportista del BO).
     * Aplica costo de envío de Yuju: 0 = gratis, >0 = con costo.
     *
     * @param Order $order
     * @param float $shipping_cost
     * @param string|null $tracking_number
     * @return bool
     */
    protected function attachOrderCarrier(Order $order, $shipping_cost = 0.0, $tracking_number = null)
    {
        if (!Validate::isLoadedObject($order) || !(int) $order->id) {
            return false;
        }

        $id_carrier = (int) $order->id_carrier;
        if ($id_carrier <= 0) {
            $id_carrier = $this->getCarrierId('Yuju');
            $order->id_carrier = $id_carrier;
            $order->update();
        }

        $shipping = round((float) $shipping_cost, 6);
        if ($shipping < 0) {
            $shipping = 0;
        }

        $weight = 0.0;
        try {
            if (method_exists($order, 'getTotalWeight')) {
                $weight = (float) $order->getTotalWeight();
            }
        } catch (Exception $e) {
            $weight = 0.0;
        }

        $id_order_carrier = (int) Db::getInstance()->getValue(
            'SELECT id_order_carrier FROM `' . _DB_PREFIX_ . 'order_carrier`
             WHERE id_order = ' . (int) $order->id
        );

        try {
            if ($id_order_carrier > 0) {
                $orderCarrier = new OrderCarrier($id_order_carrier);
            } else {
                $orderCarrier = new OrderCarrier();
                $orderCarrier->id_order = (int) $order->id;
            }

            $orderCarrier->id_carrier = $id_carrier;
            $orderCarrier->id_order_invoice = 0;
            $orderCarrier->weight = $weight;
            $orderCarrier->shipping_cost_tax_excl = $shipping;
            $orderCarrier->shipping_cost_tax_incl = $shipping;
            if ($tracking_number !== null && $tracking_number !== '') {
                $orderCarrier->tracking_number = pSQL((string) $tracking_number);
            }

            $ok = $id_order_carrier > 0 ? $orderCarrier->update() : $orderCarrier->add();
            if (!$ok) {
                // Fallback SQL por si ObjectModel falla
                if ($id_order_carrier > 0) {
                    Db::getInstance()->execute(
                        'UPDATE `' . _DB_PREFIX_ . 'order_carrier`
                         SET id_carrier = ' . $id_carrier . ',
                             shipping_cost_tax_excl = ' . (float) $shipping . ',
                             shipping_cost_tax_incl = ' . (float) $shipping . ',
                             weight = ' . (float) $weight . '
                         WHERE id_order_carrier = ' . $id_order_carrier
                    );
                } else {
                    Db::getInstance()->execute(
                        'INSERT INTO `' . _DB_PREFIX_ . 'order_carrier`
                        (`id_order`, `id_carrier`, `id_order_invoice`, `weight`,
                         `shipping_cost_tax_excl`, `shipping_cost_tax_incl`, `tracking_number`, `date_add`)
                        VALUES (
                            ' . (int) $order->id . ',
                            ' . $id_carrier . ',
                            0,
                            ' . (float) $weight . ',
                            ' . (float) $shipping . ',
                            ' . (float) $shipping . ',
                            "' . pSQL((string) $tracking_number) . '",
                            "' . pSQL(date('Y-m-d H:i:s')) . '"
                        )'
                    );
                }
            }

            // Totales de envío en la orden (gratis o con costo)
            $order->total_shipping = $shipping;
            $order->total_shipping_tax_incl = $shipping;
            $order->total_shipping_tax_excl = $shipping;
            if ($tracking_number) {
                $order->shipping_number = (string) $tracking_number;
            }
            $order->update();

            $this->logger->log(
                'OrderCarrier attached: order=' . (int) $order->id
                . ' carrier=' . $id_carrier
                . ' shipping=' . $shipping,
                'info'
            );

            return true;
        } catch (Exception $e) {
            $this->logger->log('attachOrderCarrier failed: ' . $e->getMessage(), 'error');
            return false;
        } catch (Throwable $e) {
            $this->logger->log('attachOrderCarrier failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Tras venta Yuju → orden PS: descuenta stock en PrestaShop y empuja el stock nuevo a Yuju.
     *
     * @param Order $order
     * @return array
     */
    protected function applyOrderStockDecrementAndPushToYuju(Order $order)
    {
        $report = [
            'decreased' => [],
            'pushed' => [],
            'skipped' => [],
            'errors' => [],
        ];

        if (!Validate::isLoadedObject($order) || !(int) $order->id) {
            $report['errors'][] = 'Orden inválida';
            return $report;
        }

        $lines = [];
        try {
            $lines = $order->getProducts();
        } catch (Exception $e) {
            $lines = [];
        }
        if (empty($lines)) {
            $lines = Db::getInstance()->executeS(
                'SELECT product_id, product_attribute_id, product_quantity, product_reference
                 FROM `' . _DB_PREFIX_ . 'order_detail`
                 WHERE id_order = ' . (int) $order->id
            );
        }
        if (!is_array($lines) || empty($lines)) {
            $report['errors'][] = 'Sin líneas de pedido para descontar stock';
            return $report;
        }

        $id_shop = (int) $order->id_shop;
        if ($id_shop <= 0) {
            $id_shop = (int) $this->context->shop->id;
        }

        // Agrupar por producto+atributo (por si hay líneas duplicadas)
        $qtyByKey = [];
        foreach ($lines as $line) {
            $id_product = (int) ($line['product_id'] ?? $line['id_product'] ?? 0);
            $id_attr = (int) ($line['product_attribute_id'] ?? $line['id_product_attribute'] ?? 0);
            $qty = (int) ($line['product_quantity'] ?? $line['cart_quantity'] ?? 0);
            if ($id_product <= 0 || $qty <= 0) {
                continue;
            }
            $key = $id_product . ':' . $id_attr;
            if (!isset($qtyByKey[$key])) {
                $qtyByKey[$key] = [
                    'id_product' => $id_product,
                    'id_product_attribute' => $id_attr,
                    'qty' => 0,
                ];
            }
            $qtyByKey[$key]['qty'] += $qty;
        }

        self::$suppressStockHookToYuju = true;
        $productsToPush = []; // id_product => true (push stock total del producto)

        try {
            foreach ($qtyByKey as $row) {
                $id_product = (int) $row['id_product'];
                $id_attr = (int) $row['id_product_attribute'];
                $qty = (int) $row['qty'];

                try {
                    $before = (int) StockAvailable::getQuantityAvailableByProduct(
                        $id_product,
                        $id_attr > 0 ? $id_attr : null,
                        $id_shop
                    );

                    // Delta negativo = venta
                    StockAvailable::updateQuantity($id_product, $id_attr, -$qty, $id_shop);

                    $after = (int) StockAvailable::getQuantityAvailableByProduct(
                        $id_product,
                        $id_attr > 0 ? $id_attr : null,
                        $id_shop
                    );

                    $report['decreased'][] = [
                        'id_product' => $id_product,
                        'id_product_attribute' => $id_attr,
                        'qty' => $qty,
                        'stock_before' => $before,
                        'stock_after' => $after,
                    ];
                    $productsToPush[$id_product] = true;

                    $this->logger->log(
                        'Stock decreased for order ' . (int) $order->id
                        . ': product=' . $id_product
                        . ' attr=' . $id_attr
                        . ' -' . $qty
                        . ' (' . $before . ' → ' . $after . ')',
                        'info'
                    );
                } catch (Exception $e) {
                    $report['errors'][] = 'product ' . $id_product . ': ' . $e->getMessage();
                    $this->logger->log(
                        'Failed to decrease stock product ' . $id_product . ': ' . $e->getMessage(),
                        'error'
                    );
                }
            }

            foreach (array_keys($productsToPush) as $id_product) {
                $pushResult = $this->pushProductStockToYuju((int) $id_product, $id_shop);
                if (!empty($pushResult['skipped'])) {
                    $report['skipped'][] = $pushResult;
                } elseif (!empty($pushResult['success'])) {
                    $report['pushed'][] = $pushResult;
                } else {
                    $report['errors'][] = $pushResult;
                }
            }
        } finally {
            self::$suppressStockHookToYuju = false;
        }

        return $report;
    }

    /**
     * Empuja el stock actual de un producto PS a Yuju (PUT product stock).
     *
     * @param int $prestashop_product_id
     * @param int $id_shop
     * @return array
     */
    protected function pushProductStockToYuju($prestashop_product_id, $id_shop = null)
    {
        $prestashop_product_id = (int) $prestashop_product_id;
        $result = [
            'success' => false,
            'prestashop_product_id' => $prestashop_product_id,
            'yuju_product_id' => null,
            'stock' => null,
            'skipped' => false,
            'message' => '',
        ];

        if ($prestashop_product_id <= 0) {
            $result['skipped'] = true;
            $result['message'] = 'Invalid product id';
            return $result;
        }

        $yuju_product_id = $this->findYujuProductId($prestashop_product_id);
        if (!$yuju_product_id) {
            $result['skipped'] = true;
            $result['message'] = 'Product not mapped to Yuju';
            $this->logger->log(
                'Stock push skipped: PS product ' . $prestashop_product_id . ' has no yuju_product_id',
                'warning'
            );
            return $result;
        }

        $result['yuju_product_id'] = $yuju_product_id;
        $stock = (int) StockAvailable::getQuantityAvailableByProduct(
            $prestashop_product_id,
            null,
            $id_shop ? (int) $id_shop : null
        );
        if ($stock < 0) {
            $stock = 0;
        }
        $result['stock'] = $stock;

        try {
            Db::getInstance()->update(
                'yuju_product_status',
                [
                    'sync_status' => pSQL('updating_in_yuju'),
                    'last_error' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'prestashop_product_id = ' . $prestashop_product_id
            );

            $payload = ['stock' => $stock];
            $api = $this->api_client ?: new YujuApiClient();
            $start_time = microtime(true);
            $response = $api->updateProduct($yuju_product_id, $payload);
            $sync_duration = microtime(true) - $start_time;
            $ok = is_array($response) && !empty($response['success']);

            Db::getInstance()->update(
                'yuju_product_status',
                [
                    'sync_status' => pSQL($ok ? 'synced' : 'synced_with_errors'),
                    'last_error' => $ok ? null : pSQL($response['message'] ?? 'Stock push failed'),
                    'last_sync_at' => $ok ? date('Y-m-d H:i:s') : null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'prestashop_product_id = ' . $prestashop_product_id
            );

            $result['success'] = $ok;
            $result['message'] = $ok
                ? 'Stock pushed to Yuju'
                : ($response['message'] ?? 'Stock push failed');
            $result['api_response'] = $response;

            try {
                require_once dirname(__FILE__) . '/YujuProductManager.php';
                $pm = new YujuProductManager();
                $pm->logSyncHistory([
                    'prestashop_product_id' => $prestashop_product_id,
                    'yuju_product_id' => $yuju_product_id,
                    'sync_direction' => 'to_yuju',
                    'action' => 'update',
                    'status' => $ok ? 'success' : 'error',
                    'http_status_code' => (int) ($response['http_code'] ?? 0),
                    'request_data' => $pm->buildHistoryRequestPayload($payload, [
                        'origin' => 'venta',
                        'changed_fields' => ['stock'],
                        'action' => 'update',
                        'new_values' => ['stock' => $stock],
                        'priority' => 'high',
                    ]),
                    'response_data' => json_encode($response),
                    'error_message' => $ok ? '' : ($response['message'] ?? 'Stock push failed'),
                    'sync_duration' => $sync_duration,
                    'created_by' => 'venta',
                ]);
            } catch (Exception $histEx) {
                $this->logger->log(
                    'pushProductStockToYuju: historial no guardado: ' . $histEx->getMessage(),
                    'warning'
                );
            }

            $this->logger->log(
                ($ok ? 'Stock pushed' : 'Stock push FAILED')
                . ' PS=' . $prestashop_product_id
                . ' Yuju=' . $yuju_product_id
                . ' stock=' . $stock
                . ($ok ? '' : (' err=' . $result['message'])),
                $ok ? 'info' : 'error'
            );
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $this->logger->log('pushProductStockToYuju exception: ' . $e->getMessage(), 'error');
        }

        return $result;
    }

    protected function getCountryId($country_code)
    {
        $country_id = Country::getByIso($country_code);

        return $country_id ? $country_id : Configuration::get('PS_COUNTRY_DEFAULT');
    }

    protected function getStateId($state_code, $country_id)
    {
        if (empty($state_code)) {
            return 0;
        }

        $state_id = Db::getInstance()->getValue('
        SELECT id_state FROM ' . _DB_PREFIX_ . 'state
        WHERE iso_code = "' . pSQL($state_code) . '" AND id_country = ' . (int) $country_id . '
        ');

        return $state_id ? $state_id : 0;
    }

    protected function generateOrderReference($order_data = null)
    {
        $prefix = YujuConfig::get('YUJU_ORDER_PREFIX', null) ?: 'YJ';

        // Preferir la referencia estable de Yuju (evita IDs opacos y ayuda a detectar duplicados)
        if (is_array($order_data) && !empty($order_data['reference'])) {
            $ref = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $order_data['reference']);
            $ref = substr($ref, 0, 32);
            if ($ref !== '') {
                $exists = (int) Db::getInstance()->getValue(
                    'SELECT id_order FROM ' . _DB_PREFIX_ . 'orders
                     WHERE reference = "' . pSQL($ref) . '"'
                );
                if (!$exists) {
                    return $ref;
                }
                // Ya usada (p.ej. orden duplicada histórica): sufijo corto único
                return substr($ref, 0, 24) . '-' . substr((string) time(), -4);
            }
        }

        $timestamp = time();
        $random = mt_rand(1000, 9999);

        return $prefix . '-' . $timestamp . '-' . $random;
    }

    /**
     * Add order history entry.
     */
    protected function addOrderHistory($order_id, $status_id, $message = '')
    {
        if (!class_exists('OrderHistory')) {
            throw new Exception('PrestaShop OrderHistory class not loaded');
        }
        
        // Obtener o crear empleado Yuju
        $employee = $this->getOrCreateYujuEmployee();
        
        // Asignar empleado al contexto si no hay uno
        if (!isset($this->context->employee) || !$this->context->employee->id) {
            $this->context->employee = $employee;
        }
        
        // Cargar la orden
        $order = new Order((int)$order_id);
        if (!Validate::isLoadedObject($order)) {
            throw new Exception('Order not found: ' . $order_id);
        }
        
        // CRÍTICO: Verificar si existen OrderDetail, si no, crearlos desde el carrito
        try {
            $order_details_count = Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_detail` 
                WHERE id_order = ' . (int)$order_id
            );
            
            $this->logger->log('OrderDetail count for order ' . $order_id . ': ' . $order_details_count, 'debug');
        } catch (Exception $e) {
            $this->logger->log('Error checking OrderDetail count: ' . $e->getMessage(), 'error');
            throw $e;
        }
        
        if ($order_details_count == 0) {
            $this->logger->log('No OrderDetail found, creating from cart ' . $order->id_cart, 'warning');
            
            // Cargar el carrito
            $cart = new Cart((int)$order->id_cart);
            if (Validate::isLoadedObject($cart)) {
                $products = $cart->getProducts();
                
                foreach ($products as $product) {
                    $order_detail = new OrderDetail();
                    $order_detail->id_order = (int)$order_id;
                    $order_detail->product_id = (int)$product['id_product'];
                    $order_detail->product_attribute_id = (int)$product['id_product_attribute'];
                    $order_detail->product_name = $product['name'];
                    $order_detail->product_quantity = (int)$product['cart_quantity'];
                    $order_detail->product_price = (float)$product['price'];
                    $order_detail->unit_price_tax_incl = (float)$product['price_wt'];
                    $order_detail->unit_price_tax_excl = (float)$product['price'];
                    $order_detail->total_price_tax_incl = (float)$product['total_wt'];
                    $order_detail->total_price_tax_excl = (float)$product['total'];
                    $order_detail->product_reference = isset($product['reference']) ? $product['reference'] : '';
                    $order_detail->product_ean13 = isset($product['ean13']) ? $product['ean13'] : '';
                    $order_detail->product_upc = isset($product['upc']) ? $product['upc'] : '';
                    $order_detail->product_weight = isset($product['weight']) ? (float)$product['weight'] : 0;
                    $order_detail->id_warehouse = 0; // Warehouse por defecto (0 = sin warehouse específico)
                    $order_detail->id_shop = (int)$this->context->shop->id;
                    $order_detail->original_product_price = (float)$product['price'];
                    $order_detail->ecotax = 0;
                    $order_detail->reduction_percent = 0;
                    $order_detail->reduction_amount = 0;
                    $order_detail->reduction_amount_tax_incl = 0;
                    $order_detail->reduction_amount_tax_excl = 0;
                    $order_detail->group_reduction = 0;
                    $order_detail->product_quantity_discount = 0;
                    $order_detail->product_quantity_refunded = 0;
                    $order_detail->product_quantity_return = 0;
                    $order_detail->product_quantity_reinjected = 0;
                    $order_detail->download_hash = '';
                    $order_detail->download_nb = 0;
                    $order_detail->download_deadline = '0000-00-00 00:00:00';
                    
                    if ($order_detail->add()) {
                        $this->logger->log('OrderDetail created for product ' . $product['id_product'], 'info');
                    } else {
                        $errors = $order_detail->getErrors();
                        $error_msg = !empty($errors) ? implode(', ', $errors) : 'Unknown error';
                        $this->logger->log('Failed to create OrderDetail for product ' . $product['id_product'] . ': ' . $error_msg, 'error');
                    }
                }
            }
        }
        
        // Primero actualizar el current_state en la tabla orders
        Db::getInstance()->update(
            'orders',
            ['current_state' => (int)$status_id],
            'id_order = ' . (int)$order_id
        );
        
        // Verificar si ya existe un OrderHistory para esta orden
        try {
            $existing_history = Db::getInstance()->getValue(
                'SELECT id_order_history FROM `' . _DB_PREFIX_ . 'order_history` 
                WHERE id_order = ' . (int)$order_id
            );
            
            $this->logger->log('Existing OrderHistory check for order ' . $order_id . ': ' . ($existing_history ? 'Found ID ' . $existing_history : 'None'), 'debug');
        } catch (Exception $e) {
            $this->logger->log('Error checking OrderHistory: ' . $e->getMessage(), 'error');
            throw $e;
        }
        
        if ($existing_history) {
            $this->logger->log('OrderHistory already exists for order ' . $order_id, 'info');
            return new OrderHistory($existing_history);
        }
        
        // Crear el registro de historial
        $order_history = new OrderHistory();
        $order_history->id_order = (int)$order_id;
        $order_history->id_order_state = (int)$status_id;
        $order_history->id_employee = (int)$employee->id;
        $order_history->date_add = date('Y-m-d H:i:s');
        
        // Usar método simple add() ya que los OrderDetail ya están creados
        if (!$order_history->add()) {
            throw new Exception('Failed to add order history record');
        }
        
        $this->logger->log('Order history added for order ' . $order_id . ' with state ' . $status_id, 'info');
        
        return $order_history;
    }
    
    /**
     * Obtiene o crea el empleado "Empleado Yuju" para operaciones automáticas
     * 
     * @return Employee
     */
    protected function getOrCreateYujuEmployee()
    {
        if (!class_exists('Employee')) {
            throw new Exception('PrestaShop Employee class not loaded');
        }
        
        $email = 'yuju@automated.local';
        
        // Buscar empleado existente por email usando consulta SQL
        $employee_id = Db::getInstance()->getValue(
            'SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` 
            WHERE email = "' . pSQL($email) . '"'
        );
        
        if ($employee_id) {
            $employee = new Employee($employee_id);
            if (Validate::isLoadedObject($employee)) {
                return $employee;
            }
        }
        
        // Si no existe, crear empleado nuevo
        $this->logger->log('Creating Yuju employee for automated operations', 'info');
        
        $employee = new Employee();
        $employee->firstname = 'Empleado';
        $employee->lastname = 'Yuju';
        $employee->email = $email;
        $employee->passwd = md5(pSQL(_COOKIE_KEY_ . uniqid('yuju_', true)));
        $employee->active = 1;
        $employee->id_profile = 1; // SuperAdmin profile
        $employee->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $employee->default_tab = 1;
        
        if (!$employee->add()) {
            $this->logger->log('Failed to create Yuju employee, using default employee', 'warning');
            // Intentar obtener cualquier empleado activo
            $default_employee_id = Db::getInstance()->getValue(
                'SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` 
                WHERE active = 1 
                ORDER BY id_employee ASC'
            );
            
            if ($default_employee_id) {
                return new Employee($default_employee_id);
            }
            
            throw new Exception('No employee found or created for order history');
        }
        
        $this->logger->log('Yuju employee created successfully with ID: ' . $employee->id, 'info');
        
        return $employee;
    }
    
    /**
     * Método alternativo para actualizar el estado de una orden
     */
    protected function updateOrderState($order_id, $status_id)
    {
        if (!class_exists('OrderHistory')) {
            throw new Exception('PrestaShop OrderHistory class not loaded');
        }

        $order_id = (int) $order_id;
        $status_id = (int) $status_id;
        if ($order_id <= 0 || $status_id <= 0) {
            return false;
        }

        // Idempotente: no duplicar historial si ya está en ese estado
        if ($this->orderAlreadyHasState($order_id, $status_id)) {
            $this->logger->log(
                'updateOrderState skipped (same state): order ' . $order_id . ' status ' . $status_id,
                'info'
            );
            return true;
        }
        
        // Obtener o crear empleado Yuju
        $employee = $this->getOrCreateYujuEmployee();
        
        // Crear el registro de historial manualmente
        $order_history = new OrderHistory();
        $order_history->id_order = $order_id;
        $order_history->id_order_state = $status_id;
        $order_history->id_employee = (int) $employee->id;
        $order_history->date_add = date('Y-m-d H:i:s');
        
        if (!$order_history->add()) {
            throw new Exception('Failed to add order history record');
        }
        
        // Actualizar el estado actual de la orden
        Db::getInstance()->update(
            'orders',
            ['current_state' => $status_id],
            'id_order = ' . $order_id
        );
        
        $this->logger->log('Order state updated using alternative method for order ' . $order_id, 'info');
        
        return true;
    }
    
    /**
     * Elimina una orden de PrestaShop
     * 
     * @param int $order_id ID de la orden a eliminar
     * @param bool $delete_all Si es true, elimina también cliente, direcciones y carrito
     * @return array Resultado de la operación
     */
    public function deleteOrder($order_id, $delete_all = false)
    {
        $this->logger->log('Deleting order ' . $order_id . ' (delete_all=' . ($delete_all ? 'true' : 'false') . ')', 'info');
        
        $result = [
            'success' => false,
            'order_deleted' => false,
            'customer_deleted' => false,
            'addresses_deleted' => [],
            'cart_deleted' => false,
            'errors' => [],
        ];
        
        try {
            // Cargar la orden
            if (!class_exists('Order')) {
                throw new Exception('Order class not loaded');
            }
            
            $order = new Order((int)$order_id);
            
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Order not found with ID: ' . $order_id);
            }
            
            // Guardar IDs para eliminar después si delete_all es true
            $customer_id = $order->id_customer;
            $cart_id = $order->id_cart;
            $address_delivery_id = $order->id_address_delivery;
            $address_invoice_id = $order->id_address_invoice;
            
            // 1. Eliminar OrderHistory
            try {
                Db::getInstance()->delete('order_history', 'id_order = ' . (int)$order_id);
                $this->logger->log('Order history deleted for order ' . $order_id, 'info');
            } catch (Exception $e) {
                $result['errors'][] = 'Failed to delete order history: ' . $e->getMessage();
            }
            
            // 2. Eliminar OrderDetail
            try {
                Db::getInstance()->delete('order_detail', 'id_order = ' . (int)$order_id);
                $this->logger->log('Order details deleted for order ' . $order_id, 'info');
            } catch (Exception $e) {
                $result['errors'][] = 'Failed to delete order details: ' . $e->getMessage();
            }
            
            // 3. Eliminar OrderPayment
            try {
                Db::getInstance()->delete('order_payment', 'order_reference = "' . pSQL($order->reference) . '"');
                $this->logger->log('Order payments deleted for order ' . $order_id, 'info');
            } catch (Exception $e) {
                $result['errors'][] = 'Failed to delete order payments: ' . $e->getMessage();
            }
            
            // 4. Eliminar mapping de Yuju
            try {
                Db::getInstance()->delete('yuju_order_mapping', 'prestashop_order_id = ' . (int)$order_id);
                $this->logger->log('Yuju order mapping deleted for order ' . $order_id, 'info');
            } catch (Exception $e) {
                $result['errors'][] = 'Failed to delete order mapping: ' . $e->getMessage();
            }
            
            // 5. Eliminar la orden
            if ($order->delete()) {
                $result['order_deleted'] = true;
                $this->logger->log('Order ' . $order_id . ' deleted successfully', 'info');
            } else {
                throw new Exception('Failed to delete order');
            }
            
            // Si delete_all es true, eliminar recursos asociados
            if ($delete_all) {
                // Eliminar carrito
                if ($cart_id) {
                    try {
                        $cart = new Cart($cart_id);
                        if (Validate::isLoadedObject($cart)) {
                            if ($cart->delete()) {
                                $result['cart_deleted'] = true;
                                $this->logger->log('Cart ' . $cart_id . ' deleted', 'info');
                            }
                        }
                    } catch (Exception $e) {
                        $result['errors'][] = 'Failed to delete cart: ' . $e->getMessage();
                    }
                }
                
                // Eliminar direcciones
                $addresses_to_delete = array_unique([$address_delivery_id, $address_invoice_id]);
                foreach ($addresses_to_delete as $address_id) {
                    if ($address_id) {
                        try {
                            $address = new Address($address_id);
                            if (Validate::isLoadedObject($address)) {
                                // Verificar que no haya otras órdenes usando esta dirección
                                $other_orders = Db::getInstance()->getValue(
                                    'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'orders 
                                    WHERE (id_address_delivery = ' . (int)$address_id . ' 
                                    OR id_address_invoice = ' . (int)$address_id . ')
                                    AND id_order != ' . (int)$order_id
                                );
                                
                                if ($other_orders == 0) {
                                    if ($address->delete()) {
                                        $result['addresses_deleted'][] = $address_id;
                                        $this->logger->log('Address ' . $address_id . ' deleted', 'info');
                                    }
                                } else {
                                    $this->logger->log('Address ' . $address_id . ' not deleted (used by ' . $other_orders . ' other orders)', 'warning');
                                }
                            }
                        } catch (Exception $e) {
                            $result['errors'][] = 'Failed to delete address ' . $address_id . ': ' . $e->getMessage();
                        }
                    }
                }
                
                // Eliminar cliente (solo si no tiene otras órdenes)
                if ($customer_id) {
                    try {
                        $customer = new Customer($customer_id);
                        if (Validate::isLoadedObject($customer)) {
                            // Verificar que no haya otras órdenes de este cliente
                            $other_orders = Db::getInstance()->getValue(
                                'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'orders 
                                WHERE id_customer = ' . (int)$customer_id . '
                                AND id_order != ' . (int)$order_id
                            );
                            
                            if ($other_orders == 0) {
                                if ($customer->delete()) {
                                    $result['customer_deleted'] = true;
                                    $this->logger->log('Customer ' . $customer_id . ' deleted', 'info');
                                }
                            } else {
                                $this->logger->log('Customer ' . $customer_id . ' not deleted (has ' . $other_orders . ' other orders)', 'warning');
                            }
                        }
                    } catch (Exception $e) {
                        $result['errors'][] = 'Failed to delete customer: ' . $e->getMessage();
                    }
                }
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->logger->log('Failed to delete order: ' . $e->getMessage(), 'error');
        }
        
        return $result;
    }
}
