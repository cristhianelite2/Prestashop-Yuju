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
    public function processWebhookOrder($webhook_data)
    {
        $this->logger->log('Processing webhook order: ' . json_encode($webhook_data), 'info');

        try {
            $order_data = $webhook_data['data'];
            $event_type = $webhook_data['event'];

            switch ($event_type) {
                case 'order.created':
                    return $this->createOrderFromYuju($order_data);
                case 'order.updated':
                    return $this->updateOrderFromYuju($order_data);
                case 'order.status_changed':
                    return $this->updateOrderStatus($order_data);
                case 'order.cancelled':
                    return $this->cancelOrder($order_data);
                default:
                    throw new Exception('Unknown webhook event type: ' . $event_type);
            }
        } catch (Exception $e) {
            $this->logger->log('Webhook order processing failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Create PrestaShop order from Yuju data.
     */
    protected function createOrderFromYuju($order_data)
    {
        // Check if order already exists
        $existing_order = $this->findOrderByYujuId($order_data['id']);

        if ($existing_order) {
            throw new Exception('Order already exists with Yuju ID: ' . $order_data['id']);
        }

        // Get or create customer
        $customer = $this->getOrCreateCustomer($order_data['customer']);

        // Get or create address
        $address = $this->getOrCreateAddress($order_data['shipping_address'], $customer->id);

        // Create cart
        $cart = $this->createCartFromOrderData($order_data, $customer->id, $address->id);

        // Create order
        $order = new Order();
        $order->id_customer = $customer->id;
        $order->id_cart = $cart->id;
        $order->id_currency = $this->getCurrencyId($order_data['currency']);
        $order->id_lang = $this->context->language->id;
        $order->id_address_delivery = $address->id;
        $order->id_address_invoice = $address->id;
        $order->id_carrier = $this->getCarrierId($order_data['shipping_method']);
        $order->payment = isset($order_data['payment_method']) ? $order_data['payment_method'] : 'Yuju';
        $order->module = 'prestashopyuju';
        $order->total_paid = (float) $order_data['total_amount'];
        $order->total_paid_tax_incl = (float) $order_data['total_amount'];
        $order->total_paid_tax_excl = (float) $order_data['total_amount_tax_excl'];
        $order->total_products = (float) $order_data['products_total'];
        $order->total_products_wt = (float) $order_data['products_total'];
        $order->total_shipping = (float) $order_data['shipping_cost'];
        $order->total_shipping_tax_incl = (float) $order_data['shipping_cost'];
        $order->total_shipping_tax_excl = (float) $order_data['shipping_cost'];
        $order->reference = $this->generateOrderReference($order_data);
        $order->current_state = $this->mapYujuStatusToPrestaShop($order_data['status']);
        $order->date_add = isset($order_data['created_at']) ? $order_data['created_at'] : date('Y-m-d H:i:s');

        if (!$order->add()) {
            throw new Exception('Failed to create order in PrestaShop');
        }

        // Add order details
        $this->addOrderDetails($order, $order_data['items']);

        // Store Yuju order mapping
        $this->createOrderMapping($order->id, $order_data['id']);

        // Add order history
        $this->addOrderHistory($order->id, $order->current_state, 'Order created from Yuju');

        $this->logger->log('Created order from Yuju: PS Order ID ' . $order->id . ', Yuju Order ID ' . $order_data['id'], 'info');

        return [
            'success' => true,
            'order_id' => $order->id,
            'yuju_order_id' => $order_data['id'],
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
        // Try to find existing customer by email
        $customer_id = Customer::customerExists($customer_data['email'], true);

        if ($customer_id) {
            return new Customer($customer_id);
        }

        // Create new customer
        $customer = new Customer();
        $customer->email = $customer_data['email'];
        $customer->firstname = $customer_data['first_name'];
        $customer->lastname = $customer_data['last_name'];
        $customer->passwd = password_hash(Tools::passwdGen(), PASSWORD_DEFAULT);
        $customer->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $customer->id_lang = $this->context->language->id;
        $customer->active = true;

        if (!$customer->add()) {
            throw new Exception('Failed to create customer');
        }

        return $customer;
    }

    /**
     * Get or create address from order data.
     */
    protected function getOrCreateAddress($address_data, $customer_id)
    {
        $address = new Address();
        $address->id_customer = $customer_id;
        $address->firstname = $address_data['first_name'];
        $address->lastname = $address_data['last_name'];
        $address->company = isset($address_data['company']) ? $address_data['company'] : '';
        $address->address1 = $address_data['address_line_1'];
        $address->address2 = isset($address_data['address_line_2']) ? $address_data['address_line_2'] : '';
        $address->postcode = $address_data['postal_code'];
        $address->city = $address_data['city'];
        $address->id_country = $this->getCountryId($address_data['country_code']);
        $address->id_state = $this->getStateId($address_data['state_code'], $address->id_country);
        $address->phone = isset($address_data['phone']) ? $address_data['phone'] : '';
        $address->alias = 'Yuju Address';

        if (!$address->add()) {
            throw new Exception('Failed to create address');
        }

        return $address;
    }

    /**
     * Create cart from order data.
     */
    protected function createCartFromOrderData($order_data, $customer_id, $address_id)
    {
        $cart = new Cart();
        $cart->id_customer = $customer_id;
        $cart->id_address_delivery = $address_id;
        $cart->id_address_invoice = $address_id;
        $cart->id_lang = $this->context->language->id;
        $cart->id_currency = $this->getCurrencyId($order_data['currency']);
        $cart->id_carrier = $this->getCarrierId($order_data['shipping_method']);

        if (!$cart->add()) {
            throw new Exception('Failed to create cart');
        }

        // Add products to cart
        foreach ($order_data['items'] as $item) {
            $product_id = $this->findProductByYujuId($item['product_id']);

            if ($product_id) {
                $cart->updateQty(
                    (int) $item['quantity'],
                    $product_id,
                    isset($item['product_attribute_id']) ? (int) $item['product_attribute_id'] : null,
                    false,
                    'up'
                );
            }
        }

        return $cart;
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

        if ($mapping) {
            return new Order($mapping['prestashop_order_id']);
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

    protected function createOrderMapping($prestashop_order_id, $yuju_order_id)
    {
        return Db::getInstance()->insert('yuju_order_mapping', [
            'prestashop_order_id' => (int) $prestashop_order_id,
            'yuju_order_id' => pSQL($yuju_order_id),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function getOrderMapping($prestashop_order_id)
    {
        return Db::getInstance()->getRow('
        SELECT * FROM ' . _DB_PREFIX_ . 'yuju_order_mapping
        WHERE prestashop_order_id = ' . (int) $prestashop_order_id . '
        ');
    }

    protected function mapYujuStatusToPrestaShop($yuju_status)
    {
        return isset($this->status_mapping['yuju_to_ps'][$yuju_status])
            ? $this->status_mapping['yuju_to_ps'][$yuju_status]
            : Configuration::get('PS_OS_PREPARATION');
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
        // Try to find carrier by name
        $carrier_id = Db::getInstance()->getValue('
        SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier
        WHERE name = "' . pSQL($shipping_method) . '" AND active = 1
        ');

        return $carrier_id ? $carrier_id : Configuration::get('PS_CARRIER_DEFAULT');
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
        $prefix = Configuration::get('YUJU_ORDER_PREFIX', null) ?: 'YJ';
        $timestamp = time();
        $random = mt_rand(1000, 9999);

        return $prefix . '-' . $timestamp . '-' . $random;
    }

    /**
     * Add order history entry.
     */
    protected function addOrderHistory($order_id, $status_id, $message = '')
    {
        $order_history = new OrderHistory();
        $order_history->id_order = $order_id;
        $order_history->id_order_state = $status_id;
        $order_history->date_add = date('Y-m-d H:i:s');

        if ($message) {
            $order_history->addWithemail(true, [], $this->context);
        } else {
            $order_history->add();
        }

        return $order_history;
    }
}
