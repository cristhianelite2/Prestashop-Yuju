<?php
/**
 * 2024 Yuju Integration - Webhook Receiver
 */

// Limpiar cualquier output previo
if (ob_get_level()) {
    ob_end_clean();
}

// Iniciar output buffering limpio
ob_start();

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

// Include webhook manager
require_once dirname(__FILE__) . '/classes/YujuWebhookManager.php';
require_once dirname(__FILE__) . '/classes/YujuLogger.php';
require_once dirname(__FILE__) . '/classes/YujuWebhookStorage.php';

// Set content type ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');

// Initialize logger
$logger = new YujuLogger();
$start_time = microtime(true);

// Información de debug
$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
];

try {
    // Check if module is active (usando clase Module de PrestaShop)
    if (!class_exists('Module') || !Module::isEnabled('prestashopyuju')) {
        throw new Exception('Yuju module is not active');
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method not allowed. Only POST requests are accepted.');
    }

    // Get raw POST data (Yuju webhooks may not send body, only headers)
    $raw_payload = file_get_contents('php://input');
    $debug_info['payload_size'] = strlen($raw_payload);
    
    // Yuju webhooks pueden venir sin body (solo headers), esto NO es un error
    if (empty($raw_payload)) {
        $debug_info['payload_type'] = 'headers_only';
        $debug_info['note'] = 'Yuju webhooks send data in headers only, no body payload';
    } else {
        // Si hay payload, intentar decodificar JSON
        $json_payload = json_decode($raw_payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug_info['json_error'] = json_last_error_msg();
            $debug_info['payload_type'] = 'non_json';
        } else {
            $debug_info['payload_decoded'] = true;
            $debug_info['payload_type'] = 'json';
        }
    }

    // Get headers
    $headers = [];
    $yuju_headers_found = [];

    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header_name = str_replace('HTTP_', '', $key);
            $header_name = str_replace('_', '-', $header_name);
            $headers[$header_name] = $value;
        }
    }
    
    // Capturar headers específicos de Yuju con X-Yuju
    $yuju_headers = [
        'x-yuju-id', 'x-yuju-topic', 'x-yuju-resource', 
        'x-yuju-id-account', 'x-yuju-id-shop', 'x-yuju-id-channel',
        'x-yuju-attempts', 'x-yuju-received', 'x-yuju-send',
        'x-yuju-sku', 'x-yuju-sku-simple', 'x-yuju-id-parent'
    ];
    
    foreach ($yuju_headers as $header) {
        $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (isset($_SERVER[$server_key])) {
            $headers[$header] = $_SERVER[$server_key];
            $yuju_headers_found[$header] = $_SERVER[$server_key];
        }
    }
    
    $debug_info['yuju_headers_count'] = count($yuju_headers_found);
    $debug_info['yuju_headers'] = $yuju_headers_found;
    
    // Validar headers requeridos de Yuju
    $required_headers = ['x-yuju-id', 'x-yuju-topic'];
    $missing_headers = [];
    foreach ($required_headers as $req_header) {
        if (!isset($yuju_headers_found[$req_header])) {
            $missing_headers[] = $req_header;
        }
    }
    
    if (!empty($missing_headers)) {
        $debug_info['validation'] = 'Missing required Yuju headers: ' . implode(', ', $missing_headers);
        $logger->log('Webhook validation warning: ' . $debug_info['validation'], 'warning');
    }

    // Log incoming webhook
    $logger->log('Webhook received: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'], 'info');
    $logger->log('Yuju Headers: ' . json_encode($yuju_headers_found), 'info');
    $logger->log('Payload length: ' . strlen($raw_payload), 'debug');

    // Initialize webhook manager
    $webhook_manager = new YujuWebhookManager();

    // Process webhook (esto enriquecerá los datos con fetch de la API)
    $result = $webhook_manager->processWebhook($raw_payload, $headers);
    
    // Obtener el payload enriquecido desde el resultado
    $enriched_payload = isset($result['enriched_data']) ? json_encode($result['enriched_data']) : $raw_payload;
    
    // Guardar webhook con datos enriquecidos en el storage
    $webhook_storage = new YujuWebhookStorage();
    $storage_result = $webhook_storage->saveWebhook($headers, $enriched_payload);
    $debug_info['stored_in_cache'] = $storage_result;
    
    // Si el resultado incluye información de creación de orden, actualizar el webhook
    if (isset($result['auto_created']) || isset($result['prestashop_order_id'])) {
        $topic = $yuju_headers_found['x-yuju-topic'] ?? 'unknown';
        $resource_id = $yuju_headers_found['x-yuju-resource'] ?? null;
        
        if ($resource_id && $topic !== 'unknown') {
            $webhook_storage->updateWebhookProcessing($topic, $resource_id, $result);
        }
    }
    
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    $debug_info['execution_time_ms'] = $execution_time;
    $debug_info['processing_result'] = $result;

    // Return success response
    http_response_code(200);
    
    $response = [
        'success' => true,
        'message' => 'Webhook processed successfully',
        'webhook' => [
            'id' => $yuju_headers_found['x-yuju-id'] ?? 'unknown',
            'topic' => $yuju_headers_found['x-yuju-topic'] ?? 'unknown',
            'resource' => $yuju_headers_found['x-yuju-resource'] ?? null,
            'attempts' => $yuju_headers_found['x-yuju-attempts'] ?? 1,
        ],
        'processing' => [
            'status' => 'completed',
            'execution_time_ms' => $execution_time,
            'stored' => $storage_result ?? false,
            'result' => $result,
        ],
        'debug' => $debug_info,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
    
    // Limpiar buffer y enviar respuesta
    ob_end_clean();
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $logger->log('Webhook processed successfully in ' . $execution_time . 'ms', 'info');
    
} catch (Exception $e) {
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    
    // Log error
    $logger->log('Webhook processing failed: ' . $e->getMessage(), 'error');
    $logger->log('Stack trace: ' . $e->getTraceAsString(), 'debug');

    // Set appropriate HTTP status code based on error
    $error_code = 500;
    $error_type = 'internal_error';

    if (strpos($e->getMessage(), 'Invalid webhook signature') !== false) {
        $error_code = 401;
        $error_type = 'authentication_error';
    } elseif (strpos($e->getMessage(), 'Empty payload') !== false
    || strpos($e->getMessage(), 'Invalid webhook payload') !== false) {
        $error_code = 400;
        $error_type = 'bad_request';
    } elseif (strpos($e->getMessage(), 'Method not allowed') !== false) {
        $error_code = 405;
        $error_type = 'method_not_allowed';
    } elseif (strpos($e->getMessage(), 'not active') !== false) {
        $error_code = 503;
        $error_type = 'service_unavailable';
    }

    http_response_code($error_code);

    $error_response = [
        'success' => false,
        'error' => [
            'type' => $error_type,
            'message' => $e->getMessage(),
            'code' => $error_code,
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ],
        'processing' => [
            'status' => 'failed',
            'execution_time_ms' => $execution_time,
        ],
        'debug' => $debug_info,
        'timestamp' => date('Y-m-d H:i:s'),
        'help' => [
            'required_method' => 'POST',
            'required_headers' => ['x-yuju-id', 'x-yuju-topic'],
            'expected_content_type' => 'application/json',
            'documentation' => 'https://api-docs.yuju.io/docs/funcionamiento-general',
        ],
    ];
    
    // Limpiar buffer y enviar respuesta de error
    ob_end_clean();
    echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Flush output y terminar
flush();
exit;
