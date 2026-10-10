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
 * clientes ni credenciales de pago.
 *
 * Registro (ver docs/MONITOR_API.md):
 * - La tienda solo se registra cuando la validación de credenciales/autorización
 *   del módulo ha sido satisfactoria (`state === 'connected'` y sin `needs_auth`).
 *   Un mero formato correcto no registra nada.
 * - El registro es idempotente: si la tienda ya tiene API key no se rota; y si
 *   el monitor la conoce, presenta la clave vigente para recuperar la MISMA.
 */
class YujuMonitor
{
    public const CONFIG_URL = 'YUJU_MONITOR_URL';
    public const CONFIG_TOKEN = 'YUJU_MONITOR_TOKEN';

    /** URL por defecto del monitor. */
    public const DEFAULT_MONITOR_URL = 'https://yuju.ceballosleon.com';

    /** Rutas versionadas del contrato compartido. */
    private const REGISTER_PATH = '/api/v1/modules/register';
    private const ACTIVITY_PATH = '/api/v1/modules/activity';

    /** Marca de tiempo del último intento de vinculación. */
    private const CONFIG_LAST_CONNECT = 'YUJU_MONITOR_LAST_CONNECT';

    /** Espera entre intentos automáticos de vinculación (6 horas). */
    private const CONNECT_RETRY_SECONDS = 21600;

    /** Límite de eventos por lote aceptado por el monitor. */
    private const MAX_BATCH = 50;
    private const MAX_COUNTER = 1000000;
    private const CONNECT_TIMEOUT_SECONDS = 1;
    private const REQUEST_TIMEOUT_SECONDS = 2;
    private const MAX_ENDPOINT_LENGTH = 100;

    /** Esquema cerrado aceptado por el monitor. */
    private const EVENT_TYPES = ['api.request', 'sync.product', 'sync.order', 'sync.error', 'sync.run'];
    private const EVENT_STATUSES = ['success', 'warning', 'error', 'failed', 'started'];
    private const EVENT_DIRECTIONS = ['to_yuju', 'from_yuju'];
    private const EVENT_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private const EVENT_LEVELS = ['warning', 'error', 'critical'];

    /** Tipos que se agregan por ejecución con tally(). */
    private const TALLY_TYPES = ['sync.product', 'sync.order', 'sync.error'];

    /** @var array<int, array<string, mixed>> Eventos pendientes de enviar en esta petición. */
    private static $queue = [];

    /** @var array<string, array<string, mixed>> Contadores agregados por tipo. */
    private static $tallies = [];

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
     * Devuelve si la tienda está vinculada al monitor.
     *
     * No realiza ningún handshake: la vinculación solo ocurre tras una prueba de
     * conectividad autorizada (ver registerIfAuthorized()), de modo que una
     * mera instalación o visita al panel no registra la tienda.
     *
     * @return bool
     */
    public static function ensureConnected()
    {
        try {
            return self::isConfigured();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Registra la tienda si la prueba de conectividad demuestra autorización.
     *
     * Solo actúa cuando la validación existente del módulo devolvió
     * `state === 'connected'` sin `needs_auth`; en cualquier otro caso no
     * registra ni genera clave.
     *
     * @param array<string, mixed> $connectionTest Resultado de YujuApiClient::testConnection()
     * @param bool                 $force          Ignora el intervalo entre intentos (acción explícita)
     *
     * @return bool true si la tienda queda vinculada (antes o en este intento)
     */
    public static function registerIfAuthorized($connectionTest, $force = false)
    {
        try {
            if (!class_exists('Configuration')) {
                return false;
            }

            if (self::isConfigured()) {
                return true;
            }

            if (!self::isAuthorized($connectionTest)) {
                return false;
            }

            if (!$force) {
                $now = time();
                $last = (int) Configuration::get(self::CONFIG_LAST_CONNECT);

                if ($last > 0 && ($now - $last) < self::CONNECT_RETRY_SECONDS) {
                    return false;
                }
            }

            // Se marca antes de intentar: si la petición cuelga, no se reintenta
            // en cada carga hasta que toque.
            Configuration::updateValue(self::CONFIG_LAST_CONNECT, (string) time());

            $url = self::getMonitorUrl();

            if ($url === '') {
                $url = self::DEFAULT_MONITOR_URL;
            }

            $name = trim((string) Configuration::get('PS_SHOP_NAME'));

            if ($name === '' && class_exists('Tools')) {
                $name = (string) Tools::getServerName();
            }

            $result = self::register($url, $name);
            self::logConnect($result);

            return !empty($result['success']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Comprueba que la prueba de conectividad demuestra autorización real.
     *
     * @param mixed $connectionTest
     */
    private static function isAuthorized($connectionTest)
    {
        if (!is_array($connectionTest)) {
            return false;
        }

        if (empty($connectionTest['success']) || !empty($connectionTest['needs_auth'])) {
            return false;
        }

        return isset($connectionTest['state']) && $connectionTest['state'] === 'connected';
    }

    /**
     * Registro mínimo del resultado de la vinculación (sin datos sensibles).
     *
     * @param array<string, mixed> $result
     */
    private static function logConnect(array $result)
    {
        try {
            if (!class_exists('YujuLogger')) {
                return;
            }

            $logger = new YujuLogger();

            if (!empty($result['success'])) {
                $logger->info('Tienda vinculada al monitor de telemetría', [
                    'already_registered' => !empty($result['already_registered']),
                ]);

                return;
            }

            $logger->warning('No se pudo vincular la tienda al monitor de telemetría', [
                'reason' => isset($result['message']) ? (string) $result['message'] : 'unknown',
            ]);
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
     * API key de instalación guardada (nunca se expone en el panel).
     */
    private static function getToken()
    {
        if (!class_exists('Configuration')) {
            return '';
        }

        return trim((string) Configuration::get(self::CONFIG_TOKEN));
    }

    /**
     * Registra un evento puntual de telemetría. Devuelve false si el tipo no es
     * válido; nunca lanza excepciones.
     *
     * Para contadores agregados de sincronización usa tally().
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
     * Acumula contadores agregados de una ejecución de sincronización.
     *
     * Evita el envío de un evento por producto/pedido: al cerrar la petición se
     * emite un único evento por tipo con el total y los fallos. Los contadores
     * se saturan en MAX_COUNTER para respetar el esquema del monitor.
     *
     * @param string               $type    sync.product | sync.order | sync.error
     * @param int                  $count   elementos procesados (éxitos + fallos)
     * @param int                  $failed  elementos fallidos
     * @param array<string, mixed> $payload dirección y nivel opcionales
     *
     * @return bool
     */
    public static function tally($type, $count = 0, $failed = 0, array $payload = [])
    {
        try {
            if (!is_string($type) || !in_array($type, self::TALLY_TYPES, true)) {
                return false;
            }

            $count = max(0, min(self::MAX_COUNTER, (int) $count));
            $failed = max(0, min(self::MAX_COUNTER, (int) $failed));

            if (!isset(self::$tallies[$type])) {
                self::$tallies[$type] = ['count' => 0, 'failed' => 0];
            }

            self::$tallies[$type]['count'] = min(self::MAX_COUNTER, self::$tallies[$type]['count'] + $count);
            self::$tallies[$type]['failed'] = min(self::MAX_COUNTER, self::$tallies[$type]['failed'] + $failed);

            if (isset($payload['direction']) && in_array($payload['direction'], self::EVENT_DIRECTIONS, true)) {
                self::$tallies[$type]['direction'] = $payload['direction'];
            }

            if ($type === 'sync.error'
                && isset($payload['level'])
                && in_array(strtolower((string) $payload['level']), self::EVENT_LEVELS, true)
            ) {
                self::$tallies[$type]['level'] = strtolower((string) $payload['level']);
            }

            self::registerFlush();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Envía el lote pendiente (eventos puntuales + agregados). Se invoca en el
     * cierre de la petición.
     */
    public static function flush()
    {
        if (self::$flushing) {
            return;
        }

        if (self::$queue === [] && self::$tallies === []) {
            return;
        }

        self::$flushing = true;

        try {
            $events = array_splice(self::$queue, 0, self::MAX_BATCH);
            $events = array_merge($events, self::takeTallyEvents());

            // El contrato admite 50 eventos por lote. Los agregados (más
            // valiosos) van al final, así que al recortar se conservan.
            if (count($events) > self::MAX_BATCH) {
                $events = array_slice($events, -self::MAX_BATCH);
            }

            if ($events !== [] && self::isConfigured()) {
                self::postActivity($events);
            }
        } catch (Throwable $e) {
            // La telemetría es best-effort: nunca interrumpe al llamante.
        } finally {
            self::$flushing = false;
        }
    }

    /**
     * Convierte los contadores acumulados en eventos agregados y los vacía.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function takeTallyEvents()
    {
        if (self::$tallies === []) {
            return [];
        }

        // Correlation id único por lote: hace idempotente un reenvío y evita
        // que el índice único del monitor descarte eventos legítimos de otro
        // lote. Formato válido para el contrato ([A-Za-z0-9_-]{8,64}).
        $seed = 'r' . substr(hash('sha256', uniqid('', true)), 0, 12);
        $events = [];

        foreach (self::TALLY_TYPES as $type) {
            if (!isset(self::$tallies[$type])) {
                continue;
            }

            $tally = self::$tallies[$type];
            $event = [
                'type' => $type,
                'status' => $tally['failed'] > 0 ? ($type === 'sync.error' ? 'error' : 'warning') : 'success',
                'count' => $tally['count'],
                'failedCount' => $tally['failed'],
                'correlationId' => $seed . '-' . str_replace('.', '_', $type),
                'occurredAt' => gmdate('Y-m-d H:i:s'),
            ];

            if (isset($tally['direction'])) {
                $event['direction'] = $tally['direction'];
            }

            if (isset($tally['level'])) {
                $event['level'] = $tally['level'];
            }

            $events[] = $event;
        }

        self::$tallies = [];

        return $events;
    }

    /**
     * Envía los eventos al endpoint versionado de actividad, autenticando con
     * la API key de la instalación en la cabecera `X-API-Key`.
     *
     * @param array<int, array<string, mixed>> $events
     */
    private static function postActivity(array $events)
    {
        self::post(
            self::getMonitorUrl() . self::ACTIVITY_PATH,
            ['events' => $events],
            null,
            ['X-API-Key: ' . self::getToken()]
        );
    }

    /**
     * Registra la instalación contra el monitor y guarda la API key devuelta.
     *
     * Solo se envían datos mínimos (dominio, nombre, versiones y fecha UTC). Si
     * la tienda ya posee una clave se presenta en `X-API-Key` para que el
     * monitor devuelva la MISMA clave lógica sin rotarla.
     *
     * @param string $url  URL base del monitor
     * @param string $name Nombre de la tienda
     *
     * @return array<string, mixed>
     */
    public static function register($url, $name)
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
            'registered_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($module_version) {
            $payload['module_version'] = (string) $module_version;
        }

        if ($prestashop_version) {
            $payload['prestashop_version'] = (string) $prestashop_version;
        }

        $headers = [];
        $current_key = self::getToken();

        if ($current_key !== '') {
            $headers[] = 'X-API-Key: ' . $current_key;
        }

        try {
            $response = self::post($url . self::REGISTER_PATH, $payload, null, $headers);
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
                'message' => 'El monitor rechazó la llave de conexión o la API key de la tienda.',
            ];
        }

        if ($response['status'] === 409) {
            return [
                'success' => false,
                'message' => 'El monitor ya tiene registrada esta tienda y no se pudo recuperar su clave.',
            ];
        }

        if ($response['status'] < 200 || $response['status'] >= 300 || empty($body['ok'])) {
            $message = isset($body['message']) ? (string) $body['message'] : 'HTTP ' . $response['status'];

            return ['success' => false, 'message' => 'El monitor rechazó el registro: ' . $message];
        }

        if (empty($body['api_key'])) {
            return ['success' => false, 'message' => 'El monitor no devolvió una API key de instalación.'];
        }

        self::saveConfiguration($url, (string) $body['api_key']);

        return [
            'success' => true,
            'already_registered' => !empty($body['already_registered']),
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
     * Guarda la URL del monitor y, opcionalmente, la API key de instalación.
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
            'occurredAt' => gmdate('Y-m-d H:i:s'),
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

        foreach (['httpStatus' => 599, 'durationMs' => 600000, 'count' => self::MAX_COUNTER, 'failedCount' => self::MAX_COUNTER] as $field => $max) {
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
     * URL pública de la tienda para el registro.
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
     * @param array<string, mixed>  $body
     * @param array<int, string>    $extraHeaders
     *
     * @return array{status: int, body: string|false, error: string}|null
     */
    private static function post($url, array $body, $token = null, array $extraHeaders = [])
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        foreach ($extraHeaders as $header) {
            $headers[] = $header;
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
