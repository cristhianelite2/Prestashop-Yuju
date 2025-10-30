<?php
/**
 * Reintentar descarga de JSON desde Yuju cuando hay error 403
 */

// Cargar PrestaShop
$prestashop_path = dirname(__FILE__, 4);
require_once $prestashop_path . '/config/config.inc.php';
require_once $prestashop_path . '/init.php';

// Cargar clases del módulo
require_once dirname(__FILE__, 2) . '/classes/YujuApiClient.php';
require_once dirname(__FILE__, 2) . '/classes/YujuLogger.php';

// Función helper para logging seguro
function safeLog($logger, $message) {
    if ($logger !== null) {
        try {
            $logger->info($message);
        } catch (Exception $e) {
            // Ignorar errores de logging
        }
    }
}

// Headers para JSON
header('Content-Type: application/json');

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$download_id = isset($input['download_id']) ? (int)$input['download_id'] : 0;

if (!$download_id) {
    http_response_code(400);
    echo json_encode(['error' => 'download_id es requerido']);
    exit;
}

try {
    // Intentar crear logger, pero continuar si falla
    try {
        $logger = new YujuLogger();
    } catch (Exception $e) {
        $logger = null; // Continuar sin logger
    }
    
    // Obtener el registro de la descarga fallida
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE `id` = ' . (int)$download_id;
    $old_download = Db::getInstance()->getRow($sql);
    
    if (!$old_download) {
        throw new Exception('Descarga no encontrada');
    }
    
    if ($old_download['status'] !== 'failed') {
        throw new Exception('Solo se pueden reintentar descargas fallidas');
    }
    
    safeLog($logger, "Validando descarga ID: {$download_id}");
    
    // Extraer detalles del registro anterior
    $old_details = json_decode($old_download['details'], true);
    
    // 1. VALIDAR SI YA EXISTE UN REINTENTO EXITOSO PARA ESTA DESCARGA
    // Buscar todos los reintentos exitosos y verificar manualmente
    $check_retry_sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                        WHERE `status` = "completed" 
                        AND `sync_direction` = "yuju_to_prestashop"
                        ORDER BY `start_time` DESC';
    $all_successful = Db::getInstance()->executeS($check_retry_sql);
    
    $existing_retry = null;
    if ($all_successful) {
        foreach ($all_successful as $record) {
            $record_details = json_decode($record['details'], true);
            if (isset($record_details['retry_of']) && $record_details['retry_of'] == $download_id) {
                $existing_retry = $record;
                break;
            }
        }
    }
    
    if ($existing_retry) {
        $retry_details = json_decode($existing_retry['details'], true);
        $logger->info("Ya existe un reintento exitoso (ID: {$existing_retry['id']}) para la descarga {$download_id}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Ya existe una descarga exitosa para este reintento',
            'new_log_id' => $existing_retry['id'],
            'total_products' => $existing_retry['total_items'],
            'base_products' => $retry_details['base_products_count'] ?? $existing_retry['total_items'],
            'variations' => $retry_details['variations_count'] ?? 0,
            'file_size_mb' => isset($retry_details['file_size_bytes']) ? round($retry_details['file_size_bytes'] / 1024 / 1024, 2) : 0,
            'already_downloaded' => true
        ]);
        exit;
    }
    
    // 2. BUSCAR ARCHIVO EXISTENTE EN CACHE
    $cache_dir = dirname(__FILE__, 2) . '/cache/';
    $existing_file = null;
    $file_valid = false;
    
    // Verificar si hay un file_path en los detalles
    if (isset($old_details['file_path']) && file_exists($old_details['file_path'])) {
        $file_size = filesize($old_details['file_path']);
        if ($file_size > 1024) { // Mayor a 1KB
            $existing_file = $old_details['file_path'];
            $file_valid = true;
            $logger->info("Archivo encontrado en file_path: {$existing_file} ({$file_size} bytes)");
        }
    }
    
    // Verificar archivo latest en cache
    if (!$file_valid) {
        $latest_file = $cache_dir . 'yuju_products.json';
        if (file_exists($latest_file)) {
            $file_size = filesize($latest_file);
            if ($file_size > 1024) {
                $existing_file = $latest_file;
                $file_valid = true;
                $logger->info("Archivo encontrado en cache latest: {$existing_file} ({$file_size} bytes)");
            }
        }
    }
    
    // 3. SI EXISTE ARCHIVO VÁLIDO, USARLO SIN DESCARGAR
    if ($file_valid && $existing_file) {
        $logger->info("Usando archivo existente, sin descargar nuevamente");
        
        $json_content = file_get_contents($existing_file);
        $yuju_products = json_decode($json_content, true);
        
        if (!is_array($yuju_products) || empty($yuju_products)) {
            $logger->warning("Archivo existe pero contenido inválido, forzando descarga");
            $file_valid = false;
        } else {
            // Calcular productos base vs variaciones
            $base_products = [];
            $variations = [];
            foreach ($yuju_products as $product) {
                if (isset($product['id_parent']) && $product['id_parent']) {
                    $variations[] = $product;
                } else {
                    $base_products[] = $product;
                }
            }
            
            $file_size = filesize($existing_file);
            $size_mb = round($file_size / 1024 / 1024, 2);
            
            // Actualizar el registro anterior como completado (recuperado)
            Db::getInstance()->update('yuju_sync_logs', [
                'status' => 'completed',
                'end_time' => date('Y-m-d H:i:s'),
                'total_items' => count($yuju_products),
                'error_message' => '', // Limpiar el error
                'details' => pSQL(json_encode(array_merge($old_details, [
                    'recovered_from_cache' => true,
                    'file_path' => realpath($existing_file),
                    'file_size_bytes' => (int)$file_size,
                    'products_count' => count($yuju_products),
                    'base_products_count' => count($base_products),
                    'variations_count' => count($variations),
                    'products_breakdown' => [
                        'total' => count($yuju_products),
                        'base' => count($base_products),
                        'variations' => count($variations)
                    ],
                    'step' => 'recovered'
                ]), JSON_UNESCAPED_UNICODE))
            ], 'id = ' . $download_id);
            
            $logger->info("Descarga {$download_id} marcada como completada (recuperada desde cache)");
            
            echo json_encode([
                'success' => true,
                'message' => 'Archivo recuperado desde cache',
                'new_log_id' => $download_id,
                'total_products' => count($yuju_products),
                'base_products' => count($base_products),
                'variations' => count($variations),
                'file_size_mb' => $size_mb,
                'recovered_from_cache' => true
            ]);
            exit;
        }
    }
    
    // 4. SI NO HAY ARCHIVO VÁLIDO, DESCARGAR
    $logger->info("No hay archivo válido en cache, procediendo a descargar");
    
    // Obtener la URL de CloudFront guardada
    $download_url = null;
    
    // Intentar extraer la URL de múltiples campos posibles
    if (isset($old_details['cloudfront_url'])) {
        $download_url = $old_details['cloudfront_url'];
    } elseif (isset($old_details['download_url'])) {
        $download_url = $old_details['download_url'];
    } elseif (isset($old_details['url'])) {
        $download_url = $old_details['url'];
    } else {
        // Buscar en todo el array cualquier URL de CloudFront
        foreach ($old_details as $key => $value) {
            if (is_string($value) && strpos($value, 'cloudfront.net') !== false) {
                $download_url = $value;
                break;
            }
        }
    }
    
    // Si no se encuentra URL guardada, intentar obtener nueva desde la API
    if (!$download_url) {
        $logger->info("No se encontró URL guardada, obteniendo nueva desde API");
        $api = new YujuApiClient();
        $response = $api->get('products/dump');
        
        if (!isset($response['url'])) {
            throw new Exception('No se pudo obtener URL de descarga. No hay URL guardada ni disponible desde la API');
        }
        
        $download_url = $response['url'];
    }
    
    // Limpiar URL si viene escapada
    $download_url = stripslashes($download_url);
    $logger->info("Usando URL: {$download_url}");
    
    // ACTUALIZAR el registro existente en lugar de crear uno nuevo
    Db::getInstance()->update('yuju_sync_logs', [
        'status' => 'processing',
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => null,
        'error_message' => '',
        'details' => pSQL(json_encode(array_merge($old_details, [
            'retry_attempt' => true,
            'retry_time' => date('Y-m-d H:i:s'),
            'cloudfront_url' => $download_url,
            'step' => 'downloading_retry'
        ]), JSON_UNESCAPED_UNICODE))
    ], 'id = ' . $download_id);
    
    $new_log_id = $download_id; // Usar el mismo ID
    $logger->info("Actualizando registro existente ID: {$new_log_id}");
    
    // Descargar JSON desde CloudFront
    $ch = curl_init($download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $json_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $download_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $error_msg = "Error HTTP {$http_code} al descargar JSON desde CloudFront";
        
        Db::getInstance()->update('yuju_sync_logs', [
            'status' => 'failed',
            'end_time' => date('Y-m-d H:i:s'),
            'error_message' => pSQL($error_msg),
            'details' => pSQL(json_encode([
                'retry_of' => $download_id,
                'request_url' => 'products/dump',
                'cloudfront_url' => $download_url,
                'http_code' => $http_code,
                'download_http_code' => $http_code,
                'error' => $error_msg,
                'step' => 'failed'
            ], JSON_UNESCAPED_UNICODE))
        ], 'id = ' . $new_log_id);
        
        throw new Exception($error_msg);
    }
    
    $yuju_products = json_decode($json_data, true);
    
    if (!is_array($yuju_products)) {
        throw new Exception('Formato de JSON inválido');
    }
    
    // Calcular productos base vs variaciones
    $base_products = [];
    $variations = [];
    foreach ($yuju_products as $product) {
        if (isset($product['id_parent']) && $product['id_parent']) {
            $variations[] = $product;
        } else {
            $base_products[] = $product;
        }
    }
    
    // Guardar en cache
    $cache_dir = dirname(__FILE__, 2) . '/cache/';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0777, true);
        @chmod($cache_dir, 0777);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $timestamped_file = $cache_dir . "yuju_products_{$timestamp}.json";
    $cache_file = $cache_dir . 'yuju_products.json';
    
    $saved_timestamped = @file_put_contents($timestamped_file, json_encode($yuju_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $saved_latest = @file_put_contents($cache_file, json_encode($yuju_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Si falló guardar, intentar cambiar permisos y reintentar
    if ($saved_timestamped === false || $saved_latest === false) {
        @chmod($cache_dir, 0777);
        $saved_timestamped = @file_put_contents($timestamped_file, json_encode($yuju_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $saved_latest = @file_put_contents($cache_file, json_encode($yuju_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // Si aún falla, registrar warning pero continuar
    if ($saved_timestamped === false || $saved_latest === false) {
        $logger->warning("No se pudo guardar en cache por permisos. Cache dir: {$cache_dir}");
    }
    
    $download_size = strlen($json_data);
    $size_mb = round($download_size / 1024 / 1024, 2);
    
    // Actualizar log - descarga exitosa
    Db::getInstance()->update('yuju_sync_logs', [
        'status' => 'completed',
        'end_time' => date('Y-m-d H:i:s'),
        'total_items' => count($yuju_products),
        'details' => pSQL(json_encode([
            'retry_of' => $download_id,
            'request_url' => 'products/dump',
            'cloudfront_url' => $download_url,
            'download_url' => $download_url,
            'http_code' => $http_code,
            'download_http_code' => $http_code,
            'file_path' => realpath($timestamped_file),
            'file_path_latest' => realpath($cache_file),
            'file_size_bytes' => (int)$download_size,
            'products_count' => count($yuju_products),
            'base_products_count' => count($base_products),
            'variations_count' => count($variations),
            'products_breakdown' => [
                'total' => count($yuju_products),
                'base' => count($base_products),
                'variations' => count($variations)
            ],
            'step' => 'completed'
        ], JSON_UNESCAPED_UNICODE))
    ], 'id = ' . $new_log_id);
    
    $logger->info("Reintento exitoso. Descargados " . count($yuju_products) . " productos. Nuevo ID: {$new_log_id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Descarga completada exitosamente',
        'new_log_id' => $new_log_id,
        'total_products' => count($yuju_products),
        'base_products' => count($base_products),
        'variations' => count($variations),
        'file_size_mb' => $size_mb
    ]);
    
} catch (Exception $e) {
    $logger->error("Error en reintento de descarga: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
