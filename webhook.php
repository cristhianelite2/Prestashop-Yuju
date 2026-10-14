<?php
/**
 * Entrada pública del webhook de Yuju para instalaciones donde PrestaShop
 * vive en /shop y el módulo está publicado en /modules/yuju.
 *
 * URL: https://chilangoeshop.com/modules/yuju/webhook.php
 *
 * Este archivo solo enruta la petición al front controller nativo de PrestaShop.
 * El procesamiento real está en controllers/front/webhook.php.
 */

$moduleDir = __DIR__;
$documentRoot = dirname($moduleDir, 2);

// Soporta ambas ubicaciones:
// 1. /public_html/modules/yuju + PrestaShop en /public_html/shop
// 2. /public_html/shop/modules/yuju + PrestaShop en /public_html/shop
$possibleRoots = [
    $documentRoot . '/shop',
    dirname($moduleDir, 2),
];

$prestashopRoot = null;
foreach ($possibleRoots as $candidate) {
    if (is_file($candidate . '/config/config.inc.php')
        && is_file($candidate . '/index.php')) {
        $prestashopRoot = realpath($candidate);
        break;
    }
}

if ($prestashopRoot === false || $prestashopRoot === null) {
    error_log('[Yuju webhook] No se encontró PrestaShop. Rutas comprobadas: ' . implode(', ', $possibleRoots));
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'PrestaShop installation could not be located',
    ]);
    exit;
}

// El front controller se encarga de inicializar PrestaShop y cargar el módulo.
$_GET['fc'] = 'module';
$_GET['module'] = 'yuju';
$_GET['controller'] = 'webhook';
$_REQUEST['fc'] = 'module';
$_REQUEST['module'] = 'yuju';
$_REQUEST['controller'] = 'webhook';

require $prestashopRoot . '/index.php';
