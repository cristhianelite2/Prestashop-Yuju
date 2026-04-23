<?php
/**
 * 2024 Yuju Integration - CRON Principal
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * CONFIGURACIÓN EN CRON:
 * 
 * Ejecutar cada 5 minutos
 * 
 * Por CLI:
 * Cada 5 minutos: php /ruta/a/prestashop/modules/prestashopyuju/cron/cron.php
 * 
 * Por URL (recomendado para servicios externos):
 * https://tutienda.com/modules/prestashopyuju/cron/cron.php
 */

// Configuración de PrestaShop
$root_path = dirname(__FILE__, 2);
require_once $root_path . '/../../config/config.inc.php';
require_once $root_path . '/../../init.php';
require_once $root_path . '/classes/YujuLogger.php';
require_once $root_path . '/config/config.php';

// Inicializar logger
$logger = new YujuLogger();

// Cargar clase de cola
require_once $root_path . '/classes/YujuSyncQueue.php';

// Detectar si es llamada web
$is_web = (php_sapi_name() !== 'cli');

if ($is_web) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Yuju CRON - Estado</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-clock text-blue-500 mr-3"></i>
                                Yuju CRON Principal
                            </h1>
                            <p class="text-gray-600 mt-2">Estado de tareas automáticas</p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Fecha y Hora</div>
                            <div class="text-lg font-semibold text-gray-800"><?php echo date('Y-m-d H:i:s'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Contador Compacto (Superior Izquierda) -->
                <?php
                // Calcular información de sincronización para el widget
                $widget_last_sync_time = (int)YujuConfig::get('YUJU_LAST_SYNC_TIME', 0);
                $widget_sync_count_today = (int)YujuConfig::get('YUJU_SYNC_COUNT', 0);
                $widget_max_daily_syncs = (int)YujuConfig::get('YUJU_MAX_DAILY_SYNCS', 2);
                $widget_sync_interval_hours = 24 / $widget_max_daily_syncs;
                $widget_sync_interval = $widget_sync_interval_hours * 3600;
                $widget_current_time = time();
                
                $widget_time_since_last_sync = $widget_last_sync_time > 0 ? ($widget_current_time - $widget_last_sync_time) : $widget_sync_interval + 1;
                $widget_time_until_next = $widget_sync_interval - $widget_time_since_last_sync;
                if ($widget_time_until_next < 0) $widget_time_until_next = 0;
                
                $widget_hours_until_next = floor($widget_time_until_next / 3600);
                $widget_minutes_until_next = floor(($widget_time_until_next % 3600) / 60);
                $widget_seconds_until_next = $widget_time_until_next % 60;
                
                // Verificar si API está bloqueada (usando la misma lógica del código principal)
                $widget_block_info_file = dirname(__FILE__) . '/../cache/yuju_block_info.json';
                $widget_block_info = null;
                $widget_is_blocked = false;
                
                if (file_exists($widget_block_info_file)) {
                    $widget_block_info = json_decode(file_get_contents($widget_block_info_file), true);
                    if ($widget_block_info && isset($widget_block_info['unlock_time'])) {
                        $widget_unlock_timestamp = strtotime($widget_block_info['unlock_time']);
                        $widget_is_blocked = $widget_unlock_timestamp > time();
                        
                        if ($widget_is_blocked) {
                            $widget_time_remaining = $widget_unlock_timestamp - time();
                            $widget_block_info['hours_remaining'] = floor($widget_time_remaining / 3600);
                            $widget_block_info['minutes_remaining'] = floor(($widget_time_remaining % 3600) / 60);
                            $widget_block_info['seconds_remaining'] = $widget_time_remaining % 60;
                        }
                    }
                }
                ?>
                
                <div class="bg-white rounded-lg shadow p-3 mb-4 inline-block">
                    <div class="flex items-center gap-3 text-xs">
                        <?php if ($widget_is_blocked): ?>
                            <div class="flex items-center text-red-600">
                                <i class="fas fa-ban mr-1"></i>
                                <span class="font-semibold">API Bloqueada</span>
                            </div>
                            <div class="text-gray-600">
                                Desbloqueo en: <span class="font-mono font-bold"><?php echo $widget_block_info['hours_remaining']; ?>h <?php echo $widget_block_info['minutes_remaining']; ?>m <?php echo $widget_block_info['seconds_remaining']; ?>s</span>
                            </div>
                        <?php elseif ($widget_sync_count_today >= $widget_max_daily_syncs): ?>
                            <div class="flex items-center text-yellow-600">
                                <i class="fas fa-check-circle mr-1"></i>
                                <span class="font-semibold">Límite alcanzado</span>
                            </div>
                            <div class="text-gray-600">
                                <?php echo $widget_sync_count_today; ?>/<?php echo $widget_max_daily_syncs; ?> sincronizaciones hoy
                            </div>
                        <?php else: ?>
                            <div class="flex items-center text-blue-600">
                                <i class="fas fa-clock mr-1"></i>
                                <span class="font-semibold">Próxima sync:</span>
                            </div>
                            <div class="text-gray-800">
                                <span class="font-mono font-bold text-sm"><?php echo $widget_hours_until_next; ?>h <?php echo $widget_minutes_until_next; ?>m <?php echo $widget_seconds_until_next; ?>s</span>
                            </div>
                            <div class="text-gray-500 border-l pl-3">
                                <?php echo $widget_sync_count_today; ?>/<?php echo $widget_max_daily_syncs; ?> hoy
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
    <?php
} else {
    echo "==============================================\n";
    echo "  YUJU CRON PRINCIPAL\n";
    echo "  " . date('Y-m-d H:i:s') . "\n";
    echo "==============================================\n\n";
}

try {
    $logger->log('info', 'Iniciando ejecución de CRON principal');
    
    $current_time = time();
    
    // =============================================
    // TAREA: Sincronización de Stock y Precios
    // =============================================
    $today = date('Y-m-d');
    $last_sync_date = YujuConfig::get('YUJU_SYNC_DATE');
    $last_sync_time = (int) YujuConfig::get('YUJU_LAST_SYNC_TIME');
    $sync_count_today = (int) YujuConfig::get('YUJU_SYNC_COUNT');
    $max_daily_syncs = (int) YujuConfig::get('YUJU_MAX_DAILY_SYNCS') ?: 5;
    
    // Calcular intervalo entre sincronizaciones (24 horas dividido entre max_daily_syncs)
    $sync_interval = (24 * 3600) / $max_daily_syncs; // en segundos
    $sync_interval_hours = $sync_interval / 3600;
    
    // Resetear contador si es un nuevo día
    if ($last_sync_date !== $today) {
        $sync_count_today = 0;
        $last_sync_time = 0;
        YujuConfig::set('YUJU_SYNC_DATE', $today, 'string');
        YujuConfig::set('YUJU_SYNC_COUNT', 0, 'integer');
        YujuConfig::set('YUJU_LAST_SYNC_TIME', 0, 'integer');
    }
    
    // Verificar cache de JSON
    $cache_file = dirname(__FILE__) . '/../cache/yuju_products.json';
    $cache_meta_file = dirname(__FILE__) . '/../cache/yuju_products_meta.json';
    $block_info_file = dirname(__FILE__) . '/../cache/yuju_block_info.json';
    $cache_exists = file_exists($cache_file) && file_exists($cache_meta_file);
    $cache_info = null;
    $json_preview = null;
    $block_info = null;
    $is_blocked = false;
    
    // Verificar si hay bloqueo de Yuju
    if (file_exists($block_info_file)) {
        $block_info = json_decode(file_get_contents($block_info_file), true);
        if ($block_info && isset($block_info['unlock_time'])) {
            $unlock_timestamp = strtotime($block_info['unlock_time']);
            $is_blocked = $unlock_timestamp > time();
            
            if ($is_blocked) {
                $time_remaining = $unlock_timestamp - time();
                $block_info['hours_remaining'] = floor($time_remaining / 3600);
                $block_info['minutes_remaining'] = floor(($time_remaining % 3600) / 60);
                $block_info['seconds_remaining'] = $time_remaining % 60;
            } else {
                // Bloqueo expirado, eliminar archivo
                unlink($block_info_file);
                $block_info = null;
                $is_blocked = false;
            }
        }
    }
    
    if ($cache_exists) {
        $cache_info = json_decode(file_get_contents($cache_meta_file), true);
        $cache_age = time() - $cache_info['timestamp'];
        $cache_info['age_minutes'] = floor($cache_age / 60);
        $cache_info['age_hours'] = floor($cache_age / 3600);
        $cache_info['expires_in'] = 180 - $cache_info['age_minutes']; // 3 horas = 180 min
        $cache_info['is_expired'] = $cache_age >= (3 * 3600);
        $cache_info['file_path'] = realpath($cache_file);
        $cache_info['file_size_kb'] = round(filesize($cache_file) / 1024, 2);
        
        // Leer primeros productos para preview
        $json_data = json_decode(file_get_contents($cache_file), true);
        if (is_array($json_data) && count($json_data) > 0) {
            // Validar que los datos tengan formato de productos Yuju
            $first_item = reset($json_data);
            $is_valid_yuju_format = is_array($first_item) && (
                isset($first_item['sku']) || 
                isset($first_item['sku_simple']) || 
                isset($first_item['name']) || 
                isset($first_item['id'])
            );
            
            if ($is_valid_yuju_format) {
                $json_preview = array_slice($json_data, 0, 15); // Primeros 15 productos
            }
            $cache_info['total_products'] = count($json_data);
        }
    }
    
    // Verificar si puede sincronizar
    $time_since_last_sync = $last_sync_time > 0 ? ($current_time - $last_sync_time) : $sync_interval + 1;
    $can_sync = $sync_count_today < $max_daily_syncs && $time_since_last_sync >= $sync_interval;
    
    // Calcular tiempo restante
    if ($last_sync_time > 0) {
        $time_until_next = $sync_interval - $time_since_last_sync;
        if ($time_until_next < 0) $time_until_next = 0;
        $hours_until_next = floor($time_until_next / 3600);
        $minutes_until_next = floor(($time_until_next % 3600) / 60);
    } else {
        $time_until_next = 0;
        $hours_until_next = 0;
        $minutes_until_next = 0;
    }
    
    // NO permitir sincronización si la API está bloqueada
    if ($is_blocked) {
        $can_sync = false;
    }
    
    if ($can_sync) {
        if ($is_web) {
            echo '<div class="bg-white rounded-lg shadow-lg p-6 mb-6">';
            echo '<h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">';
            echo '<i class="fas fa-sync-alt text-green-500 mr-2"></i>';
            echo "Sincronización de Stock y Precios (Ejecución " . ($sync_count_today + 1) . " de $max_daily_syncs)";
            echo '</h2>';
            echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mb-3">';
            echo '<p class="text-sm text-gray-700">';
            echo '<i class="fas fa-info-circle mr-2"></i>';
            echo "<strong>Intervalo configurado:</strong> Cada " . number_format($sync_interval_hours, 1) . " horas (24h ÷ $max_daily_syncs sincronizaciones)";
            echo '</p>';
            echo '</div>';
            echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">';
        } else {
            echo "[TAREA] Sincronización de Stock y Precios (Ejecución " . ($sync_count_today + 1) . " de $max_daily_syncs)\n";
            echo "Intervalo: Cada " . number_format($sync_interval_hours, 1) . " horas\n";
            echo "----------------------------------------\n";
        }
        
        // Ejecutar sincronización
        require_once dirname(__FILE__) . '/sync_products.php';
        
        // Actualizar contador y timestamp
        YujuConfig::set('YUJU_SYNC_COUNT', $sync_count_today + 1, 'integer');
        YujuConfig::set('YUJU_LAST_SYNC_TIME', $current_time, 'integer');
        
        if ($is_web) {
            echo '</div></div>';
        } else {
            echo "\n";
        }
    } else {
        if ($is_web) {
            // Versión compacta en la esquina superior izquierda - sin mostrar nada aquí
        } else {
            if ($is_blocked) {
                echo "[BLOQUEADO] Sincronización de Productos\n";
                echo "   API bloqueada por Yuju - sincronización deshabilitada\n";
                echo "   Desbloqueo: {$block_info['unlock_time']}\n";
                echo "   Tiempo restante: {$block_info['hours_remaining']}h {$block_info['minutes_remaining']}m {$block_info['seconds_remaining']}s\n\n";
            } elseif ($sync_count_today >= $max_daily_syncs) {
                echo "[OMITIDO] Sincronización de Productos\n";
                echo "   Límite diario alcanzado: $sync_count_today de $max_daily_syncs sincronizaciones completadas\n";
                echo "   El contador se reiniciará mañana\n\n";
            } else {
                echo "[ESPERANDO] Sincronización de Productos\n";
                echo "   Sincronizaciones hoy: $sync_count_today de $max_daily_syncs\n";
                echo "   Intervalo: Cada " . number_format($sync_interval_hours, 1) . " horas\n";
                echo "   Próxima sincronización en: {$hours_until_next}h {$minutes_until_next}m\n\n";
            }
        }
    }
    
    // =============================================
    // TAREA 2: Aquí puedes agregar más tareas futuras
    // =============================================
    // Por ejemplo:
    // - Sincronización de productos (cada X minutos)
    // - Sincronización de órdenes (cada Y minutos)
    // - Limpieza de logs antiguos (cada día)
    // etc.
    
    if ($is_web) {
        // Obtener estadísticas resumidas para el acordeón
        $table_name = _DB_PREFIX_ . 'yuju_sync_logs';
        
        $last_download = null;
        $last_sync = null;
        $last_sync_summary = null;
        
        try {
            $last_download = Db::getInstance()->getRow(
                "SELECT status, start_time, total_items 
                FROM `{$table_name}`
                WHERE entity_type = 'products' 
                AND sync_direction = 'yuju_to_prestashop'
                ORDER BY start_time DESC
                LIMIT 1"
            );
        } catch (Exception $e) {
            // Si falla, continuar sin estadísticas
        }
        
        try {
            $last_sync = Db::getInstance()->getRow(
                "SELECT status, start_time, details 
                FROM `{$table_name}`
                WHERE entity_type = 'products' 
                AND sync_direction = 'prestashop_to_yuju'
                ORDER BY start_time DESC
                LIMIT 1"
            );
            
            if ($last_sync && isset($last_sync['details'])) {
                $sync_details = json_decode($last_sync['details'], true);
                $last_sync_summary = isset($sync_details['summary']) ? $sync_details['summary'] : null;
            }
        } catch (Exception $e) {
            // Si falla, continuar sin estadísticas
        }
        ?>
        <!-- =============================================
             ACORDEÓN: SINCRONIZACIÓN DE STOCK Y PRECIO
             ============================================= -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <button 
                onclick="toggleSyncAccordion()" 
                class="w-full text-left focus:outline-none group"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center flex-1">
                        <i id="accordion-icon" class="fas fa-chevron-right text-purple-600 mr-3 text-xl transition-transform duration-200"></i>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-sync-alt text-purple-600 mr-3"></i>
                                Sincronización de Stock y Precio (CRON)
                            </h2>
                            <p class="text-sm text-gray-500 mt-1 ml-10">
                                Sistema automático de descarga y sincronización cada 10 minutos
                            </p>
                        </div>
                    </div>
                    
                    <!-- Estadísticas resumidas -->
                    <div class="flex items-center gap-4 ml-6">
                        <?php if ($last_download): ?>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">Última descarga</div>
                                <div class="text-sm font-semibold text-gray-700">
                                    <?php echo date('d/m H:i', strtotime($last_download['start_time'])); ?>
                                    <?php if ($last_download['status'] === 'completed'): ?>
                                        <span class="ml-1 text-green-600"><i class="fas fa-check-circle"></i></span>
                                    <?php else: ?>
                                        <span class="ml-1 text-red-600"><i class="fas fa-times-circle"></i></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($last_download['total_items']): ?>
                                    <div class="text-xs text-purple-600">
                                        <?php echo number_format($last_download['total_items']); ?> productos
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($last_sync && $last_sync_summary): ?>
                            <div class="text-right border-l pl-4">
                                <div class="text-xs text-gray-500">Última sincronización</div>
                                <div class="text-sm font-semibold text-gray-700">
                                    <?php echo date('d/m H:i', strtotime($last_sync['start_time'])); ?>
                                </div>
                                <div class="flex gap-2 text-xs mt-1">
                                    <span class="text-green-600">
                                        <i class="fas fa-arrow-up"></i> <?php echo $last_sync_summary['updated'] ?? 0; ?>
                                    </span>
                                    <span class="text-blue-600">
                                        <i class="fas fa-check"></i> <?php echo $last_sync_summary['synced'] ?? 0; ?>
                                    </span>
                                    <?php if (($last_sync_summary['errors'] ?? 0) > 0): ?>
                                        <span class="text-red-600">
                                            <i class="fas fa-exclamation-circle"></i> <?php echo $last_sync_summary['errors']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-gray-400 group-hover:text-gray-600 transition-colors">
                            <i class="fas fa-angle-down text-2xl"></i>
                        </div>
                    </div>
                </div>
            </button>
            
            <div id="sync-accordion-content" class="hidden mt-6">
                <!-- =============================================
                     HISTÓRICO DE DESCARGAS JSON
                     ============================================= -->
        <?php
        // Obtener últimas 10 descargas de JSON desde yuju_sync_logs
        $download_history = Db::getInstance()->executeS('
            SELECT 
                id,
                status,
                start_time,
                end_time,
                total_items,
                error_message,
                details
            FROM ' . _DB_PREFIX_ . 'yuju_sync_logs
            WHERE entity_type = "products" 
            AND sync_direction = "yuju_to_prestashop"
            AND (created_by = "cron" OR created_by = "inspector_web" OR created_by = "inspector_script")
            ORDER BY start_time DESC
            LIMIT 10
        ');
        
        if ($download_history && count($download_history) > 0):
        ?>
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-history text-purple-500 mr-2"></i>
                Histórico de Descargas JSON (Últimas 10)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Productos</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">URL CloudFront</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Detalles</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($download_history as $index => $download): ?>
                            <?php 
                            $is_latest = ($index === 0); // Primera descarga es la más reciente
                            $details = json_decode($download['details'], true);
                            
                            // Extraer URL de CloudFront con múltiples intentos
                            $download_url = null;
                            if (isset($details['download_url'])) {
                                $download_url = $details['download_url'];
                            } elseif (isset($details['cloudfront_url'])) {
                                $download_url = $details['cloudfront_url'];
                            } elseif (isset($details['url'])) {
                                $download_url = $details['url'];
                            } else {
                                // Buscar en todo el array de detalles cualquier URL de CloudFront
                                foreach ($details as $key => $value) {
                                    if (is_string($value) && strpos($value, 'cloudfront.net') !== false) {
                                        $download_url = $value;
                                        break;
                                    }
                                }
                            }
                            
                            // Limpiar URL si viene escapada
                            if ($download_url) {
                                $download_url = stripslashes($download_url);
                            }
                            
                            // Extraer file_path del JSON de detalles
                            $file_path = isset($details['file_path']) ? $details['file_path'] : null;
                            if (!$file_path && isset($details['saved_to'])) {
                                $file_path = $details['saved_to'];
                            }
                            
                            $http_code = isset($details['http_code']) ? $details['http_code'] : null;
                            $download_http_code = isset($details['download_http_code']) ? $details['download_http_code'] : null;
                            $file_size = isset($details['file_size_bytes']) ? round($details['file_size_bytes'] / 1024 / 1024, 2) : null;
                            
                            // Calcular productos base (excluyendo variaciones)
                            $base_products_count = $download['total_items'];
                            if (isset($details['base_products_count'])) {
                                $base_products_count = $details['base_products_count'];
                            } elseif (isset($details['products_breakdown'])) {
                                $base_products_count = isset($details['products_breakdown']['base']) ? $details['products_breakdown']['base'] : $download['total_items'];
                            }
                            ?>
                            <tr class="hover:bg-gray-50" data-download-id="<?php echo $download['id']; ?>">
                                <td class="px-4 py-3 text-sm font-mono text-blue-600">#<?php echo $download['id']; ?></td>
                                
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?php echo date('d/m/Y H:i', strtotime($download['start_time'])); ?>
                                </td>
                                
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($download['status'] === 'completed'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle"></i> Exitoso
                                        </span>
                                    <?php elseif ($download['status'] === 'failed'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle"></i> Error
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock"></i> <?php echo ucfirst($download['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($http_code): ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-mono rounded <?php echo $http_code == 200 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            HTTP <?php echo $http_code; ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($download_http_code && $download_http_code != 200): ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-mono rounded bg-red-100 text-red-700">
                                            DL <?php echo $download_http_code; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3 text-sm text-center">
                                    <?php if ($base_products_count): ?>
                                        <span class="font-semibold text-purple-600">
                                            <?php echo number_format($base_products_count); ?>
                                        </span>
                                        <?php if ($file_size): ?>
                                            <br><span class="text-xs text-gray-500"><?php echo $file_size; ?> MB</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($download_url): ?>
                                        <a href="<?php echo htmlspecialchars($download_url); ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 hover:underline flex items-center"
                                           title="<?php echo htmlspecialchars($download_url); ?>">
                                            <i class="fas fa-external-link-alt mr-1"></i>
                                            CloudFront
                                        </a>
                                        <span class="text-xs text-gray-500 block mt-1">
                                            <?php echo substr(basename($download_url), 0, 25); ?>...
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No disponible</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3 text-sm">
                                    <button 
                                        onclick="toggleDetails('details-<?php echo $download['id']; ?>')"
                                        class="text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        <i class="fas fa-info-circle"></i> Ver
                                    </button>
                                    
                                    <?php if ($is_latest && $download['status'] === 'completed' && $download['total_items'] > 0): ?>
                                        <button 
                                            onclick="syncWithYuju(<?php echo $download['id']; ?>)"
                                            id="sync-btn-<?php echo $download['id']; ?>"
                                            class="ml-2 px-3 py-1 text-xs bg-purple-600 text-white rounded hover:bg-purple-700 font-medium"
                                            title="Comparar con PrestaShop y actualizar diferencias en Yuju"
                                        >
                                            <i class="fas fa-sync-alt"></i> Sincronizar
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($download['error_message']): ?>
                                        <div class="mt-1">
                                            <span class="px-2 py-1 text-xs bg-red-50 text-red-700 rounded">
                                                <i class="fas fa-exclamation-triangle"></i> Error
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Fila expandible con detalles -->
                            <tr id="details-<?php echo $download['id']; ?>" class="hidden bg-gray-50">
                                <td colspan="6" class="px-4 py-4">
                                    <div class="bg-white rounded-lg p-3 border border-gray-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-bold text-gray-700 text-sm">
                                                <i class="fas fa-info-circle text-indigo-500 mr-1"></i>
                                                Descarga #<?php echo $download['id']; ?>
                                            </h4>
                                            <div class="flex items-center gap-3 text-xs text-gray-600">
                                                <span><i class="fas fa-play-circle mr-1"></i><?php echo date('d/m H:i', strtotime($download['start_time'])); ?></span>
                                                <span><i class="fas fa-stop-circle mr-1"></i><?php echo $download['end_time'] ? date('d/m H:i', strtotime($download['end_time'])) : 'N/A'; ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                        // Verificar si el archivo existe y es válido
                                        $file_exists = false;
                                        $file_path = null;
                                        if (isset($download_details['file_path']) && file_exists($download_details['file_path'])) {
                                            $file_size = filesize($download_details['file_path']);
                                            if ($file_size > 1024) { // Mayor a 1KB
                                                $file_exists = true;
                                                $file_path = $download_details['file_path'];
                                            }
                                        }
                                        
                                        // Mostrar error solo si NO hay archivo válido
                                        if ($download['error_message'] && !$file_exists): 
                                        ?>
                                            <div class="bg-red-50 border-l-2 border-red-400 p-2 rounded-r mb-2" id="error-container-<?php echo $download['id']; ?>">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-xs text-red-700">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        <strong>Error:</strong> <?php echo htmlspecialchars($download['error_message']); ?>
                                                    </p>
                                                    <?php if (($download_http_code == 403 || strpos($download['error_message'], '403') !== false) && $download['status'] === 'failed'): ?>
                                                        <button 
                                                            onclick="retryDownload(<?php echo $download['id']; ?>)"
                                                            id="retry-btn-<?php echo $download['id']; ?>"
                                                            class="ml-2 px-2 py-1 text-xs bg-orange-600 text-white rounded hover:bg-orange-700"
                                                            title="Reintentar descarga con nueva URL"
                                                        >
                                                            <i class="fas fa-redo"></i> Reintentar
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($file_exists && $download['status'] !== 'completed'): ?>
                                            <div class="bg-green-50 border-l-2 border-green-400 p-2 rounded-r mb-2" id="recovered-container-<?php echo $download['id']; ?>">
                                                <div class="flex items-start">
                                                    <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                                                    <div class="text-xs text-green-700 flex-1">
                                                        <p><strong>Archivo recuperado desde cache:</strong></p>
                                                        <p class="mt-1 font-mono text-[10px] break-all"><?php echo htmlspecialchars($file_path); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($download_url): ?>
                                            <div class="bg-blue-50 border-l-2 border-blue-400 p-2 rounded-r mb-2">
                                                <div class="space-y-1 text-xs">
                                                    <div class="flex items-start">
                                                        <i class="fas fa-link text-blue-600 mr-2 mt-0.5"></i>
                                                        <a href="<?php echo htmlspecialchars($download_url); ?>" target="_blank" class="text-blue-700 hover:underline break-all flex-1">
                                                            <?php echo htmlspecialchars($download_url); ?>
                                                        </a>
                                                    </div>
                                                    <?php if ($file_path): ?>
                                                        <div class="flex items-start pt-1 border-t border-blue-200">
                                                            <i class="fas fa-save text-blue-600 mr-2 mt-0.5"></i>
                                                            <span class="text-blue-700 font-mono break-all flex-1"><?php echo htmlspecialchars($file_path); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // DESCARGAR EL JSON DESDE CLOUDFRONT DIRECTAMENTE
                                        $json_products = null;
                                        $download_error = null;
                                        
                                        if ($download_url) {
                                            // Descargar el JSON desde CloudFront
                                            $ch = curl_init();
                                            curl_setopt_array($ch, [
                                                CURLOPT_URL => $download_url,
                                                CURLOPT_RETURNTRANSFER => true,
                                                CURLOPT_TIMEOUT => 30,
                                                CURLOPT_FOLLOWLOCATION => true,
                                                CURLOPT_SSL_VERIFYPEER => true,
                                            ]);
                                            
                                            $json_content = curl_exec($ch);
                                            $dl_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                            $dl_error = curl_error($ch);
                                            curl_close($ch);
                                            
                                            if ($dl_http_code === 200 && !empty($json_content)) {
                                                $json_products = json_decode($json_content, true);
                                            } else {
                                                $download_error = "Error al descargar JSON: HTTP $dl_http_code" . ($dl_error ? " - $dl_error" : "");
                                                
                                                // Si la URL expiró, intentar desde cache como fallback
                                                $cache_file = dirname(__FILE__) . '/../cache/yuju_products.json';
                                                if (file_exists($cache_file)) {
                                                    $json_content = file_get_contents($cache_file);
                                                    $json_products = json_decode($json_content, true);
                                                    $download_error .= " (Mostrando desde cache local)";
                                                }
                                            }
                                        }
                                        
                                        if ($json_products && is_array($json_products) && count($json_products) > 0):
                                            // Agrupar productos usando id_parent para detectar variaciones
                                            $parents_map = [];
                                            $children_by_parent = [];
                                            
                                            // Primera pasada: identificar padres e hijos
                                            foreach ($json_products as $product) {
                                                $id_product = $product['id_product'] ?? null;
                                                $id_parent = $product['id_parent'] ?? null;
                                                
                                                if ($id_parent === null) {
                                                    // Es un producto padre (sin id_parent)
                                                    $parents_map[$id_product] = $product;
                                                    if (!isset($children_by_parent[$id_product])) {
                                                        $children_by_parent[$id_product] = [];
                                                    }
                                                } else {
                                                    // Es una variación (tiene id_parent)
                                                    if (!isset($children_by_parent[$id_parent])) {
                                                        $children_by_parent[$id_parent] = [];
                                                    }
                                                    $children_by_parent[$id_parent][] = $product;
                                                }
                                            }
                                            
                                            // Consolidar: crear items con padres y sus variaciones
                                            $consolidated_items = [];
                                            
                                            foreach ($parents_map as $parent_id => $parent_product) {
                                                $variations = $children_by_parent[$parent_id] ?? [];
                                                
                                                $consolidated_items[] = [
                                                    'type' => !empty($variations) ? 'parent' : 'simple',
                                                    'product' => $parent_product,
                                                    'variations' => $variations
                                                ];
                                            }
                                            
                                            $total_items = count($consolidated_items);
                                            $total_variations = count($json_products) - $total_items;
                                            
                                            // Paginación
                                            $page = isset($_GET['page_' . $download['id']]) ? max(1, (int)$_GET['page_' . $download['id']]) : 1;
                                            $per_page = 50;
                                            $total_pages = ceil($total_items / $per_page);
                                            $offset = ($page - 1) * $per_page;
                                            $paginated_items = array_slice($consolidated_items, $offset, $per_page);
                                        ?>
                                            <div class="bg-green-50 border-l-2 border-green-400 p-2 rounded-r mb-2">
                                                <div class="flex items-center justify-between text-xs">
                                                    <div class="flex items-center text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        <strong>JSON descargado</strong>
                                                    </div>
                                                    <div class="flex items-center gap-3 text-green-700">
                                                        <span><i class="fas fa-cube mr-1"></i><?php echo count($json_products); ?> total</span>
                                                        <span><i class="fas fa-box mr-1"></i><?php echo $total_items; ?> base</span>
                                                        <span><i class="fas fa-layer-group mr-1"></i><?php echo $total_variations; ?> var</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <details class="mt-3" open>
                                                <summary class="cursor-pointer text-sm text-gray-700 hover:text-gray-900 font-medium bg-indigo-100 p-2 rounded">
                                                    <i class="fas fa-table"></i> <strong>Tabla de Productos</strong> 
                                                    <span id="pagination-info-<?php echo $download['id']; ?>">
                                                        (Página <?php echo $page; ?> de <?php echo $total_pages; ?> - <?php echo $total_items; ?> productos base)
                                                    </span>
                                                </summary>
                                                
                                                <div id="products-table-<?php echo $download['id']; ?>" class="mt-3 bg-gray-50 rounded-lg p-4 overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                                        <thead class="bg-gray-100">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase w-8"></th>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">SKU</th>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Nombre</th>
                                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 uppercase">Stock</th>
                                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 uppercase">Precio</th>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">ID Yuju</th>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Variaciones</th>
                                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-700 uppercase">Sync</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white divide-y divide-gray-200">
                                                            <?php 
                                                            $row_index = 0;
                                                            foreach ($paginated_items as $item): 
                                                                $product = $item['product'];
                                                                $variations = $item['variations'];
                                                                $has_variations = !empty($variations);
                                                                $row_id = 'row_' . $download['id'] . '_' . $row_index;
                                                                $row_index++;
                                                            ?>
                                                                <!-- Producto principal/padre -->
                                                                <tr class="hover:bg-gray-50 <?php echo $has_variations ? 'bg-blue-50' : ''; ?>">
                                                                    <td class="px-3 py-2">
                                                                        <?php if ($has_variations): ?>
                                                                            <button 
                                                                                onclick="toggleVariations('<?php echo $row_id; ?>')"
                                                                                class="text-indigo-600 hover:text-indigo-900"
                                                                                title="Ver variaciones"
                                                                            >
                                                                                <i class="fas fa-plus-circle" id="icon_<?php echo $row_id; ?>"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">
                                                                        <?php echo htmlspecialchars($product['sku'] ?? $product['sku_simple'] ?? 'N/A'); ?>
                                                                        <?php if ($has_variations): ?>
                                                                            <span class="ml-1 px-1 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">
                                                                                Base
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-gray-900">
                                                                        <?php 
                                                                        $name = $product['name'] ?? 'Sin nombre';
                                                                        echo htmlspecialchars(substr($name, 0, 50)); 
                                                                        if (strlen($name) > 50) echo '...';
                                                                        ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right font-semibold whitespace-nowrap <?php echo ($product['stock'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                                        <?php echo number_format($product['stock'] ?? 0); ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right font-semibold text-purple-600 whitespace-nowrap">
                                                                        $<?php echo number_format($product['price'] ?? 0, 2); ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 font-mono text-gray-500 whitespace-nowrap">
                                                                        <?php echo htmlspecialchars($product['id_product'] ?? $product['id'] ?? 'N/A'); ?>
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        <?php if ($has_variations): ?>
                                                                            <span class="px-2 py-1 bg-indigo-100 text-indigo-800 rounded text-xs font-semibold">
                                                                                <i class="fas fa-layer-group"></i>
                                                                                <?php echo count($variations); ?> variación(es)
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="text-gray-400 text-xs">-</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-center">
                                                                        <span class="sync-status" data-sku="<?php echo htmlspecialchars($product['sku'] ?? $product['sku_simple'] ?? ''); ?>">
                                                                            <i class="fas fa-circle text-gray-300" title="Pendiente"></i>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                
                                                                <!-- Variaciones (colapsadas por defecto) -->
                                                                <?php if ($has_variations): ?>
                                                                    <tr id="<?php echo $row_id; ?>" class="hidden bg-indigo-50">
                                                                        <td colspan="7" class="px-3 py-2">
                                                                            <div class="ml-8 border-l-2 border-indigo-300 pl-4">
                                                                                <p class="text-xs font-semibold text-indigo-900 mb-2">
                                                                                    <i class="fas fa-sitemap"></i> 
                                                                                    Variaciones de este producto (<?php echo count($variations); ?>):
                                                                                </p>
                                                                                <table class="min-w-full text-xs">
                                                                                    <thead class="bg-indigo-100">
                                                                                        <tr>
                                                                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">SKU Variación</th>
                                                                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">Nombre</th>
                                                                                            <th class="px-2 py-1 text-right text-xs font-medium text-gray-700">Stock</th>
                                                                                            <th class="px-2 py-1 text-right text-xs font-medium text-gray-700">Precio</th>
                                                                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">ID Yuju</th>
                                                                                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">Atributos</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody class="bg-white divide-y divide-gray-100">
                                                                                        <?php foreach ($variations as $variation): ?>
                                                                                            <tr class="hover:bg-indigo-50">
                                                                                                <td class="px-2 py-1 font-mono text-blue-600 text-xs">
                                                                                                    <?php echo htmlspecialchars($variation['sku'] ?? 'N/A'); ?>
                                                                                                </td>
                                                                                                <td class="px-2 py-1 text-gray-700 text-xs">
                                                                                                    <?php 
                                                                                                    $var_name = $variation['name'] ?? 'Sin nombre';
                                                                                                    echo htmlspecialchars(substr($var_name, 0, 40));
                                                                                                    if (strlen($var_name) > 40) echo '...';
                                                                                                    ?>
                                                                                                </td>
                                                                                                <td class="px-2 py-1 text-right font-semibold text-xs <?php echo ($variation['stock'] ?? 0) > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                                                                    <?php echo number_format($variation['stock'] ?? 0); ?>
                                                                                                </td>
                                                                                                <td class="px-2 py-1 text-right font-semibold text-purple-600 text-xs">
                                                                                                    $<?php echo number_format($variation['price'] ?? 0, 2); ?>
                                                                                                </td>
                                                                                                <td class="px-2 py-1 font-mono text-gray-500 text-xs">
                                                                                                    <?php echo htmlspecialchars($variation['id_product'] ?? $variation['id'] ?? 'N/A'); ?>
                                                                                                </td>
                                                                                                <td class="px-2 py-1 text-xs">
                                                                                                    <?php 
                                                                                                    if (isset($variation['attributes']) && is_array($variation['attributes'])) {
                                                                                                        foreach ($variation['attributes'] as $attr) {
                                                                                                            if (isset($attr['name']) && isset($attr['value'])) {
                                                                                                                echo '<span class="inline-block px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded mr-1 mb-1">';
                                                                                                                echo htmlspecialchars($attr['name']) . ': ' . htmlspecialchars($attr['value']);
                                                                                                                echo '</span>';
                                                                                                            }
                                                                                                        }
                                                                                                    } else {
                                                                                                        echo '<span class="text-gray-400">-</span>';
                                                                                                    }
                                                                                                    ?>
                                                                                                </td>
                                                                                            </tr>
                                                                                        <?php endforeach; ?>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    
                                                    <!-- Paginación -->
                                                    <?php if ($total_pages > 1): ?>
                                                        <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-3">
                                                            <div class="text-xs text-gray-600" id="page-counter-<?php echo $download['id']; ?>">
                                                                Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_items); ?> de <?php echo $total_items; ?> productos base
                                                            </div>
                                                            <div class="flex gap-1" id="pagination-buttons-<?php echo $download['id']; ?>">
                                                                <?php if ($page > 1): ?>
                                                                    <button onclick="loadProductsPage(<?php echo $download['id']; ?>, <?php echo $page - 1; ?>, <?php echo $total_pages; ?>, <?php echo $total_items; ?>, <?php echo $per_page; ?>)" 
                                                                       class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">
                                                                        <i class="fas fa-chevron-left"></i> Anterior
                                                                    </button>
                                                                <?php endif; ?>
                                                                
                                                                <?php 
                                                                $start_page = max(1, $page - 2);
                                                                $end_page = min($total_pages, $page + 2);
                                                                
                                                                for ($i = $start_page; $i <= $end_page; $i++): 
                                                                ?>
                                                                    <button onclick="loadProductsPage(<?php echo $download['id']; ?>, <?php echo $i; ?>, <?php echo $total_pages; ?>, <?php echo $total_items; ?>, <?php echo $per_page; ?>)" 
                                                                       class="px-3 py-1 text-xs border rounded <?php echo $i === $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                                                                        <?php echo $i; ?>
                                                                    </button>
                                                                <?php endfor; ?>
                                                                
                                                                <?php if ($page < $total_pages): ?>
                                                                    <button onclick="loadProductsPage(<?php echo $download['id']; ?>, <?php echo $page + 1; ?>, <?php echo $total_pages; ?>, <?php echo $total_items; ?>, <?php echo $per_page; ?>)" 
                                                                       class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">
                                                                        Siguiente <i class="fas fa-chevron-right"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                            
                                            <script>
                                            // Guardar productos en JavaScript para paginación AJAX
                                            window.productsData_<?php echo $download['id']; ?> = <?php echo json_encode($consolidated_items, JSON_UNESCAPED_UNICODE); ?>;
                                            window.productsConfig_<?php echo $download['id']; ?> = {
                                                totalItems: <?php echo $total_items; ?>,
                                                totalPages: <?php echo $total_pages; ?>,
                                                perPage: <?php echo $per_page; ?>,
                                                currentPage: <?php echo $page; ?>,
                                                downloadId: <?php echo $download['id']; ?>
                                            };
                                            
                                            // Cargar la primera página al inicio
                                            document.addEventListener('DOMContentLoaded', function() {
                                                loadProductsPage(<?php echo $download['id']; ?>, <?php echo $page; ?>, <?php echo $total_pages; ?>, <?php echo $total_items; ?>, <?php echo $per_page; ?>);
                                            });
                                            </script>
                                            
                                        <?php elseif ($download_error): ?>
                                            <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded-r mb-3">
                                                <p class="text-sm font-semibold text-red-800">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    No se pudo descargar el JSON desde CloudFront
                                                </p>
                                                <p class="text-xs text-red-700 mt-1"><?php echo htmlspecialchars($download_error); ?></p>
                                                <p class="text-xs text-red-600 mt-2">
                                                    <strong>Posibles causas:</strong>
                                                </p>
                                                <ul class="text-xs text-red-600 list-disc ml-5 mt-1">
                                                    <li>La URL de CloudFront ha expirado (son temporales)</li>
                                                    <li>Error 403: Acceso denegado</li>
                                                    <li>El archivo ya no existe en el servidor</li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded-r mb-3">
                                                <p class="text-sm text-yellow-800">
                                                    <i class="fas fa-info-circle"></i>
                                                    No hay URL de CloudFront disponible para esta descarga
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modal de Resultados de Sincronización -->
        <div id="syncModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-sync-alt text-purple-600"></i> Resultados de Sincronización
                    </h3>
                    <button onclick="closeSyncModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <div id="syncModalContent" class="mt-4">
                    <!-- Contenido dinámico -->
                </div>
            </div>
        </div>
        
        <!-- =============================================
             HISTÓRICO DE SINCRONIZACIONES PS → YUJU
             ============================================= -->
        <?php
        // Obtener últimas 10 sincronizaciones de PrestaShop a Yuju
        $sync_history = Db::getInstance()->executeS('
            SELECT 
                id,
                status,
                start_time,
                end_time,
                total_items,
                error_message,
                details
            FROM ' . _DB_PREFIX_ . 'yuju_sync_logs
            WHERE entity_type = "products" 
            AND sync_direction = "prestashop_to_yuju"
            ORDER BY start_time DESC
            LIMIT 10
        ');
        
        if ($sync_history && count($sync_history) > 0):
        ?>
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-exchange-alt text-purple-600 mr-3"></i>
                Histórico de Sincronizaciones PrestaShop → Yuju (Últimas 10)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Fecha/Hora</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">Actualizados</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">Sincronizados</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">No encontrados</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">Errores</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($sync_history as $sync): ?>
                            <?php 
                            $sync_details = json_decode($sync['details'], true);
                            $summary = isset($sync_details['summary']) ? $sync_details['summary'] : [];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-mono text-purple-600">#<?php echo $sync['id']; ?></td>
                                
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?php echo date('d/m/Y H:i', strtotime($sync['start_time'])); ?>
                                </td>
                                
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($sync['status'] === 'completed'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle"></i> Completado
                                        </span>
                                    <?php elseif ($sync['status'] === 'completed_with_errors'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                            <i class="fas fa-exclamation-triangle"></i> Con errores
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?php echo ucfirst($sync['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded font-semibold">
                                        <?php echo isset($summary['updated']) ? $summary['updated'] : 0; ?>
                                    </span>
                                </td>
                                
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold">
                                        <?php echo isset($summary['synced']) ? $summary['synced'] : 0; ?>
                                    </span>
                                </td>
                                
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded font-semibold">
                                        <?php echo isset($summary['not_found']) ? $summary['not_found'] : 0; ?>
                                    </span>
                                </td>
                                
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded font-semibold">
                                        <?php echo isset($summary['errors']) ? $summary['errors'] : 0; ?>
                                    </span>
                                </td>
                                
                                <td class="px-4 py-3 text-sm">
                                    <button 
                                        onclick="viewSyncDetails(<?php echo $sync['id']; ?>)"
                                        class="text-purple-600 hover:text-purple-800 font-medium"
                                    >
                                        <i class="fas fa-eye"></i> Ver detalles
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
            </div> <!-- Fin del contenido del acordeón -->
        </div> <!-- Fin del contenedor del acordeón -->
        
        <!-- =============================================
             WEBHOOKS Y COLA DE SINCRONIZACIÓN
             ============================================= -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-cogs text-indigo-600 mr-3"></i>
                Gestión Avanzada
            </h2>
            
            <!-- Tabs Principales -->
            <div class="flex border-b border-gray-200 mb-4">
                <button 
                    onclick="switchMainTab('webhooks')" 
                    id="main-tab-webhooks"
                    class="px-6 py-3 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600"
                >
                    <i class="fas fa-bell mr-2"></i> Gestión de Webhooks
                </button>
                <button 
                    onclick="switchMainTab('queue')" 
                    id="main-tab-queue"
                    class="px-6 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300"
                >
                    <i class="fas fa-layer-group mr-2"></i> Cola de Sincronización
                    <?php 
                    // Pre-cargar estadísticas de cola para el badge
                    require_once $root_path . '/classes/YujuSyncQueue.php';
                    $temp_queue = new YujuSyncQueue();
                    $temp_stats = $temp_queue->getQueueStats();
                    if ($temp_stats['pending'] > 0): 
                    ?>
                        <span class="ml-1 bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $temp_stats['pending']; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- ============================================= 
                 TAB: GESTIÓN DE WEBHOOKS 
                 ============================================= -->
            <div id="main-webhooks-tab" class="main-content-tab">
                <div class="text-sm text-gray-600 mb-4 bg-blue-50 border-l-4 border-blue-500 p-3 rounded-r-lg">
                    <i class="fas fa-info-circle mr-2"></i>
                    Los webhooks te permiten recibir notificaciones automáticas cuando ocurren eventos en tu tienda de Yuju.
                    <strong>URL del webhook:</strong> 
                    <?php 
                    // Yuju requiere HTTPS para webhooks
                    $webhook_url = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/prestashopyuju/webhook.php';
                    echo '<code class="bg-white px-2 py-1 rounded">' . htmlspecialchars($webhook_url) . '</code>';
                    ?>
                    <button 
                        onclick="refreshWebhookData()" 
                        class="ml-4 text-sm bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-3 py-1 rounded-lg transition-colors"
                        id="refresh-webhooks-btn"
                    >
                        <i class="fas fa-sync-alt mr-1"></i> Actualizar
                    </button>
                </div>
                
                <!-- Sub-Tabs de Webhooks -->
                <div class="flex border-b border-gray-200 mb-4">
                    <button 
                        onclick="switchWebhookTab('subscriptions')" 
                        id="tab-subscriptions"
                        class="px-4 py-2 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600"
                    >
                        <i class="fas fa-list-check mr-1"></i> Suscripciones
                    </button>
                    <button 
                        onclick="switchWebhookTab('received')" 
                        id="tab-received"
                        class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300"
                    >
                        <i class="fas fa-inbox mr-1"></i> Recibidos
                        <span id="webhook-received-badge" class="ml-1 bg-gray-200 text-gray-700 text-xs px-2 py-0.5 rounded-full">0</span>
                    </button>
                </div>
                
                <!-- Tab: Suscripciones -->
                <div id="webhook-subscriptions-tab" class="webhook-tab">
                <div id="webhook-subscriptions-loading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
                    <p class="text-gray-600 mt-2">Cargando suscripciones...</p>
                </div>
                
                <div id="webhook-subscriptions-content" class="hidden">
                    <!-- Topics disponibles -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-tags text-purple-600 mr-2"></i>
                                Topics Disponibles
                            </h3>
                            <div class="flex gap-2">
                                <button 
                                    id="subscribe-selected-btn"
                                    onclick="subscribeSelectedTopics()" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled
                                >
                                    <i class="fas fa-bell mr-1"></i> Suscribirse a Seleccionados
                                </button>
                                <button 
                                    id="unsubscribe-selected-btn"
                                    onclick="unsubscribeSelectedTopics()" 
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled
                                >
                                    <i class="fas fa-bell-slash mr-1"></i> Desuscribirse de Seleccionados
                                </button>
                            </div>
                        </div>
                        <div id="available-topics-grid" class="space-y-2">
                            <!-- Se llenará con JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Botón para nueva suscripción -->
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-link text-green-600 mr-2"></i>
                            Suscripciones Activas
                            <span id="active-subscriptions-count" class="ml-2 bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">0</span>
                        </h3>
                        <button 
                            onclick="showSubscribeModal()" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        >
                            <i class="fas fa-plus mr-1"></i> Nueva Suscripción
                        </button>
                    </div>
                    
                    <!-- Lista de suscripciones -->
                    <div id="active-subscriptions-list" class="space-y-3">
                        <!-- Se llenará con JavaScript -->
                    </div>
                    
                    <div id="no-subscriptions-message" class="hidden text-center py-8 text-gray-500">
                        <i class="fas fa-bell-slash text-4xl mb-2"></i>
                        <p>No hay suscripciones activas</p>
                        <p class="text-sm mt-1">Crea una nueva suscripción para comenzar a recibir webhooks</p>
                    </div>
                </div>
                
                <div id="webhook-subscriptions-error" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-3 mt-0.5"></i>
                        <div>
                            <h4 class="font-semibold text-red-800">Error al cargar suscripciones</h4>
                            <p class="text-red-700 text-sm mt-1" id="webhook-subscriptions-error-message"></p>
                        </div>
                    </div>
                </div>
            </div> <!-- Fin webhook-subscriptions-tab -->
            
            <!-- Tab: Webhooks Recibidos -->
            <div id="webhook-received-tab" class="webhook-tab hidden">
                <div id="webhook-received-loading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
                    <p class="text-gray-600 mt-2">Cargando webhooks recibidos...</p>
                </div>
                
                <div id="webhook-received-content" class="hidden">
                    <!-- Estadísticas -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-blue-700 font-medium">Total Recibidos</p>
                                    <p class="text-2xl font-bold text-blue-900" id="stats-total">0</p>
                                </div>
                                <i class="fas fa-inbox text-3xl text-blue-400"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-green-700 font-medium">Últimas 24h</p>
                                    <p class="text-2xl font-bold text-green-900" id="stats-last-24h">0</p>
                                </div>
                                <i class="fas fa-clock text-3xl text-green-400"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-purple-700 font-medium">Último Recibido</p>
                                    <p class="text-sm font-bold text-purple-900" id="stats-last-received">-</p>
                                </div>
                                <i class="fas fa-calendar text-3xl text-purple-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">Filtrar por topic:</label>
                            <select 
                                id="webhook-topic-filter" 
                                onchange="filterWebhooksByTopic()"
                                class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">Todos</option>
                            </select>
                        </div>
                        
                        <button 
                            onclick="clearWebhookHistory()" 
                            class="text-sm bg-red-100 hover:bg-red-200 text-red-700 px-4 py-2 rounded-lg transition-colors"
                        >
                            <i class="fas fa-trash mr-1"></i> Limpiar historial
                        </button>
                    </div>
                    
                    <!-- Lista de webhooks -->
                    <div id="received-webhooks-list" class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Se llenará con JavaScript -->
                    </div>
                    
                    <div id="no-webhooks-message" class="hidden text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>No se han recibido webhooks aún</p>
                        <p class="text-sm mt-1">Los webhooks recibidos aparecerán aquí automáticamente</p>
                    </div>
                </div>
                
                <div id="webhook-received-error" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-3 mt-0.5"></i>
                        <div>
                            <h4 class="font-semibold text-red-800">Error al cargar webhooks</h4>
                            <p class="text-red-700 text-sm mt-1" id="webhook-received-error-message"></p>
                        </div>
                    </div>
                </div>
            </div> <!-- Fin webhook-received-tab -->
            
        <!-- Modales -->
        <!-- Modal para nueva suscripción -->
        <div id="subscribe-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                            Nueva Suscripción de Webhook
                        </h3>
                        <button onclick="closeSubscribeModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    
                    <form id="subscribe-form" onsubmit="submitSubscription(event)">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-link mr-1"></i> URL del Webhook
                            </label>
                            <input 
                                type="url" 
                                id="webhook-url-input"
                                value="<?php echo htmlspecialchars($webhook_url); ?>"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Debe ser una URL HTTPS válida con certificado SSL
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tags mr-1"></i> Seleccionar Topics (máx. 3)
                            </label>
                            <div id="topics-checkboxes" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-3">
                                <!-- Se llenará con JavaScript -->
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Selecciona hasta 3 eventos para recibir notificaciones
                            </p>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button 
                                type="button" 
                                onclick="closeSubscribeModal()"
                                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                                id="subscribe-submit-btn"
                            >
                                <i class="fas fa-check mr-1"></i> Crear Suscripción
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div> <!-- Fin subscribe-modal -->

        <!-- Modal de Detalles del Webhook -->
        <div id="webhookDetailsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-5xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-info-circle text-indigo-600"></i> Detalles del Webhook
                    </h3>
                    <button onclick="closeWebhookDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <div id="webhookDetailsContent" class="mt-4 max-h-[70vh] overflow-y-auto">
                    <!-- Contenido dinámico -->
                </div>

                <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
                    <button 
                        onclick="copyWebhookDetails()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                        title="Copiar JSON al portapapeles"
                    >
                        <i class="fas fa-copy mr-1"></i> Copiar JSON
                    </button>
                    <button 
                        onclick="closeWebhookDetailsModal()" 
                        class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors"
                    >
                        <i class="fas fa-times mr-1"></i> Cerrar
                    </button>
                </div>
            </div>
        </div> <!-- Fin webhookDetailsModal -->
        
            </div> <!-- Fin main-webhooks-tab -->
            
            <!-- ============================================= 
                 TAB: COLA DE SINCRONIZACIÓN 
                 ============================================= -->
            <div id="main-queue-tab" class="main-content-tab hidden">
                <?php
                // Obtener datos de la cola
                $sync_queue = new YujuSyncQueue();
                $batch_size = (int) YujuConfig::get('YUJU_BATCH_SIZE', 100);
                $queue_stats = $sync_queue->getQueueStats();
                ?>
                
                <div class="text-sm text-gray-600 mb-4 bg-purple-50 border-l-4 border-purple-500 p-3 rounded-r-lg">
                    <i class="fas fa-info-circle mr-2"></i>
                    El sistema procesa automáticamente productos en lotes de <strong><?php echo $batch_size; ?></strong> 
                    cada vez que se ejecuta el cron (recomendado cada 5 minutos).
                    Los cambios de <strong>precio y stock</strong> se sincronizan inmediatamente, 
                    otros cambios se agrupan en la cola.
                </div>
                
                <!-- Sub-Tabs de Cola -->
                <div class="flex border-b border-gray-200 mb-4">
                    <button 
                        onclick="switchQueueTab('stats')" 
                        id="tab-queue-stats"
                        class="px-4 py-2 text-sm font-medium border-b-2 border-purple-600 text-purple-600"
                    >
                        <i class="fas fa-chart-bar mr-1"></i> Estadísticas
                    </button>
                    <button 
                        onclick="switchQueueTab('processing')" 
                        id="tab-queue-processing"
                        class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300"
                    >
                        <i class="fas fa-cog mr-1"></i> Procesamiento
                        <?php if ($queue_stats['pending'] > 0): ?>
                            <span class="ml-1 bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $queue_stats['pending']; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <!-- Sub-Tab: Estadísticas -->
                <div id="queue-stats-tab" class="queue-tab">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 font-medium">Total en Cola</p>
                                    <p class="text-2xl font-bold text-gray-800"><?php echo $queue_stats['total']; ?></p>
                                </div>
                                <i class="fas fa-database text-3xl text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-yellow-700 font-medium">Pendientes</p>
                                    <p class="text-2xl font-bold text-yellow-800"><?php echo $queue_stats['pending']; ?></p>
                                </div>
                                <i class="fas fa-clock text-3xl text-yellow-400"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-blue-700 font-medium">Procesando</p>
                                    <p class="text-2xl font-bold text-blue-800"><?php echo $queue_stats['processing']; ?></p>
                                </div>
                                <i class="fas fa-spinner text-3xl text-blue-400"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-green-700 font-medium">Completados</p>
                                    <p class="text-2xl font-bold text-green-800"><?php echo $queue_stats['completed']; ?></p>
                                </div>
                                <i class="fas fa-check-circle text-3xl text-green-400"></i>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-lg border border-red-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-red-700 font-medium">Fallidos</p>
                                    <p class="text-2xl font-bold text-red-800"><?php echo $queue_stats['failed']; ?></p>
                                </div>
                                <i class="fas fa-exclamation-triangle text-3xl text-red-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-layer-group text-2xl text-purple-600 mr-3"></i>
                                <div>
                                    <p class="text-sm text-gray-600">Tamaño de lote configurado</p>
                                    <p class="text-lg font-bold text-gray-800"><?php echo $batch_size; ?> productos por ejecución</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">Frecuencia recomendada</p>
                                <p class="text-lg font-bold text-gray-800">Cada 5 minutos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sub-Tab: Procesamiento -->
                <div id="queue-processing-tab" class="queue-tab hidden">
                    <?php if ($queue_stats['pending'] > 0): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mb-4">
                            <p class="text-sm text-gray-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                Procesando lote de <strong><?php echo $batch_size; ?></strong> productos...
                            </p>
                        </div>
                        
                        <?php
                        $process_start = microtime(true);
                        $batch_stats = $sync_queue->processBatch($batch_size);
                        $process_duration = microtime(true) - $process_start;
                        
                        $logger->log('info', 'Cola procesada', [
                            'batch_size' => $batch_size,
                            'stats' => $batch_stats,
                            'duration_seconds' => round($process_duration, 2)
                        ]);
                        ?>
                        
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">
                            <p class="text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                Resultados del procesamiento:
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-white p-3 rounded-lg border border-gray-200">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-600 mb-1">Procesados</p>
                                        <p class="text-2xl font-bold text-gray-800"><?php echo $batch_stats['processed']; ?></p>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-green-200">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-600 mb-1">Exitosos</p>
                                        <p class="text-2xl font-bold text-green-600"><?php echo $batch_stats['success']; ?></p>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-red-200">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-600 mb-1">Fallidos</p>
                                        <p class="text-2xl font-bold text-red-600"><?php echo $batch_stats['failed']; ?></p>
                                    </div>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-blue-200">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-600 mb-1">Duración</p>
                                        <p class="text-2xl font-bold text-blue-600"><?php echo round($process_duration, 2); ?>s</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-50 border-l-4 border-gray-400 p-4 rounded-r-lg text-center">
                            <i class="fas fa-check-circle text-4xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600 font-medium">
                                No hay productos pendientes en la cola
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Todos los productos están sincronizados
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div> <!-- Fin Tab Cola -->
        </div> <!-- Fin contenedor principal -->
        
        <script>
        // Funciones para cambiar tabs principales
        function switchMainTab(tab) {
            // Ocultar todos los tabs principales
            document.querySelectorAll('.main-content-tab').forEach(el => el.classList.add('hidden'));
            
            // Resetear estilos de botones principales
            document.querySelectorAll('[id^="main-tab-"]').forEach(btn => {
                btn.classList.remove('border-indigo-600', 'text-indigo-600');
                btn.classList.add('border-transparent', 'text-gray-600');
            });
            
            // Mostrar tab seleccionada
            if (tab === 'webhooks') {
                document.getElementById('main-webhooks-tab').classList.remove('hidden');
                document.getElementById('main-tab-webhooks').classList.remove('border-transparent', 'text-gray-600');
                document.getElementById('main-tab-webhooks').classList.add('border-indigo-600', 'text-indigo-600');
            } else if (tab === 'queue') {
                document.getElementById('main-queue-tab').classList.remove('hidden');
                document.getElementById('main-tab-queue').classList.remove('border-transparent', 'text-gray-600');
                document.getElementById('main-tab-queue').classList.add('border-indigo-600', 'text-indigo-600');
            }
        }
        
        // Funciones para sub-tabs de cola
        function switchQueueTab(tab) {
            // Ocultar todas las sub-tabs de cola
            document.querySelectorAll('.queue-tab').forEach(el => el.classList.add('hidden'));
            
            // Resetear estilos de botones de cola
            document.querySelectorAll('[id^="tab-queue-"]').forEach(btn => {
                btn.classList.remove('border-purple-600', 'text-purple-600');
                btn.classList.add('border-transparent', 'text-gray-600');
            });
            
            // Mostrar sub-tab seleccionada
            if (tab === 'stats') {
                document.getElementById('queue-stats-tab').classList.remove('hidden');
                document.getElementById('tab-queue-stats').classList.remove('border-transparent', 'text-gray-600');
                document.getElementById('tab-queue-stats').classList.add('border-purple-600', 'text-purple-600');
            } else if (tab === 'processing') {
                document.getElementById('queue-processing-tab').classList.remove('hidden');
                document.getElementById('tab-queue-processing').classList.remove('border-transparent', 'text-gray-600');
                document.getElementById('tab-queue-processing').classList.add('border-purple-600', 'text-purple-600');
            }
        }
        </script>
        
        <script>
        function toggleSyncAccordion() {
            const content = document.getElementById('sync-accordion-content');
            const icon = document.getElementById('accordion-icon');
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            }
        }
        
        function toggleDetails(id) {
            const element = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            
            if (element.classList.contains('hidden')) {
                element.classList.remove('hidden');
                if (icon) {
                    icon.classList.remove('fa-plus-circle');
                    icon.classList.add('fa-minus-circle');
                }
            } else {
                element.classList.add('hidden');
                if (icon) {
                    icon.classList.remove('fa-minus-circle');
                    icon.classList.add('fa-plus-circle');
                }
            }
        }
        
        function toggleVariations(id) {
            const element = document.getElementById(id);
            const icon = document.getElementById('icon_' + id);
            
            if (element.classList.contains('hidden')) {
                element.classList.remove('hidden');
                if (icon) {
                    icon.classList.remove('fa-plus-circle');
                    icon.classList.add('fa-minus-circle');
                }
            } else {
                element.classList.add('hidden');
                if (icon) {
                    icon.classList.remove('fa-minus-circle');
                    icon.classList.add('fa-plus-circle');
                }
            }
        }
        
        function loadProductsPage(downloadId, page, totalPages, totalItems, perPage) {
            const productsData = window['productsData_' + downloadId];
            const config = window['productsConfig_' + downloadId];
            
            if (!productsData) {
                console.error('No hay datos de productos para el download ID:', downloadId);
                return;
            }
            
            // Calcular offset
            const offset = (page - 1) * perPage;
            const paginatedItems = productsData.slice(offset, offset + perPage);
            
            // Renderizar tabla
            let tableHTML = `
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase w-8"></th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">SKU</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Nombre</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 uppercase">Stock</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-700 uppercase">Precio</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">ID Yuju</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-700 uppercase">Variaciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            let rowIndex = offset;
            paginatedItems.forEach(item => {
                const product = item.product;
                const variations = item.variations || [];
                const hasVariations = variations.length > 0;
                const rowId = 'row_' + downloadId + '_' + rowIndex;
                rowIndex++;
                
                const sku = product.sku || product.sku_simple || 'N/A';
                const name = (product.name || 'Sin nombre').substring(0, 50) + ((product.name && product.name.length > 50) ? '...' : '');
                const stock = product.stock || 0;
                const price = product.price || 0;
                const idProduct = product.id_product || product.id || 'N/A';
                const stockClass = stock > 0 ? 'text-green-600' : 'text-red-600';
                const bgClass = hasVariations ? 'bg-blue-50' : '';
                
                // Fila principal
                tableHTML += `
                    <tr class="hover:bg-gray-50 ${bgClass}">
                        <td class="px-3 py-2">
                            ${hasVariations ? `<button onclick="toggleVariations('${rowId}')" class="text-indigo-600 hover:text-indigo-900" title="Ver variaciones"><i class="fas fa-plus-circle" id="icon_${rowId}"></i></button>` : ''}
                        </td>
                        <td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">
                            ${escapeHtml(sku)}
                            ${hasVariations ? '<span class="ml-1 px-1 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">Base</span>' : ''}
                        </td>
                        <td class="px-3 py-2 text-gray-900">${escapeHtml(name)}</td>
                        <td class="px-3 py-2 text-right font-semibold whitespace-nowrap ${stockClass}">${formatNumber(stock)}</td>
                        <td class="px-3 py-2 text-right font-semibold text-purple-600 whitespace-nowrap">$${formatNumber(price, 2)}</td>
                        <td class="px-3 py-2 font-mono text-gray-500 whitespace-nowrap">${escapeHtml(idProduct)}</td>
                        <td class="px-3 py-2">
                            ${hasVariations ? `<span class="px-2 py-1 bg-indigo-100 text-indigo-800 rounded text-xs font-semibold"><i class="fas fa-layer-group"></i> ${variations.length} variación(es)</span>` : '<span class="text-gray-400 text-xs">-</span>'}
                        </td>
                    </tr>
                `;
                
                // Fila de variaciones (colapsada)
                if (hasVariations) {
                    tableHTML += `
                        <tr id="${rowId}" class="hidden bg-indigo-50">
                            <td colspan="7" class="px-3 py-2">
                                <div class="ml-8 border-l-2 border-indigo-300 pl-4">
                                    <p class="text-xs font-semibold text-indigo-900 mb-2">
                                        <i class="fas fa-sitemap"></i> Variaciones de este producto (${variations.length}):
                                    </p>
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-indigo-100">
                                            <tr>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">SKU Variación</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">Nombre</th>
                                                <th class="px-2 py-1 text-right text-xs font-medium text-gray-700">Stock</th>
                                                <th class="px-2 py-1 text-right text-xs font-medium text-gray-700">Precio</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">ID Yuju</th>
                                                <th class="px-2 py-1 text-left text-xs font-medium text-gray-700">Atributos</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                    `;
                    
                    variations.forEach(variation => {
                        const varSku = variation.sku || 'N/A';
                        const varName = (variation.name || 'Sin nombre').substring(0, 40) + ((variation.name && variation.name.length > 40) ? '...' : '');
                        const varStock = variation.stock || 0;
                        const varPrice = variation.price || 0;
                        const varId = variation.id_product || variation.id || 'N/A';
                        const varStockClass = varStock > 0 ? 'text-green-600' : 'text-red-600';
                        
                        let attributesHTML = '<span class="text-gray-400">-</span>';
                        if (variation.attributes && Array.isArray(variation.attributes)) {
                            attributesHTML = '';
                            variation.attributes.forEach(attr => {
                                if (attr.name && attr.value) {
                                    attributesHTML += `<span class="inline-block px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded mr-1 mb-1">${escapeHtml(attr.name)}: ${escapeHtml(attr.value)}</span>`;
                                }
                            });
                        }
                        
                        tableHTML += `
                            <tr class="hover:bg-indigo-50">
                                <td class="px-2 py-1 font-mono text-blue-600 text-xs">${escapeHtml(varSku)}</td>
                                <td class="px-2 py-1 text-gray-700 text-xs">${escapeHtml(varName)}</td>
                                <td class="px-2 py-1 text-right font-semibold text-xs ${varStockClass}">${formatNumber(varStock)}</td>
                                <td class="px-2 py-1 text-right font-semibold text-purple-600 text-xs">$${formatNumber(varPrice, 2)}</td>
                                <td class="px-2 py-1 font-mono text-gray-500 text-xs">${escapeHtml(varId)}</td>
                                <td class="px-2 py-1 text-xs">${attributesHTML}</td>
                            </tr>
                        `;
                    });
                    
                    tableHTML += `
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            });
            
            tableHTML += `
                    </tbody>
                </table>
            `;
            
            // Agregar paginación si hay más de una página
            if (totalPages > 1) {
                const startPage = Math.max(1, page - 2);
                const endPage = Math.min(totalPages, page + 2);
                
                tableHTML += `
                    <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-3">
                        <div class="text-xs text-gray-600">
                            Mostrando ${offset + 1} - ${Math.min(offset + perPage, totalItems)} de ${totalItems} productos base
                        </div>
                        <div class="flex gap-1">
                `;
                
                if (page > 1) {
                    tableHTML += `<button onclick="loadProductsPage(${downloadId}, ${page - 1}, ${totalPages}, ${totalItems}, ${perPage})" class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50"><i class="fas fa-chevron-left"></i> Anterior</button>`;
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    const activeClass = i === page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white border-gray-300 hover:bg-gray-50';
                    tableHTML += `<button onclick="loadProductsPage(${downloadId}, ${i}, ${totalPages}, ${totalItems}, ${perPage})" class="px-3 py-1 text-xs border rounded ${activeClass}">${i}</button>`;
                }
                
                if (page < totalPages) {
                    tableHTML += `<button onclick="loadProductsPage(${downloadId}, ${page + 1}, ${totalPages}, ${totalItems}, ${perPage})" class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">Siguiente <i class="fas fa-chevron-right"></i></button>`;
                }
                
                tableHTML += `
                        </div>
                    </div>
                `;
            }
            
            // Actualizar el contenedor
            document.getElementById('products-table-' + downloadId).innerHTML = tableHTML;
            
            // Actualizar información de paginación en el summary
            document.getElementById('pagination-info-' + downloadId).textContent = `(Página ${page} de ${totalPages} - ${totalItems} productos base)`;
            
            // Actualizar config
            config.currentPage = page;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatNumber(num, decimals = 0) {
            // Si es un string '-' o null/undefined, retornar '-'
            if (num === '-' || num === null || num === undefined || num === '') {
                return '-';
            }
            
            // Convertir a número
            const numValue = Number(num);
            
            // Si no es un número válido, retornar '-'
            if (isNaN(numValue)) {
                return '-';
            }
            
            if (decimals > 0) {
                return numValue.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            return numValue.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // Sincronizar con Yuju
        async function syncWithYuju(downloadId) {
            const btn = document.getElementById('sync-btn-' + downloadId);
            const originalHtml = btn.innerHTML;
            
            // Deshabilitar botón y mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';
            
            try {
                const response = await fetch('sync_to_yuju.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ download_id: downloadId })
                });
                
                const data = await response.json();
                
                console.log('Datos recibidos de sync:', data);
                console.log('Results:', data.results);
                
                if (!response.ok || data.error) {
                    throw new Error(data.error || 'Error en la sincronización');
                }
                
                // Guardar resultados en variable global
                window['syncResults_' + downloadId] = data.results;
                
                // Actualizar indicadores visuales
                updateSyncIndicators(data.results);
                
                // Mostrar modal con resultados
                showSyncModal(data.results);
                
                // Cambiar botón a "Completado"
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Completado';
                btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al sincronizar: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
        
        // Mostrar modal con resultados
        function showSyncModal(results) {
            const modal = document.getElementById('syncModal');
            const content = document.getElementById('syncModalContent');
            
            let html = `
                <!-- Resumen -->
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                        <div class="text-sm text-purple-700 font-medium">Productos en Yuju</div>
                        <div class="text-3xl font-bold text-purple-600">${results.total_yuju || 0}</div>
                    </div>
                    <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded">
                        <div class="text-sm text-indigo-700 font-medium">Productos en PrestaShop</div>
                        <div class="text-3xl font-bold text-indigo-600">${results.total_prestashop || 0}</div>
                    </div>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <div class="text-sm text-blue-700 font-medium">Encontrados en PS</div>
                        <div class="text-3xl font-bold text-blue-600">${results.found_in_prestashop || 0}</div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded">
                        <div class="text-sm text-orange-700 font-medium">Con Diferencias</div>
                        <div class="text-3xl font-bold text-orange-600">${results.with_differences || 0}</div>
                        <div class="text-xs text-orange-600 mt-1">Stock o precio diferentes</div>
                    </div>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="text-sm text-green-700 font-medium">Peticiones API</div>
                        <div class="text-3xl font-bold text-green-600">${results.api_requests || 0}</div>
                        <div class="text-xs text-green-600 mt-1">Actualizaciones enviadas</div>
                    </div>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="text-sm text-red-700 font-medium">Errores</div>
                        <div class="text-3xl font-bold text-red-600">${results.errors || 0}</div>
                        <div class="text-xs text-red-600 mt-1">Fallos en actualización</div>
                    </div>
                </div>
                
                <!-- Detalles expandibles -->
                <details class="mb-4" open>
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900 bg-gray-100 p-3 rounded">
                        <i class="fas fa-list"></i> Ver detalles de productos (${results.details ? results.details.length : 0})
                    </summary>
                
                <!-- Filtros -->
                <div class="mt-3 mb-3 flex gap-2 flex-wrap">
                    <button onclick="filterSyncResults('all')" id="filter-all" class="px-3 py-1.5 text-sm font-medium rounded bg-gray-200 text-gray-800 hover:bg-gray-300">
                        <i class="fas fa-list"></i> Todos (${results.details ? results.details.length : 0})
                    </button>
                    <button onclick="filterSyncResults('updated')" id="filter-updated" class="px-3 py-1.5 text-sm font-medium rounded bg-gray-100 text-gray-700 hover:bg-green-100 hover:text-green-800">
                        <i class="fas fa-check-circle"></i> Actualizados (${results.updated || 0})
                    </button>
                    <button onclick="filterSyncResults('synced')" id="filter-synced" class="px-3 py-1.5 text-sm font-medium rounded bg-gray-100 text-gray-700 hover:bg-blue-100 hover:text-blue-800">
                        <i class="fas fa-check"></i> Sincronizados (${results.synced || 0})
                    </button>
                    <button onclick="filterSyncResults('not_found')" id="filter-not_found" class="px-3 py-1.5 text-sm font-medium rounded bg-gray-100 text-gray-700 hover:bg-red-100 hover:text-red-800">
                        <i class="fas fa-times-circle"></i> No encontrados (${results.not_found || 0})
                    </button>
                    <button onclick="filterSyncResults('error')" id="filter-error" class="px-3 py-1.5 text-sm font-medium rounded bg-gray-100 text-gray-700 hover:bg-orange-100 hover:text-orange-800">
                        <i class="fas fa-exclamation-triangle"></i> Errores (${results.errors || 0})
                    </button>
                </div>
                
                <!-- Tabla de detalles -->
                <div class="overflow-x-auto max-h-96 overflow-y-auto mt-3">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">SKU</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Mensaje</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Stock PS</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Stock Yuju</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Precio PS</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Precio Yuju</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="syncResultsTableBody">
            `;
            
            // Guardar detalles en variable global para filtrado
            window.currentSyncDetails = results.details || [];
            
            // Mostrar TODOS los productos
            results.details.forEach(detail => {
                let statusBadge = '';
                let rowClass = '';
                
                switch(detail.status) {
                    case 'updated':
                        statusBadge = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800"><i class="fas fa-check-circle"></i> Actualizado</span>';
                        rowClass = 'bg-green-50';
                        break;
                    case 'synced':
                        statusBadge = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"><i class="fas fa-check"></i> Sincronizado</span>';
                        break;
                    case 'not_found':
                        statusBadge = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800"><i class="fas fa-times-circle"></i> No encontrado</span>';
                        rowClass = 'bg-red-50';
                        break;
                    case 'error':
                        statusBadge = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800"><i class="fas fa-exclamation-triangle"></i> Error</span>';
                        rowClass = 'bg-orange-50';
                        break;
                }
                
                // Formatear valores de stock y precio
                const psStock = detail.ps_stock === '-' ? '<span class="text-red-600 font-bold">-</span>' : formatNumber(detail.ps_stock);
                const yujuStock = formatNumber(detail.yuju_stock);
                const psPrice = detail.ps_price === '-' ? '<span class="text-red-600 font-bold">-</span>' : '$' + formatNumber(detail.ps_price, 2);
                const yujuPrice = '$' + formatNumber(detail.yuju_price, 2);
                
                html += `
                    <tr class="${rowClass}" data-status="${detail.status}">
                        <td class="px-4 py-3 text-sm">${statusBadge}</td>
                        <td class="px-4 py-3 text-sm font-mono">${escapeHtml(detail.sku)}</td>
                        <td class="px-4 py-3 text-sm">${escapeHtml(detail.message)}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold">${psStock}</td>
                        <td class="px-4 py-3 text-sm text-right">${yujuStock}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold">${psPrice}</td>
                        <td class="px-4 py-3 text-sm text-right">${yujuPrice}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                </details>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="closeSyncModal()" class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            `;
            
            content.innerHTML = html;
            modal.classList.remove('hidden');
        }
        
        // Cerrar modal
        function closeSyncModal() {
            document.getElementById('syncModal').classList.add('hidden');
        }
        
        // Filtrar resultados de sincronización por estado
        function filterSyncResults(status) {
            const rows = document.querySelectorAll('#syncResultsTableBody tr');
            
            // Actualizar botones activos
            document.querySelectorAll('[id^="filter-"]').forEach(btn => {
                btn.classList.remove('bg-gray-200', 'bg-green-100', 'bg-blue-100', 'bg-red-100', 'bg-orange-100');
                btn.classList.remove('text-gray-800', 'text-green-800', 'text-blue-800', 'text-red-800', 'text-orange-800');
                btn.classList.add('bg-gray-100', 'text-gray-700');
            });
            
            const activeBtn = document.getElementById('filter-' + status);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-100', 'text-gray-700');
                
                switch(status) {
                    case 'all':
                        activeBtn.classList.add('bg-gray-200', 'text-gray-800');
                        break;
                    case 'updated':
                        activeBtn.classList.add('bg-green-100', 'text-green-800');
                        break;
                    case 'synced':
                        activeBtn.classList.add('bg-blue-100', 'text-blue-800');
                        break;
                    case 'not_found':
                        activeBtn.classList.add('bg-red-100', 'text-red-800');
                        break;
                    case 'error':
                        activeBtn.classList.add('bg-orange-100', 'text-orange-800');
                        break;
                }
            }
            
            // Filtrar filas
            let visibleCount = 0;
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Mostrar mensaje si no hay resultados
            const tbody = document.getElementById('syncResultsTableBody');
            const noResultsRow = tbody.querySelector('.no-results-row');
            
            if (visibleCount === 0) {
                if (!noResultsRow) {
                    tbody.innerHTML += `
                        <tr class="no-results-row">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-search text-3xl mb-2"></i>
                                <p>No hay productos con este estado</p>
                            </td>
                        </tr>
                    `;
                }
            } else {
                if (noResultsRow) {
                    noResultsRow.remove();
                }
            }
        }
        
        // Ver detalles de una sincronización anterior
        async function viewSyncDetails(syncId) {
            try {
                // Obtener datos del servidor
                const response = await fetch('get_sync_details.php?id=' + syncId);
                const data = await response.json();
                
                if (!response.ok || data.error) {
                    throw new Error(data.error || 'Error al cargar los detalles');
                }
                
                // Mostrar en el modal
                showSyncModal(data.results);
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al cargar detalles: ' + error.message);
            }
        }
        
        // Reintentar descarga fallida
        async function retryDownload(downloadId) {
            const btn = document.getElementById('retry-btn-' + downloadId);
            const originalHtml = btn.innerHTML;
            
            // Deshabilitar botón y mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Descargando...';
            
            try {
                const response = await fetch('retry_download.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ download_id: downloadId })
                });
                
                const data = await response.json();
                
                if (!response.ok || data.error) {
                    throw new Error(data.error || 'Error al reintentar descarga');
                }
                
                // 1. Ocultar el contenedor de error en los detalles
                const errorContainer = btn.closest('.bg-red-50');
                if (errorContainer) {
                    errorContainer.style.display = 'none';
                }
                
                // 2. Cambiar el estado en la fila de la tabla principal
                const detailsRow = document.querySelector(`tr[data-download-id="${downloadId}"]`);
                if (detailsRow) {
                    // Cambiar el badge de estado (columna 3)
                    const statusCell = detailsRow.querySelector('td:nth-child(3)');
                    if (statusCell) {
                        const statusLabel = data.already_downloaded || data.recovered_from_cache ? 'Recuperado' : 'Reintentado';
                        statusCell.innerHTML = `
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                <i class="fas fa-check-circle"></i> ${statusLabel}
                            </span>
                            <span class="ml-2 px-2 py-1 text-xs font-mono rounded bg-green-100 text-green-700">
                                HTTP 200
                            </span>
                        `;
                    }
                    
                    // Actualizar la columna de productos (columna 4)
                    const productsCell = detailsRow.querySelector('td:nth-child(4)');
                    if (productsCell) {
                        productsCell.innerHTML = `
                            <span class="font-semibold text-purple-600">${data.base_products}</span>
                            <br><span class="text-xs text-gray-500">${data.file_size_mb} MB</span>
                        `;
                    }
                    
                    // Eliminar el indicador de error en la columna de detalles (columna 6)
                    const detailsCell = detailsRow.querySelector('td:nth-child(6)');
                    if (detailsCell) {
                        const errorIndicator = detailsCell.querySelector('.bg-red-50');
                        if (errorIndicator) {
                            errorIndicator.remove();
                        }
                    }
                }
                
                // 3. Actualizar el título de la sección de detalles expandida
                const detailsSection = document.getElementById('details-' + downloadId);
                if (detailsSection) {
                    const errorBadge = detailsSection.querySelector('.bg-red-50');
                    if (errorBadge) {
                        const successMessage = data.already_downloaded 
                            ? 'Ya existía una descarga exitosa previa' 
                            : (data.recovered_from_cache 
                                ? 'Archivo recuperado desde cache (sin descargar nuevamente)' 
                                : 'Descarga reintentada exitosamente');
                        
                        errorBadge.innerHTML = `
                            <p class="text-xs text-green-700">
                                <i class="fas fa-check-circle mr-1"></i>
                                <strong>Éxito:</strong> ${successMessage} - ${data.total_products} productos
                            </p>
                        `;
                        errorBadge.classList.remove('bg-red-50', 'border-red-400');
                        errorBadge.classList.add('bg-green-50', 'border-green-400');
                    }
                }
                
                // Mostrar mensaje de éxito
                const statusMsg = data.already_downloaded 
                    ? '♻️ Ya existía descarga previa' 
                    : (data.recovered_from_cache 
                        ? '♻️ Recuperado desde cache' 
                        : '✅ Descarga completada');
                        
                alert(`${statusMsg}!\n\n✅ Total: ${data.total_products} productos\n📦 Base: ${data.base_products}\n🔄 Variaciones: ${data.variations}\n💾 Tamaño: ${data.file_size_mb} MB\n\n⏳ Recargando página...`);
                
                // Recargar la página para mostrar la nueva descarga
                setTimeout(() => {
                    location.reload();
                }, 1000);
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al reintentar descarga: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
        
        // Actualizar indicadores visuales en la tabla
        function updateSyncIndicators(results) {
            if (!results || !results.details) return;
            
            results.details.forEach(detail => {
                const sku = detail.sku;
                const status = detail.status;
                
                // Buscar el elemento por SKU
                const statusElements = document.querySelectorAll('.sync-status[data-sku="' + sku + '"]');
                
                statusElements.forEach(element => {
                    let icon = '';
                    let title = '';
                    
                    switch(status) {
                        case 'updated':
                            icon = '<i class="fas fa-check-circle text-green-500" title="' + escapeHtml(detail.message) + '"></i>';
                            title = detail.message;
                            break;
                        case 'synced':
                            icon = '<i class="fas fa-check text-blue-500" title="Ya sincronizado"></i>';
                            title = 'Ya sincronizado';
                            break;
                        case 'not_found':
                            icon = '<i class="fas fa-times-circle text-red-500" title="No encontrado en PrestaShop"></i>';
                            title = 'No encontrado';
                            break;
                        case 'error':
                            icon = '<i class="fas fa-exclamation-triangle text-orange-500" title="' + escapeHtml(detail.message) + '"></i>';
                            title = detail.message;
                            break;
                        default:
                            icon = '<i class="fas fa-circle text-gray-300" title="Pendiente"></i>';
                            title = 'Pendiente';
                    }
                    
                    element.innerHTML = icon;
                    element.setAttribute('title', title);
                });
            });
        }
        
        // =============================================
        // FUNCIONES DE GESTIÓN DE WEBHOOKS
        // =============================================
        
        let webhookData = {
            subscriptions: [],
            availableTopics: {},
            receivedWebhooks: [],
            stats: {}
        };
        
        // Cargar datos al inicio
        document.addEventListener('DOMContentLoaded', function() {
            refreshWebhookData();
        });
        
        // Cambiar entre tabs
        function switchWebhookTab(tabName) {
            // Ocultar todos los tabs
            document.querySelectorAll('.webhook-tab').forEach(tab => tab.classList.add('hidden'));
            
            // Remover clase activa de todos los botones
            document.querySelectorAll('[id^="tab-"]').forEach(btn => {
                btn.classList.remove('border-indigo-600', 'text-indigo-600');
                btn.classList.add('border-transparent', 'text-gray-600');
            });
            
            // Mostrar tab seleccionado
            document.getElementById('webhook-' + tabName + '-tab').classList.remove('hidden');
            
            // Activar botón
            const activeBtn = document.getElementById('tab-' + tabName);
            activeBtn.classList.add('border-indigo-600', 'text-indigo-600');
            activeBtn.classList.remove('border-transparent', 'text-gray-600');
            
            // Recargar datos si es necesario
            if (tabName === 'received') {
                loadReceivedWebhooks();
            }
        }
        
        // Refrescar todos los datos de webhooks
        async function refreshWebhookData() {
            const btn = document.getElementById('refresh-webhooks-btn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Actualizando...';
            
            try {
                await Promise.all([
                    loadWebhookSubscriptions(),
                    loadReceivedWebhooks()
                ]);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
        
        // Cargar suscripciones de webhooks
        async function loadWebhookSubscriptions() {
            const loadingEl = document.getElementById('webhook-subscriptions-loading');
            const contentEl = document.getElementById('webhook-subscriptions-content');
            const errorEl = document.getElementById('webhook-subscriptions-error');
            
            loadingEl.classList.remove('hidden');
            contentEl.classList.add('hidden');
            errorEl.classList.add('hidden');
            
            try {
                const response = await fetch('webhook_manager.php?action=get_subscriptions');
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al cargar suscripciones');
                }
                
                webhookData.subscriptions = data.subscriptions || [];
                webhookData.availableTopics = data.available_topics || {};
                
                renderAvailableTopics();
                renderActiveSubscriptions();
                
                loadingEl.classList.add('hidden');
                contentEl.classList.remove('hidden');
                
            } catch (error) {
                console.error('Error loading subscriptions:', error);
                loadingEl.classList.add('hidden');
                errorEl.classList.remove('hidden');
                document.getElementById('webhook-subscriptions-error-message').textContent = error.message;
            }
        }
        
        // Renderizar topics disponibles
        function renderAvailableTopics() {
            const grid = document.getElementById('available-topics-grid');
            const subscribedTopics = new Set();
            
            // Obtener todos los topics suscritos
            webhookData.subscriptions.forEach(sub => {
                if (sub.topics && Array.isArray(sub.topics)) {
                    sub.topics.forEach(topic => subscribedTopics.add(topic));
                }
            });
            
            let html = '';
            for (const [topic, description] of Object.entries(webhookData.availableTopics)) {
                const isSubscribed = subscribedTopics.has(topic);
                const bgClass = isSubscribed ? 'bg-green-50' : 'bg-white';
                const borderClass = isSubscribed ? 'border-green-300' : 'border-gray-200';
                const statusBadge = isSubscribed 
                    ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded ml-2">Suscrito</span>'
                    : '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded ml-2">No suscrito</span>';
                
                html += `
                    <div class="border ${borderClass} ${bgClass} rounded-lg p-4 hover:shadow-sm transition-all">
                        <div class="flex items-start gap-3">
                            <input 
                                type="checkbox" 
                                id="topic-${topic}"
                                value="${topic}"
                                data-subscribed="${isSubscribed}"
                                onchange="updateTopicSelectionButtons()"
                                class="topic-checkbox mt-1 w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                            />
                            <label for="topic-${topic}" class="flex-1 cursor-pointer">
                                <div class="flex items-center">
                                    <span class="font-semibold text-gray-800">${topic}</span>
                                    ${statusBadge}
                                </div>
                                <div class="text-xs text-gray-600 mt-1">${description}</div>
                            </label>
                            <div class="flex gap-1">
                                ${isSubscribed ? `
                                    <button 
                                        onclick="quickUnsubscribeTopic('${topic}')"
                                        class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded text-xs transition-colors"
                                        title="Desuscribirse"
                                    >
                                        <i class="fas fa-bell-slash"></i>
                                    </button>
                                ` : `
                                    <button 
                                        onclick="quickSubscribeTopic('${topic}')"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded text-xs transition-colors"
                                        title="Suscribirse"
                                    >
                                        <i class="fas fa-bell"></i>
                                    </button>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            grid.innerHTML = html;
            updateTopicSelectionButtons();
        }
        
        // Renderizar suscripciones activas
        function renderActiveSubscriptions() {
            const list = document.getElementById('active-subscriptions-list');
            const noSubsMsg = document.getElementById('no-subscriptions-message');
            const countBadge = document.getElementById('active-subscriptions-count');
            
            countBadge.textContent = webhookData.subscriptions.length;
            
            if (webhookData.subscriptions.length === 0) {
                list.innerHTML = '';
                noSubsMsg.classList.remove('hidden');
                return;
            }
            
            noSubsMsg.classList.add('hidden');
            
            let html = '';
            webhookData.subscriptions.forEach(sub => {
                const topicBadges = (sub.topics || []).map(topic => 
                    `<span class="inline-block bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded mr-1 mb-1">${topic}</span>`
                ).join('');
                
                html += `
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-link text-indigo-600 mr-2"></i>
                                    <span class="font-semibold text-gray-800">Suscripción #${sub.id_third_party_app_webhook}</span>
                                    <span class="ml-2 text-xs ${sub.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'} px-2 py-0.5 rounded">
                                        ${sub.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 mb-2 break-all">
                                    <i class="fas fa-globe mr-1"></i> ${sub.url}
                                </div>
                                <div class="flex flex-wrap items-center">
                                    <span class="text-xs text-gray-600 mr-2">Topics:</span>
                                    ${topicBadges}
                                </div>
                            </div>
                            <div class="flex-shrink-0 ml-4">
                                <button 
                                    onclick="unsubscribeWebhook(${sub.id_third_party_app_webhook})"
                                    class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded text-sm transition-colors"
                                    title="Eliminar suscripción"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            list.innerHTML = html;
        }
        
        // Cargar webhooks recibidos
        async function loadReceivedWebhooks() {
            const loadingEl = document.getElementById('webhook-received-loading');
            const contentEl = document.getElementById('webhook-received-content');
            const errorEl = document.getElementById('webhook-received-error');
            
            loadingEl.classList.remove('hidden');
            contentEl.classList.add('hidden');
            errorEl.classList.add('hidden');
            
            try {
                const response = await fetch('webhook_manager.php?action=get_received&limit=50');
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al cargar webhooks recibidos');
                }
                
                webhookData.receivedWebhooks = data.webhooks || [];
                webhookData.stats = data.stats || {};
                
                renderWebhookStats();
                renderReceivedWebhooks();
                updateTopicFilter();
                
                loadingEl.classList.add('hidden');
                contentEl.classList.remove('hidden');
                
                // Actualizar badge en el tab
                document.getElementById('webhook-received-badge').textContent = webhookData.stats.total || 0;
                
            } catch (error) {
                console.error('Error loading received webhooks:', error);
                loadingEl.classList.add('hidden');
                errorEl.classList.remove('hidden');
                document.getElementById('webhook-received-error-message').textContent = error.message;
            }
        }
        
        // Renderizar estadísticas
        function renderWebhookStats() {
            document.getElementById('stats-total').textContent = webhookData.stats.total || 0;
            document.getElementById('stats-last-24h').textContent = webhookData.stats.last_24h || 0;
            document.getElementById('stats-last-received').textContent = webhookData.stats.last_received || '-';
        }
        
        // Renderizar webhooks recibidos
        function renderReceivedWebhooks(filtered = null) {
            const list = document.getElementById('received-webhooks-list');
            const noWebhooksMsg = document.getElementById('no-webhooks-message');
            
            const webhooks = filtered || webhookData.receivedWebhooks;
            
            if (webhooks.length === 0) {
                list.innerHTML = '';
                noWebhooksMsg.classList.remove('hidden');
                return;
            }
            
            noWebhooksMsg.classList.add('hidden');
            
            let html = '';
            webhooks.forEach(wh => {
                const topicColor = getTopicColor(wh.topic);
                const hasMultipleAttempts = wh.attempts > 1;
                const attemptsClass = hasMultipleAttempts ? 'text-orange-600 font-semibold' : 'text-gray-600';
                
                html += `
                    <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 transition-colors ${hasMultipleAttempts ? 'border-l-4 border-l-orange-500' : ''}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-1">
                                    <span class="inline-block ${topicColor} text-xs font-medium px-2 py-0.5 rounded mr-2">
                                        ${wh.topic}
                                    </span>
                                    <span class="text-xs text-gray-500">${wh.received_at}</span>
                                    ${hasMultipleAttempts ? `
                                        <span class="ml-2 inline-block bg-orange-100 text-orange-700 text-xs font-semibold px-2 py-0.5 rounded">
                                            <i class="fas fa-redo mr-1"></i> ${wh.attempts} intentos
                                        </span>
                                    ` : ''}
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mt-2">
                                    ${wh.resource_id ? `<div><i class="fas fa-hashtag mr-1"></i> Resource: ${wh.resource_id}</div>` : ''}
                                    ${wh.sku ? `<div><i class="fas fa-barcode mr-1"></i> SKU: ${wh.sku}</div>` : ''}
                                    ${wh.sku_simple ? `<div><i class="fas fa-tag mr-1"></i> SKU Simple: ${wh.sku_simple}</div>` : ''}
                                    ${wh.id_parent ? `<div><i class="fas fa-sitemap mr-1"></i> Parent: ${wh.id_parent}</div>` : ''}
                                    ${wh.last_attempt_at && hasMultipleAttempts ? `<div class="${attemptsClass}"><i class="fas fa-clock mr-1"></i> Último: ${wh.last_attempt_at}</div>` : ''}
                                </div>
                            </div>
                            <button 
                                onclick="viewWebhookDetails('${wh.id}')"
                                class="ml-2 text-indigo-600 hover:text-indigo-800 text-sm"
                                title="Ver detalles"
                            >
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            list.innerHTML = html;
        }
        
        // Actualizar filtro de topics
        function updateTopicFilter() {
            const select = document.getElementById('webhook-topic-filter');
            const topics = webhookData.stats.by_topic || {};
            
            let html = '<option value="">Todos</option>';
            for (const [topic, count] of Object.entries(topics)) {
                html += `<option value="${topic}">${topic} (${count})</option>`;
            }
            
            select.innerHTML = html;
        }
        
        // Filtrar webhooks por topic
        function filterWebhooksByTopic() {
            const topic = document.getElementById('webhook-topic-filter').value;
            
            if (!topic) {
                renderReceivedWebhooks();
                return;
            }
            
            const filtered = webhookData.receivedWebhooks.filter(wh => wh.topic === topic);
            renderReceivedWebhooks(filtered);
        }
        
        // Obtener color según el topic
        function getTopicColor(topic) {
            const colors = {
                'new-order': 'bg-green-100 text-green-700',
                'updated-order': 'bg-blue-100 text-blue-700',
                'new-std-order': 'bg-green-100 text-green-700',
                'updated-std-order': 'bg-blue-100 text-blue-700',
                'product-created': 'bg-purple-100 text-purple-700',
                'product-deleted': 'bg-red-100 text-red-700',
                'products-datasheet': 'bg-yellow-100 text-yellow-700',
                'products-offer': 'bg-orange-100 text-orange-700',
            };
            
            return colors[topic] || 'bg-gray-100 text-gray-700';
        }
        
        // Mostrar modal de suscripción
        function showSubscribeModal() {
            const modal = document.getElementById('subscribe-modal');
            const checkboxesContainer = document.getElementById('topics-checkboxes');
            
            // Llenar checkboxes de topics
            let html = '';
            for (const [topic, description] of Object.entries(webhookData.availableTopics)) {
                html += `
                    <label class="flex items-start p-2 hover:bg-gray-50 rounded cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="topics" 
                            value="${topic}"
                            class="mt-1 mr-2 topic-checkbox"
                            onchange="limitTopicSelection()"
                        />
                        <div class="flex-1">
                            <div class="font-medium text-sm">${topic}</div>
                            <div class="text-xs text-gray-600">${description}</div>
                        </div>
                    </label>
                `;
            }
            
            checkboxesContainer.innerHTML = html;
            modal.classList.remove('hidden');
        }
        
        // Cerrar modal de suscripción
        function closeSubscribeModal() {
            document.getElementById('subscribe-modal').classList.add('hidden');
            document.getElementById('subscribe-form').reset();
        }
        
        // Limitar selección de topics
        function limitTopicSelection() {
            const checkboxes = document.querySelectorAll('.topic-checkbox');
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            
            if (checked.length >= 3) {
                // Deshabilitar checkboxes no seleccionados
                checkboxes.forEach(cb => {
                    if (!cb.checked) {
                        cb.disabled = true;
                    }
                });
            } else {
                // Habilitar todos
                checkboxes.forEach(cb => cb.disabled = false);
            }
        }
        
        // Enviar suscripción
        async function submitSubscription(event) {
            event.preventDefault();
            
            const form = event.target;
            const btn = document.getElementById('subscribe-submit-btn');
            const originalHtml = btn.innerHTML;
            
            // Obtener topics seleccionados
            const topics = Array.from(form.querySelectorAll('input[name="topics"]:checked')).map(cb => cb.value);
            
            if (topics.length === 0) {
                alert('Debes seleccionar al menos un topic');
                return;
            }
            
            if (topics.length > 3) {
                alert('Máximo 3 topics permitidos');
                return;
            }
            
            const url = document.getElementById('webhook-url-input').value;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creando...';
            
            try {
                const response = await fetch('webhook_manager.php?action=subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url, topics })
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al crear suscripción');
                }
                
                alert('✅ Suscripción creada exitosamente');
                closeSubscribeModal();
                await loadWebhookSubscriptions();
                
            } catch (error) {
                console.error('Error:', error);
                alert(formatWebhookError(error.message));
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
        
        // Desuscribirse
        async function unsubscribeWebhook(webhookId) {
            if (!confirm('¿Estás seguro de eliminar esta suscripción?')) {
                return;
            }
            
            try {
                const response = await fetch('webhook_manager.php?action=unsubscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ webhook_id: webhookId })
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al eliminar suscripción');
                }
                
                alert('✅ Suscripción eliminada exitosamente');
                await loadWebhookSubscriptions();
                
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al eliminar suscripción: ' + error.message);
            }
        }
        
        // Actualizar estado de botones según selección
        function updateTopicSelectionButtons() {
            const checkboxes = document.querySelectorAll('.topic-checkbox:checked');
            const subscribeBtn = document.getElementById('subscribe-selected-btn');
            const unsubscribeBtn = document.getElementById('unsubscribe-selected-btn');
            
            if (checkboxes.length === 0) {
                subscribeBtn.disabled = true;
                unsubscribeBtn.disabled = true;
                return;
            }
            
            // Verificar si hay topics no suscritos seleccionados
            const hasUnsubscribed = Array.from(checkboxes).some(cb => cb.dataset.subscribed === 'false');
            // Verificar si hay topics suscritos seleccionados
            const hasSubscribed = Array.from(checkboxes).some(cb => cb.dataset.subscribed === 'true');
            
            subscribeBtn.disabled = !hasUnsubscribed;
            unsubscribeBtn.disabled = !hasSubscribed;
        }
        
        // Función auxiliar para formatear errores de API
        function formatWebhookError(error) {
            // Simplemente devolver el error tal cual viene de la API
            return error;
        }
        
        // Suscripción rápida a un topic individual
        async function quickSubscribeTopic(topic) {
            try {
                const webhookUrl = `https://<?php echo $_SERVER['HTTP_HOST']; ?>/modules/prestashopyuju/webhook.php`;
                
                const response = await fetch('webhook_manager.php?action=subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        url: webhookUrl,
                        topics: [topic]
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al suscribirse');
                }
                
                alert(`✅ Suscrito exitosamente al topic: ${topic}`);
                await loadWebhookSubscriptions();
                
            } catch (error) {
                console.error('Error:', error);
                alert(formatWebhookError(error.message));
            }
        }
        
        // Desuscripción rápida de un topic individual
        async function quickUnsubscribeTopic(topic) {
            // Buscar la suscripción que contiene este topic
            const subscription = webhookData.subscriptions.find(sub => 
                sub.topics && sub.topics.includes(topic)
            );
            
            if (!subscription) {
                alert('❌ No se encontró la suscripción para este topic');
                return;
            }
            
            if (!confirm(`¿Desuscribirse del topic "${topic}"?`)) {
                return;
            }
            
            await unsubscribeWebhook(subscription.id_third_party_app_webhook);
        }
        
        // Suscribirse a topics seleccionados (masivo)
        async function subscribeSelectedTopics() {
            const checkboxes = document.querySelectorAll('.topic-checkbox:checked');
            const topicsToSubscribe = Array.from(checkboxes)
                .filter(cb => cb.dataset.subscribed === 'false')
                .map(cb => cb.value);
            
            if (topicsToSubscribe.length === 0) {
                alert('⚠️ No hay topics no suscritos seleccionados');
                return;
            }
            
            if (topicsToSubscribe.length > 3) {
                alert('⚠️ Máximo 3 topics por suscripción. Selecciona hasta 3 topics.');
                return;
            }
            
            if (!confirm(`¿Suscribirse a ${topicsToSubscribe.length} topic(s)?`)) {
                return;
            }
            
            try {
                const webhookUrl = `https://<?php echo $_SERVER['HTTP_HOST']; ?>/modules/prestashopyuju/webhook.php`;
                
                const response = await fetch('webhook_manager.php?action=subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        url: webhookUrl,
                        topics: topicsToSubscribe
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al suscribirse');
                }
                
                alert(`✅ Suscrito exitosamente a ${topicsToSubscribe.length} topic(s)`);
                
                // Desmarcar checkboxes
                checkboxes.forEach(cb => cb.checked = false);
                
                await loadWebhookSubscriptions();
                
            } catch (error) {
                console.error('Error:', error);
                alert(formatWebhookError(error.message));
            }
        }
        
        // Desuscribirse de topics seleccionados (masivo)
        async function unsubscribeSelectedTopics() {
            const checkboxes = document.querySelectorAll('.topic-checkbox:checked');
            const topicsToUnsubscribe = Array.from(checkboxes)
                .filter(cb => cb.dataset.subscribed === 'true')
                .map(cb => cb.value);
            
            if (topicsToUnsubscribe.length === 0) {
                alert('⚠️ No hay topics suscritos seleccionados');
                return;
            }
            
            if (!confirm(`¿Desuscribirse de ${topicsToUnsubscribe.length} topic(s)?`)) {
                return;
            }
            
            try {
                // Buscar todas las suscripciones que contienen estos topics
                const subscriptionsToDelete = new Set();
                
                topicsToUnsubscribe.forEach(topic => {
                    const subscription = webhookData.subscriptions.find(sub => 
                        sub.topics && sub.topics.includes(topic)
                    );
                    if (subscription) {
                        subscriptionsToDelete.add(subscription.id_third_party_app_webhook);
                    }
                });
                
                if (subscriptionsToDelete.size === 0) {
                    alert('❌ No se encontraron suscripciones para eliminar');
                    return;
                }
                
                // Eliminar cada suscripción
                let successCount = 0;
                let errorCount = 0;
                
                for (const webhookId of subscriptionsToDelete) {
                    try {
                        const response = await fetch('webhook_manager.php?action=unsubscribe', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ webhook_id: webhookId })
                        });
                        
                        const data = await response.json();
                        
                        if (response.ok && data.success) {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    } catch (error) {
                        errorCount++;
                    }
                }
                
                if (successCount > 0) {
                    alert(`✅ ${successCount} suscripción(es) eliminada(s)${errorCount > 0 ? ` (${errorCount} errores)` : ''}`);
                } else {
                    alert(`❌ Error al eliminar suscripciones`);
                }
                
                // Desmarcar checkboxes
                checkboxes.forEach(cb => cb.checked = false);
                
                await loadWebhookSubscriptions();
                
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al desuscribirse: ' + error.message);
            }
        }
        
        // Limpiar historial de webhooks
        async function clearWebhookHistory() {
            if (!confirm('¿Estás seguro de eliminar todo el historial de webhooks recibidos?')) {
                return;
            }
            
            try {
                const response = await fetch('webhook_manager.php?action=clear_received', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al limpiar historial');
                }
                
                alert('✅ Historial eliminado exitosamente');
                await loadReceivedWebhooks();
                
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al limpiar historial: ' + error.message);
            }
        }
        
        // Ver detalles de un webhook
        function viewWebhookDetails(webhookId) {
            const webhook = webhookData.receivedWebhooks.find(wh => wh.id === webhookId);
            if (!webhook) return;
            
            const modal = document.getElementById('webhookDetailsModal');
            const content = document.getElementById('webhookDetailsContent');
            
            // Almacenar webhook actual para copiar
            window.currentWebhookDetails = webhook;
            
            // Crear HTML con los detalles del webhook
            let html = '<div class="space-y-4">';
            
            // Información básica
            html += `
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-lg mb-3 text-gray-800">
                        <i class="fas fa-info-circle text-blue-600"></i> Información Básica
                    </h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">ID:</span>
                            <code class="ml-2 text-indigo-600">${webhook.id}</code>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Topic:</span>
                            <code class="ml-2 text-purple-600">${webhook.topic}</code>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Resource ID:</span>
                            <code class="ml-2 text-green-600">${webhook.resource_id || 'N/A'}</code>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Primera recepción:</span>
                            <span class="ml-2">${webhook.received_at}</span>
                        </div>
                        ${webhook.attempts > 1 ? `
                        <div class="col-span-2 bg-orange-50 border border-orange-200 rounded p-2 mt-2">
                            <span class="font-medium text-orange-700">
                                <i class="fas fa-redo mr-1"></i> Total de intentos: ${webhook.attempts}
                            </span>
                            ${webhook.last_attempt_at ? `<span class="ml-3 text-sm text-orange-600">Último: ${webhook.last_attempt_at}</span>` : ''}
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            // Historial de intentos (si hay múltiples)
            if (webhook.attempts > 1 && webhook.attempt_history && webhook.attempt_history.length > 0) {
                html += `
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-lg mb-3 text-gray-800">
                            <i class="fas fa-history text-orange-600"></i> Historial de Intentos
                        </h4>
                        <div class="bg-white rounded border border-orange-200">
                            <table class="min-w-full text-xs">
                                <thead class="bg-orange-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">#</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">Timestamp</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">Webhook ID</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">Intentos Yuju</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${webhook.attempt_history.map((attempt, index) => `
                                        <tr class="border-t border-orange-100 hover:bg-orange-50">
                                            <td class="px-3 py-2">${index + 1}</td>
                                            <td class="px-3 py-2 font-mono">${attempt.timestamp}</td>
                                            <td class="px-3 py-2 font-mono text-indigo-600">${attempt.webhook_id || 'N/A'}</td>
                                            <td class="px-3 py-2">${attempt.yuju_attempts}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            // Headers
            html += `
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-lg mb-3 text-gray-800">
                        <i class="fas fa-list text-blue-600"></i> Headers${webhook.attempts > 1 ? ' <span class="text-sm font-normal text-orange-600">(Último intento)</span>' : ''}
                    </h4>
                    <div class="bg-white p-3 rounded border border-blue-200 font-mono text-xs overflow-x-auto">
                        <pre>${JSON.stringify(webhook.headers, null, 2)}</pre>
                    </div>
                </div>
            `;
            
            // Payload - detectar si es string y parsearlo
            let payloadFormatted;
            let hasOrderDetails = false;
            let fetchStatus = null;
            
            try {
                // Si payload es string, parsearlo
                const payloadData = typeof webhook.payload === 'string' 
                    ? JSON.parse(webhook.payload) 
                    : webhook.payload;
                
                // Verificar si tiene datos de orden completos
                hasOrderDetails = payloadData.order_details || payloadData.items || payloadData.customer;
                fetchStatus = payloadData.fetch_status;
                
                payloadFormatted = JSON.stringify(payloadData, null, 2);
            } catch (e) {
                // Si falla el parse, mostrar como está
                payloadFormatted = typeof webhook.payload === 'string' 
                    ? webhook.payload 
                    : JSON.stringify(webhook.payload, null, 2);
            }
            
            // Payload
            html += `
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="font-semibold text-lg text-gray-800">
                            <i class="fas fa-file-code text-green-600"></i> Payload${webhook.attempts > 1 ? ' <span class="text-sm font-normal text-orange-600">(Último intento)</span>' : ''}
                        </h4>
                        <div class="flex space-x-2">
                            ${(!hasOrderDetails && webhook.topic && webhook.topic.includes('order') && webhook.resource_id) ? `
                                <button 
                                    onclick="fetchOrderDetails('${webhook.id}', '${webhook.resource_id}', '${webhook.headers['x-yuju-id-channel'] || webhook.headers['X-YUJU-ID-CHANNEL'] || ''}')"
                                    id="fetchOrderBtn_${webhook.id}"
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg transition-colors flex items-center space-x-1.5">
                                    <i class="fas fa-download"></i>
                                    <span>Descargar Detalles</span>
                                </button>
                            ` : ''}
                            ${(hasOrderDetails && webhook.topic && webhook.topic.includes('order') && webhook.resource_id) ? `
                                <button 
                                    onclick="createOrderInPrestaShop('${webhook.id}', '${webhook.resource_id}')"
                                    id="createOrderBtn_${webhook.id}"
                                    class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs rounded-lg transition-colors flex items-center space-x-1.5">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Crear Orden en PrestaShop</span>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    ${webhook.attempts > 1 ? `
                        <div class="bg-orange-100 border border-orange-300 rounded px-3 py-2 mb-3 text-sm text-orange-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Este payload corresponde al último intento recibido (${webhook.last_attempt_at || 'fecha desconocida'})
                        </div>
                    ` : ''}
                    ${(!hasOrderDetails && webhook.topic && webhook.topic.includes('order')) ? `
                        <div class="bg-yellow-100 border border-yellow-300 rounded px-3 py-2 mb-3 text-sm text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No se encontraron detalles completos de la orden. Usa el botón "Descargar Detalles" para obtenerlos desde la API.
                        </div>
                    ` : ''}
                    ${(fetchStatus === 'failed' || fetchStatus === 'exception') ? `
                        <div class="bg-red-100 border border-red-300 rounded px-3 py-2 mb-3 text-sm text-red-800">
                            <i class="fas fa-times-circle mr-1"></i>
                            Error al obtener detalles: ${fetchStatus}. Puedes reintentar con el botón "Descargar Detalles".
                        </div>
                    ` : ''}
                    
                    <!-- Área de progreso para crear orden -->
                    <div id="orderCreationProgress_${webhook.id}" class="hidden mb-3 bg-blue-50 border border-blue-300 rounded p-3">
                        <h5 class="font-semibold text-sm text-blue-900 mb-2">
                            <i class="fas fa-tasks mr-1"></i> Progreso de Creación de Orden
                        </h5>
                        <div id="progressSteps_${webhook.id}" class="space-y-2 text-xs">
                            <!-- Los pasos se agregarán dinámicamente aquí -->
                        </div>
                    </div>
                    
                    <div class="bg-white p-3 rounded border border-green-200 font-mono text-xs overflow-x-auto max-h-96" id="payloadContent_${webhook.id}">
                        <pre>${payloadFormatted}</pre>
                    </div>
                </div>
            `;
            
            html += '</div>';
            
            content.innerHTML = html;
            modal.classList.remove('hidden');
        }
        
        // Cerrar modal de detalles
        function closeWebhookDetailsModal() {
            document.getElementById('webhookDetailsModal').classList.add('hidden');
        }
        
        // Copiar detalles del webhook al portapapeles
        function copyWebhookDetails() {
            if (!window.currentWebhookDetails) return;
            
            const text = JSON.stringify(window.currentWebhookDetails, null, 2);
            navigator.clipboard.writeText(text).then(() => {
                // Mostrar mensaje de éxito
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check mr-1"></i> ¡Copiado!';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            }).catch(err => {
                alert('Error al copiar: ' + err);
            });
        }
        
        // Descargar detalles de orden desde la API
        async function fetchOrderDetails(webhookId, orderId, channelId) {
            const btn = document.getElementById('fetchOrderBtn_' + webhookId);
            const payloadContent = document.getElementById('payloadContent_' + webhookId);
            
            if (!btn || !payloadContent) return;
            
            // Deshabilitar botón y mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Descargando...';
            
            try {
                // Hacer petición al endpoint que obtiene los detalles
                const response = await fetch('fetch_order_details.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        webhook_id: webhookId,
                        order_id: orderId,
                        channel_id: channelId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Actualizar el payload en el modal
                    const formattedData = JSON.stringify(result.order_data, null, 2);
                    payloadContent.innerHTML = `<pre>${formattedData}</pre>`;
                    
                    // Remover alertas de advertencia si existen
                    const warnings = payloadContent.parentElement.querySelectorAll('.bg-yellow-100, .bg-red-100');
                    warnings.forEach(warning => warning.remove());
                    
                    // Actualizar botón a éxito
                    btn.innerHTML = '<i class="fas fa-check mr-1"></i> ¡Descargado!';
                    btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    btn.classList.add('bg-green-600', 'hover:bg-green-700');
                    
                    // Actualizar el webhook en memoria
                    const webhook = webhookData.receivedWebhooks.find(wh => wh.id === webhookId);
                    if (webhook) {
                        webhook.payload = result.order_data;
                    }
                    
                    // Mostrar mensaje de éxito (sin recargar página)
                    const successDiv = document.createElement('div');
                    successDiv.className = 'mb-3 bg-green-100 border border-green-300 rounded px-3 py-2 text-sm text-green-800';
                    successDiv.innerHTML = `
                        <div class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-0.5 flex-shrink-0"></i>
                            <div>
                                <strong>¡Datos descargados exitosamente!</strong><br>
                                Se obtuvieron ${result.items_count || 0} items de la orden.
                                Los cambios ya están guardados.
                            </div>
                        </div>
                    `;
                    payloadContent.parentElement.insertBefore(successDiv, payloadContent);
                    
                    // Ocultar botón de descarga (ya no es necesario)
                    btn.style.display = 'none';
                    
                    // Remover mensaje de éxito después de 5 segundos
                    setTimeout(() => {
                        successDiv.remove();
                    }, 5000);
                    
                } else {
                    throw new Error(result.message || 'Error desconocido');
                }
                
            } catch (error) {
                console.error('Error fetching order details:', error);
                
                // Mostrar error en el botón
                btn.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-red-600', 'hover:bg-red-700');
                
                // Determinar el mensaje de error apropiado
                let errorMessage = error.message || 'Error desconocido al descargar detalles';
                let actionLink = '';
                
                if (error.message && (error.message.includes('autorización') || error.message.includes('OAuth'))) {
                    actionLink = '<br><a href="../admin728ikh1zgdznq8wwnf2/index.php?controller=AdminYujuConfiguration&token=1992ec4a503be381d66e8cd7142bc9a7" class="underline text-blue-700 hover:text-blue-900" target="_blank">Ir a Configuración →</a>';
                }
                
                // Mostrar mensaje de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mt-3 bg-red-100 border border-red-300 rounded px-3 py-2 text-sm text-red-800';
                errorDiv.innerHTML = `
                    <div class="flex items-start">
                        <i class="fas fa-times-circle mr-2 mt-0.5 flex-shrink-0"></i>
                        <div>
                            ${errorMessage}
                            ${actionLink}
                        </div>
                    </div>
                `;
                payloadContent.parentElement.insertBefore(errorDiv, payloadContent);
                
                // Restaurar botón después de 3 segundos
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo mr-1"></i> Reintentar';
                    btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 3000);
            }
        }
        
        // Crear orden en PrestaShop desde webhook de Yuju
        async function createOrderInPrestaShop(webhookId, orderId) {
            const btn = document.getElementById('createOrderBtn_' + webhookId);
            const progressContainer = document.getElementById('orderCreationProgress_' + webhookId);
            const progressSteps = document.getElementById('progressSteps_' + webhookId);
            
            if (!btn || !progressContainer || !progressSteps) return;
            
            // Obtener datos del webhook
            const webhook = webhookData.receivedWebhooks.find(wh => wh.id === webhookId);
            if (!webhook) {
                alert('No se encontró el webhook');
                return;
            }
            
            // Deshabilitar botón y mostrar progreso
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creando...';
            progressContainer.classList.remove('hidden');
            progressSteps.innerHTML = '';
            
            // Función para agregar paso
            function addStep(message, status = 'loading', details = null, resourceType = null, resourceId = null) {
                const stepDiv = document.createElement('div');
                stepDiv.className = 'py-1';
                
                let icon = '<i class="fas fa-spinner fa-spin text-blue-600"></i>';
                let textClass = 'text-gray-700';
                let bgClass = '';
                
                if (status === 'success') {
                    icon = '<i class="fas fa-check-circle text-green-600"></i>';
                    textClass = 'text-green-700';
                } else if (status === 'error') {
                    icon = '<i class="fas fa-times-circle text-red-600"></i>';
                    textClass = 'text-red-700';
                    bgClass = 'bg-red-50';
                } else if (status === 'warning') {
                    icon = '<i class="fas fa-exclamation-triangle text-yellow-600"></i>';
                    textClass = 'text-yellow-700';
                    bgClass = 'bg-yellow-50';
                }
                
                // Si hay error/warning con detalles, crear acordeón
                const hasErrorDetails = (status === 'error' || status === 'warning') && details && details !== 'Error desconocido';
                const accordionId = 'accordion_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                
                // Botones de acción (solo para recursos creados exitosamente)
                let actionButtons = '';
                if (status === 'success' && resourceId && resourceType) {
                    actionButtons = `
                        <div class="flex items-center space-x-1 ml-2">
                            <button onclick="deleteResource('${resourceType}', ${resourceId})" 
                                    class="px-2 py-0.5 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded"
                                    title="Eliminar ${resourceType} #${resourceId}">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                            <a href="${getResourceUrl(resourceType, resourceId)}" target="_blank"
                               class="px-2 py-0.5 text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded"
                               title="Ver ${resourceType} #${resourceId}">
                                <i class="fas fa-external-link-alt"></i> Ver
                            </a>
                        </div>
                    `;
                }
                
                if (hasErrorDetails) {
                    // Acordeón con detalles de error
                    stepDiv.innerHTML = `
                        <div class="${bgClass} border border-${status === 'error' ? 'red' : 'yellow'}-200 rounded p-2">
                            <div class="flex items-center justify-between cursor-pointer" onclick="document.getElementById('${accordionId}').classList.toggle('hidden')">
                                <div class="flex items-center space-x-2 flex-1">
                                    <span class="flex-shrink-0">${icon}</span>
                                    <span class="${textClass} font-medium">${message}</span>
                                </div>
                                <button class="text-xs ${textClass} hover:underline">
                                    <i class="fas fa-chevron-down"></i> Ver Detalles
                                </button>
                            </div>
                            <div id="${accordionId}" class="hidden mt-2 pl-6 text-sm text-gray-700 bg-white p-2 rounded border border-gray-200">
                                <div class="font-semibold mb-1">Detalles del error:</div>
                                <pre class="text-xs whitespace-pre-wrap break-words">${details}</pre>
                            </div>
                        </div>
                    `;
                } else {
                    // Formato normal (sin acordeón)
                    let displayDetails = details && details !== 'Error desconocido' ? ` <span class="text-xs text-gray-500">(${details})</span>` : '';
                    
                    stepDiv.innerHTML = `
                        <div class="flex items-start justify-between hover:bg-gray-50 px-2 rounded ${bgClass}">
                            <div class="flex items-start space-x-2 flex-1">
                                <span class="flex-shrink-0 mt-0.5">${icon}</span>
                                <span class="${textClass}">${message}${displayDetails}</span>
                            </div>
                            ${actionButtons}
                        </div>
                    `;
                }
                
                progressSteps.appendChild(stepDiv);
                return stepDiv;
            }
            
            // Función para obtener URL del recurso en admin
            function getResourceUrl(resourceType, resourceId) {
                const adminPath = '/admin'; // Ajustar según tu instalación
                const baseUrl = window.location.origin + adminPath;
                
                switch(resourceType) {
                    case 'customer':
                        return baseUrl + '/index.php?controller=AdminCustomers&id_customer=' + resourceId + '&viewcustomer';
                    case 'address':
                        return baseUrl + '/index.php?controller=AdminAddresses&id_address=' + resourceId + '&updateaddress';
                    case 'cart':
                        return baseUrl + '/index.php?controller=AdminCarts&id_cart=' + resourceId + '&viewcart';
                    case 'order':
                        return baseUrl + '/index.php?controller=AdminOrders&id_order=' + resourceId + '&vieworder';
                    default:
                        return '#';
                }
            }
            
            try {
                addStep('Iniciando creación de orden...');
                
                // Hacer petición al endpoint
                const response = await fetch('create_order_from_webhook.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        webhook_id: webhookId,
                        order_id: orderId
                    })
                });
                
                const result = await response.json();
                
                // Limpiar pasos y mostrar progreso detallado
                progressSteps.innerHTML = '';
                
                if (result.steps && Array.isArray(result.steps)) {
                    result.steps.forEach(step => {
                        // Extraer ID del mensaje si existe
                        let resourceType = null;
                        let resourceId = null;
                        
                        // Para recursos con ID en details
                        if (step.details && step.details.includes('ID:')) {
                            resourceId = step.details.match(/ID:\s*(\d+)/)?.[1];
                            
                            // Determinar tipo de recurso según el mensaje
                            if (step.message.includes('Cliente')) resourceType = 'customer';
                            else if (step.message.includes('envío')) resourceType = 'address';
                            else if (step.message.includes('facturación')) resourceType = 'address';
                            else if (step.message.includes('Carrito')) resourceType = 'cart';
                            else if (step.message.includes('Orden creada')) resourceType = 'order';
                        }
                        
                        // Pasar details completo (puede ser ID o mensaje de error)
                        addStep(step.message, step.status, step.details, resourceType, resourceId);
                    });
                }
                
                if (result.success) {
                    // Éxito total
                    btn.innerHTML = '<i class="fas fa-check mr-1"></i> ¡Orden Creada!';
                    btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                    btn.classList.add('bg-green-600', 'hover:bg-green-700');
                    
                    // Agregar mensaje final con enlace y botones de eliminación
                    setTimeout(() => {
                        const finalStep = document.createElement('div');
                        finalStep.className = 'mt-3 p-3 bg-green-100 border border-green-300 rounded';
                        finalStep.innerHTML = `
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-green-800 font-semibold">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Orden #${result.prestashop_order_id || 'N/A'} creada exitosamente
                                    </span>
                                    ${result.order_url ? `
                                        <a href="${result.order_url}" target="_blank" 
                                           class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                                            Ver Orden <i class="fas fa-external-link-alt ml-1"></i>
                                        </a>
                                    ` : ''}
                                </div>
                                ${result.prestashop_order_id ? `
                                    <div class="flex gap-2 pt-2 border-t border-green-200">
                                        <button onclick="deleteOrder(${result.prestashop_order_id}, false, '${webhookId}')" 
                                                class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded flex items-center">
                                            <i class="fas fa-trash mr-1"></i> Borrar Orden
                                        </button>
                                        <button onclick="deleteOrder(${result.prestashop_order_id}, true, '${webhookId}')" 
                                                class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-sm rounded flex items-center">
                                            <i class="fas fa-trash-alt mr-1"></i> Borrar Todo (Cliente + Direcciones + Carrito)
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                        progressSteps.appendChild(finalStep);
                    }, 500);
                    
                } else {
                    throw new Error(result.message || 'Error al crear la orden');
                }
                
            } catch (error) {
                console.error('Error creating order:', error);
                
                // Mostrar error
                addStep('❌ ' + error.message, 'error');
                
                btn.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error';
                btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                btn.classList.add('bg-red-600', 'hover:bg-red-700');
                
                // Restaurar botón después de 3 segundos
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo mr-1"></i> Reintentar Creación';
                    btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                    btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                }, 3000);
            }
        }
        
        // Función global para eliminar recursos
        async function deleteResource(resourceType, resourceId) {
            if (!confirm(`¿Estás seguro de eliminar este ${resourceType} #${resourceId}?`)) {
                return;
            }
            
            try {
                // Aquí implementarías el endpoint para eliminar recursos
                alert('Función de eliminación en desarrollo. Recurso: ' + resourceType + ' #' + resourceId);
            } catch (error) {
                alert('Error al eliminar: ' + error.message);
            }
        }
        
        // Función para eliminar orden
        async function deleteOrder(orderId, deleteAll, webhookId) {
            const actionText = deleteAll 
                ? 'eliminar COMPLETAMENTE esta orden (incluyendo cliente, direcciones y carrito)'
                : 'eliminar esta orden (el cliente y direcciones se preservarán)';
                
            if (!confirm(`¿Estás seguro de ${actionText}?\n\nOrden ID: ${orderId}\n\nEsta acción NO se puede deshacer.`)) {
                return;
            }
            
            try {
                // Mostrar indicador de carga
                const progressDiv = document.getElementById('orderCreationProgress_' + webhookId);
                if (progressDiv) {
                    const loadingDiv = document.createElement('div');
                    loadingDiv.id = 'deletionProgress_' + orderId;
                    loadingDiv.className = 'mt-3 p-3 bg-yellow-100 border border-yellow-300 rounded text-sm';
                    loadingDiv.innerHTML = `
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Eliminando orden${deleteAll ? ' y recursos asociados' : ''}...
                    `;
                    progressDiv.appendChild(loadingDiv);
                }
                
                const response = await fetch('delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        delete_all: deleteAll
                    })
                });
                
                const result = await response.json();
                
                // Eliminar indicador de carga
                const loadingDiv = document.getElementById('deletionProgress_' + orderId);
                if (loadingDiv) {
                    loadingDiv.remove();
                }
                
                if (result.success) {
                    // Mostrar mensaje de éxito
                    const successDiv = document.createElement('div');
                    successDiv.className = 'mt-3 p-3 bg-green-100 border border-green-300 rounded text-sm';
                    
                    let deletedItems = ['Orden'];
                    if (deleteAll && result.details) {
                        if (result.details.customer_deleted) deletedItems.push('Cliente');
                        if (result.details.addresses_deleted && result.details.addresses_deleted.length > 0) {
                            deletedItems.push(`${result.details.addresses_deleted.length} Dirección(es)`);
                        }
                        if (result.details.cart_deleted) deletedItems.push('Carrito');
                    }
                    
                    successDiv.innerHTML = `
                        <div class="text-green-800">
                            <i class="fas fa-check-circle mr-2"></i>
                            <strong>Eliminación exitosa</strong>
                            <div class="mt-2">Eliminado: ${deletedItems.join(', ')}</div>
                            ${result.errors && result.errors.length > 0 ? `
                                <div class="mt-2 text-yellow-800">
                                    <strong>Advertencias:</strong>
                                    <ul class="list-disc list-inside ml-2">
                                        ${result.errors.map(err => `<li>${err}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    if (progressDiv) {
                        progressDiv.appendChild(successDiv);
                    }
                    
                    // Recargar la página después de 2 segundos
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                    
                } else {
                    throw new Error(result.message || 'Error desconocido al eliminar la orden');
                }
                
            } catch (error) {
                console.error('Error deleting order:', error);
                
                // Mostrar error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mt-3 p-3 bg-red-100 border border-red-300 rounded text-sm text-red-800';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Error al eliminar:</strong> ${error.message}
                `;
                
                const progressDiv = document.getElementById('orderCreationProgress_' + webhookId);
                if (progressDiv) {
                    progressDiv.appendChild(errorDiv);
                }
            }
        }
        
        // Auto-reintentar último error al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Auto-sync script iniciado');
            
            // PASO 1: Verificar si hay error en la última descarga para reintentar
            const lastErrorRow = document.querySelector('tr[data-download-id] td .bg-red-100');
            console.log('Buscando errores... encontrado:', lastErrorRow ? 'SÍ' : 'NO');
            
            if (lastErrorRow) {
                // Buscar el ID de descarga más reciente con error
                const errorRow = lastErrorRow.closest('tr[data-download-id]');
                if (errorRow) {
                    const downloadId = errorRow.getAttribute('data-download-id');
                    const retryBtn = document.getElementById('retry-btn-' + downloadId);
                    
                    console.log('Error encontrado en descarga ID:', downloadId);
                    console.log('Botón de reintentar existe:', retryBtn ? 'SÍ' : 'NO');
                    
                    // Solo reintentar si existe el botón de retry (error de descarga)
                    // Si no existe el botón, es otro tipo de error y continuamos con auto-sync
                    if (retryBtn && downloadId) {
                        console.log('🔄 Descarga con error detectada (ID: ' + downloadId + '), reintentando automáticamente...');
                        
                        // Verificar si es realmente la última descarga (ID más alto)
                        const allRows = document.querySelectorAll('tr[data-download-id]');
                        let maxId = 0;
                        allRows.forEach(row => {
                            const id = parseInt(row.getAttribute('data-download-id'));
                            if (id > maxId) maxId = id;
                        });
                        
                        // Solo reintentar si es la última descarga
                        if (parseInt(downloadId) === maxId) {
                            // Esperar 2 segundos antes de reintentar (para que el usuario vea la página)
                            setTimeout(() => {
                                console.log('▶️ Ejecutando reintento automático de descarga...');
                                retryDownload(downloadId);
                            }, 2000);
                            return; // Terminar aquí, no ejecutar auto-sync
                        }
                    } else {
                        console.log('No hay botón de reintentar, continuando con verificación de auto-sync...');
                    }
                }
            }
            
            // PASO 2: Verificar si la última descarga exitosa necesita sincronización automática
            console.log('✓ No hay errores pendientes, verificando auto-sincronización...');
            
            // Buscar la última descarga exitosa (ID más alto con estado completed)
            const allRows = document.querySelectorAll('tr[data-download-id]');
            console.log('📊 Total de filas encontradas:', allRows.length);
            
            let latestSuccessfulId = 0;
            let latestSuccessfulRow = null;
            
            allRows.forEach(row => {
                const id = parseInt(row.getAttribute('data-download-id'));
                const statusCell = row.querySelector('td:nth-child(3)');
                const statusBadge = statusCell ? statusCell.querySelector('span') : null;
                
                console.log('Fila ID:', id, 'Badge:', statusBadge ? statusBadge.className : 'no badge');
                
                // Verificar que tenga badge de éxito (verde con "Completado" o "Recuperado")
                if (statusBadge && (statusBadge.classList.contains('bg-green-100') || statusBadge.textContent.includes('Completado') || statusBadge.textContent.includes('Recuperado'))) {
                    console.log('  ✓ Descarga exitosa encontrada: ID', id);
                    if (id > latestSuccessfulId) {
                        latestSuccessfulId = id;
                        latestSuccessfulRow = row;
                    }
                }
            });
            
            if (latestSuccessfulId > 0 && latestSuccessfulRow) {
                console.log('📦 Última descarga exitosa encontrada: ID ' + latestSuccessfulId);
                
                // Verificar si ya existe una sincronización para esta descarga
                // Buscar en ps_yuju_sync_logs si hay registros con sync_direction = 'prestashop_to_yuju'
                // y details contiene "source_download_id": latestSuccessfulId
                
                fetch('check_sync_status.php?download_id=' + latestSuccessfulId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_sync) {
                            console.log('✓ Ya existe sincronización para descarga #' + latestSuccessfulId + ' (' + data.sync_count + ' veces)');
                        } else {
                            console.log('⚡ No hay sincronización para descarga #' + latestSuccessfulId + ', ejecutando auto-sync...');
                            
                            // Esperar 3 segundos y ejecutar sincronización automática
                            setTimeout(() => {
                                const syncBtn = document.getElementById('sync-btn-' + latestSuccessfulId);
                                if (syncBtn && !syncBtn.disabled) {
                                    console.log('▶️ Ejecutando primera sincronización automática...');
                                    
                                    // Mostrar notificación visual
                                    const notification = document.createElement('div');
                                    notification.className = 'fixed top-4 right-4 bg-purple-600 text-white px-6 py-4 rounded-lg shadow-lg z-50 animate-bounce';
                                    notification.innerHTML = `
                                        <div class="flex items-center">
                                            <i class="fas fa-sync-alt fa-spin mr-3 text-xl"></i>
                                            <div>
                                                <div class="font-bold">Auto-Sincronización Iniciada</div>
                                                <div class="text-sm">Sincronizando descarga #${latestSuccessfulId}...</div>
                                            </div>
                                        </div>
                                    `;
                                    document.body.appendChild(notification);
                                    
                                    // Ejecutar sincronización
                                    syncWithYuju(latestSuccessfulId);
                                    
                                    // Remover notificación después de 4 segundos
                                    setTimeout(() => {
                                        notification.remove();
                                    }, 4000);
                                }
                            }, 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Error verificando estado de sincronización:', error);
                    });
            } else {
                console.log('ℹ️ No se encontró descarga exitosa para auto-sincronizar');
            }
        });
        </script>
        
        <?php endif; ?>
    </body>
    </html>
        <?php
    } else {
        echo "==============================================\n";
        echo "  CRON Principal completado exitosamente\n";
        echo "==============================================\n";
        
        // Procesar cola en modo CLI
        try {
            require_once $root_path . '/classes/YujuSyncQueue.php';
            $sync_queue = new YujuSyncQueue();
            $batch_size = (int) YujuConfig::get('YUJU_BATCH_SIZE', 100);
            $queue_stats = $sync_queue->getQueueStats();
            
            echo "\n[COLA] Estadísticas de sincronización\n";
            echo "  Total: " . $queue_stats['total'] . "\n";
            echo "  Pendientes: " . $queue_stats['pending'] . "\n";
            echo "  Procesando: " . $queue_stats['processing'] . "\n";
            echo "  Completados: " . $queue_stats['completed'] . "\n";
            echo "  Fallidos: " . $queue_stats['failed'] . "\n";
            
            if ($queue_stats['pending'] > 0) {
                echo "\n[COLA] Procesando lote de $batch_size productos...\n";
                
                $process_start = microtime(true);
                $batch_stats = $sync_queue->processBatch($batch_size);
                $process_duration = microtime(true) - $process_start;
                
                $logger->log('info', 'Cola procesada', [
                    'batch_size' => $batch_size,
                    'stats' => $batch_stats,
                    'duration_seconds' => round($process_duration, 2)
                ]);
                
                echo "\n[COLA] Resultados del procesamiento:\n";
                echo "  Procesados: " . $batch_stats['processed'] . "\n";
                echo "  Exitosos: " . $batch_stats['success'] . "\n";
                echo "  Fallidos: " . $batch_stats['failed'] . "\n";
                echo "  Duración: " . round($process_duration, 2) . " segundos\n";
            } else {
                echo "\n[COLA] No hay productos pendientes en la cola\n";
            }
            
            // Limpiar registros completados antiguos (más de 7 días)
            $sync_queue->cleanOldCompleted(7);
            
        } catch (Exception $e) {
            $logger->log('error', 'Error procesando cola de sincronización', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            echo "\n[ERROR] Cola: " . $e->getMessage() . "\n";
        }
    }
    
    $logger->log('info', 'CRON principal ejecutado exitosamente');
    exit(0);
    
} catch (Exception $e) {
    $error_message = 'Error en CRON principal: ' . $e->getMessage();
    $logger->log('error', $error_message, ['trace' => $e->getTraceAsString()]);
    
    if ($is_web) {
        ?>
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <div class="bg-red-100 border-l-4 border-red-500 rounded-lg shadow-lg p-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl mr-4"></i>
                        <div>
                            <h2 class="text-2xl font-bold text-red-800 mb-2">Error Crítico</h2>
                            <p class="text-red-700"><?php echo htmlspecialchars($e->getMessage()); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
    } else {
        echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    }
    exit(1);
}
