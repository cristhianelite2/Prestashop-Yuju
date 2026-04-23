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
require_once dirname(__FILE__) . '/../config/config.php';

class YujuOrderManager
{
    private $api_client;
    private $logger;
    private $status_mapping;
    private $context;

    public function __construct($context = null)
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->context = $context ?: Context::getContext();
        $this->loadStatusMapping();
    }

    /**
     * Load order status mapping configuration.
     */
    protected function loadStatusMapping()
    {
        $this->status_mapping = [
            'ps_to_yuju' => [
                '1' => 'pending',           // Payment accepted
                '2' => 'processing',        // Payment error
                '3' => 'processing',        // Preparation in progress
                '4' => 'shipped',           // Shipped
                '5' => 'delivered',         // Delivered
                '6' => 'cancelled',         // Canceled
                '7' => 'refunded',          // Refunded
                '8' => 'error',             // Payment error
                '9' => 'processing',        // On backorder (paid)
                '10' => 'processing',       // Awaiting bank wire payment
                '11' => 'processing',       // Remote payment accepted
                '12' => 'processing',       // On backorder (not paid)
            ],
            'yuju_to_ps' => [
                'pending' => '1',           // Payment accepted
                'processing' => '3',        // Preparation in progress
                'shipped' => '4',           // Shipped
                'delivered' => '5',         // Delivered
                'cancelled' => '6',         // Canceled
                'refunded' => '7',          // Refunded
                'error' => '8',             // Payment error
                // Progress-based states from Yuju
                'paid' => '2',              // PS_OS_PAYMENT - Payment accepted
                'ready_to_ship' => '3',     // PS_OS_PREPARATION - Preparation in progress
            ],
        ];

        // Load custom mappings from database if they exist
        $custom_mappings = Db::getInstance()->executeS('
        SELECT prestashop_status_id, yuju_status_name
        FROM ' . _DB_PREFIX_ . 'yuju_order_status_mapping
        ');

        foreach ($custom_mappings as $mapping) {
            $this->status_mapping['ps_to_yuju'][$mapping['prestashop_status_id']] = $mapping['yuju_status_name'];
            $this->status_mapping['yuju_to_ps'][$mapping['yuju_status_name']] = $mapping['prestashop_status_id'];
        }
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
     * @param array $yuju_data Datos originales de Yuju
     * @return array Datos adaptados
     */
    protected function adaptYujuOrderData($yuju_data)
    {
        // LOG: Ver estructura original recibida
        $this->logger->log('adaptYujuOrderData - ORIGINAL DATA STRUCTURE: Has order_details=' . (isset($yuju_data['order_details']) ? 'YES' : 'NO') . 
            ' | Has customer in root=' . (isset($yuju_data['customer']) ? 'YES' : 'NO') . 
            ' | Has shipping_address in root=' . (isset($yuju_data['shipping_address']) ? 'YES' : 'NO'), 'debug');
        
        // Normalizar estructura: Si tiene order_details, extraer datos de allí
        // pero mantener customer y shipping_address de la raíz si existen
        if (isset($yuju_data['order_details'])) {
            $order_details = $yuju_data['order_details'];
            
            // Mantener customer y shipping_address de la raíz si existen, sino tomar de order_details
            $yuju_data = array_merge($order_details, [
                'customer' => $yuju_data['customer'] ?? $order_details['customer'] ?? [],
                'shipping_address' => $yuju_data['shipping_address'] ?? $order_details['shipping_address'] ?? [],
                'billing_address' => $yuju_data['billing_address'] ?? $order_details['billing_address'] ?? null,
                'items' => $yuju_data['items'] ?? $order_details['items'] ?? [],
            ]);
            
            $this->logger->log('adaptYujuOrderData - AFTER NORMALIZATION: customer=' . json_encode($yuju_data['customer']) . 
                ' | shipping_address=' . json_encode($yuju_data['shipping_address']), 'debug');
        }
        
        // Mapear nombre del país a código ISO
        $country_map = [
            'México' => 'MX',
            'Mexico' => 'MX',
            'Estados Unidos' => 'US',
            'United States' => 'US',
            'Colombia' => 'CO',
            'Chile' => 'CL',
            'Argentina' => 'AR',
            'Perú' => 'PE',
            'Peru' => 'PE',
            'Brasil' => 'BR',
            'Brazil' => 'BR',
        ];
        
        // Extraer país y convertir a código
        $country_name = isset($yuju_data['shipping_address']['country']) ? $yuju_data['shipping_address']['country'] : '';
        $country_code = isset($country_map[$country_name]) ? $country_map[$country_name] : 'MX'; // Default MX
        
        // Extraer nombres del cliente - SIEMPRE de customer
        $customer_first_name = !empty($yuju_data['customer']['first_name']) ? trim($yuju_data['customer']['first_name']) : 'Not Found';
        $customer_last_name = !empty($yuju_data['customer']['last_name']) ? trim($yuju_data['customer']['last_name']) : 'Not Found';
        
        // Extraer nombres de dirección - usar shipping_address solo si tiene last_name válido, sino usar customer
        // Yuju a veces pone el nombre completo en first_name y deja last_name vacío
        $address_first_name = $customer_first_name;
        $address_last_name = $customer_last_name;
        
        if (!empty($yuju_data['shipping_address']['first_name']) && !empty($yuju_data['shipping_address']['last_name'])) {
            // Si shipping_address tiene ambos nombres, usarlos
            $address_first_name = trim($yuju_data['shipping_address']['first_name']);
            $address_last_name = trim($yuju_data['shipping_address']['last_name']);
        }
        
        // Construir estructura adaptada
        $adapted = [
            'id' => $yuju_data['id_order'] ?? $yuju_data['reference'] ?? uniqid('yuju_'),
            'reference' => $yuju_data['reference'] ?? $yuju_data['id_order'] ?? '',
            'status' => $yuju_data['status'] ?? 'open',
            'progress' => $yuju_data['progress'] ?? null, // Array de progreso de Yuju
            'currency' => strtoupper($yuju_data['currency'] ?? 'MXN'),
            'payment_method' => $yuju_data['payment_method'] ?? 'Yuju',
            'shipping_method' => 'Yuju Shipping',
            'created_at' => isset($yuju_data['order_created_at']) ? date('Y-m-d H:i:s', strtotime($yuju_data['order_created_at'])) : date('Y-m-d H:i:s'),
            
            // Totales - IMPORTANTE: paid_total incluye shipping_cost, total NO lo incluye
            // paid_total = total + shipping_cost
            // Ejemplo: total=$3000, shipping_cost=$200, paid_total=$3200
            'total_amount' => floatval($yuju_data['paid_total'] ?? ($yuju_data['total'] ?? 0) + ($yuju_data['shipping_cost'] ?? 0)),
            'total_amount_tax_excl' => floatval($yuju_data['paid_total'] ?? ($yuju_data['total'] ?? 0) + ($yuju_data['shipping_cost'] ?? 0)),
            'products_total' => floatval($yuju_data['total'] ?? 0), // Solo productos, sin envío
            'shipping_cost' => floatval($yuju_data['shipping_cost'] ?? 0),
            
            // Cliente - campos requeridos con "Not Found" como fallback
            'customer' => [
                'email' => !empty($yuju_data['customer']['email']) ? $yuju_data['customer']['email'] : 'noemail@yuju.io',
                'first_name' => $customer_first_name,
                'last_name' => $customer_last_name,
                'phone' => $yuju_data['customer']['phone'] ?? '000000000',
            ],
            
            // Dirección de envío - usar SOLO datos de shipping_address
            'shipping_address' => [
                'first_name' => $address_first_name,
                'last_name' => $address_last_name,
                'company' => '',
                'address_line_1' => !empty($yuju_data['shipping_address']['address']) 
                    ? $yuju_data['shipping_address']['address'] 
                    : ((!empty($yuju_data['shipping_address']['street_name']) ? $yuju_data['shipping_address']['street_name'] : 'Not Found') 
                        . (!empty($yuju_data['shipping_address']['street_number']) ? ' ' . $yuju_data['shipping_address']['street_number'] : '')),
                'address_line_2' => (!empty($yuju_data['shipping_address']['neighborhood']) ? $yuju_data['shipping_address']['neighborhood'] . '. ' : '') 
                    . (!empty($yuju_data['shipping_address']['reference']) ? $yuju_data['shipping_address']['reference'] : ''),
                'city' => !empty($yuju_data['shipping_address']['city']) ? $yuju_data['shipping_address']['city'] : 'Not Found',
                'postal_code' => !empty($yuju_data['shipping_address']['postal_code']) ? $yuju_data['shipping_address']['postal_code'] : '00000',
                'country_code' => $country_code,
                'state_code' => '', // Yuju no envía código de estado, solo nombre
                'phone' => $yuju_data['shipping_address']['phone'] ?? $yuju_data['customer']['phone'] ?? '000000000',
                'dni' => $yuju_data['customer']['doc_number'] ?? '00000000', // DNI/RFC/Documento de identidad
            ],
            
            // Items - Procesar items si existen
            'items' => [],
        ];
        
        // Procesar items de la orden
        if (!empty($yuju_data['items']) && is_array($yuju_data['items'])) {
            foreach ($yuju_data['items'] as $item) {
                $adapted['items'][] = [
                    'product_id' => $item['id_product'] ?? $item['product_id'] ?? null,
                    'sku' => $item['sku'] ?? $item['reference'] ?? null,
                    'name' => $item['name'] ?? $item['product_name'] ?? 'Unknown Product',
                    'quantity' => intval($item['quantity'] ?? 1),
                    'unit_price' => floatval($item['unit_price'] ?? $item['price'] ?? 0),
                    'total_price' => floatval($item['total_price'] ?? $item['total'] ?? 0),
                    'product_attribute_id' => $item['product_attribute_id'] ?? null,
                ];
            }
            
            $this->logger->log('Processed ' . count($adapted['items']) . ' items from Yuju order', 'info');
        } else {
            $this->logger->log('No items found in Yuju order data', 'warning');
        }
        
        $this->logger->log('Adapted Yuju order data with ' . count($adapted['items']) . ' items', 'debug');
        
        return $adapted;
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
        
        // Check if order already exists (puede ser 0 para pending_sync o > 0 para orden real)
        // SOLO si NO se está forzando la creación
        if (!$force) {
            $existing_order = $this->findOrderByYujuId($adapted_data['id']);

            if ($existing_order !== null) {
                if ($existing_order > 0) {
                    // Orden real ya existe
                    $this->logger->log('Order already exists with Yuju ID: ' . $adapted_data['id'] . ', order ID: ' . $existing_order, 'warning');
                    return [
                        'success' => false,
                        'message' => 'Order already exists',
                        'yuju_order_id' => $adapted_data['id'],
                        'prestashop_order_id' => $existing_order,
                        'details' => $details,
                    ];
                } else {
                    // Mapping temporal existe (pending_sync)
                    $this->logger->log('Order webhook already received for Yuju ID: ' . $adapted_data['id'] . ' (pending full sync)', 'info');
                    return [
                        'success' => true,
                        'message' => 'Order webhook already received. Waiting for full order sync.',
                        'action_required' => 'fetch_full_order',
                        'yuju_order_id' => $adapted_data['id'],
                        'yuju_reference' => $adapted_data['reference'],
                        'status' => 'pending_sync_duplicate',
                        'details' => $details,
                    ];
                }
            }
        } else {
            // Si se está forzando, eliminar mapping existente si hay
            $existing_order = $this->findOrderByYujuId($adapted_data['id']);
            if ($existing_order !== null) {
                $this->logger->log('Force mode: Deleting existing mapping for Yuju ID: ' . $adapted_data['id'], 'info');
                Db::getInstance()->delete('yuju_order_mapping', 'yuju_order_id = "' . pSQL($adapted_data['id']) . '"');
            }
        }

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
        } catch (Exception $e) {
            $details['customer_error'] = $e->getMessage();
            $this->logger->log('Failed to create customer: ' . $e->getMessage(), 'error');
            // NO lanzar excepción, solo retornar con error
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
            $details['billing_address_id'] = $address->id; // Using same address for billing
            $details['address_status'] = $address_existed ? 'EXISTING' : 'CREATED';
        } catch (Exception $e) {
            $details['shipping_address_error'] = $e->getMessage();
            $details['billing_address_error'] = $e->getMessage();
            $this->logger->log('Failed to create address: ' . $e->getMessage(), 'error');
            // NO lanzar excepción, solo retornar con error
            return [
                'success' => false,
                'message' => 'Failed to create shipping address: ' . $e->getMessage(),
                'details' => $details,
            ];
        }

        // Verificar si hay items para crear el carrito y orden completa
        if (empty($adapted_data['items'])) {
            // Webhook de new-order NO incluye items, solo guardar mapping y retornar
            $this->logger->log('Webhook new-order received without items. Order registered but not created in PrestaShop yet. Yuju Order ID: ' . $adapted_data['id'], 'warning');
            
            // Crear mapping temporal (solo llega aquí si no existía)
            $this->createOrderMapping(0, $adapted_data['id'], [
                'status' => 'pending_sync',
                'yuju_reference' => $adapted_data['reference'],
                'created_at' => date('Y-m-d H:i:s'),
                'webhook_data' => json_encode($order_data),
            ]);
            
            return [
                'success' => true,
                'message' => 'Order webhook received. Full order sync required to create in PrestaShop.',
                'action_required' => 'fetch_full_order',
                'yuju_order_id' => $adapted_data['id'],
                'yuju_reference' => $adapted_data['reference'],
                'details' => $details,
            ];
        }

        // Create cart (solo si hay items)
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
            
            // Obtener info de productos agregados desde los logs del método
            // El método createCartFromOrderData ya loguea cuántos productos se agregaron
        } catch (Exception $e) {
            $details['cart_error'] = $e->getMessage();
            $this->logger->log('Failed to create cart: ' . $e->getMessage(), 'error');
            // NO lanzar excepción, solo retornar con error
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
            $order->id_address_invoice = $address->id;
            $order->id_carrier = $this->getCarrierId($adapted_data['shipping_method']);
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
                $errors = $order->getErrors();
                $error_msg = !empty($errors) 
                    ? 'PrestaShop validation errors: ' . implode(', ', $errors)
                    : 'Order->add() returned false. Possible causes: Invalid order state, missing required fields, or database constraint violations.';
                
                // Log detallado de los datos de la orden
                $this->logger->log('Failed to create order: ' . $error_msg, 'error');
                $this->logger->log('Order data: Customer=' . $customer->id . ', Cart=' . $cart->id . ', Currency=' . $order->id_currency . ', State=' . $order->current_state . ', Shop=' . $order->id_shop, 'error');
                
                throw new Exception($error_msg);
            }
            $details['order_id'] = $order->id;
            $this->logger->log('Order created successfully with ID: ' . $order->id . ' for shop ' . $order->id_shop, 'info');
        } catch (Exception $e) {
            $details['order_error'] = $e->getMessage();
            $this->logger->log('Failed to create order: ' . $e->getMessage(), 'error');
            // NO lanzar excepción, solo retornar con error
            return [
                'success' => false,
                'message' => 'Failed to create order in PrestaShop: ' . $e->getMessage(),
                'details' => $details,
            ];
        }

        // NO agregar order details manualmente ya que los productos están en el carrito
        // PrestaShop los copiará automáticamente cuando se cree el OrderHistory
        
        // Store Yuju order mapping
        try {
            $mapping_id = $this->createOrderMapping($order->id, $adapted_data['id']);
            $details['mapping_id'] = $mapping_id;
        } catch (Exception $e) {
            $details['mapping_error'] = $e->getMessage();
            $this->logger->log('Failed to create mapping: ' . $e->getMessage(), 'warning');
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

        $this->logger->log('Created order from Yuju: PS Order ID ' . $order->id . ', Yuju Order ID ' . $adapted_data['id'], 'info');

        return [
            'success' => true,
            'prestashop_order_id' => $order->id,
            'yuju_order_id' => $adapted_data['id'],
            'details' => $details,
        ];
    }

    /**
     * Update PrestaShop order from Yuju data.
     */
    protected function updateOrderFromYuju($order_data)
    {
        $order = $this->findOrderByYujuId($order_data['id']);

        if (!$order) {
            // If order doesn't exist, create it
            return $this->createOrderFromYuju($order_data);
        }

        // Update order status if changed
        $new_status = $this->mapYujuStatusToPrestaShop($order_data['status']);

        if ($order->current_state != $new_status) {
            $this->updateOrderStatus($order_data);
        }

        // Update tracking information if provided
        if (isset($order_data['tracking_number']) && !empty($order_data['tracking_number'])) {
            $order->shipping_number = $order_data['tracking_number'];
            $order->update();
        }

        $this->logger->log('Updated order from Yuju: PS Order ID ' . $order->id . ', Yuju Order ID ' . $order_data['id'], 'info');

        return [
            'success' => true,
            'order_id' => $order->id,
            'yuju_order_id' => $order_data['id'],
        ];
    }

    /**
     * Update order status.
     */
    protected function updateOrderStatus($order_data)
    {
        $order = $this->findOrderByYujuId($order_data['id']);

        if (!$order) {
            throw new Exception('Order not found with Yuju ID: ' . $order_data['id']);
        }

        $new_status = $this->mapYujuStatusToPrestaShop($order_data['status']);

        if ($order->current_state != $new_status) {
            $order_history = new OrderHistory();
            $order_history->id_order = $order->id;
            $order_history->id_employee = 0; // System update
            $order_history->changeIdOrderState($new_status, $order->id);

            $message = 'Status updated from Yuju: ' . $order_data['status'];

            if (isset($order_data['status_message'])) {
                $message .= ' - ' . $order_data['status_message'];
            }

            $order_history->addWithemail(true, [], $this->context);

            $this->logger->log('Updated order status: PS Order ID ' . $order->id . ' to status ' . $new_status, 'info');
        }

        return [
            'success' => true,
            'order_id' => $order->id,
            'new_status' => $new_status,
        ];
    }

    /**
     * Cancel order.
     */
    protected function cancelOrder($order_data)
    {
        $order = $this->findOrderByYujuId($order_data['id']);

        if (!$order) {
            throw new Exception('Order not found with Yuju ID: ' . $order_data['id']);
        }

        $cancelled_status = (int) Configuration::get('PS_OS_CANCELED');

        $order_history = new OrderHistory();
        $order_history->id_order = $order->id;
        $order_history->id_employee = 0;
        $order_history->changeIdOrderState($cancelled_status, $order->id);

        $message = 'Order cancelled from Yuju';

        if (isset($order_data['cancellation_reason'])) {
            $message .= ' - Reason: ' . $order_data['cancellation_reason'];
        }

        $order_history->addWithemail(true, [], $this->context);

        $this->logger->log('Cancelled order: PS Order ID ' . $order->id, 'info');

        return [
            'success' => true,
            'order_id' => $order->id,
            'status' => 'cancelled',
        ];
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
            // Obtener errores de validación de PrestaShop
            $errors = $customer->getErrors();
            $error_msg = !empty($errors) 
                ? 'PrestaShop validation errors: ' . implode(', ', $errors)
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
        $cart->id_carrier = $this->getCarrierId($order_data['shipping_method']);

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
            
            // Si no existe mapping, buscar por SKU/reference
            if (!$product_id && !empty($item['sku'])) {
                $product_id = $this->findProductBySKU($item['sku']);
                if (!$product_id) {
                    $error_detail = 'Product not found - Yuju ID: ' . $item['product_id'] . ', SKU: ' . ($item['sku'] ?? 'N/A') . ', Name: ' . ($item['name'] ?? 'N/A');
                    $products_failed[] = $error_detail;
                    $this->logger->log($error_detail, 'error');
                    continue;
                }
                $this->logger->log('Product found by SKU: ' . $item['sku'] . ' -> ID: ' . $product_id, 'info');
            } elseif (!$product_id) {
                $error_detail = 'Product not found by Yuju ID: ' . $item['product_id'] . ' and no SKU provided';
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
        $mapping = Db::getInstance()->getRow('
        SELECT prestashop_order_id FROM ' . _DB_PREFIX_ . 'yuju_order_mapping
        WHERE yuju_order_id = "' . pSQL($yuju_order_id) . '"
        ');

        if ($mapping && isset($mapping['prestashop_order_id'])) {
            // Retornar solo el ID, no el objeto Order
            return (int) $mapping['prestashop_order_id'];
        }

        return null;
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
        // Solo guardar los campos que existen en la tabla
        // extra_data se ignora ya que la tabla no tiene columnas adicionales
        $data = [
            'prestashop_order_id' => (int) $prestashop_order_id,
            'yuju_order_id' => pSQL($yuju_order_id),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        // Log de datos extra si se proporcionaron (para debugging)
        if (!empty($extra_data)) {
            $this->logger->log('Order mapping extra data (not stored in DB): ' . json_encode($extra_data), 'debug');
        }
        
        $result = Db::getInstance()->insert('yuju_order_mapping', $data);
        
        if ($result) {
            return Db::getInstance()->Insert_ID();
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
        
        return isset($this->status_mapping['yuju_to_ps'][$yuju_status])
            ? $this->status_mapping['yuju_to_ps'][$yuju_status]
            : Configuration::get('PS_OS_PREPARATION');
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
        // Si no especifica método, usar "Yuju" por defecto
        if (empty($shipping_method)) {
            $shipping_method = 'Yuju';
        }
        
        // Buscar carrier por nombre
        $carrier_id = Db::getInstance()->getValue('
            SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier
            WHERE name = "' . pSQL($shipping_method) . '" AND deleted = 0
        ');

        // Si no existe, crear el carrier "Yuju"
        if (!$carrier_id && $shipping_method === 'Yuju') {
            $this->logger->log('Creating Yuju carrier...', 'info');
            $carrier_id = $this->createYujuCarrier();
        }

        // Si aún no hay carrier, usar el por defecto
        return $carrier_id ? $carrier_id : Configuration::get('PS_CARRIER_DEFAULT');
    }
    
    /**
     * Create Yuju carrier if it doesn't exist
     */
    protected function createYujuCarrier()
    {
        try {
            $carrier = new Carrier();
            $carrier->name = 'Yuju';
            $carrier->delay = [
                Configuration::get('PS_LANG_DEFAULT') => 'Envío gestionado por Yuju'
            ];
            $carrier->active = true;
            $carrier->deleted = false;
            $carrier->shipping_handling = false;
            $carrier->range_behavior = 0;
            $carrier->is_module = false;
            $carrier->shipping_external = false;
            $carrier->external_module_name = '';
            $carrier->need_range = false;
            $carrier->url = '';
            
            // Agregar para todos los idiomas
            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                $carrier->delay[$language['id_lang']] = 'Envío gestionado por Yuju';
            }
            
            if ($carrier->add()) {
                // Crear grupos asociados
                $groups = Group::getGroups(true);
                foreach ($groups as $group) {
                    Db::getInstance()->insert('carrier_group', [
                        'id_carrier' => (int)$carrier->id,
                        'id_group' => (int)$group['id_group']
                    ]);
                }
                
                // Crear zonas (todas las zonas disponibles)
                $zones = Zone::getZones(true);
                foreach ($zones as $zone) {
                    Db::getInstance()->insert('carrier_zone', [
                        'id_carrier' => (int)$carrier->id,
                        'id_zone' => (int)$zone['id_zone']
                    ]);
                }
                
                $this->logger->log('Yuju carrier created successfully', [
                    'carrier_id' => $carrier->id
                ]);
                
                return $carrier->id;
            }
            
            throw new Exception('Failed to create Yuju carrier');
            
        } catch (Exception $e) {
            $this->logger->log('Error creating Yuju carrier: ' . $e->getMessage(), 'error');
            return Configuration::get('PS_CARRIER_DEFAULT');
        }
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
        
        // Obtener o crear empleado Yuju
        $employee = $this->getOrCreateYujuEmployee();
        
        // Crear el registro de historial manualmente
        $order_history = new OrderHistory();
        $order_history->id_order = (int)$order_id;
        $order_history->id_order_state = (int)$status_id;
        $order_history->id_employee = (int)$employee->id;
        $order_history->date_add = date('Y-m-d H:i:s');
        
        if (!$order_history->add()) {
            throw new Exception('Failed to add order history record');
        }
        
        // Actualizar el estado actual de la orden
        Db::getInstance()->update(
            'orders',
            ['current_state' => (int)$status_id],
            'id_order = ' . (int)$order_id
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
