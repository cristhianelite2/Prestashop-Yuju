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

// Enrutar internamente a PrestaShop sin redirigir al cliente.
// Es importante ajustar REQUEST_URI: algunas instalaciones en subdirectorios
// redirigen al home si conservan la URL pública /modules/yuju/webhook.php.
$documentRootPath = realpath($_SERVER['DOCUMENT_ROOT'] ?? $documentRoot);
if ($documentRootPath === false) {
    $documentRootPath = realpath($documentRoot);
}

$relativeRoot = str_replace(
    DIRECTORY_SEPARATOR,
    '/',
    ltrim(substr($prestashopRoot, strlen(rtrim($documentRootPath, DIRECTORY_SEPARATOR))), DIRECTORY_SEPARATOR)
);
$baseUri = $relativeRoot !== '' ? '/' . trim($relativeRoot, '/') : '';
$scriptUri = $baseUri . '/index.php';

$_GET['fc'] = 'module';
$_GET['module'] = 'yuju';
$_GET['controller'] = 'webhook';
$_REQUEST['fc'] = 'module';
$_REQUEST['module'] = 'yuju';
$_REQUEST['controller'] = 'webhook';

$queryString = http_build_query($_GET);
$_SERVER['REQUEST_URI'] = $scriptUri . ($queryString !== '' ? '?' . $queryString : '');
$_SERVER['QUERY_STRING'] = $queryString;
$_SERVER['SCRIPT_NAME'] = $scriptUri;
$_SERVER['PHP_SELF'] = $scriptUri;
$_SERVER['SCRIPT_FILENAME'] = $prestashopRoot . '/index.php';

require $prestashopRoot . '/index.php';
