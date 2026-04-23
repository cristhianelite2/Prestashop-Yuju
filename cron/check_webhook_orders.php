<?php
/**
 * 2024 Yuju Integration - Check Webhook Orders Status
 * 
 * Este script verifica el estado de las órdenes recibidas por webhook
 * y muestra si se crearon exitosamente o fallaron.
 */

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

// Include required classes
require_once dirname(__FILE__) . '/../classes/YujuWebhookStorage.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';

// Set content type
header('Content-Type: application/json; charset=utf-8');

$logger = new YujuLogger();
$webhook_storage = new YujuWebhookStorage();

try {
    // Obtener parámetros de filtrado
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $topic_filter = isset($_GET['topic']) ? $_GET['topic'] : null;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null; // 'completed', 'failed', 'pending'
    
    // Obtener todos los webhooks
    $all_webhooks = $webhook_storage->getAllWebhooks();
    
    // Filtrar webhooks de órdenes
    $order_webhooks = array_filter($all_webhooks, function($wh) use ($topic_filter) {
        $is_order = isset($wh['topic']) && strpos($wh['topic'], 'order') !== false;
        
        if ($topic_filter) {
            return $is_order && $wh['topic'] === $topic_filter;
        }
        
        return $is_order;
    });
    
    // Filtrar por estado de procesamiento
    if ($status_filter) {
        $order_webhooks = array_filter($order_webhooks, function($wh) use ($status_filter) {
            if ($status_filter === 'pending') {
                return !isset($wh['processing_status']);
            }
            return isset($wh['processing_status']) && $wh['processing_status'] === $status_filter;
        });
    }
    
    // Ordenar por más recientes primero
    usort($order_webhooks, function($a, $b) {
        $time_a = $a['timestamp'] ?? 0;
        $time_b = $b['timestamp'] ?? 0;
        return $time_b - $time_a;
    });
    
    // Limitar resultados
    $order_webhooks = array_slice($order_webhooks, 0, $limit);
    
    // Procesar cada webhook para extraer información relevante
    $processed_orders = [];
    foreach ($order_webhooks as $wh) {
        $processing_result = $wh['processing_result'] ?? null;
        
        $order_info = [
            'webhook_id' => $wh['id'] ?? 'unknown',
            'received_at' => $wh['received_at'] ?? 'unknown',
            'topic' => $wh['topic'] ?? 'unknown',
            'yuju_order_id' => $wh['resource_id'] ?? 'unknown',
            'attempts' => $wh['attempts'] ?? 1,
            'processing_status' => $wh['processing_status'] ?? 'pending',
            'processed_at' => $wh['processed_at'] ?? null,
        ];
        
        // Si hay resultado de procesamiento
        if ($processing_result) {
            $order_info['creation_success'] = $processing_result['success'] ?? false;
            $order_info['prestashop_order_id'] = $processing_result['prestashop_order_id'] ?? null;
            $order_info['message'] = $processing_result['message'] ?? '';
            
            // Si hay detalles de error/éxito
            if (isset($processing_result['details'])) {
                $details = $processing_result['details'];
                
                $order_info['details'] = [
                    'customer' => [
                        'id' => $details['customer_id'] ?? null,
                        'status' => isset($details['customer_error']) ? 'ERROR' : 
                                   (isset($details['customer_existed']) && $details['customer_existed'] ? 'EXISTING' : 'CREATED'),
                        'error' => $details['customer_error'] ?? null,
                    ],
                    'address' => [
                        'id' => $details['shipping_address_id'] ?? null,
                        'status' => isset($details['address_error']) ? 'ERROR' : 
                                   (isset($details['address_existed']) && $details['address_existed'] ? 'EXISTING' : 'CREATED'),
                        'error' => $details['address_error'] ?? null,
                    ],
                    'cart' => [
                        'id' => $details['cart_id'] ?? null,
                        'status' => isset($details['cart_error']) ? 'ERROR' : 
                                   (isset($details['cart_existed']) && $details['cart_existed'] ? 'REUSED' : 'CREATED'),
                        'products_added' => $details['cart_products_added'] ?? 0,
                        'products_failed' => $details['cart_products_failed'] ?? [],
                        'error' => $details['cart_error'] ?? null,
                    ],
                    'order' => [
                        'id' => $details['order_id'] ?? null,
                        'status' => isset($details['order_error']) ? 'ERROR' : 'CREATED',
                        'error' => $details['order_error'] ?? null,
                    ],
                    'mapping' => [
                        'id' => $details['mapping_id'] ?? null,
                        'error' => $details['mapping_error'] ?? null,
                    ],
                ];
            }
            
            // Si hay error general
            if (!$processing_result['success']) {
                $order_info['error'] = $processing_result['error'] ?? $processing_result['message'] ?? 'Unknown error';
                $order_info['error_details'] = $processing_result['error_details'] ?? null;
            }
            
            // Si la orden ya existía
            if (isset($processing_result['already_exists'])) {
                $order_info['already_existed'] = true;
                $order_info['existing_prestashop_order_id'] = $processing_result['existing_prestashop_order_id'] ?? null;
            }
        } else {
            // Webhook aún no procesado automáticamente
            $order_info['creation_success'] = null;
            $order_info['message'] = 'Webhook received but not processed yet (waiting for manual trigger or cron)';
        }
        
        // Incluir datos de creación si existen
        if (isset($wh['creation_details'])) {
            $order_info['creation_details'] = $wh['creation_details'];
        }
        
        $processed_orders[] = $order_info;
    }
    
    // Calcular estadísticas
    $total_orders = count($processed_orders);
    $successful = count(array_filter($processed_orders, function($o) {
        return $o['creation_success'] === true;
    }));
    $failed = count(array_filter($processed_orders, function($o) {
        return $o['creation_success'] === false;
    }));
    $pending = count(array_filter($processed_orders, function($o) {
        return $o['creation_success'] === null;
    }));
    
    // Respuesta
    $response = [
        'success' => true,
        'summary' => [
            'total_order_webhooks' => $total_orders,
            'successful_creations' => $successful,
            'failed_creations' => $failed,
            'pending_processing' => $pending,
            'success_rate' => $total_orders > 0 ? round(($successful / $total_orders) * 100, 2) . '%' : 'N/A',
        ],
        'filters' => [
            'limit' => $limit,
            'topic' => $topic_filter ?? 'all order topics',
            'status' => $status_filter ?? 'all statuses',
        ],
        'orders' => $processed_orders,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $logger->log('Webhook orders status check completed: ' . $total_orders . ' orders found', 'info');
    
} catch (Exception $e) {
    http_response_code(500);
    
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ];
    
    echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $logger->log('Error checking webhook orders status: ' . $e->getMessage(), 'error');
}
