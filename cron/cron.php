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
