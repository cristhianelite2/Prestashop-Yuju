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

require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuOAuth
{
    public const SANDBOX_AUTH_URL = 'https://auth-sandbox.yuju.io';
    public const PRODUCTION_AUTH_URL = 'https://auth.yuju.io';

    private $auth_url;
    private $client_id;
    private $client_secret;
    private $logger;

    public function __construct()
    {
        $this->logger = new YujuLogger();

        $environment = Configuration::get('YUJU_ENVIRONMENT', 'sandbox');
        $this->auth_url = ($environment === 'production') ? self::PRODUCTION_AUTH_URL : self::SANDBOX_AUTH_URL;

        $this->client_id = $this->normalizeClientId(
            $this->resolveCredential('YUJU_CLIENT_ID', 'YUJU_API_CLIENT_ID')
        );
        $this->client_secret = $this->resolveCredential('YUJU_CLIENT_SECRET', 'YUJU_API_CLIENT_SECRET');
    }

    /**
     * Resuelve una credencial desde Configuration.
     *
     * Si la clave actual está vacía intenta migrar automáticamente el valor
     * guardado por versiones anteriores bajo una clave legada (YUJU_API_*).
     */
    private function resolveCredential($key, $legacy_key = null)
    {
        $value = Configuration::get($key);
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' && $legacy_key) {
            $legacy_value = Configuration::get($legacy_key);
            $legacy_value = is_string($legacy_value) ? trim($legacy_value) : '';

            if ($legacy_value !== '') {
                Configuration::updateValue($key, $legacy_value);
                $this->logger->info('Credencial OAuth migrada desde clave legada', [
                    'key' => $key,
                    'legacy_key' => $legacy_key,
                ]);

                return $legacy_value;
            }
        }

        return $value;
    }

    /**
     * Normaliza el Client ID: elimina espacios, comillas y pasa a minúsculas
     * cuando el valor tiene el formato esperado (32 caracteres hexadecimales).
     */
    private function normalizeClientId($value)
    {
        $value = trim((string) $value);
        $value = trim($value, "\"'");

        if (preg_match('/^[0-9a-fA-F]{32}$/', $value)) {
            return strtolower($value);
        }

        return $value;
    }

    /**
     * Obtiene la URL de autorización de OAuth para iniciar el flujo de autorización.
     */
    public function getAuthorizationUrl($state = null)
    {
        if (!$this->client_id) {
            throw new Exception('Client ID no configurado');
        }

        $redirect_uri = $this->getRedirectUri();
        $scope = Configuration::get('YUJU_SCOPE', 'read write');

        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => $scope,
        ];

        if ($state) {
            $params['state'] = $state;
        } else {
            // Generar state aleatorio para seguridad CSRF
            $state = bin2hex(random_bytes(16));
            $params['state'] = $state;
        }

        // Guardar state en sesión para validación en callback
        Context::getContext()->cookie->yuju_oauth_state = $state;

        return $this->auth_url . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Intercambia el código de autorización por un access token usando la API de Yuju.
     */
    public function exchangeCodeForToken($code, $state = null)
    {
        $code = trim((string) $code);

        if ($code === '') {
            throw new Exception('Código de autorización vacío');
        }

        if (!$this->client_id || !$this->client_secret) {
            $this->logger->error('Credenciales OAuth incompletas', [
                'client_id_set' => !empty($this->client_id),
                'client_secret_set' => !empty($this->client_secret),
            ]);

            throw new Exception('Credenciales OAuth no configuradas (Client ID / Secret)');
        }

        // Validación previa: el code es de un solo uso, así que no se envía
        // la petición si las credenciales tienen un formato que Yuju va a
        // rechazar. De este modo el code sigue vigente para reintentarlo.
        $credential_error = $this->validateCredentialsForTokenRequest();

        if ($credential_error !== null) {
            $this->logger->error('Credenciales OAuth con formato inválido (petición no enviada)', $this->getCredentialAudit());

            throw new Exception($credential_error);
        }

        $data = [
            'client_id' => $this->client_id,
            'secret_key' => $this->client_secret,
            'code' => $code,
        ];

        $response = $this->makeYujuTokenRequest($data);

        if ($response['success']) {
            $this->saveTokenData($response['data']);
            $this->logger->log('info', 'OAuth token obtenido exitosamente', ['state' => $state]);

            return true;
        } else {
            $this->logger->log('error', 'Error al obtener OAuth token', $response);

            throw new Exception('Error al obtener token: ' . $this->buildTokenErrorMessage($response));
        }
    }

    /**
     * Valida el formato de las credenciales antes de pedir el token.
     *
     * Devuelve null si el formato es aceptable para Yuju, o el mensaje de
     * error (sin exponer valores) si la petición se debe abortar sin gastar
     * el code de un solo uso.
     *
     * @return string|null
     */
    private function validateCredentialsForTokenRequest()
    {
        $client_id = (string) $this->client_id;
        $secret = (string) $this->client_secret;

        if (!preg_match('/^[0-9a-f]{32}$/', $client_id)) {
            return 'El Client ID guardado no tiene el formato esperado (32 caracteres hexadecimales en minúsculas; '
                . 'longitud actual: ' . strlen($client_id) . '). Re-guárdalo desde la configuración del módulo '
                . 'copiándolo exactamente de Yuju > Aplicaciones > Ver credenciales. El code no se ha gastado.';
        }

        if (preg_match('/\s/', $secret)) {
            return 'El Secret Key guardado contiene espacios o saltos de línea (longitud actual: ' . strlen($secret) . '). '
                . 'Re-guárdalo copiándolo exactamente de Yuju > Aplicaciones > Ver credenciales, sin espacios '
                . 'ni caracteres de más. El code no se ha gastado.';
        }

        if (preg_match('/[^\x20-\x7E]/', $secret)) {
            return 'El Secret Key guardado contiene caracteres invisibles o no ASCII (se guardaron ' . strlen($secret) . ' car., '
                . 'pero ocupan más al enviarse: típico de copiar-pegar desde una web). Bórralo por completo en la '
                . 'configuración del módulo, cópialo de nuevo desde Yuju pasándolo primero por un editor de texto '
                . 'plano (Bloc de notas) y guárdalo. El code no se ha gastado.';
        }

        return null;
    }

    /**
     * Auditoría de credenciales sin exponer valores (solo longitudes y formato).
     *
     * @return array
     */
    public function getCredentialAudit()
    {
        $client_id = (string) $this->client_id;
        $secret = (string) $this->client_secret;

        return [
            'client_id_length' => strlen($client_id),
            'client_id_is_hex32' => (bool) preg_match('/^[0-9a-f]{32}$/', $client_id),
            'secret_key_length' => strlen($secret),
            'secret_has_whitespace' => (bool) preg_match('/\s/', $secret),
            'secret_is_ascii' => ! (bool) preg_match('/[^\x20-\x7E]/', $secret),
        ];
    }

    /**
     * Construye un mensaje de error útil a partir de la respuesta de Yuju.
     */
    private function buildTokenErrorMessage($response)
    {
        $message = isset($response['message']) ? (string) $response['message'] : 'Error desconocido';

        if (stripos($message, 'invalid credential') !== false) {
            $message .= '. Verifica que el Client ID tenga 32 caracteres hexadecimales '
                . '(sin espacios, comillas ni mayúsculas) y que el Secret Key sea exactamente el de '
                . 'Yuju > Aplicaciones > Ver credenciales (sin espacios ni caracteres de más). '
                . 'Yuju devuelve este mismo error cuando el code ya se usó o caducó: el code es de un '
                . 'solo uso y vence en minutos, así que genera uno nuevo pulsando "Conectar" en Yuju '
                . 'y ábrelo una sola vez sin recargarlo.';
        } elseif (stripos($message, 'expired') !== false) {
            $message .= '. El code caduca: pulsa "Conectar" en Yuju y usa el enlace resultante sin recargarlo.';
        }

        return $message;
    }

    /**
     * Refresca el access token usando el refresh token.
     */
    public function refreshToken()
    {
        $oauth_data = $this->getStoredTokenData();

        if (!$oauth_data || !$oauth_data['refresh_token']) {
            throw new Exception('No hay refresh token disponible');
        }

        $data = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $oauth_data['refresh_token'],
        ];

        $response = $this->makeTokenRequest($data);

        if ($response['success']) {
            $this->saveTokenData($response['data']);
            $this->logger->log('info', 'OAuth token refrescado exitosamente');

            return true;
        } else {
            $this->logger->log('error', 'Error al refrescar OAuth token', $response);

            throw new Exception('Error al refrescar token: ' . $response['message']);
        }
    }

    /**
     * Obtiene un access token válido (refresca si es necesario).
     *
     * Yuju no documenta refresh ni expiración: si no hay refresh token o el
     * refresh falla, se devuelve el token guardado como último recurso (la
     * API dirá si sigue vigente) en vez de anularlo localmente.
     */
    public function getValidAccessToken()
    {
        $oauth_data = $this->getStoredTokenData();

        if (!$oauth_data || !$oauth_data['access_token']) {
            return null;
        }

        // Verificar si el token ha expirado
        if ($this->isTokenExpired($oauth_data)) {
            if (empty($oauth_data['refresh_token'])) {
                $this->logger->log('warning', 'Token con expiración pasada pero sin refresh token: se reutiliza el guardado', [
                    'token_expires' => $oauth_data['token_expires'],
                ]);

                return $oauth_data['access_token'];
            }

            try {
                $this->refreshToken();
                $oauth_data = $this->getStoredTokenData();

                if (!$oauth_data || !$oauth_data['access_token']) {
                    return null;
                }
            } catch (Exception $e) {
                $this->logger->log('warning', 'No se pudo refrescar el token: se reutiliza el guardado', ['error' => $e->getMessage()]);

                return $oauth_data['access_token'];
            }
        }

        return $oauth_data['access_token'];
    }

    /**
     * Verifica si el token ha expirado.
     */
    private function isTokenExpired($oauth_data)
    {
        // Yuju no documenta expiración del token: sin fecha de expiración se considera vigente
        if (empty($oauth_data['token_expires'])) {
            return false;
        }

        $expires_at = strtotime($oauth_data['token_expires']);
        $now = time();

        // Considerar expirado si faltan menos de 5 minutos
        return ($expires_at - $now) < 300;
    }

    /**
     * Realiza una petición para obtener token usando el endpoint específico de Yuju.
     */
    private function makeYujuTokenRequest($data)
    {
        $url = 'https://api.tp.yuju.io/auth-generate-token';

        $attempt_id = $this->logOAuthAttempt('start', [
            'url' => $url,
            'request_data' => $data,
            'client_id' => isset($data['client_id']) ? $data['client_id'] : '',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_VERBOSE => true,
        ]);

        // Capture verbose output for debugging
        $verbose_output = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose_output);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_info = curl_getinfo($ch);
        
        // Get verbose output
        rewind($verbose_output);
        $verbose_log = stream_get_contents($verbose_output);
        fclose($verbose_output);
        
        curl_close($ch);

        $result = [
            'http_code' => $http_code,
            'response_body' => $response_body,
            'curl_error' => $curl_error,
            'curl_info' => $curl_info,
            'verbose_log' => $verbose_log,
        ];

        if ($curl_error) {
            $result['success'] = false;
            $result['message'] = 'cURL Error: ' . $curl_error;
            $this->logOAuthAttempt('error', array_merge([
                'attempt_id' => $attempt_id,
                'url' => $url,
                'request_data' => $data,
                'client_id' => isset($data['client_id']) ? $data['client_id'] : '',
            ], $result));
            return $result;
        }

        $response_data = json_decode($response_body, true);

        if ($http_code >= 200 && $http_code < 300) {
            if (isset($response_data['token'])) {
                $result['success'] = true;
                $result['data'] = [
                    'access_token' => $response_data['token'],
                ];
                $this->logOAuthAttempt('success', array_merge([
                    'attempt_id' => $attempt_id,
                    'url' => $url,
                    'request_data' => $data,
                    'client_id' => isset($data['client_id']) ? $data['client_id'] : '',
                ], $result));
                return $result;
            } else {
                $result['success'] = false;
                $result['message'] = 'Token no encontrado en la respuesta';
                $this->logOAuthAttempt('error', array_merge([
                    'attempt_id' => $attempt_id,
                    'url' => $url,
                    'request_data' => $data,
                    'client_id' => isset($data['client_id']) ? $data['client_id'] : '',
                ], $result));
                return $result;
            }
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Error desconocido';
            $result['success'] = false;
            $result['message'] = $error_message;
            $this->logOAuthAttempt('error', array_merge([
                'attempt_id' => $attempt_id,
                'url' => $url,
                'request_data' => $data,
                'client_id' => isset($data['client_id']) ? $data['client_id'] : '',
            ], $result));
            return $result;
        }
    }

    /**
     * Realiza una petición para obtener o refrescar tokens (método legacy).
     */
    private function makeTokenRequest($data)
    {
        $url = $this->auth_url . '/oauth/token';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'CURL_ERROR',
                'message' => $curl_error,
            ];
        }

        $decoded_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON_DECODE_ERROR',
                'message' => 'Invalid JSON response',
            ];
        }

        $success = ($http_code >= 200 && $http_code < 300);

        return [
            'success' => $success,
            'http_code' => $http_code,
            'data' => $decoded_response,
            'error' => $success ? null : (isset($decoded_response['error']) ? $decoded_response['error'] : 'HTTP_' . $http_code),
            'message' => $success ? null : (isset($decoded_response['error_description']) ? $decoded_response['error_description'] : 'HTTP Error ' . $http_code),
        ];
    }

    /**
     * Guarda los datos del token en la base de datos.
     */
    private function saveTokenData($token_data)
    {
        $expires_at = null;

        if (isset($token_data['expires_in'])) {
            $expires_at = date('Y-m-d H:i:s', time() + (int) $token_data['expires_in']);
        }

        $oauth_record = $this->getStoredTokenData();

        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'access_token' => $token_data['access_token'],
            'refresh_token' => isset($token_data['refresh_token']) ? $token_data['refresh_token'] : ($oauth_record ? $oauth_record['refresh_token'] : null),
            'token_expires' => $expires_at,
            'scope' => isset($token_data['scope']) ? $token_data['scope'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($oauth_record) {
            // Actualizar registro existente
            $result = Db::getInstance()->update(
                'yuju_oauth_tokens',
                $data,
                'id = ' . (int) $oauth_record['id']
            );
        } else {
            // Crear nuevo registro
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert('yuju_oauth_tokens', $data);
        }

        if (!$result) {
            throw new Exception('Error al guardar datos de OAuth en la base de datos');
        }
    }

    /**
     * Obtiene los datos del token almacenados.
     */
    private function getStoredTokenData()
    {
        try {
            // Verificar si la tabla existe
            $tableExists = Db::getInstance()->executeS(
                "SHOW TABLES LIKE '" . _DB_PREFIX_ . "yuju_oauth_tokens'"
            );
            
            if (empty($tableExists)) {
                // Si la tabla no existe, retornar null
                return null;
            }
            
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_oauth_tokens` ORDER BY `id` DESC LIMIT 1';
            
            return Db::getInstance()->getRow($sql);
        } catch (Exception $e) {
            // Log del error y retornar null
            PrestaShopLogger::addLog('Error in getStoredTokenData: ' . $e->getMessage(), 3);
            return null;
        }
    }

    /**
     * Obtiene la URI de redirección.
     */
    public function getRedirectUri()
    {
        $useAlternative = Configuration::get('YUJU_USE_ALTERNATIVE_OAUTH_ROUTE', false);
        
        if ($useAlternative) {
            // Ruta alternativa: /yuju/oauth.php (archivo directo) o /yuju/oauth (amigable)
            return Context::getContext()->link->getBaseLink() . 'yuju/oauth';
        }
        
        // Ruta estándar: /module/prestashopyuju/oauth (amigable)
        return Context::getContext()->link->getModuleLink('prestashopyuju', 'oauth');
    }

    /**
     * Revoca el token actual.
     */
    public function revokeToken()
    {
        $oauth_data = $this->getStoredTokenData();

        if (!$oauth_data || !$oauth_data['access_token']) {
            return true; // No hay token que revocar
        }

        $data = [
            'token' => $oauth_data['access_token'],
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
        ];

        $url = $this->auth_url . '/oauth/revoke';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Eliminar datos locales independientemente del resultado
        $this->clearStoredTokenData();

        $this->logger->log('info', 'Token OAuth revocado', ['http_code' => $http_code]);

        return $http_code >= 200 && $http_code < 300;
    }

    /**
     * Elimina los datos del token almacenados.
     */
    private function clearStoredTokenData()
    {
        return Db::getInstance()->delete('yuju_oauth_tokens', '1=1');
    }

    /**
     * Verifica si OAuth está configurado.
     */
    public function isConfigured()
    {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * Verifica si hay un token válido.
     */
    public function hasValidToken()
    {
        return !empty($this->getValidAccessToken());
    }

    /**
     * Obtiene información del estado de OAuth.
     */
    public function getOAuthStatus()
    {
        $oauth_data = $this->getStoredTokenData();

        return [
            'configured' => $this->isConfigured(),
            'has_token' => !empty($oauth_data) && !empty($oauth_data['access_token']),
            'token_valid' => $this->hasValidToken(),
            'is_connected' => $this->hasValidToken(),
            'token_expires' => $oauth_data && isset($oauth_data['token_expires']) ? $oauth_data['token_expires'] : null,
            'scope' => $oauth_data && isset($oauth_data['scope']) ? $oauth_data['scope'] : null,
            'last_updated' => $oauth_data && isset($oauth_data['updated_at']) ? $oauth_data['updated_at'] : null,
        ];
    }

    /**
     * Actualiza las credenciales OAuth.
     *
     * Solo elimina el token guardado cuando las credenciales realmente
     * cambiaron: re-guardar los mismos valores conserva la autorización.
     *
     * @return bool true si las credenciales cambiaron (token eliminado)
     */
    public function updateCredentials($client_id, $client_secret)
    {
        $changed = $this->haveCredentialsChanged($client_id, $client_secret);

        $this->client_id = $this->normalizeClientId($client_id);
        $this->client_secret = trim((string) $client_secret);

        Configuration::updateValue('YUJU_CLIENT_ID', $this->client_id);
        Configuration::updateValue('YUJU_CLIENT_SECRET', $this->client_secret);

        if ($changed) {
            // Las credenciales cambiaron: el token guardado pertenece a las
            // anteriores, así que se limpia para forzar una re-autorización.
            $this->clearStoredTokenData();
            $this->logger->log('info', 'Credenciales OAuth actualizadas (token anterior eliminado: re-autoriza la app)');
        } else {
            $this->logger->log('info', 'Credenciales OAuth re-guardadas sin cambios (token conservado)');
        }

        return $changed;
    }

    /**
     * Indica si unas credenciales difieren de las guardadas (normalizadas).
     *
     * @return bool
     */
    public function haveCredentialsChanged($client_id, $client_secret)
    {
        return $this->normalizeClientId($this->client_id) !== $this->normalizeClientId($client_id)
            || $this->client_secret !== trim((string) $client_secret);
    }

    /**
     * Obtiene los scopes disponibles.
     */
    public function getAvailableScopes()
    {
        return [
            'read' => 'Lectura de datos',
            'write' => 'Escritura de datos',
            'products' => 'Gestión de productos',
            'orders' => 'Gestión de órdenes',
            'webhooks' => 'Gestión de webhooks',
        ];
    }

    /**
     * Registra un intento de OAuth en archivo de log (JSON).
     *
     * El identificador del intento es una cadena; si ya existe un archivo para
     * ese intento se actualiza (merge) en lugar de crear uno nuevo, de forma que
     * la respuesta final conserve el request registrado al iniciar.
     */
    private function logOAuthAttempt($status, $data)
    {
        try {
            $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/oauth_attempts/';
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }

            $attempt_id = isset($data['attempt_id']) && $data['attempt_id'] !== ''
                ? (string) $data['attempt_id']
                : uniqid('oauth_', true);

            $filename = $log_dir . $attempt_id . '.json';

            $request_data = isset($data['request_data']) && is_array($data['request_data'])
                ? $this->maskSensitiveData($data['request_data'])
                : [];

            $record = [
                'attempt_id' => $attempt_id,
                'status' => $status,
                'client_id' => isset($data['client_id']) && $data['client_id'] !== ''
                    ? substr((string) $data['client_id'], 0, 100)
                    : substr((string) ($request_data['client_id'] ?? ''), 0, 100),
                'url' => $data['url'] ?? '',
                'request_data' => $request_data,
                'response_body' => $data['response_body'] ?? '',
                'http_code' => $data['http_code'] ?? 0,
                'curl_error' => $data['curl_error'] ?? '',
                'curl_info' => $data['curl_info'] ?? [],
                'verbose_log' => $data['verbose_log'] ?? '',
                'message' => $data['message'] ?? '',
                'credentials' => $this->getCredentialDiagnostics($data),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // Si ya existe un registro para este intento, fusionar para no perder
            // la información capturada al iniciar la petición.
            if (file_exists($filename)) {
                $previous = json_decode((string) file_get_contents($filename), true);

                if (is_array($previous)) {
                    $record['created_at'] = $previous['created_at'] ?? $record['created_at'];
                    $record['request_data'] = !empty($record['request_data'])
                        ? $record['request_data']
                        : ($previous['request_data'] ?? []);
                    // Los registros de resultado a veces llegan sin el contexto del
                    // request: conservar url, client_id y diagnóstico previos.
                    if (empty($record['url']) && !empty($previous['url'])) {
                        $record['url'] = $previous['url'];
                    }
                    if (empty($record['client_id']) && !empty($previous['client_id'])) {
                        $record['client_id'] = $previous['client_id'];
                    }
                    if (isset($previous['credentials']) && is_array($previous['credentials'])) {
                        $prev_client_len = (int) ($previous['credentials']['client_id_length'] ?? 0);
                        $new_client_len = (int) ($record['credentials']['client_id_length'] ?? 0);
                        if ($new_client_len === 0 && $prev_client_len > 0) {
                            $record['credentials'] = $previous['credentials'];
                        }
                    }
                }
            }

            file_put_contents($filename, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $attempt_id;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Oculta valores sensibles antes de escribirlos en el log.
     */
    private function maskSensitiveData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach (['secret_key', 'client_secret', 'secret'] as $sensitive_key) {
            if (isset($data[$sensitive_key]) && is_string($data[$sensitive_key]) && $data[$sensitive_key] !== '') {
                $data[$sensitive_key] = substr($data[$sensitive_key], 0, 4) . '***';
            }
        }

        return $data;
    }

    /**
     * Devuelve un resumen de diagnóstico de las credenciales usadas.
     */
    private function getCredentialDiagnostics($data)
    {
        $request_data = isset($data['request_data']) && is_array($data['request_data'])
            ? $data['request_data']
            : [];

        $client_id = (string) ($data['client_id'] ?? ($request_data['client_id'] ?? ''));
        $secret = (string) ($data['request_data']['secret_key'] ?? '');

        return [
            'client_id_length' => strlen($client_id),
            'client_id_is_hex32' => (bool) preg_match('/^[0-9a-f]{32}$/', $client_id),
            'secret_key_length' => strlen($secret),
        ];
    }

    /**
     * Obtiene el historial de intentos de OAuth.
     */
    public function getOAuthAttempts($limit = 20)
    {
        try {
            $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/oauth_attempts/';
            if (!is_dir($log_dir)) {
                return [];
            }

            $files = glob($log_dir . 'oauth_*.json');
            if (empty($files)) {
                $files = glob($log_dir . '*.json');
            }

            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $attempts = [];
            foreach (array_slice($files, 0, $limit) as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if ($data) {
                    $attempts[] = [
                        'id' => $data['attempt_id'] ?? basename($file, '.json'),
                        'status' => $data['status'] ?? 'unknown',
                        'client_id' => $data['client_id'] ?? '',
                        'http_code' => $data['http_code'] ?? 0,
                        'message' => $data['message'] ?? '',
                        'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s', filemtime($file)),
                    ];
                }
            }

            return $attempts;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene un intento específico con todos sus detalles.
     */
    public function getOAuthAttemptDetail($id)
    {
        try {
            $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/oauth_attempts/';

            // Sanitizar el identificador para evitar path traversal
            $id = preg_replace('/[^A-Za-z0-9_.\-]/', '', (string) $id);

            if ($id === '') {
                return null;
            }

            // Buscar por attempt_id en el nombre del archivo
            $files = glob($log_dir . $id . '.json');
            if (empty($files)) {
                $files = glob($log_dir . 'oauth_' . $id . '.json');
            }
            if (empty($files)) {
                // Buscar en todos los archivos
                $all_files = glob($log_dir . '*.json');
                foreach ($all_files as $file) {
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);
                    if ($data && ($data['attempt_id'] ?? '') === $id) {
                        $files = [$file];
                        break;
                    }
                }
            }

            if (empty($files)) {
                return null;
            }

            $file = $files[0];
            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if ($data) {
                $data['request_data_decoded'] = $data['request_data'] ?? [];
                $data['curl_info_decoded'] = $data['curl_info'] ?? [];
                $data['response_data_decoded'] = $data['response_body'] ? json_decode($data['response_body'], true) : null;
            }

            return $data;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Limpia intentos antiguos (más de X días).
     */
    public function cleanOldOAuthAttempts($days = 30)
    {
        try {
            $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/oauth_attempts/';
            if (!is_dir($log_dir)) {
                return false;
            }

            $files = glob($log_dir . '*.json');
            $cutoff = time() - ($days * 86400);
            $deleted = 0;

            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    unlink($file);
                    $deleted++;
                }
            }

            return $deleted > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
