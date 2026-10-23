<?php
/**
 * Endpoint web protegido para tareas programadas de Yuju.
 *
 * Cada tarea utiliza un token independiente generado desde el Back Office.
 * Este endpoint no depende de PHP CLI ni de exec/proc_open/popen.
 */
$moduleRoot = dirname(__FILE__, 2);
$shopConfig = $moduleRoot . '/../../config/config.inc.php';

if (!is_file($shopConfig)) {
    http_response_code(500);
    exit('No se pudo cargar PrestaShop.');
}

require_once $shopConfig;
$shopInit = dirname($shopConfig) . '/init.php';
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

// Solo se publican tareas recurrentes y diseñadas para ejecución web.
$allowedTasks = [
    'cron.php' => 'Tareas generales de Yuju',
    'process_queue.php' => 'Procesamiento de la cola de sincronización',
];

if (!isset($allowedTasks[$task])) {
    http_response_code(404);
    exit("Tarea no válida.\n");
}

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
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
    exit("Acceso denegado. Verifique la URL protegida desde el Back Office de Yuju.\n");
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

echo 'Yuju: ' . $allowedTasks[$task] . "\n";
echo 'Inicio: ' . date('Y-m-d H:i:s') . "\n";
if (function_exists('ob_implicit_flush')) {
    @ob_implicit_flush(true);
}

// El script mantiene su propia lógica de bloqueo, cuotas y registro.
require $script;
