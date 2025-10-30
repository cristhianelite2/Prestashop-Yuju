<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspector de JSON Yuju</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-search text-blue-500 mr-3"></i>
                    Inspector de JSON Yuju
                </h1>
                <p class="text-gray-600 mt-2">Descarga y analiza registros de URLs de CloudFront</p>
            </div>

            <?php
            // Cargar PrestaShop
            $root_path = dirname(__FILE__, 2);
            require_once $root_path . '/../../config/config.inc.php';
            require_once $root_path . '/../../init.php';

            $cloudfront_url = isset($_POST['url']) ? trim($_POST['url']) : '';
            $show_results = false;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cloudfront_url)) {
                $show_results = true;
                
                echo '<div class="bg-white rounded-lg shadow-lg p-6 mb-6">';
                echo '<h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">';
                echo '<i class="fas fa-download text-green-500 mr-2"></i>';
                echo 'Descargando JSON...';
                echo '</h2>';
                
                echo '<div class="mb-4">';
                echo '<p class="text-sm text-gray-600">URL:</p>';
                echo '<code class="block bg-gray-100 p-2 rounded text-xs break-all">' . htmlspecialchars($cloudfront_url) . '</code>';
                echo '</div>';

                // Descargar el JSON
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $cloudfront_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 120,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ]);

                $json_content = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $download_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
                $curl_error = curl_error($ch);
                curl_close($ch);

                echo '<div class="grid grid-cols-2 gap-4 mb-4">';
                echo '<div class="bg-blue-50 p-3 rounded">';
                echo '<p class="text-sm text-gray-600">Código HTTP</p>';
                echo '<p class="text-2xl font-bold ' . ($http_code === 200 ? 'text-green-600' : 'text-red-600') . '">' . $http_code . '</p>';
                echo '</div>';
                echo '<div class="bg-blue-50 p-3 rounded">';
                echo '<p class="text-sm text-gray-600">Tamaño Descargado</p>';
                echo '<p class="text-2xl font-bold text-blue-600">' . round($download_size / 1024, 2) . ' KB</p>';
                echo '</div>';
                echo '</div>';

                if ($http_code !== 200) {
                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">';
                    echo '<h3 class="font-bold text-red-800 mb-2"><i class="fas fa-times-circle mr-2"></i>Error al Descargar</h3>';
                    echo '<p class="text-red-700">HTTP ' . $http_code . '</p>';
                    if ($curl_error) {
                        echo '<p class="text-sm text-red-600 mt-2">Error cURL: ' . htmlspecialchars($curl_error) . '</p>';
                    }
                    echo '<div class="mt-3 text-sm text-red-600">';
                    echo '<p><strong>Posibles causas:</strong></p>';
                    echo '<ul class="list-disc ml-5">';
                    echo '<li>La URL ha expirado (CloudFront usa URLs temporales)</li>';
                    echo '<li>Restricciones de IP o región</li>';
                    echo '<li>Problemas de certificado SSL</li>';
                    echo '</ul>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg mb-4">';
                    echo '<p class="text-green-700"><i class="fas fa-check-circle mr-2"></i>Descarga exitosa</p>';
                    echo '</div>';

                    // Decodificar JSON
                    $json_data = json_decode($json_content, true);

                    if (!is_array($json_data)) {
                        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">';
                        echo '<h3 class="font-bold text-red-800 mb-2">JSON Inválido</h3>';
                        echo '<p class="text-sm text-red-600">El contenido no se puede parsear como JSON</p>';
                        echo '<pre class="mt-2 bg-white p-2 rounded text-xs overflow-x-auto">' . htmlspecialchars(substr($json_content, 0, 500)) . '</pre>';
                        echo '</div>';
                        echo '</div>';
                    } else {
                        echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg mb-4">';
                        echo '<p class="text-green-700"><i class="fas fa-check-circle mr-2"></i>JSON válido - Total de registros: <strong>' . count($json_data) . '</strong></p>';
                        echo '</div>';

                        if (count($json_data) > 0) {
                            $first_record = reset($json_data);

                            echo '<h3 class="text-lg font-bold text-gray-800 mb-3">Primer Registro</h3>';
                            echo '<div class="bg-gray-50 p-4 rounded-lg mb-4">';
                            echo '<pre class="text-xs overflow-x-auto">' . htmlspecialchars(json_encode($first_record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                            echo '</div>';

                            echo '<h3 class="text-lg font-bold text-gray-800 mb-3">Estructura de Campos</h3>';
                            echo '<div class="bg-white rounded-lg border mb-4">';
                            echo '<table class="min-w-full divide-y divide-gray-200">';
                            echo '<thead class="bg-gray-50">';
                            echo '<tr>';
                            echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Campo</th>';
                            echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>';
                            echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Valor</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody class="divide-y divide-gray-200">';
                            foreach ($first_record as $key => $value) {
                                $type = gettype($value);
                                $display_value = '';
                                
                                if (is_array($value)) {
                                    $type .= ' (' . count($value) . ' elementos)';
                                    $display_value = json_encode($value, JSON_UNESCAPED_UNICODE);
                                } elseif (is_bool($value)) {
                                    $display_value = $value ? 'true' : 'false';
                                } elseif (is_null($value)) {
                                    $display_value = 'null';
                                } else {
                                    $display_value = (string)$value;
                                }
                                
                                $display_value = strlen($display_value) > 100 ? substr($display_value, 0, 100) . '...' : $display_value;
                                
                                echo '<tr class="hover:bg-gray-50">';
                                echo '<td class="px-4 py-2 text-sm font-mono text-blue-600">' . htmlspecialchars($key) . '</td>';
                                echo '<td class="px-4 py-2 text-sm text-gray-600">' . htmlspecialchars($type) . '</td>';
                                echo '<td class="px-4 py-2 text-sm text-gray-900 font-mono">' . htmlspecialchars($display_value) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';

                            // Guardar en BD
                            try {
                                $log_data = [
                                    'sync_type' => 'manual',
                                    'entity_type' => 'products',
                                    'sync_direction' => 'yuju_to_prestashop',
                                    'status' => 'completed',
                                    'total_items' => count($json_data),
                                    'processed_items' => 1,
                                    'success_items' => 1,
                                    'start_time' => date('Y-m-d H:i:s'),
                                    'end_time' => date('Y-m-d H:i:s'),
                                    'created_by' => 'inspector_web',
                                    'details' => json_encode([
                                        'script' => 'inspect_yuju_json_web.php',
                                        'cloudfront_url' => $cloudfront_url,
                                        'http_code' => $http_code,
                                        'download_size_bytes' => $download_size,
                                        'total_products' => count($json_data),
                                        'first_record' => $first_record,
                                        'fields' => array_keys($first_record),
                                        'timestamp' => time(),
                                        'date' => date('Y-m-d H:i:s')
                                    ], JSON_UNESCAPED_UNICODE)
                                ];
                                
                                $result = Db::getInstance()->insert('yuju_sync_logs', $log_data);
                                $log_id = (int)Db::getInstance()->Insert_ID();

                                if ($result) {
                                    echo '<div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">';
                                    echo '<h3 class="font-bold text-green-800 mb-2"><i class="fas fa-database mr-2"></i>Guardado en Base de Datos</h3>';
                                    echo '<p class="text-green-700">Registro guardado en <code>yuju_sync_logs</code></p>';
                                    echo '<p class="text-sm text-green-600 mt-2">ID del registro: <strong>' . $log_id . '</strong></p>';
                                    echo '<p class="text-xs text-gray-600 mt-2">Campos guardados: ' . implode(', ', array_keys($first_record)) . '</p>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">';
                                    echo '<p class="text-red-700">Error al guardar en BD: ' . Db::getInstance()->getMsgError() . '</p>';
                                    echo '</div>';
                                }

                                // Guardar en archivo
                                $output_file = dirname(__FILE__) . '/../cache/yuju_first_record.json';
                                file_put_contents($output_file, json_encode($first_record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                                echo '<div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mt-4">';
                                echo '<h3 class="font-bold text-blue-800 mb-2"><i class="fas fa-file mr-2"></i>Guardado en Archivo</h3>';
                                echo '<p class="text-blue-700">Archivo JSON guardado para inspección manual</p>';
                                echo '<code class="block bg-white p-2 rounded text-xs mt-2">' . realpath($output_file) . '</code>';
                                echo '</div>';

                            } catch (Exception $e) {
                                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">';
                                echo '<p class="text-red-700">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r-lg">';
                            echo '<p class="text-yellow-700"><i class="fas fa-exclamation-triangle mr-2"></i>El JSON está vacío (no contiene productos)</p>';
                            echo '</div>';
                        }

                        echo '</div>';
                    }
                }
            }

            if (!$show_results) {
            ?>
            <!-- Form -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">
                            <i class="fas fa-link mr-2"></i>URL de CloudFront
                        </label>
                        <input 
                            type="url" 
                            name="url" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="https://d2cx75vx0ihfxy.cloudfront.net/tmp/1m/offer-1084494-1761200231.868765.json"
                            value="<?php echo htmlspecialchars($cloudfront_url); ?>"
                            required
                        >
                        <p class="text-sm text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Pega aquí la URL de CloudFront que obtienes de Yuju API
                        </p>
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center justify-center"
                    >
                        <i class="fas fa-search mr-2"></i>
                        Inspeccionar JSON
                    </button>
                </form>
            </div>
            <?php } else { ?>
            <div class="text-center">
                <a href="?" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Inspeccionar otra URL
                </a>
            </div>
            <?php } ?>
        </div>
    </div>
</body>
</html>
