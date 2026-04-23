<?php
/**
 * Endpoint para obtener detalles completos de una orden desde la API de Yuju
 * y actualizar el webhook en el storage
 */

// Headers para respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';
require_once dirname(__FILE__) . '/../classes/YujuApiClient.php';
require_once dirname(__FILE__) . '/../classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../classes/YujuWebhookStorage.php';

try {
    // Verificar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    // Obtener datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $webhook_id = $data['webhook_id'] ?? null;
    $order_id = $data['order_id'] ?? null;
    $channel_id = $data['channel_id'] ?? null;
    
    if (!$webhook_id || !$order_id) {
        throw new Exception('Missing required parameters: webhook_id and order_id');
    }
    
    $logger = new YujuLogger();
    $logger->log('Manual fetch order details requested', [
        'webhook_id' => $webhook_id,
        'order_id' => $order_id,
        'channel_id' => $channel_id
    ]);
    
    // Inicializar API client y verificar OAuth
    $api_client = new YujuApiClient();
    $oauth = new YujuOAuth();
    
    // Verificar que tengamos un token válido
    $access_token = $oauth->getValidAccessToken();
    if (!$access_token) {
        throw new Exception('No access token available. Please authorize the connection first.');
    }
    
    $logger->log('Access token available', [
        'token_length' => strlen($access_token),
        'token_preview' => substr($access_token, 0, 10) . '...'
    ]);
    
    // Hacer petición a la API de Yuju
    $logger->log('Fetching order from Yuju API', [
        'order_id' => $order_id,
        'channel_id' => $channel_id
    ]);
    
    $api_response = $api_client->getOrder($order_id, $channel_id);
    
    // Log de la respuesta completa para debugging
    $logger->log('API response received', [
        'response_keys' => array_keys($api_response),
        'has_success_key' => isset($api_response['success']),
        'success_value' => $api_response['success'] ?? 'not_set'
    ]);
    
    // Verificar respuesta
    if (isset($api_response['success']) && $api_response['success'] === false) {
        $error_msg = $api_response['message'] ?? 'API returned error';
        $logger->log('API returned error', [
            'error' => $error_msg,
            'full_response' => $api_response
        ]);
        throw new Exception($error_msg);
    }
    
    // Extraer datos de la orden
    $order_data = $api_response;
    if (isset($api_response['data'])) {
        $order_data = $api_response['data'];
    }
    
    $logger->log('Order data fetched successfully', [
        'order_id' => $order_id,
        'has_items' => isset($order_data['items']),
        'items_count' => isset($order_data['items']) ? count($order_data['items']) : 0
    ]);
    
    // Actualizar el webhook en el storage con los datos completos
    $webhook_storage = new YujuWebhookStorage();
    $all_webhooks = $webhook_storage->getAllWebhooks();
    
    $webhook_updated = false;
    foreach ($all_webhooks as &$webhook) {
        if ($webhook['id'] === $webhook_id) {
            // Parsear payload actual
            $current_payload = is_string($webhook['payload']) 
                ? json_decode($webhook['payload'], true) 
                : $webhook['payload'];
            
            // Agregar los datos de la orden
            $enriched_payload = array_merge($current_payload ?: [], [
                'order_details' => $order_data,
                'fetch_status' => 'success',
                'fetch_timestamp' => date('Y-m-d H:i:s'),
                'fetch_method' => 'manual',
                
                // Campos importantes en nivel raíz
                'id_order' => $order_data['id_order'] ?? $order_id,
                'reference' => $order_data['reference'] ?? null,
                'status' => $order_data['status'] ?? null,
                'items' => $order_data['items'] ?? [],
                'customer' => $order_data['customer'] ?? null,
                'shipping_address' => $order_data['shipping_address'] ?? null,
                'billing_address' => $order_data['billing_address'] ?? null,
                'total' => $order_data['total'] ?? null,
                'currency' => $order_data['currency'] ?? null,
            ]);
            
            // Actualizar el webhook
            $webhook['payload'] = json_encode($enriched_payload);
            $webhook['updated_at'] = date('Y-m-d H:i:s');
            $webhook_updated = true;
            
            $logger->log('Webhook payload updated with order details', [
                'webhook_id' => $webhook_id
            ]);
            
            break;
        }
    }
    
    if (!$webhook_updated) {
        throw new Exception('Webhook not found in storage: ' . $webhook_id);
    }
    
    // Guardar webhooks actualizados
    $storage_file = dirname(__FILE__) . '/../cache/yuju_webhooks_received.json';
    file_put_contents($storage_file, json_encode($all_webhooks, JSON_PRETTY_PRINT));
    
    // Retornar éxito
    echo json_encode([
        'success' => true,
        'message' => 'Order details fetched and saved successfully',
        'order_data' => $enriched_payload,
        'order_id' => $order_id,
        'has_items' => isset($order_data['items']),
        'items_count' => isset($order_data['items']) ? count($order_data['items']) : 0
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    
    $logger = new YujuLogger();
    $logger->log('Error fetching order details: ' . $e->getMessage(), 'error');
    
    $error_message = $e->getMessage();
    
    // Detectar errores de autorización
    if (strpos($error_message, 'Authorization') !== false || 
        strpos($error_message, 'Invalid key=value pair') !== false ||
        strpos($error_message, 'access token') !== false) {
        $error_message = 'Error de autorización: El token OAuth no es válido o ha expirado. ' .
                        'Por favor, ve a Configuración del módulo Yuju y haz clic en "Autorizar Conexión OAuth" ' .
                        'para reconectar con la API de Yuju.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'error' => $e->getMessage(),
        'action_required' => (strpos($error_message, 'autorización') !== false) ? 'reauthorize_oauth' : null
    ], JSON_PRETTY_PRINT);
}
