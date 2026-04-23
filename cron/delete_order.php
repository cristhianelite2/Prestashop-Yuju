<?php
/**
 * Endpoint para eliminar una orden de PrestaShop
 * Puede eliminar solo la orden o también todos los recursos asociados
 */

// Headers para respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';
require_once dirname(__FILE__) . '/../classes/YujuOrderManager.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';

$logger = new YujuLogger();

try {
    // Verificar que sea POST o DELETE
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
        throw new Exception('Only POST or DELETE methods are allowed');
    }
    
    // Obtener datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        // Intentar obtener de parámetros GET si es DELETE
        $data = $_GET;
    }
    
    $order_id = $data['order_id'] ?? null;
    $delete_all = isset($data['delete_all']) && ($data['delete_all'] === true || $data['delete_all'] === 'true' || $data['delete_all'] === '1');
    
    if (!$order_id) {
        throw new Exception('Missing required parameter: order_id');
    }
    
    $logger->log('Delete order request: order_id=' . $order_id . ', delete_all=' . ($delete_all ? 'true' : 'false'), 'info');
    
    // Crear instancia del manager
    $order_manager = new YujuOrderManager();
    
    // Eliminar la orden
    $result = $order_manager->deleteOrder((int)$order_id, $delete_all);
    
    if ($result['success']) {
        $message = 'Order deleted successfully';
        
        $details = [
            'order_deleted' => $result['order_deleted'],
        ];
        
        if ($delete_all) {
            $details['customer_deleted'] = $result['customer_deleted'];
            $details['addresses_deleted'] = $result['addresses_deleted'];
            $details['cart_deleted'] = $result['cart_deleted'];
            
            $message .= ' (including ';
            $deleted_items = [];
            if ($result['customer_deleted']) $deleted_items[] = 'customer';
            if (!empty($result['addresses_deleted'])) $deleted_items[] = count($result['addresses_deleted']) . ' address(es)';
            if ($result['cart_deleted']) $deleted_items[] = 'cart';
            $message .= implode(', ', $deleted_items) . ')';
        }
        
        $response = [
            'success' => true,
            'message' => $message,
            'order_id' => (int)$order_id,
            'details' => $details,
            'errors' => $result['errors'],
        ];
        
        http_response_code(200);
    } else {
        $response = [
            'success' => false,
            'message' => 'Failed to delete order',
            'order_id' => (int)$order_id,
            'errors' => $result['errors'],
        ];
        
        http_response_code(500);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $logger->log('Delete order error: ' . $e->getMessage(), 'error');
    
    http_response_code(400);
    
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
