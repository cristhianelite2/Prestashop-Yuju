<?php
/**
 * Script de diagnóstico para verificar por qué la orden 23 no aparece en el backoffice
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 23;

try {
    $db = Db::getInstance();
    
    $result = [
        'order_id' => $order_id,
        'checks' => []
    ];
    
    // 1. Verificar si existe la orden en ps_orders
    $order = $db->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'orders WHERE id_order = ' . $order_id);
    $result['checks']['order_exists'] = !empty($order);
    $result['order_data'] = $order;
    
    if (!$order) {
        $result['error'] = 'Order does not exist in ps_orders';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    // 2. Verificar OrderDetail
    $order_details = $db->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'order_detail WHERE id_order = ' . $order_id);
    $result['checks']['order_detail_exists'] = !empty($order_details);
    $result['order_detail_count'] = count($order_details);
    $result['order_details'] = $order_details;
    
    // 3. Verificar OrderHistory
    $order_history = $db->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'order_history WHERE id_order = ' . $order_id);
    $result['checks']['order_history_exists'] = !empty($order_history);
    $result['order_history_count'] = count($order_history);
    $result['order_history'] = $order_history;
    
    // 4. Verificar estado de la orden
    $state_id = $order['current_state'];
    $order_state = $db->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'order_state WHERE id_order_state = ' . $state_id);
    $result['checks']['order_state_exists'] = !empty($order_state);
    $result['order_state'] = $order_state;
    
    // 5. Verificar order_state_lang
    $state_lang = $db->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'order_state_lang WHERE id_order_state = ' . $state_id);
    $result['checks']['order_state_lang_exists'] = !empty($state_lang);
    $result['order_state_lang'] = $state_lang;
    
    // 6. Verificar customer
    $customer = $db->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'customer WHERE id_customer = ' . $order['id_customer']);
    $result['checks']['customer_exists'] = !empty($customer);
    $result['customer'] = $customer;
    
    // 7. Verificar cart
    $cart = $db->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . $order['id_cart']);
    $result['checks']['cart_exists'] = !empty($cart);
    $result['cart'] = $cart;
    
    // 8. Verificar addresses
    $delivery_address = $db->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'address WHERE id_address = ' . $order['id_address_delivery']);
    $invoice_address = $db->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'address WHERE id_address = ' . $order['id_address_invoice']);
    $result['checks']['delivery_address_exists'] = !empty($delivery_address);
    $result['checks']['invoice_address_exists'] = !empty($invoice_address);
    $result['delivery_address'] = $delivery_address;
    $result['invoice_address'] = $invoice_address;
    
    // 9. Verificar si la orden está marcada como deleted o válida
    $result['checks']['order_not_deleted'] = ($order['valid'] == 1);
    $result['checks']['order_valid'] = ($order['valid'] == 1);
    
    // 10. Verificar en qué shop está
    $result['checks']['shop_id'] = $order['id_shop'];
    $result['checks']['current_shop'] = Context::getContext()->shop->id;
    $result['checks']['shop_match'] = ($order['id_shop'] == Context::getContext()->shop->id);
    
    // 11. Cargar usando la clase Order de PrestaShop
    try {
        $ps_order = new Order($order_id);
        $result['checks']['order_loadable'] = Validate::isLoadedObject($ps_order);
        $result['prestashop_order'] = [
            'id' => $ps_order->id,
            'reference' => $ps_order->reference,
            'current_state' => $ps_order->current_state,
            'valid' => $ps_order->valid,
            'deleted' => isset($ps_order->deleted) ? $ps_order->deleted : 'N/A'
        ];
    } catch (Exception $e) {
        $result['checks']['order_loadable'] = false;
        $result['order_load_error'] = $e->getMessage();
    }
    
    // 12. Verificar órdenes que SÍ aparecen en el backoffice
    $visible_orders = $db->executeS('
        SELECT o.id_order, o.reference, o.current_state, o.valid, o.id_shop
        FROM ' . _DB_PREFIX_ . 'orders o
        WHERE o.id_shop = ' . Context::getContext()->shop->id . '
        ORDER BY o.id_order DESC
        LIMIT 5
    ');
    $result['visible_orders_sample'] = $visible_orders;
    
    // Resumen
    $all_checks_passed = true;
    foreach ($result['checks'] as $check => $passed) {
        if (!$passed && $check !== 'shop_match') {
            $all_checks_passed = false;
            break;
        }
    }
    
    $result['summary'] = [
        'all_checks_passed' => $all_checks_passed,
        'likely_issue' => !$all_checks_passed ? 'Missing required data' : 'Unknown - all data exists'
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
