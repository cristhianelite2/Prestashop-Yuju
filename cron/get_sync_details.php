<?php
/**
 * Obtener detalles de una sincronización PrestaShop → Yuju
 */

// Cargar PrestaShop
$prestashop_path = dirname(__FILE__, 4);
require_once $prestashop_path . '/config/config.inc.php';
require_once $prestashop_path . '/init.php';

// Headers para JSON
header('Content-Type: application/json');

// Verificar que sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$sync_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$sync_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de sincronización es requerido']);
    exit;
}

try {
    // Obtener detalles de la sincronización
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
            WHERE `id` = ' . (int)$sync_id . ' 
            AND `sync_direction` = "prestashop_to_yuju"
            LIMIT 1';
    
    $sync = Db::getInstance()->getRow($sql);
    
    if (!$sync) {
        throw new Exception('Sincronización no encontrada');
    }
    
    $details = json_decode($sync['details'], true);
    
    if (!isset($details['summary']) || !isset($details['details'])) {
        throw new Exception('Formato de datos inválido');
    }
    
    // Preparar respuesta en el formato esperado por showSyncModal
    $results = [
        'total_yuju' => $details['summary']['total_yuju'] ?? 0,
        'total_prestashop' => $details['summary']['total_prestashop'] ?? 0,
        'found_in_prestashop' => $details['summary']['found_in_prestashop'] ?? 0,
        'with_differences' => $details['summary']['with_differences'] ?? 0,
        'api_requests' => $details['summary']['api_requests'] ?? 0,
        'updated' => $details['summary']['updated'] ?? 0,
        'synced' => $details['summary']['synced'] ?? 0,
        'not_found' => $details['summary']['not_found'] ?? 0,
        'errors' => $details['summary']['errors'] ?? 0,
        'details' => $details['details'] ?? []
    ];
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'sync_info' => [
            'id' => $sync['id'],
            'date' => $sync['start_time'],
            'status' => $sync['status']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
