<?php
/**
 * 2024 Yuju Integration - Webhook Management API
 * 
 * Endpoints AJAX para gestión de webhooks
 */

// Configuración de PrestaShop
$root_path = dirname(__FILE__, 2);
require_once $root_path . '/../../config/config.inc.php';
require_once $root_path . '/../../init.php';
require_once $root_path . '/classes/YujuApiClient.php';
require_once $root_path . '/classes/YujuWebhookStorage.php';
require_once $root_path . '/classes/YujuLogger.php';

header('Content-Type: application/json');

$logger = new YujuLogger();
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : null);

try {
    $api = new YujuApiClient();
    $storage = new YujuWebhookStorage();
    
    switch ($action) {
        case 'get_subscriptions':
            // Obtener todas las suscripciones activas
            $response = $api->getWebhookSubscriptions();
            
            if ($response['success']) {
                echo json_encode([
                    'success' => true,
                    'subscriptions' => $response['data'] ?? [],
                    'available_topics' => $api->getAvailableWebhookTopics()
                ]);
            } else {
                // Verificar si es error de autenticación
                if (isset($response['http_code']) && $response['http_code'] == 401) {
                    throw new Exception('Token de autenticación inválido o expirado. Por favor, reconecta tu cuenta de Yuju en la configuración del módulo (Configuración > Aplicaciones de Yuju).');
                }
                throw new Exception($response['message'] ?? 'Error al obtener suscripciones');
            }
            break;
            
        case 'subscribe':
            // Crear nueva suscripción
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['topics']) || !is_array($input['topics']) || empty($input['topics'])) {
                throw new Exception('Topics es requerido y debe ser un array no vacío');
            }
            
            // URL del webhook (endpoint de este módulo)
            $webhook_url = isset($input['url']) ? $input['url'] : null;
            
            if (!$webhook_url) {
                // Construir URL automáticamente
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $domain = $_SERVER['HTTP_HOST'];
                $webhook_url = $protocol . $domain . '/modules/prestashopyuju/webhook.php';
            }
            
            // Validar SSL
            if (strpos($webhook_url, 'https://') !== 0) {
                throw new Exception('La URL del webhook debe usar HTTPS');
            }
            
            $topics = $input['topics'];
            $headers = isset($input['headers']) ? $input['headers'] : null;
            
            $logger->info('Creating webhook subscription', [
                'url' => $webhook_url,
                'topics' => $topics,
                'headers' => $headers
            ]);
            
            $response = $api->createWebhookSubscription($webhook_url, $topics, $headers);
            
            $logger->info('Webhook subscription API response', [
                'success' => $response['success'],
                'http_code' => $response['http_code'] ?? 'N/A',
                'message' => $response['message'] ?? 'N/A',
                'data' => $response['data'] ?? null
            ]);
            
            if ($response['success']) {
                $logger->info('Webhook subscription created', [
                    'url' => $webhook_url,
                    'topics' => $topics,
                    'subscription_id' => $response['data']['id_third_party_app_webhook'] ?? 'N/A'
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Suscripción creada exitosamente',
                    'subscription' => $response['data']
                ]);
            } else {
                throw new Exception($response['message'] ?? 'Error al crear suscripción');
            }
            break;
            
        case 'unsubscribe':
            // Eliminar suscripción
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['webhook_id'])) {
                throw new Exception('webhook_id es requerido');
            }
            
            $webhook_id = (int)$input['webhook_id'];
            $response = $api->deleteWebhookSubscription($webhook_id);
            
            if ($response['success']) {
                $logger->info('Webhook subscription deleted', ['webhook_id' => $webhook_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Suscripción eliminada exitosamente'
                ]);
            } else {
                throw new Exception($response['message'] ?? 'Error al eliminar suscripción');
            }
            break;
            
        case 'update_subscription':
            // Actualizar suscripción existente
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['webhook_id'])) {
                throw new Exception('webhook_id es requerido');
            }
            
            $webhook_id = (int)$input['webhook_id'];
            $update_data = [];
            
            if (isset($input['topics'])) {
                $update_data['topics'] = $input['topics'];
            }
            if (isset($input['url'])) {
                $update_data['url'] = $input['url'];
            }
            if (isset($input['headers'])) {
                $update_data['headers'] = $input['headers'];
            }
            
            if (empty($update_data)) {
                throw new Exception('No hay datos para actualizar');
            }
            
            $response = $api->updateWebhookSubscription($webhook_id, $update_data);
            
            if ($response['success']) {
                $logger->info('Webhook subscription updated', [
                    'webhook_id' => $webhook_id,
                    'updated_fields' => array_keys($update_data)
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Suscripción actualizada exitosamente',
                    'subscription' => $response['data']
                ]);
            } else {
                throw new Exception($response['message'] ?? 'Error al actualizar suscripción');
            }
            break;
            
        case 'get_received':
            // Obtener webhooks recibidos
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $topic = isset($_GET['topic']) ? $_GET['topic'] : null;
            
            if ($topic) {
                $webhooks = $storage->getWebhooksByTopic($topic, $limit);
            } else {
                $webhooks = $storage->getRecentWebhooks($limit);
            }
            
            $stats = $storage->getStats();
            
            echo json_encode([
                'success' => true,
                'webhooks' => $webhooks,
                'stats' => $stats
            ]);
            break;
            
        case 'get_stats':
            // Obtener estadísticas de webhooks recibidos
            $stats = $storage->getStats();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'clear_received':
            // Limpiar todos los webhooks recibidos
            $success = $storage->clearAll();
            
            if ($success) {
                $logger->info('Webhook storage cleared');
                echo json_encode([
                    'success' => true,
                    'message' => 'Webhooks recibidos eliminados exitosamente'
                ]);
            } else {
                throw new Exception('Error al limpiar webhooks');
            }
            break;
            
        case 'clean_old':
            // Limpiar webhooks antiguos
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            $removed = $storage->cleanOldWebhooks($days);
            
            $logger->info('Old webhooks cleaned', ['removed' => $removed, 'days' => $days]);
            
            echo json_encode([
                'success' => true,
                'message' => "Se eliminaron $removed webhooks antiguos",
                'removed' => $removed
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $logger->error('Webhook management error', [
        'action' => $action,
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
