<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Puente de telemetría del módulo hacia el monitor Yuju.
 *
 * Envía exclusivamente metadatos (método, endpoint normalizado, estado, duración y
 * contadores) al monitor configurado en el panel del módulo. El envío es
 * best-effort: los eventos se acumulan en memoria y se envían en un único lote al
 * terminar la petición PHP, con timeouts cortos y sin lanzar nunca excepciones,
 * de modo que la telemetría no puede interrumpir la sincronización ni la operación
 * de la tienda.
 *
 * No se envían cuerpos de peticiones, cabeceras, productos, pedidos, datos de
 * clientes ni credenciales.
 */
class YujuMonitor
{
    public const CONFIG_URL = 'YUJU_MONITOR_URL';
    public const CONFIG_TOKEN = 'YUJU_MONITOR_TOKEN';

    /** URL por defecto del monitor: la conexión es silenciosa y automática. */
    public const DEFAULT_MONITOR_URL = 'https://yuju.ceballosleon.com';

    /** Marca de tiempo del último intento silencioso de vinculación. */
    private const CONFIG_LAST_CONNECT = 'YUJU_MONITOR_LAST_CONNECT';

    /** Espera entre intentos silenciosos de vinculación (6 horas). */
    private const CONNECT_RETRY_SECONDS = 21600;

    /** Límite de eventos por lote aceptado por el monitor. */
    private const MAX_BATCH = 50;
    private const CONNECT_TIMEOUT_SECONDS = 1;
    private const REQUEST_TIMEOUT_SECONDS = 2;
    private const MAX_ENDPOINT_LENGTH = 100;

    /** Esquema cerrado aceptado por el monitor. */
    private const EVENT_TYPES = ['api.request', 'sync.product', 'sync.order', 'sync.error', 'sync.run'];
    private const EVENT_STATUSES = ['success', 'warning', 'error', 'failed', 'started'];
    private const EVENT_DIRECTIONS = ['to_yuju', 'from_yuju'];
    private const EVENT_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private const EVENT_LEVELS = ['warning', 'error', 'critical'];

    /** @var array<int, array<string, mixed>> Eventos pendientes de enviar en esta petición. */
    private static $queue = [];

    private static $flush_registered = false;

    private static $flushing = false;

    /**
     * Indica si la tienda ya está vinculada a un monitor.
     */
    public static function isConfigured()
    {
        return self::getMonitorUrl() !== '' && self::getToken() !== '';
    }

    /**
     * Vincula la tienda con el monitor de forma silenciosa.
     *
     * Se ejecuta sin interfaz y sin mostrar mensajes: si la tienda aún no está
     * vinculada, intenta el handshake contra la URL guardada (o la pública por
     * defecto) y guarda el token devuelto. Los fallos se ignoran y no se
     * reintenta antes de `CONNECT_RETRY_SECONDS`, de modo que la página del
     * módulo nunca se ralentiza por el monitor.
     *
     * @return bool true si la tienda quedó vinculada (antes o en este intento)
     */
    public static function ensureConnected()
    {
        try {
            if (self::isConfigured()) {
                return true;
            }

            if (!class_exists('Configuration')) {
                return false;
            }

            $now = time();
            $last = (int) Configuration::get(self::CONFIG_LAST_CONNECT);

            if ($last > 0 && ($now - $last) < self::CONNECT_RETRY_SECONDS) {
                return false;
            }

            // Se marca antes de intentar: si el handshake cuelga, no se vuelve
            // a probar hasta que toque.
            Configuration::updateValue(self::CONFIG_LAST_CONNECT, (string) $now);

            $url = self::getMonitorUrl();

            if ($url === '') {
                $url = self::DEFAULT_MONITOR_URL;
            }

            $name = trim((string) Configuration::get('PS_SHOP_NAME'));

            if ($name === '' && class_exists('Tools')) {
                $name = (string) Tools::getServerName();
            }

            $result = self::connect($url, $name);

            if (!empty($result['success'])) {
                self::logSilentConnect('ok');
            }

            return !empty($result['success']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Registro mínimo del resultado del handshake silencioso (sin datos).
     */
    private static function logSilentConnect($result)
    {
        try {
            if (class_exists('YujuLogger')) {
                $logger = new YujuLogger();
                $logger->info('Monitor de telemetría vinculado en segundo plano', ['result' => (string) $result]);
            }
        } catch (Throwable $e) {
            // Best-effort: nunca interrumpe.
        }
    }

    /**
     * URL base del monitor configurada en la tienda.
     */
    public static function getMonitorUrl()
    {
        if (!class_exists('Configuration')) {
            return '';
        }

        return rtrim(trim((string) Configuration::get(self::CONFIG_URL)), '/');
    }

    /**
     * Token de instalación guardado (nunca se expone en el panel).
     */
    private static function getToken()
    {
        if (!class_exists('Configuration')) {
            return '';
        }

        return trim((string) Configuration::get(self::CONFIG_TOKEN));
    }

    /**
     * Registra un evento de telemetría. Devuelve false si el tipo no es válido o
     * si la tienda no está vinculada; nunca lanza excepciones.
     *
     * @param string               $type
     * @param array<string, mixed> $payload
     */
    public static function emit($type, array $payload = [])
    {
        try {
            $event = self::buildEvent($type, $payload);

            if ($event === null) {
                return false;
            }

            self::$queue[] = $event;

            if (count(self::$queue) > self::MAX_BATCH) {
                array_shift(self::$queue);
            }

            self::registerFlush();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Envía el lote pendiente. Se invoca en el cierre de la petición.
     */
    public static function flush()
    {
        if (self::$flushing) {
            return;
        }

        if (self::$queue === []) {
            return;
        }

        self::$flushing = true;
        $events = array_splice(self::$queue, 0, self::MAX_BATCH);

        try {
            if (self::isConfigured()) {
                self::post(self::getMonitorUrl() . '/api/events', ['events' => $events], self::getToken());
            }
        } catch (Throwable $e) {
            // La telemetría es best-effort: nunca interrumpe al llamante.
        } finally {
            self::$flushing = false;
        }
    }

    /**
     * Realiza el handshake con el monitor y guarda el token devuelto.
     *
     * El módulo envía una llave derivada de su hora (SHA-256 de "yuju" + fecha/hora)
     * que el monitor valida dentro de una ventana de tiempo; si es correcta, la
     * instalación queda registrada y se guarda el token para la telemetría.
     *
     * @param string $url  URL base del monitor
     * @param string $name Nombre de la tienda
     *
     * @return array<string, mixed>
     */
    public static function connect($url, $name)
    {
        $url = rtrim(trim((string) $url), '/');

        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return [
                'success' => false,
                'message' => 'La URL del monitor no es válida. Debe empezar por http:// o https://.',
            ];
        }

        $module_version = null;
        $prestashop_version = defined('_PS_VERSION_') ? _PS_VERSION_ : null;

        if (class_exists('Module')) {
            $module = Module::getInstanceByName('prestashopyuju');

            if ($module && isset($module->version)) {
                $module_version = $module->version;
            }
        }

        $payload = [
            'key' => self::handshakeKey(),
            'url' => self::shopUrl(),
            'name' => (string) $name,
        ];

        if ($module_version) {
            $payload['module_version'] = (string) $module_version;
        }

        if ($prestashop_version) {
            $payload['prestashop_version'] = (string) $prestashop_version;
        }

        try {
            $response = self::post($url . '/api/connect', $payload, null);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'No se pudo contactar con el monitor.'];
        }

        if ($response === null) {
            return ['success' => false, 'message' => 'La extensión cURL de PHP no está disponible.'];
        }

        if ($response['error'] !== '') {
            return ['success' => false, 'message' => 'Error de conexión: ' . $response['error']];
        }

        $body = json_decode((string) $response['body'], true);

        if (!is_array($body)) {
            return [
                'success' => false,
                'message' => 'El monitor respondió con un contenido inesperado (HTTP ' . $response['status'] . ').',
            ];
        }

        if ($response['status'] === 401) {
            return [
                'success' => false,
                'message' => 'El monitor rechazó la llave de conexión. Revisa la fecha y hora del servidor.',
            ];
        }

        if ($response['status'] < 200 || $response['status'] >= 300 || empty($body['ok'])) {
            $message = isset($body['message']) ? (string) $body['message'] : 'HTTP ' . $response['status'];

            return ['success' => false, 'message' => 'El monitor rechazó la conexión: ' . $message];
        }

        if (empty($body['token'])) {
            return ['success' => false, 'message' => 'El monitor no devolvió un token de instalación.'];
        }

        self::saveConfiguration($url, (string) $body['token']);

        return [
            'success' => true,
            'message' => 'Tienda vinculada al monitor correctamente.',
            'installation' => isset($body['installation']) ? $body['installation'] : null,
        ];
    }

    /**
     * Llave temporal del handshake: SHA-256 de "yuju" más la fecha/hora (d/m/y H:i).
     */
    public static function handshakeKey($timestamp = null)
    {
        $timestamp = $timestamp === null ? time() : (int) $timestamp;

        return hash('sha256', 'yuju' . date('d/m/y H:i', $timestamp));
    }

    /**
     * Guarda la URL del monitor y, opcionalmente, el token de instalación.
     */
    public static function saveConfiguration($url, $token = null)
    {
        if (!class_exists('Configuration')) {
            return;
        }

        $url = rtrim(trim((string) $url), '/');

        if ($url !== '') {
            Configuration::updateValue(self::CONFIG_URL, $url);
        }

        if ($token !== null && trim((string) $token) !== '') {
            Configuration::updateValue(self::CONFIG_TOKEN, trim((string) $token));
        }
    }

    /**
     * Construye un evento con el esquema cerrado del monitor. Devuelve null si el
     * tipo no es válido.
     *
     * @param mixed                $type
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private static function buildEvent($type, array $payload)
    {
        if (!is_string($type) || !in_array($type, self::EVENT_TYPES, true)) {
            return null;
        }

        $status = isset($payload['status']) && in_array($payload['status'], self::EVENT_STATUSES, true)
            ? $payload['status']
            : ($type === 'sync.error' ? 'error' : 'success');

        $event = [
            'type' => $type,
            'status' => $status,
        ];

        if (isset($payload['direction']) && in_array($payload['direction'], self::EVENT_DIRECTIONS, true)) {
            $event['direction'] = $payload['direction'];
        }

        if (isset($payload['action']) && is_string($payload['action'])) {
            $action = trim(preg_replace('~[^a-zA-Z0-9 _.-]~', '', $payload['action']));

            if ($action !== '') {
                $event['action'] = mb_substr($action, 0, 40);
            }
        }

        if (isset($payload['method']) && in_array(strtoupper((string) $payload['method']), self::EVENT_METHODS, true)) {
            $event['method'] = strtoupper((string) $payload['method']);
        }

        if (isset($payload['endpoint'])) {
            $endpoint = self::tidyEndpoint($payload['endpoint']);

            if ($endpoint !== '') {
                $event['endpoint'] = $endpoint;
            }
        }

        foreach (['httpStatus' => 599, 'durationMs' => 600000, 'count' => 1000000, 'failedCount' => 1000000] as $field => $max) {
            if (isset($payload[$field]) && is_numeric($payload[$field])) {
                $event[$field] = max(0, min($max, (int) $payload[$field]));
            }
        }

        if (isset($payload['level']) && in_array(strtolower((string) $payload['level']), self::EVENT_LEVELS, true)) {
            $event['level'] = strtolower((string) $payload['level']);
        }

        return $event;
    }

    /**
     * Normaliza el endpoint: sin host, sin parámetros de consulta y con los
     * identificadores numéricos sustituidos por `:id`.
     *
     * @param mixed $endpoint
     */
    private static function tidyEndpoint($endpoint)
    {
        if (!is_string($endpoint)) {
            return '';
        }

        $endpoint = trim($endpoint);

        if (preg_match('~^https?://~i', $endpoint)) {
            $path = parse_url($endpoint, PHP_URL_PATH);
            $endpoint = is_string($path) ? $path : '';
        }

        $endpoint = explode('?', $endpoint)[0];
        $endpoint = preg_replace('~\b(products|orders|categories|attributes|webhooks|shops)/[^/]+~i', '$1/:id', $endpoint);
        $endpoint = preg_replace('~\d+~', ':id', $endpoint);
        $endpoint = preg_replace('~[^a-zA-Z0-9_./:-]~', '', $endpoint);

        return mb_substr((string) $endpoint, 0, self::MAX_ENDPOINT_LENGTH);
    }

    /**
     * URL pública de la tienda para el handshake.
     */
    private static function shopUrl()
    {
        if (class_exists('Tools') && method_exists('Tools', 'getShopDomainSsl')) {
            $domain = Tools::getShopDomainSsl(true);

            if (is_string($domain) && $domain !== '') {
                return $domain;
            }
        }

        return '';
    }

    private static function registerFlush()
    {
        if (self::$flush_registered) {
            return;
        }

        self::$flush_registered = true;
        register_shutdown_function([__CLASS__, 'flush']);
    }

    /**
     * Petición POST JSON best-effort.
     *
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: string|false, error: string}|null
     */
    private static function post($url, array $body, $token = null)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response_body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = (string) curl_error($ch);

        curl_close($ch);

        return [
            'status' => $status,
            'body' => $response_body,
            'error' => $curl_error,
        ];
    }
}
