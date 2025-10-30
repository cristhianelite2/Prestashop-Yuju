<?php
/**
 * Script para descargar y analizar el primer registro del JSON de Yuju
 * Propósito: Depurar estructura de datos y detectar problemas con URLs de CloudFront
 */

// Cargar PrestaShop
$root_path = dirname(__FILE__, 2);
require_once $root_path . '/../../config/config.inc.php';
require_once $root_path . '/../../init.php';

// URL del JSON (cámbiala por la que necesites inspeccionar)
$cloudfront_url = 'https://d2cx75vx0ihfxy.cloudfront.net/tmp/1m/offer-1084494-1761200231.868765.json';

echo "==============================================\n";
echo "  INSPECTOR DE JSON DE YUJU\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

echo "URL a descargar:\n";
echo "$cloudfront_url\n\n";

echo "Descargando JSON...\n";

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

echo "Código HTTP: $http_code\n";
echo "Tamaño descargado: " . round($download_size / 1024, 2) . " KB\n";

if ($http_code !== 200) {
    echo "\n❌ ERROR: No se pudo descargar el JSON (HTTP $http_code)\n";
    if ($curl_error) {
        echo "Error cURL: $curl_error\n";
    }
    echo "\nPosibles causas:\n";
    echo "- La URL ha expirado (CloudFront tiene URLs temporales)\n";
    echo "- Restricciones de IP o región\n";
    echo "- Problemas de certificado SSL\n";
    exit(1);
}

if (empty($json_content)) {
    echo "\n❌ ERROR: El contenido descargado está vacío\n";
    exit(1);
}

echo "\n✓ Descarga exitosa\n\n";

// Decodificar JSON
$json_data = json_decode($json_content, true);

if (!is_array($json_data)) {
    echo "❌ ERROR: El contenido no es un JSON válido\n";
    echo "Primeros 500 caracteres del contenido:\n";
    echo substr($json_content, 0, 500) . "\n";
    exit(1);
}

echo "✓ JSON válido\n";
echo "Total de registros en el JSON: " . count($json_data) . "\n\n";

if (count($json_data) === 0) {
    echo "⚠ ADVERTENCIA: El JSON está vacío (no contiene productos)\n";
    exit(0);
}

// Obtener primer registro
$first_record = reset($json_data);

echo "==============================================\n";
echo "  PRIMER REGISTRO DEL JSON\n";
echo "==============================================\n\n";

// Mostrar el primer registro formateado
echo json_encode($first_record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Analizar estructura
echo "==============================================\n";
echo "  ANÁLISIS DE ESTRUCTURA\n";
echo "==============================================\n\n";

echo "Campos encontrados en el primer registro:\n";
foreach ($first_record as $key => $value) {
    $type = gettype($value);
    if (is_array($value)) {
        $type .= ' (' . count($value) . ' elementos)';
    } elseif (is_string($value)) {
        $type .= ' (longitud: ' . strlen($value) . ')';
    }
    echo "  - $key: $type\n";
}

// Guardar en base de datos
echo "\n==============================================\n";
echo "  GUARDANDO EN BASE DE DATOS\n";
echo "==============================================\n\n";

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
        'created_by' => 'inspector_script',
        'details' => json_encode([
            'script' => 'inspect_yuju_json.php',
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
    
    if ($result) {
        $log_id = (int)Db::getInstance()->Insert_ID();
        echo "✓ Primer registro guardado exitosamente en yuju_sync_logs\n";
        echo "  ID del registro: $log_id\n";
        echo "  Campos guardados: " . implode(', ', array_keys($first_record)) . "\n";
        echo "\nPara ver el registro completo, ejecuta:\n";
        echo "SELECT * FROM " . _DB_PREFIX_ . "yuju_sync_logs WHERE id = $log_id;\n";
    } else {
        echo "❌ ERROR: No se pudo guardar en la base de datos\n";
        echo "Error SQL: " . Db::getInstance()->getMsgError() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR al guardar: " . $e->getMessage() . "\n";
}

// Guardar también en archivo JSON para inspección manual
$output_file = dirname(__FILE__) . '/../cache/yuju_first_record.json';
file_put_contents($output_file, json_encode($first_record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n✓ Primer registro también guardado en:\n";
echo "  " . realpath($output_file) . "\n";

echo "\n==============================================\n";
echo "  RESUMEN\n";
echo "==============================================\n\n";

echo "✓ Descarga: OK (HTTP $http_code)\n";
echo "✓ Total productos: " . count($json_data) . "\n";
echo "✓ Primer registro analizado\n";
echo "✓ Guardado en BD: yuju_sync_logs (ID: $log_id)\n";
echo "✓ Guardado en archivo: $output_file\n";

echo "\n==============================================\n";
echo "Script completado exitosamente\n";
echo "==============================================\n";
