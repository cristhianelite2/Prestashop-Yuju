<?php
/**
 * Endpoint web de Yuju instalado fuera de /modules para evitar reglas que
 * bloquean la ejecución directa de PHP dentro del directorio de módulos.
 *
 * URL prevista: /shop/yuju/trigger.php
 */
$shopRoot = dirname(__DIR__);
$shopConfig = $shopRoot . '/config/config.inc.php';
$moduleRoot = $shopRoot . '/modules/yuju';

if (!is_file($shopConfig) || !is_file($moduleRoot . '/yuju.php')) {
    http_response_code(500);
    exit('No se pudo inicializar la tienda o el módulo Yuju.');
}

require_once $shopConfig;
$shopInit = $shopRoot . '/init.php';
if (is_file($shopInit)) {
    require_once $shopInit;
}
require_once $moduleRoot . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!class_exists('Module') || !Module::isEnabled('yuju')) {
    http_response_code(404);
    exit("Tarea no disponible.\n");
}

$task = isset($_GET['task']) ? (string) $_GET['task'] : '';
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$allowedTasks = [
    'cron.php' => 'Tareas generales de Yuju',
    'process_queue.php' => 'Cola de sincronización de Yuju',
];

if (!isset($allowedTasks[$task])) {
    http_response_code(404);
    exit("Tarea no válida.\n");
}
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(403);
    exit("Token no válido.\n");
}

$tokens = YujuConfig::get('YUJU_CRON_URL_TOKENS', []);
if (is_string($tokens)) {
    $decoded = json_decode($tokens, true);
    $tokens = is_array($decoded) ? $decoded : [];
}
$expected = is_array($tokens) && isset($tokens[$task]) ? (string) $tokens[$task] : '';
if ($expected === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    exit("Acceso denegado. El token no coincide con el configurado en Yuju.\n");
}

$script = $moduleRoot . '/cron/' . $task;
if (!is_file($script)) {
    http_response_code(500);
    exit("No se encontró el archivo de la tarea.\n");
}

if (function_exists('ignore_user_abort')) {
    @ignore_user_abort(true);
}
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
if (!defined('YUJU_CRON_TRIGGER_AUTHORIZED')) {
    define('YUJU_CRON_TRIGGER_AUTHORIZED', true);
}

echo 'Yuju: ' . $allowedTasks[$task] . "\n";
echo 'Inicio: ' . date('Y-m-d H:i:s') . "\n";
if (function_exists('ob_implicit_flush')) {
    @ob_implicit_flush(true);
}
require $script;
