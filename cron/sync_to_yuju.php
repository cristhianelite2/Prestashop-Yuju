<?php
/**
 * Sincronizar productos de PrestaShop hacia Yuju
 * Compara stock y precio, actualiza solo las diferencias
 */

// Cargar PrestaShop
$prestashop_path = dirname(__FILE__, 4);
require_once $prestashop_path . '/config/config.inc.php';
require_once $prestashop_path . '/init.php';

// Cargar clases del módulo
require_once dirname(__FILE__, 2) . '/classes/YujuApiClient.php';
require_once dirname(__FILE__, 2) . '/classes/YujuLogger.php';
require_once dirname(__FILE__, 2) . '/classes/YujuOAuth.php';

// Headers para JSON
header('Content-Type: application/json');

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Leer datos del POST
$input = json_decode(file_get_contents('php://input'), true);
$download_id = isset($input['download_id']) ? (int)$input['download_id'] : 0;

if (!$download_id) {
    http_response_code(400);
    echo json_encode(['error' => 'download_id es requerido']);
    exit;
}

try {
    // Obtener detalles de la descarga
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE `id` = ' . (int)$download_id;
    $download = Db::getInstance()->getRow($sql);
    
    if (!$download) {
        throw new Exception('Descarga no encontrada');
    }
    
    $details = json_decode($download['details'], true);
    $file_path = isset($details['file_path']) ? $details['file_path'] : null;
    
    if (!$file_path) {
        $file_path = isset($details['saved_to']) ? $details['saved_to'] : null;
    }
    
    // Definir directorio cache del módulo
    $cache_dir = dirname(__FILE__, 2) . '/cache/';
    
    // Si no hay file_path en details, usar el archivo más reciente del cache
    if (!$file_path) {
        $cache_file = $cache_dir . 'yuju_products.json';
        
        if (file_exists($cache_file)) {
            $file_path = $cache_file;
        }
    }
    
    // Verificar si la ruta existe
    if ($file_path && !file_exists($file_path)) {
        // La ruta puede ser de Docker (/var/www/html/...) pero estamos en otro contexto
        // Extraer solo el nombre del archivo y buscarlo en el cache
        $filename = basename($file_path);
        $alternative_path = $cache_dir . $filename;
        
        if (file_exists($alternative_path)) {
            $file_path = $alternative_path;
        } else {
            // Intentar con yuju_products.json (archivo latest)
            $latest_file = $cache_dir . 'yuju_products.json';
            if (file_exists($latest_file)) {
                $file_path = $latest_file;
            }
        }
    }
    
    if (!$file_path || !file_exists($file_path)) {
        // Información de depuración
        $debug_info = [
            'file_path_from_details' => $details['file_path'] ?? 'N/A',
            'saved_to_from_details' => $details['saved_to'] ?? 'N/A',
            'attempted_path' => $file_path,
            'cache_dir' => $cache_dir,
            'cache_dir_exists' => is_dir($cache_dir),
            'details_keys' => array_keys($details)
        ];
        throw new Exception('Archivo JSON no encontrado. Debug: ' . json_encode($debug_info));
    }
    
    // Leer productos del JSON
    $json_content = file_get_contents($file_path);
    $yuju_products = json_decode($json_content, true);
    
    if (!is_array($yuju_products)) {
        throw new Exception('Formato de JSON inválido');
    }
    
    // Log para depuración
    $logger = new YujuLogger();
    $logger->info("Productos leídos del JSON: " . count($yuju_products) . " productos en el archivo: " . $file_path);
    
    // Inicializar API
    $api = new YujuApiClient();
    
    // Contar productos únicos en PrestaShop (por referencia)
    $ps_count_sql = 'SELECT COUNT(DISTINCT reference) as count FROM `' . _DB_PREFIX_ . 'product` WHERE reference != ""';
    $ps_count_result = Db::getInstance()->getRow($ps_count_sql);
    $total_ps_products = $ps_count_result ? (int)$ps_count_result['count'] : 0;
    
    $results = [
        'total_yuju' => count($yuju_products),
        'total_prestashop' => $total_ps_products,
        'found_in_prestashop' => 0,
        'with_differences' => 0,
        'api_requests' => 0,
        'updated' => 0,
        'not_found' => 0,
        'synced' => 0,
        'errors' => 0,
        'details' => []
    ];
    
    // Procesar cada producto
    foreach ($yuju_products as $yuju_product) {
        $sku_simple = isset($yuju_product['sku_simple']) ? $yuju_product['sku_simple'] : null;
        $sku = isset($yuju_product['sku']) ? $yuju_product['sku'] : null;
        $id_parent = isset($yuju_product['id_parent']) ? $yuju_product['id_parent'] : null;
        
        $yuju_stock = isset($yuju_product['stock']) ? (int)$yuju_product['stock'] : 0;
        $yuju_price = isset($yuju_product['price']) ? (float)$yuju_product['price'] : 0;
        
        // IMPORTANTE: Si tiene id_parent, entonces:
        // - id_parent = ID del producto padre
        // - id_product = ID de la variación
        $yuju_variation_id = null;
        $yuju_parent_id = null;
        
        if ($id_parent) {
            // Es una variación
            $yuju_variation_id = isset($yuju_product['id_product']) ? $yuju_product['id_product'] : null;
            $yuju_parent_id = $id_parent;
        } else {
            // Es un producto base
            $yuju_parent_id = isset($yuju_product['id_product']) ? $yuju_product['id_product'] : null;
        }
        
        // Para mantener compatibilidad con el resto del código
        $yuju_id = $yuju_parent_id;
        
        // Debug: Log para variaciones
        if ($id_parent && $yuju_variation_id) {
            $logger->info("VARIACIÓN DETECTADA CORRECTAMENTE", [
                'sku' => $sku,
                'yuju_parent_id' => $yuju_parent_id,
                'yuju_variation_id' => $yuju_variation_id,
                'will_use_variation_endpoint' => true
            ]);
        }
        
        if (!$yuju_id) {
            $results['errors']++;
            $results['details'][] = [
                'sku' => $sku_simple ?? $sku ?? 'N/A',
                'status' => 'error',
                'message' => 'ID de Yuju no disponible',
                'yuju_stock' => $yuju_stock,
                'yuju_price' => $yuju_price,
                'ps_stock' => '-',
                'ps_price' => '-'
            ];
            continue;
        }
        
        $ps_product = null;
        $is_variation = false;
        
        // Si es un producto base (sin id_parent), buscar por sku_simple en ps_product
        if (!$id_parent && $sku_simple) {
            // Primero buscar el producto
            $product_sql = 'SELECT id_product, reference, price FROM ' . _DB_PREFIX_ . 'product WHERE reference = "' . pSQL($sku_simple) . '"';
            
            try {
                $ps_product = Db::getInstance()->getRow($product_sql);
                
                if ($ps_product) {
                    // Ahora buscar el stock
                    $stock_sql = 'SELECT quantity FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . (int)$ps_product['id_product'] . ' AND id_product_attribute = 0';
                    $stock_data = Db::getInstance()->getRow($stock_sql);
                    $ps_product['quantity'] = $stock_data ? (int)$stock_data['quantity'] : 0;
                }
                
                $results['debug_sql'] = $product_sql . ' | ' . ($stock_sql ?? '');
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'sku' => $sku_simple,
                    'status' => 'error',
                    'message' => 'Error SQL producto base: ' . $e->getMessage(),
                    'sql' => $product_sql,
                    'yuju_stock' => $yuju_stock,
                    'yuju_price' => $yuju_price,
                    'ps_stock' => '-',
                    'ps_price' => '-'
                ];
                continue;
            }
        }
        // Si es una variación (tiene id_parent), buscar por sku en ps_product_attribute
        else if ($id_parent && $sku) {
            // Buscar la variación (price en product_attribute es el precio adicional)
            $attr_sql = 'SELECT id_product, id_product_attribute, reference, price as price_impact FROM ' . _DB_PREFIX_ . 'product_attribute WHERE reference = "' . pSQL($sku) . '"';
            
            try {
                $ps_product = Db::getInstance()->getRow($attr_sql);
                
                if ($ps_product) {
                    // Buscar precio base del producto
                    $base_sql = 'SELECT price FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int)$ps_product['id_product'];
                    $base_data = Db::getInstance()->getRow($base_sql);
                    $base_price = $base_data ? (float)$base_data['price'] : 0;
                    
                    // Precio total = precio base + impacto de la variación
                    $price_impact = isset($ps_product['price_impact']) ? (float)$ps_product['price_impact'] : 0;
                    $ps_product['final_price'] = $base_price + $price_impact;
                    
                    // Buscar stock
                    $stock_sql = 'SELECT quantity FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . (int)$ps_product['id_product'] . ' AND id_product_attribute = ' . (int)$ps_product['id_product_attribute'];
                    $stock_data = Db::getInstance()->getRow($stock_sql);
                    $ps_product['quantity'] = $stock_data ? (int)$stock_data['quantity'] : 0;
                    
                    // Debug logging para variaciones
                    $logger->info("Variación encontrada", [
                        'sku' => $sku,
                        'id_product' => $ps_product['id_product'],
                        'id_product_attribute' => $ps_product['id_product_attribute'],
                        'stock_query' => $stock_sql,
                        'stock_result' => $stock_data,
                        'quantity' => $ps_product['quantity']
                    ]);
                }
                
                $is_variation = true;
                $results['debug_sql_variation'] = $attr_sql;
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'sku' => $sku,
                    'status' => 'error',
                    'message' => 'Error SQL variación: ' . $e->getMessage(),
                    'sql' => $attr_sql,
                    'yuju_stock' => $yuju_stock,
                    'yuju_price' => $yuju_price,
                    'ps_stock' => '-',
                    'ps_price' => '-'
                ];
                continue;
            }
        }
        
        if (!$ps_product) {
            $results['not_found']++;
            $results['details'][] = [
                'sku' => $sku_simple ?? $sku,
                'status' => 'not_found',
                'message' => 'Producto no encontrado en PrestaShop',
                'type' => $id_parent ? 'variación' : 'base',
                'yuju_stock' => $yuju_stock,
                'yuju_price' => $yuju_price,
                'ps_stock' => '-',
                'ps_price' => '-'
            ];
            continue;
        }
        
        // Producto encontrado en PrestaShop
        $results['found_in_prestashop']++;
        
        // Obtener stock y precio según el tipo
        $ps_stock = (int)$ps_product['quantity'];
        
        if ($is_variation) {
            // Para variaciones, usar el precio final calculado (base + impacto)
            $ps_price = (float)$ps_product['final_price'];
        } else {
            $ps_price = (float)$ps_product['price'];
        }
        
        $needs_update = false;
        $update_data = [];
        $changes = [];
        
        // Verificar diferencias
        if ($ps_stock != $yuju_stock) {
            $needs_update = true;
            $update_data['stock'] = $ps_stock;
            $changes[] = "stock: {$yuju_stock} → {$ps_stock}";
            
            $logger->info("DIFERENCIA EN STOCK DETECTADA", [
                'sku' => $sku_simple ?? $sku,
                'ps_stock' => $ps_stock,
                'yuju_stock' => $yuju_stock,
                'type' => gettype($ps_stock) . ' vs ' . gettype($yuju_stock)
            ]);
        }
        
        if (abs($ps_price - $yuju_price) > 0.01) {
            $needs_update = true;
            $update_data['price'] = $ps_price;
            $changes[] = "precio: {$yuju_price} → {$ps_price}";
            
            $logger->info("DIFERENCIA EN PRECIO DETECTADA", [
                'sku' => $sku_simple ?? $sku,
                'ps_price' => $ps_price,
                'yuju_price' => $yuju_price,
                'diff' => abs($ps_price - $yuju_price)
            ]);
        }
        
        if (!$needs_update) {
            $results['synced']++;
            $results['details'][] = [
                'sku' => $sku_simple ?? $sku,
                'status' => 'synced',
                'message' => 'Ya sincronizado',
                'ps_stock' => $ps_stock,
                'ps_price' => $ps_price,
                'yuju_stock' => $yuju_stock,
                'yuju_price' => $yuju_price,
                'type' => $is_variation ? 'variación' : 'base'
            ];
            continue;
        }
        
        // Producto con diferencias
        $results['with_differences']++;
        
        // Debug CRÍTICO: Verificar que sí se va a actualizar
        $logger->info("PRODUCTO CON DIFERENCIAS - VA A ACTUALIZAR", [
            'sku' => $sku_simple ?? $sku,
            'yuju_id' => $yuju_id,
            'is_variation' => $is_variation,
            'ps_stock' => $ps_stock,
            'yuju_stock' => $yuju_stock,
            'ps_price' => $ps_price,
            'yuju_price' => $yuju_price,
            'update_data' => $update_data,
            'changes' => $changes
        ]);
        
        // Actualizar en Yuju
        try {
            $results['api_requests']++;
            
            $logger->info("ANTES DE LLAMAR updateOffer", [
                'yuju_id' => $yuju_id,
                'update_data' => $update_data,
                'api_class' => get_class($api),
                'is_variation' => $is_variation,
                'yuju_variation_id' => $yuju_variation_id ?? 'N/A'
            ]);
            
            // Usar el endpoint correcto según el tipo de producto
            if ($is_variation && $yuju_variation_id) {
                // Para variaciones: PUT /products/{id_product}/variations/{id_variation}
                $response = $api->updateVariation($yuju_id, $yuju_variation_id, $update_data);
            } else {
                // Para productos base: PUT /products/{id_product}
                $response = $api->updateOffer($yuju_id, $update_data);
            }
            
            $logger->info("DESPUÉS DE LLAMAR updateOffer", [
                'response_received' => !empty($response),
                'response_type' => gettype($response),
                'response_keys' => is_array($response) ? array_keys($response) : 'not_array'
            ]);
            
            // Debug: Log de la respuesta recibida
            $logger->info("Respuesta de updateOffer", [
                'sku' => $sku_simple ?? $sku,
                'yuju_id' => $yuju_id,
                'update_data_sent' => $update_data,
                'response_full' => $response,
                'response_success' => $response['success'] ?? 'N/A',
                'response_error' => $response['error'] ?? 'N/A',
                'response_message' => $response['message'] ?? 'N/A',
                'response_http_code' => $response['http_code'] ?? 'N/A',
                'response_data' => $response['data'] ?? 'N/A'
            ]);
            
            if (!$response['success'] || isset($response['error'])) {
                // Formatear mensaje de error
                $error_message = 'Error al actualizar en Yuju';
                
                if (isset($response['message']) && $response['message']) {
                    $error_message = $response['message'];
                } else if (isset($response['error'])) {
                    $error_code = $response['error'];
                    
                    // Traducir códigos de error comunes
                    $error_translations = [
                        'HTTP_403' => 'Acceso prohibido (permisos insuficientes)',
                        'HTTP_404' => 'Producto no encontrado en Yuju',
                        'HTTP_401' => 'No autorizado (token inválido)',
                        'HTTP_500' => 'Error interno del servidor de Yuju',
                        'HTTP_400' => 'Petición incorrecta (datos inválidos)'
                    ];
                    
                    $error_message = isset($error_translations[$error_code]) 
                        ? $error_translations[$error_code] 
                        : $error_code;
                }
                
                throw new Exception($error_message);
            }
            
            $results['updated']++;
            $results['details'][] = [
                'sku' => $sku,
                'status' => 'updated',
                'message' => 'Actualizado: ' . implode(', ', $changes),
                'ps_stock' => $ps_stock,
                'ps_price' => $ps_price,
                'yuju_stock' => $yuju_stock,
                'yuju_price' => $yuju_price,
                'changes' => $update_data
            ];
            
            $logger->info("Producto sincronizado a Yuju: SKU {$sku}, cambios: " . implode(', ', $changes));
            
        } catch (Exception $e) {
            $results['errors']++;
            $results['details'][] = [
                'sku' => $sku_simple ?? $sku,
                'status' => 'error',
                'message' => 'Error al actualizar: ' . $e->getMessage(),
                'ps_stock' => $ps_stock ?? '-',
                'ps_price' => $ps_price ?? '-',
                'yuju_stock' => $yuju_stock,
                'yuju_price' => $yuju_price
            ];
            
            $logger->error("Error sincronizando producto a Yuju: SKU {$sku}, error: " . $e->getMessage());
        }
    }
    
    // Guardar resultados en la base de datos
    $end_time = date('Y-m-d H:i:s');
    $status = ($results['errors'] > 0) ? 'completed_with_errors' : 'completed';
    
    $log_data = [
        'entity_type' => 'products',
        'sync_direction' => 'prestashop_to_yuju',
        'status' => $status,
        'start_time' => $end_time,
        'end_time' => $end_time,
        'total_items' => $results['api_requests'],
        'details' => pSQL(json_encode([
            'source_download_id' => $download_id,
            'summary' => [
                'total_yuju' => $results['total_yuju'],
                'total_prestashop' => $results['total_prestashop'],
                'found_in_prestashop' => $results['found_in_prestashop'],
                'with_differences' => $results['with_differences'],
                'api_requests' => $results['api_requests'],
                'updated' => $results['updated'],
                'synced' => $results['synced'],
                'not_found' => $results['not_found'],
                'errors' => $results['errors']
            ],
            'details' => $results['details']
        ], JSON_UNESCAPED_UNICODE))
    ];
    
    if ($results['errors'] > 0 || $results['not_found'] > 0) {
        $error_messages = [];
        foreach ($results['details'] as $detail) {
            if ($detail['status'] === 'error' || $detail['status'] === 'not_found') {
                $error_messages[] = $detail['sku'] . ': ' . $detail['message'];
            }
        }
        $log_data['error_message'] = pSQL(implode('; ', array_slice($error_messages, 0, 5)));
    }
    
    Db::getInstance()->insert('yuju_sync_logs', $log_data);
    $sync_log_id = Db::getInstance()->Insert_ID();
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'sync_log_id' => $sync_log_id,
        'debug' => [
            'file_used' => $file_path,
            'products_in_file' => count($yuju_products),
            'download_id' => $download_id
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
