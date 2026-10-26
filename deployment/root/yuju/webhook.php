<?php
/**
 * Puente de webhook para la raíz de PrestaShop.
 * Destino: /shop/yuju/webhook.php
 *
 * Mantiene el endpoint fuera de /modules y delega el procesamiento al
 * controlador front del módulo Yuju.
 */
$shopRoot = dirname(__DIR__);
$configFile = $shopRoot . '/config/config.inc.php';
$indexFile = $shopRoot . '/index.php';

if (!is_file($configFile) || !is_file($indexFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No se pudo inicializar PrestaShop.']);
    exit;
}

require_once $configFile;

$_GET['fc'] = 'module';
$_GET['module'] = 'yuju';
$_GET['controller'] = 'webhook';
$_REQUEST['fc'] = 'module';
$_REQUEST['module'] = 'yuju';
$_REQUEST['controller'] = 'webhook';

require $indexFile;
