<?php
/**
 * Endpoint para crear una orden en PrestaShop desde un webhook de Yuju
 * Incluye tracking paso a paso del proceso
 */

// Headers para respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';
require_once dirname(__FILE__) . '/../classes/YujuOrderManager.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../classes/YujuWebhookStorage.php';

// Array para tracking de pasos
$steps = [];

function addStep($message, $status = 'success', $details = null) {
    global $steps;
    $steps[] = [
        'message' => $message,
        'status' => $status, // 'loading', 'success', 'error', 'warning'
        'details' => $details,
        'timestamp' => date('H:i:s')
    ];
}

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
    
    if (!$webhook_id || !$order_id) {
        throw new Exception('Missing required parameters: webhook_id and order_id');
    }
    
    $logger = new YujuLogger();
    $logger->log('Starting order creation from webhook', [
        'webhook_id' => $webhook_id,
        'order_id' => $order_id
    ]);
    
    addStep('📥 Cargando datos del webhook...', 'loading');
    
    // Obtener webhook del storage
    $webhook_storage = new YujuWebhookStorage();
    $all_webhooks = $webhook_storage->getAllWebhooks();
    
    $webhook = null;
    foreach ($all_webhooks as $wh) {
        if ($wh['id'] === $webhook_id) {
            $webhook = $wh;
            break;
        }
    }
    
    if (!$webhook) {
        throw new Exception('Webhook no encontrado: ' . $webhook_id);
    }
    
    addStep('✅ Webhook cargado correctamente', 'success');
    
    // Parsear payload
    $payload = is_string($webhook['payload']) 
        ? json_decode($webhook['payload'], true) 
        : $webhook['payload'];
    
    if (!$payload) {
        throw new Exception('Payload del webhook inválido');
    }
    
    // Verificar que tenga datos de orden completos
    // Si tiene order_details, validar allí, sino validar en la raíz
    $items_check = $payload['order_details']['items'] ?? $payload['items'] ?? [];
    $customer_check = $payload['customer'] ?? $payload['order_details']['customer'] ?? null;
    
    if (empty($items_check) || empty($customer_check)) {
        addStep('⚠️ Datos de orden incompletos', 'warning', 'Faltan items o información del cliente');
        throw new Exception('El webhook no contiene datos completos de la orden. Usa el botón "Descargar Detalles" primero.');
    }
    
    addStep('✅ Datos de orden validados', 'success', count($items_check) . ' items encontrados');
    
    // Inicializar order manager
    $order_manager = new YujuOrderManager();
    
    // Crear orden usando YujuOrderManager - Pasar el payload COMPLETO
    // FORCE = true para intentar crear incluso si ya existe un mapping
    $result = $order_manager->createOrderFromYuju($payload, true);
    
    $logger->log('Order creation result', [
        'success' => $result['success'] ?? false,
        'result' => $result
    ]);
    
    // LOG TEMPORAL: Agregar resultado completo a steps para debug
    addStep('🔍 DEBUG: Resultado completo del método', 'warning', json_encode($result, JSON_PRETTY_PRINT));
    
    // Procesar detalles de la creación SIEMPRE (incluso si falla)
    $details = $result['details'] ?? [];
    
    // LOG TEMPORAL: Ver qué detalles retornó el método
    $logger->log('DETAILS FROM RESULT: ' . json_encode($details), 'debug');
    
    // Validar y mostrar cada paso CON DETALLES DE ERROR ESPECÍFICOS
    if (!empty($details['customer_id'])) {
        $status_msg = ($details['customer_status'] ?? '') === 'EXISTING' ? ' (ya existía)' : '';
        addStep('✅ Cliente verificado/creado' . $status_msg, 'success', 'ID: ' . $details['customer_id']);
    } else {
        $error_detail = $details['customer_error'] ?? 'Error desconocido';
        addStep('⚠️ Cliente no creado', 'warning', $error_detail);
    }
    
    if (!empty($details['shipping_address_id'])) {
        $status_msg = ($details['address_status'] ?? '') === 'EXISTING' ? ' (ya existía)' : '';
        addStep('✅ Dirección de envío creada' . $status_msg, 'success', 'ID: ' . $details['shipping_address_id']);
    } else {
        $error_detail = $details['shipping_address_error'] ?? 'Error desconocido';
        addStep('⚠️ Dirección de envío no creada', 'warning', $error_detail);
    }
    
    if (!empty($details['billing_address_id'])) {
        $status_msg = ($details['address_status'] ?? '') === 'EXISTING' ? ' (ya existía)' : '';
        addStep('✅ Dirección de facturación creada' . $status_msg, 'success', 'ID: ' . $details['billing_address_id']);
    } else {
        $error_detail = $details['billing_address_error'] ?? 'Error desconocido';
        addStep('⚠️ Dirección de facturación no creada', 'warning', $error_detail);
    }
    
    if (!empty($details['cart_id'])) {
        $status_msg = ($details['cart_status'] ?? '') === 'EXISTING' ? ' (reutilizado)' : '';
        addStep('✅ Carrito de compras creado' . $status_msg, 'success', 'ID: ' . $details['cart_id']);
        addStep('✅ Productos agregados al carrito', 'success', count($items_check) . ' productos');
    } else {
        $error_detail = $details['cart_error'] ?? 'No se pudo crear el carrito';
        addStep('❌ Carrito de compras NO creado', 'error', $error_detail);
    }
    
    if (!empty($details['order_id'])) {
        addStep('✅ Orden creada en PrestaShop', 'success', 'ID: ' . $details['order_id']);
    } else {
        $error_detail = $details['order_error'] ?? 'No se pudo crear la orden en PrestaShop';
        addStep('❌ Orden NO creada', 'error', $error_detail);
    }
    
    if (!empty($details['mapping_id'])) {
        addStep('✅ Mapping guardado en base de datos', 'success', 'ID: ' . $details['mapping_id']);
    } else {
        $error_detail = $details['mapping_error'] ?? 'No se guardó el mapping';
        if (!empty($error_detail) && $error_detail !== 'No se guardó el mapping') {
            addStep('⚠️ Mapping no guardado', 'warning', $error_detail);
        } else {
            addStep('⚠️ Mapping no guardado', 'warning', $error_detail);
        }
    }
    
    // DESPUÉS de mostrar todos los detalles, verificar si el proceso tuvo éxito
    if (!isset($result['success']) || !$result['success']) {
        $error_msg = $result['message'] ?? 'Error desconocido al crear la orden';
        addStep('❌ Error: ' . $error_msg, 'error');
        throw new Exception($error_msg);
    }
    
    // VALIDACIÓN CRÍTICA: Verificar que se creó la orden
    if (empty($details['order_id'])) {
        // Si success=true pero no hay order_id, algo está mal
        $error_msg = 'Proceso incompleto: La orden no se creó en PrestaShop';
        addStep('❌ ' . $error_msg, 'error');
        
        // Log de debugging
        $logger->log('Order creation incomplete - success=true but no order_id', [
            'result' => $result,
            'details' => $details
        ], 'error');
        
        throw new Exception($error_msg);
    }
    
    $prestashop_order_id = $result['prestashop_order_id'] ?? $details['order_id'];
    
    // Construir URL de la orden (simplificada, sin token)
    $order_url = null;
    if ($prestashop_order_id) {
        // Intentar construir URL básica sin token
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        // La URL será relativa al admin, el usuario deberá buscar la orden manualmente
        $order_url = $protocol . $host . '/admin/index.php?controller=AdminOrders&id_order=' . $prestashop_order_id . '&vieworder';
    }
    
    // Retornar éxito
    echo json_encode([
        'success' => true,
        'message' => 'Orden creada exitosamente en PrestaShop',
        'prestashop_order_id' => $prestashop_order_id,
        'yuju_order_id' => $order_id,
        'order_url' => $order_url,
        'steps' => $steps,
        'details' => $result
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    
    $logger = new YujuLogger();
    $logger->log('Error creating order from webhook: ' . $e->getMessage(), 'error');
    
    addStep('❌ Error: ' . $e->getMessage(), 'error');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
        'steps' => $steps
    ], JSON_PRETTY_PRINT);
}
