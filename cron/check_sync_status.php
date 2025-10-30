<?php
/**
 * Verificar si una descarga ya tiene sincronización ejecutada
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

$download_id = isset($_GET['download_id']) ? (int)$_GET['download_id'] : 0;

if (!$download_id) {
    http_response_code(400);
    echo json_encode(['error' => 'download_id es requerido']);
    exit;
}

try {
    // Buscar sincronizaciones existentes para esta descarga
    // Las sincronizaciones tienen sync_direction = 'prestashop_to_yuju'
    // y en details->source_download_id está el ID de la descarga origen
    
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
            WHERE `sync_direction` = "prestashop_to_yuju" 
            AND `entity_type` = "products"
            ORDER BY `start_time` DESC';
    
    $all_syncs = Db::getInstance()->executeS($sql);
    
    $matching_syncs = [];
    
    if ($all_syncs) {
        foreach ($all_syncs as $sync) {
            if (!empty($sync['details'])) {
                $details = json_decode($sync['details'], true);
                
                // Verificar si el source_download_id coincide
                if (isset($details['source_download_id']) && $details['source_download_id'] == $download_id) {
                    $matching_syncs[] = [
                        'id' => $sync['id'],
                        'status' => $sync['status'],
                        'start_time' => $sync['start_time'],
                        'total_items' => $sync['total_items']
                    ];
                }
            }
        }
    }
    
    $has_sync = count($matching_syncs) > 0;
    $sync_count = count($matching_syncs);
    
    echo json_encode([
        'success' => true,
        'has_sync' => $has_sync,
        'sync_count' => $sync_count,
        'syncs' => $matching_syncs,
        'download_id' => $download_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
