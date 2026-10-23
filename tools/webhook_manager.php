<?php
/**
 * Fuente del endpoint externo de acciones AJAX de webhooks.
 * Al instalarse en /shop/yuju/webhook_manager.php, inicializa PrestaShop
 * y reenvía únicamente las acciones permitidas al controlador del módulo.
 */
$externalDir = __DIR__;
if (is_file(dirname($externalDir) . '/config/config.inc.php')
    && is_file(dirname($externalDir) . '/modules/yuju/yuju.php')) {
    $shopRoot = dirname($externalDir);
    $moduleRoot = $shopRoot . '/modules/yuju';
} else {
    $moduleRoot = dirname(__DIR__);
    $shopRoot = dirname(dirname($moduleRoot));
}
$shopConfig = $shopRoot . '/config/config.inc.php';

if (!is_file($shopConfig) || !is_file($moduleRoot . '/yuju.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No se pudo inicializar PrestaShop o el módulo Yuju.']);
    exit;
}

require_once $shopConfig;
require_once $moduleRoot . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!class_exists('Module') || !Module::isEnabled('yuju')) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'El módulo Yuju no está habilitado.']);
    exit;
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : (isset($_POST['action']) ? (string) $_POST['action'] : '');
$allowedActions = [
    'get_subscriptions',
    'subscribe',
    'unsubscribe',
    'update_subscription',
    'get_received',
    'get_stats',
    'clear_received',
    'clean_old',
];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
    exit;
}

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token no válido.']);
    exit;
}

$tokens = YujuConfig::get('YUJU_CRON_URL_TOKENS', []);
if (is_string($tokens)) {
    $decoded = json_decode($tokens, true);
    $tokens = is_array($decoded) ? $decoded : [];
}
$expected = is_array($tokens) && isset($tokens['cron.php']) ? (string) $tokens['cron.php'] : '';
if ($expected === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Regenera los tokens de CRON y vuelve a cargar la URL.']);
    exit;
}

require $moduleRoot . '/cron/webhook_manager.php';
