<?php
/**
 * 2024 Yuju Integration - Sincronización de Productos
 *
 * Sistema de sincronización con cache:
 * 1. Descarga JSON de Yuju máximo cada 3 horas (límite API)
 * 2. Guarda JSON en cache para reutilización
 * 3. Procesa productos en lotes
 * 4. Actualiza stock y precio en Yuju
 */

// Cargar PrestaShop si no está cargado
if (!defined('_PS_VERSION_')) {
    $root_path = dirname(__FILE__);
    require_once $root_path . '/../../config/config.inc.php';
    require_once $root_path . '/../../init.php';
}

require_once dirname(__FILE__) . '/../classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/../classes/YujuApiClient.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../config/config.php';

$logger = new YujuLogger();
$sync_start = microtime(true);

// Detectar si es web o CLI
$is_web = (php_sapi_name() !== 'cli');

function log_message($message, $type = 'info', $is_web = false) {
    if ($is_web) {
        $colors = [
            'info' => 'text-blue-600',
            'success' => 'text-green-600',
            'warning' => 'text-yellow-600',
            'error' => 'text-red-600'
        ];
        echo '<p class="' . ($colors[$type] ?? 'text-gray-600') . ' mb-2">';
        echo '<i class="fas fa-circle mr-2" style="font-size: 8px;"></i>';
        echo htmlspecialchars($message);
        echo '</p>';
    } else {
        echo "[" . strtoupper($type) . "] " . $message . "\n";
    }
}

try {
    log_message("Iniciando sincronización de productos...", 'info', $is_web);
    
    // Verificar token
    $oauth = new YujuOAuth();
    $token = $oauth->getValidAccessToken();
    
    if (!$token) {
        throw new Exception('No hay token válido. Reconecta OAuth.');
    }
    
    log_message("Token OAuth válido encontrado", 'success', $is_web);
    
    // ========================================
    // VALIDACIÓN DE TRABAJO PENDIENTE (EARLY EXIT)
    // ========================================
    // Si no hay trabajos pendientes y la descarga está vigente, salir temprano
    
    $two_hours_ago = date('Y-m-d H:i:s', time() - 7200);
    
    // 1. Verificar si hay descargas pendientes o con error en las últimas 2 horas
    $pending_downloads = Db::getInstance()->getRow(
        'SELECT COUNT(*) as count FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
         WHERE `entity_type` = "products" 
         AND `sync_direction` = "yuju_to_prestashop"
         AND `status` IN ("started", "failed")
         AND `start_time` >= "' . pSQL($two_hours_ago) . '"'
    );
    
    // 2. Verificar si hay sincronizaciones pendientes en las últimas 2 horas
    $pending_syncs = Db::getInstance()->executeS(
        'SELECT d.id 
         FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` d
         WHERE d.entity_type = "products" 
         AND d.sync_direction = "yuju_to_prestashop"
         AND d.status = "completed"
         AND d.start_time >= "' . pSQL($two_hours_ago) . '"
         AND NOT EXISTS (
             SELECT 1 FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` s
             WHERE s.sync_direction = "prestashop_to_yuju"
             AND s.details IS NOT NULL
             AND JSON_EXTRACT(s.details, "$.source_download_id") = d.id
         )'
    );
    
    // 3. Verificar si necesitamos nueva descarga (más de 12 horas desde última descarga exitosa)
    $last_download = Db::getInstance()->getRow(
        'SELECT `start_time`, `status` FROM `' . _DB_PREFIX_ . 'yuju_sync_logs`
         WHERE `entity_type` = "products"
         AND `sync_direction` = "yuju_to_prestashop"
         AND `status` = "completed"
         ORDER BY `start_time` DESC
         LIMIT 1'
    );
    
    $need_download = true;
    if ($last_download && $last_download['status'] === 'completed') {
        $last_download_time = strtotime($last_download['start_time']);
        $download_interval = 12 * 3600; // 12 horas
        $time_since_download = time() - $last_download_time;
        $need_download = ($time_since_download >= $download_interval);
    }
    
    $total_pending_downloads = $pending_downloads ? (int)$pending_downloads['count'] : 0;
    $total_pending_syncs = $pending_syncs ? count($pending_syncs) : 0;
    $total_pending = $total_pending_downloads + $total_pending_syncs;
    
    // Si no hay trabajo pendiente Y no necesitamos descarga, salir
    if ($total_pending === 0 && !$need_download) {
        $hours_until_next = round((($download_interval - $time_since_download) / 3600), 1);
        log_message("", 'info', $is_web);
        log_message("✅ SISTEMA AL DÍA - No hay trabajos pendientes", 'success', $is_web);
        log_message("   Última descarga: " . date('Y-m-d H:i:s', $last_download_time), 'info', $is_web);
        log_message("   Próxima descarga en: ~$hours_until_next horas", 'info', $is_web);
        log_message("   Ejecutando en modo monitoreo ligero (10 min)", 'info', $is_web);
        log_message("", 'info', $is_web);
        
        $execution_time = round(microtime(true) - $sync_start, 2);
        log_message("Tiempo de ejecución: {$execution_time}s", 'info', $is_web);
        exit(0);
    }
    
    // Si llegamos aquí, hay trabajo por hacer
    if ($total_pending > 0) {
        log_message("", 'info', $is_web);
        log_message("⚡ TRABAJO PENDIENTE DETECTADO", 'info', $is_web);
        log_message("   Descargas pendientes: $total_pending_downloads", $total_pending_downloads > 0 ? 'warning' : 'info', $is_web);
        log_message("   Sincronizaciones pendientes: $total_pending_syncs", $total_pending_syncs > 0 ? 'warning' : 'info', $is_web);
        log_message("", 'info', $is_web);
    }
    
    if ($need_download) {
        if (isset($time_since_download)) {
            $hours_since = round($time_since_download / 3600, 1);
            log_message("⚡ NUEVA DESCARGA NECESARIA (han pasado $hours_since horas)", 'info', $is_web);
        } else {
            log_message("⚡ PRIMERA DESCARGA DEL SISTEMA", 'info', $is_web);
        }
        log_message("", 'info', $is_web);
    }
    
    // Configuración de cache
    $cache_dir = dirname(__FILE__) . '/../cache/';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . 'yuju_products.json';
    $cache_meta_file = $cache_dir . 'yuju_products_meta.json';
    $cache_lifetime = 12 * 3600; // 12 horas en segundos (intervalo de descarga)
    
    $yuju_products = null;
    $from_cache = false;
    $cache_age = 0;
    $download_url = null;
    
    // Verificar si existe cache válido
    if (file_exists($cache_file) && file_exists($cache_meta_file)) {
        $cache_meta = json_decode(file_get_contents($cache_meta_file), true);
        $cache_age = time() - $cache_meta['timestamp'];
        
        if ($cache_age < $cache_lifetime) {
            // Usar cache
            $yuju_products = json_decode(file_get_contents($cache_file), true);
            $from_cache = true;
            $hours_old = round($cache_age / 3600, 1);
            $download_url = $cache_meta['download_url'] ?? 'N/A';
            log_message("✓ Usando cache de Yuju (descargado hace $hours_old horas)", 'info', $is_web);
            log_message("  → Archivo guardado en: " . realpath($cache_file), 'info', $is_web);
        } else {
            log_message("⚠ Cache expirado (más de 12 horas), descargando datos frescos...", 'warning', $is_web);
        }
    } else {
        log_message("ℹ No hay cache, iniciando descarga desde Yuju...", 'info', $is_web);
    }
    
    // Descargar de Yuju si no hay cache válido
    if (!$yuju_products && $need_download) {
        log_message("➤ PASO 1: Solicitando URL de descarga a Yuju API...", 'info', $is_web);
        log_message("  Endpoint: GET /products-offer-report", 'info', $is_web);
        
        $request_url = 'https://api.tp.yuju.io/products-offer-report';
        
        // Registrar inicio de descarga en sync_logs
        $log_data = [
            'sync_type' => 'manual',
            'entity_type' => 'products',
            'sync_direction' => 'yuju_to_prestashop',
            'status' => 'started',
            'start_time' => date('Y-m-d H:i:s'),
            'created_by' => 'cron',
            'details' => json_encode([
                'request_url' => $request_url,
                'step' => 'requesting_url',
                'timestamp' => time()
            ], JSON_UNESCAPED_UNICODE)
        ];
        
        Db::getInstance()->insert('yuju_sync_logs', $log_data);
        $log_id = (int)Db::getInstance()->Insert_ID();
        
        // Paso 1: Solicitar la URL del JSON
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $request_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $api_response = json_decode($response, true);
        
        // Actualizar log con código HTTP y respuesta
        $log_details = json_decode(Db::getInstance()->getValue('SELECT details FROM ' . _DB_PREFIX_ . 'yuju_sync_logs WHERE id = ' . $log_id), true);
        $log_details['http_code'] = $http_code;
        $log_details['api_response'] = $api_response;
        $log_details['step'] = 'url_received';
        
        Db::getInstance()->update('yuju_sync_logs', [
            'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE))
        ], 'id = ' . $log_id);
        
        // Verificar si Yuju devolvió mensaje de límite de tiempo
        if ($http_code === 200 && isset($api_response['message']) && strpos($api_response['message'], 'El reporte se puede procesar cada') !== false) {
            // Extraer el tiempo de desbloqueo del mensaje
            // Ejemplo: "El reporte se puede procesar cada 3h.El desbloqueo termina 2025-10-22 20:09:34.450113"
            preg_match('/El desbloqueo termina (.+)$/', $api_response['message'], $matches);
            $unlock_time = isset($matches[1]) ? trim($matches[1]) : null;
            
            // Actualizar log - API bloqueada
            $log_details['blocked'] = true;
            $log_details['block_message'] = $api_response['message'];
            $log_details['unlock_time'] = $unlock_time;
            
            Db::getInstance()->update('yuju_sync_logs', [
                'status' => 'failed',
                'error_message' => pSQL('API bloqueada: ' . $api_response['message']),
                'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                'end_time' => date('Y-m-d H:i:s')
            ], 'id = ' . $log_id);
            
            // Guardar información del bloqueo
            $block_info = [
                'blocked' => true,
                'message' => $api_response['message'],
                'unlock_time' => $unlock_time,
                'blocked_at' => date('Y-m-d H:i:s'),
                'timestamp' => time()
            ];
            
            file_put_contents($cache_dir . 'yuju_block_info.json', json_encode($block_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            log_message("⏳ YUJU API BLOQUEADA: " . $api_response['message'], 'warning', $is_web);
            
            if ($unlock_time) {
                try {
                    $unlock_timestamp = strtotime($unlock_time);
                    $time_remaining = $unlock_timestamp - time();
                    $hours = floor($time_remaining / 3600);
                    $minutes = floor(($time_remaining % 3600) / 60);
                    log_message("⏰ Tiempo restante para desbloqueo: {$hours}h {$minutes}m", 'warning', $is_web);
                } catch (Exception $e) {
                    log_message("⚠ No se pudo calcular tiempo de desbloqueo", 'warning', $is_web);
                }
            }
            
            // Usar cache existente si hay
            if (file_exists($cache_file)) {
                log_message("✓ Usando cache existente debido al bloqueo de Yuju", 'info', $is_web);
                $yuju_products = json_decode(file_get_contents($cache_file), true);
                $from_cache = true;
            } else {
                throw new Exception("Yuju API bloqueada y no hay cache disponible. " . $api_response['message']);
            }
        } elseif ($http_code !== 200) {
            // Actualizar log - error HTTP
            $log_details['error'] = "Error HTTP $http_code: $curl_error";
            
            Db::getInstance()->update('yuju_sync_logs', [
                'status' => 'failed',
                'error_message' => pSQL("Error HTTP $http_code: $curl_error"),
                'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                'end_time' => date('Y-m-d H:i:s')
            ], 'id = ' . $log_id);
            
            throw new Exception("Error al solicitar reporte a Yuju (HTTP $http_code): $curl_error");
        } elseif (!isset($api_response['url'])) {
            // Actualizar log - sin URL
            $log_details['error'] = "Yuju no devolvió URL de descarga válida";
            
            Db::getInstance()->update('yuju_sync_logs', [
                'status' => 'failed',
                'error_message' => pSQL("Yuju no devolvió URL de descarga válida"),
                'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                'end_time' => date('Y-m-d H:i:s')
            ], 'id = ' . $log_id);
            
            throw new Exception("Yuju no devolvió una URL de descarga válida. Respuesta: " . substr($response, 0, 200));
        } else {
            // Proceso normal - URL recibida correctamente
            $download_url = $api_response['url'];
            log_message("✓ URL de descarga obtenida: " . $download_url, 'success', $is_web);
            
            // Actualizar log con la URL
            $log_details['download_url'] = $download_url;
            $log_details['step'] = 'downloading_file';
            
            Db::getInstance()->update('yuju_sync_logs', [
                'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE))
            ], 'id = ' . $log_id);
            
            // Paso 2: Descargar el JSON desde la URL proporcionada
            log_message("➤ PASO 2: Descargando archivo JSON desde la URL...", 'info', $is_web);
            
            // IMPORTANTE: CloudFront usa signed URLs, NO enviar headers de autorización
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $download_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120, // Mayor timeout para descargas grandes
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                // NO enviar Authorization header - CloudFront usa signed URLs
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: PrestaShop-Yuju-Module/1.0'
                ],
            ]);
            
            $json_content = curl_exec($ch);
            $download_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $download_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
            $download_error = curl_error($ch);
            curl_close($ch);
            
            // Si falla con 403, intentar una segunda vez después de un pequeño delay
            if ($download_http_code === 403) {
                log_message("⚠ Error 403 detectado, reintentando en 2 segundos...", 'warning', $is_web);
                sleep(2);
                
                // Segundo intento - solicitar NUEVA URL a Yuju
                log_message("➤ Solicitando nueva URL de descarga...", 'info', $is_web);
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $request_url, // Solicitar nueva URL
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: Bearer ' . $token
                    ],
                ]);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 200) {
                    $api_response = json_decode($response, true);
                    if (isset($api_response['url'])) {
                        $download_url = $api_response['url'];
                        log_message("✓ Nueva URL obtenida, descargando...", 'success', $is_web);
                        
                        // Descargar con la nueva URL
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $download_url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 120,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_HTTPHEADER => [
                                'Accept: application/json',
                                'User-Agent: PrestaShop-Yuju-Module/1.0'
                            ],
                        ]);
                        
                        $json_content = curl_exec($ch);
                        $download_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $download_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
                        $download_error = curl_error($ch);
                        curl_close($ch);
                        
                        log_message("✓ Reintento exitoso - HTTP $download_http_code", 'success', $is_web);
                        
                        // Actualizar log con info del reintento
                        $log_details['retry_attempted'] = true;
                        $log_details['retry_successful'] = ($download_http_code === 200);
                        $log_details['download_url_retry'] = $download_url;
                    }
                }
            }
            
            if ($download_http_code !== 200) {
                // Actualizar log - error en descarga
                $log_details['download_error'] = "Error HTTP $download_http_code: $download_error";
                $log_details['download_http_code'] = $download_http_code;
                
                Db::getInstance()->update('yuju_sync_logs', [
                    'status' => 'failed',
                    'error_message' => pSQL("Error HTTP $download_http_code al descargar JSON: $download_error"),
                    'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                    'end_time' => date('Y-m-d H:i:s')
                ], 'id = ' . $log_id);
                
                log_message("❌ ERROR $download_http_code: No se pudo descargar el archivo JSON", 'error', $is_web);
                log_message("   URL guardada en log (ID: $log_id) para depuración", 'info', $is_web);
                
                throw new Exception("Error al descargar JSON desde URL (HTTP $download_http_code). URL registrada en BD para depuración.");
            }
            
            if (empty($json_content)) {
                // Actualizar log - archivo vacío
                $log_details['download_error'] = "Archivo JSON vacío";
                
                Db::getInstance()->update('yuju_sync_logs', [
                    'status' => 'failed',
                    'error_message' => pSQL("Archivo JSON vacío"),
                    'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                    'end_time' => date('Y-m-d H:i:s')
                ], 'id = ' . $log_id);
                
                throw new Exception("El archivo JSON descargado está vacío");
            }
            
            $yuju_products = json_decode($json_content, true);
            
            if (!is_array($yuju_products)) {
                // Actualizar log - JSON inválido
                $log_details['download_error'] = "Contenido no es un JSON válido";
                
                Db::getInstance()->update('yuju_sync_logs', [
                    'status' => 'failed',
                    'error_message' => pSQL("Contenido no es un JSON válido"),
                    'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                    'end_time' => date('Y-m-d H:i:s')
                ], 'id = ' . $log_id);
                
                throw new Exception("El contenido descargado no es un JSON válido");
            }
            
            $size_mb = round($download_size / 1024 / 1024, 2);
            log_message("✓ Archivo descargado exitosamente: {$size_mb} MB", 'success', $is_web);
            log_message("✓ Total de productos en JSON: " . count($yuju_products), 'success', $is_web);
            
            // Generar nombre de archivo con timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $timestamped_file = $cache_dir . "yuju_products_{$timestamp}.json";
            
            // Guardar en cache local con timestamp
            $bytes_written = file_put_contents($timestamped_file, json_encode($yuju_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // También guardar como archivo "latest" para compatibilidad
            file_put_contents($cache_file, json_encode($yuju_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            log_message("✓ Archivo guardado: " . basename($timestamped_file), 'success', $is_web);
            
            $cache_metadata = [
                'timestamp' => time(),
                'date' => date('Y-m-d H:i:s'),
                'download_url' => $download_url,
                'total_products' => count($yuju_products),
                'file_size_mb' => $size_mb,
                'http_code' => $http_code,
                'saved_to' => realpath($timestamped_file),
                'saved_to_latest' => realpath($cache_file),
                'expires_at' => date('Y-m-d H:i:s', time() + $cache_lifetime)
            ];
            
            file_put_contents($cache_meta_file, json_encode($cache_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
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
            
            // Actualizar log - descarga exitosa
            $log_details['file_path'] = realpath($timestamped_file);
            $log_details['file_path_latest'] = realpath($cache_file);
            $log_details['file_size_bytes'] = (int)$download_size;
            $log_details['products_count'] = count($yuju_products);
            $log_details['base_products_count'] = count($base_products);
            $log_details['variations_count'] = count($variations);
            $log_details['products_breakdown'] = [
                'total' => count($yuju_products),
                'base' => count($base_products),
                'variations' => count($variations)
            ];
            $log_details['step'] = 'completed';
            
            Db::getInstance()->update('yuju_sync_logs', [
                'status' => 'completed',
                'total_items' => count($yuju_products),
                'details' => pSQL(json_encode($log_details, JSON_UNESCAPED_UNICODE)),
                'end_time' => date('Y-m-d H:i:s')
            ], 'id = ' . $log_id);
            
            log_message("✓ Descarga registrada en log de sincronización (ID: $log_id)", 'success', $is_web);
            
            // Limpiar información de bloqueo si existe
            $block_info_file = $cache_dir . 'yuju_block_info.json';
            if (file_exists($block_info_file)) {
                unlink($block_info_file);
            }
            
            log_message("✓ JSON guardado localmente en: " . realpath($cache_file), 'success', $is_web);
            log_message("  → Tamaño del archivo: " . round($bytes_written / 1024 / 1024, 2) . " MB", 'info', $is_web);
            log_message("  → Expira en: " . date('Y-m-d H:i:s', time() + $cache_lifetime), 'info', $is_web);
            
            $from_cache = false;
            $cache_age = 0;
            
            // ========================================
            // AUTO-SINCRONIZACIÓN DESPUÉS DE DESCARGA
            // ========================================
            log_message("", 'info', $is_web);
            log_message("🔄 Iniciando auto-sincronización (PrestaShop → Yuju)...", 'info', $is_web);
            
            try {
                // Verificar si ya existe una sincronización para este download_id
                $check_sync_sql = 'SELECT `details` FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                                   WHERE `sync_direction` = "prestashop_to_yuju" 
                                   AND `entity_type` = "products"';
                
                $all_syncs = Db::getInstance()->executeS($check_sync_sql);
                $has_previous_sync = false;
                
                if ($all_syncs) {
                    foreach ($all_syncs as $sync) {
                        $sync_details = json_decode($sync['details'], true);
                        if (isset($sync_details['source_download_id']) && $sync_details['source_download_id'] == $log_id) {
                            $has_previous_sync = true;
                            break;
                        }
                    }
                }
                
                if ($has_previous_sync) {
                    log_message("ℹ️  Ya existe sincronización para esta descarga, omitiendo...", 'info', $is_web);
                } else {
                    // Ejecutar sincronización automática
                    log_message("⚡ Ejecutando sincronización automática...", 'info', $is_web);
                    
                    // Incluir el archivo de sincronización y ejecutarlo
                    $_POST['download_id'] = $log_id;
                    $_SERVER['REQUEST_METHOD'] = 'POST';
                    
                    // Capturar la salida
                    ob_start();
                    include dirname(__FILE__) . '/sync_to_yuju.php';
                    $sync_output = ob_get_clean();
                    
                    $sync_result = json_decode($sync_output, true);
                    
                    if ($sync_result && isset($sync_result['success']) && $sync_result['success']) {
                        $results = $sync_result['results'];
                        log_message("✓ Sincronización completada", 'success', $is_web);
                        log_message("  → Actualizados: " . ($results['updated'] ?? 0), 'success', $is_web);
                        log_message("  → Sincronizados: " . ($results['synced'] ?? 0), 'info', $is_web);
                        log_message("  → No encontrados: " . ($results['not_found'] ?? 0), 'warning', $is_web);
                        log_message("  → Errores: " . ($results['errors'] ?? 0), ($results['errors'] > 0 ? 'error' : 'info'), $is_web);
                    } else {
                        log_message("⚠️ Error en sincronización automática", 'warning', $is_web);
                    }
                }
            } catch (Exception $sync_error) {
                log_message("⚠️ Error en auto-sincronización: " . $sync_error->getMessage(), 'warning', $is_web);
            }
            
            log_message("", 'info', $is_web);
        }
    }
    
    // ========================================
    // VALIDAR SI HAY TRABAJOS PENDIENTES
    // ========================================
    log_message("🔍 Verificando trabajos pendientes...", 'info', $is_web);
    
    // Buscar descargas pendientes o con error en las últimas 2 horas
    $two_hours_ago = date('Y-m-d H:i:s', time() - 7200);
    $pending_downloads_sql = 'SELECT COUNT(*) as count FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` 
                              WHERE `entity_type` = "products" 
                              AND `sync_direction` = "yuju_to_prestashop"
                              AND `status` IN ("started", "failed")
                              AND `start_time` >= "' . pSQL($two_hours_ago) . '"';
    
    $pending_downloads = Db::getInstance()->getRow($pending_downloads_sql);
    $pending_count = $pending_downloads ? (int)$pending_downloads['count'] : 0;
    
    if ($pending_count > 0) {
        log_message("⚠️  Hay $pending_count descarga(s) pendiente(s) en las últimas 2 horas", 'warning', $is_web);
        log_message("   El CRON continuará ejecutándose cada 10 minutos", 'info', $is_web);
    } else {
        log_message("✓ No hay descargas pendientes", 'success', $is_web);
    }
    
    // Buscar sincronizaciones pendientes en las últimas 2 horas
    $pending_syncs_sql = 'SELECT d.id as download_id, d.start_time 
                          FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` d
                          WHERE d.entity_type = "products" 
                          AND d.sync_direction = "yuju_to_prestashop"
                          AND d.status = "completed"
                          AND d.start_time >= "' . pSQL($two_hours_ago) . '"
                          AND NOT EXISTS (
                              SELECT 1 FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` s
                              WHERE s.sync_direction = "prestashop_to_yuju"
                              AND JSON_EXTRACT(s.details, "$.source_download_id") = d.id
                          )';
    
    $pending_syncs = Db::getInstance()->executeS($pending_syncs_sql);
    $pending_sync_count = $pending_syncs ? count($pending_syncs) : 0;
    
    if ($pending_sync_count > 0) {
        log_message("⚠️  Hay $pending_sync_count descarga(s) sin sincronizar en las últimas 2 horas", 'warning', $is_web);
        log_message("   El CRON continuará ejecutándose cada 10 minutos", 'info', $is_web);
    } else {
        log_message("✓ No hay sincronizaciones pendientes", 'success', $is_web);
    }
    
    $total_pending = $pending_count + $pending_sync_count;
    
    if ($total_pending === 0) {
        log_message("", 'info', $is_web);
        log_message("✅ SISTEMA SINCRONIZADO - No hay trabajos pendientes", 'success', $is_web);
        log_message("   El CRON puede ejecutarse en modo ligero (cada 10 min)", 'info', $is_web);
    }
    
    log_message("", 'info', $is_web);

    
    $total_yuju = count($yuju_products);
    log_message("Productos en JSON: $total_yuju", 'success', $is_web);
    
    // Preparar reporte
    $report_date = date('Y-m-d');
    $report_file = dirname(__FILE__) . '/../logs/sync_logs/sync_' . $report_date . '.log';
    $report_dir = dirname($report_file);
    
    if (!is_dir($report_dir)) {
        mkdir($report_dir, 0755, true);
    }
    
    $report = [];
    $report[] = "=================================================";
    $report[] = "REPORTE DE SINCRONIZACIÓN - " . date('Y-m-d H:i:s');
    $report[] = "=================================================";
    $report[] = "Fuente: " . ($from_cache ? "Cache (hace " . floor($cache_age/60) . " min)" : "Descarga fresca de Yuju");
    $report[] = "";
    
    $stats = [
        'total_yuju' => $total_yuju,
        'procesados' => 0,
        'actualizados' => 0,
        'sin_cambios' => 0,
        'no_encontrados' => 0,
        'errores' => 0,
        'lotes_procesados' => 0,
        'from_cache' => $from_cache,
        'cache_age' => $cache_age
    ];
    
    // Configuración de lotes
    $batch_size = (int) YujuConfig::get('YUJU_BATCH_SIZE') ?: 100;
    $batch_frequency = (int) YujuConfig::get('YUJU_BATCH_FREQUENCY') ?: 60;
    
    log_message("Configuración: Lote de $batch_size productos, espera de $batch_frequency segundos entre lotes", 'info', $is_web);
    
    // Identificar productos con cambios
    $products_to_update = [];
    
    foreach ($yuju_products as $yuju_product) {
        $sku = $yuju_product['sku'] ?? $yuju_product['sku_simple'] ?? null;
        
        if (!$sku) {
            $stats['errores']++;
            continue;
        }
        
        // Buscar producto en PrestaShop por SKU (reference)
        $id_product = Db::getInstance()->getValue('
            SELECT id_product 
            FROM ' . _DB_PREFIX_ . 'product 
            WHERE reference = "' . pSQL($sku) . '"
        ');
        
        if (!$id_product) {
            $stats['no_encontrados']++;
            continue;
        }
        
        $ps_product = new Product((int)$id_product, false, Configuration::get('PS_LANG_DEFAULT'));
        
        // Comparar stock y precio
        $ps_quantity = (int) StockAvailable::getQuantityAvailableByProduct($id_product);
        $ps_price = (float) $ps_product->price;
        
        $yuju_quantity = (int) ($yuju_product['stock'] ?? 0);
        $yuju_price = (float) ($yuju_product['price'] ?? 0);
        
        $has_changes = false;
        $changes = [];
        
        if ($ps_quantity !== $yuju_quantity) {
            $has_changes = true;
            $changes[] = "Stock: $yuju_quantity → $ps_quantity";
        }
        
        if (abs($ps_price - $yuju_price) > 0.01) {
            $has_changes = true;
            $changes[] = "Precio: $yuju_price → $ps_price";
        }
        
        if ($has_changes) {
            $products_to_update[] = [
                'id_yuju' => $yuju_product['id'] ?? $yuju_product['id_product'] ?? null,
                'sku' => $sku,
                'stock' => $ps_quantity,
                'price' => $ps_price,
                'changes' => $changes
            ];
        } else {
            $stats['sin_cambios']++;
        }
    }
    
    $total_to_update = count($products_to_update);
    log_message("Productos con cambios detectados: $total_to_update", 'info', $is_web);
    
    // Procesar en lotes
    $batches = array_chunk($products_to_update, $batch_size);
    $total_batches = count($batches);
    
    foreach ($batches as $batch_index => $batch) {
        $batch_num = $batch_index + 1;
        $stats['lotes_procesados']++;
        
        log_message("Procesando lote $batch_num de $total_batches (" . count($batch) . " productos)...", 'info', $is_web);
        
        foreach ($batch as $product) {
            $stats['procesados']++;
            
            if (!$product['id_yuju']) {
                $stats['errores']++;
                $report[] = "✗ SKU: {$product['sku']} | ID de Yuju no encontrado";
                continue;
            }
            
            // Actualizar en Yuju
            $update_data = [
                'stock' => $product['stock'],
                'price' => $product['price']
            ];
            
            $ch_update = curl_init();
            curl_setopt_array($ch_update, [
                CURLOPT_URL => 'https://api.tp.yuju.io/products/' . $product['id_yuju'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => json_encode($update_data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ],
            ]);
            
            $update_response = curl_exec($ch_update);
            $update_code = curl_getinfo($ch_update, CURLINFO_HTTP_CODE);
            curl_close($ch_update);
            
            if ($update_code >= 200 && $update_code < 300) {
                $stats['actualizados']++;
                $report[] = "✓ SKU: {$product['sku']} | " . implode(' | ', $product['changes']);
                log_message("✓ Actualizado: {$product['sku']}", 'success', $is_web);
            } else {
                $stats['errores']++;
                $report[] = "✗ SKU: {$product['sku']} | Error HTTP $update_code";
                log_message("✗ Error al actualizar: {$product['sku']} (HTTP $update_code)", 'error', $is_web);
            }
        }
        
        // Esperar entre lotes (excepto en el último)
        if ($batch_num < $total_batches) {
            log_message("Esperando $batch_frequency segundos antes del siguiente lote...", 'info', $is_web);
            sleep($batch_frequency);
        }
    }
    
    // Completar reporte
    $report[] = "";
    $report[] = "=================================================";
    $report[] = "ESTADÍSTICAS";
    $report[] = "=================================================";
    $report[] = "Total productos Yuju: " . $stats['total_yuju'];
    $report[] = "Productos con cambios: $total_to_update";
    $report[] = "Lotes procesados: " . $stats['lotes_procesados'];
    $report[] = "Procesados: " . $stats['procesados'];
    $report[] = "Actualizados: " . $stats['actualizados'];
    $report[] = "Sin cambios: " . $stats['sin_cambios'];
    $report[] = "No encontrados en PrestaShop: " . $stats['no_encontrados'];
    $report[] = "Errores: " . $stats['errores'];
    $report[] = "";
    $report[] = "Tiempo de ejecución: " . round(microtime(true) - $sync_start, 2) . " segundos";
    $report[] = "=================================================";
    
    // Guardar reporte
    file_put_contents($report_file, implode("\n", $report), FILE_APPEND | LOCK_EX);
    
    // Mostrar resumen
    log_message("", 'info', $is_web);
    log_message("Sincronización completada", 'success', $is_web);
    log_message("📦 Lotes procesados: " . $stats['lotes_procesados'], 'info', $is_web);
    log_message("✓ Actualizados: " . $stats['actualizados'], 'success', $is_web);
    log_message("→ Sin cambios: " . $stats['sin_cambios'], 'info', $is_web);
    log_message("⚠ No encontrados: " . $stats['no_encontrados'], 'warning', $is_web);
    
    if ($stats['errores'] > 0) {
        log_message("✗ Errores: " . $stats['errores'], 'error', $is_web);
    }
    
    log_message("📄 Reporte guardado: logs/sync_logs/sync_$report_date.log", 'info', $is_web);
    
    $logger->log('info', 'Sincronización completada', $stats);
    
} catch (Exception $e) {
    $error_msg = "Error en sincronización: " . $e->getMessage();
    log_message($error_msg, 'error', $is_web);
    $logger->log('error', $error_msg);
    
    if (isset($report_file)) {
        file_put_contents($report_file, "\n[ERROR] " . date('Y-m-d H:i:s') . " - " . $error_msg . "\n", FILE_APPEND | LOCK_EX);
    }
}
