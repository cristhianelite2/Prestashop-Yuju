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


class YujuOAuth extends ObjectModel
{
    public const SANDBOX_AUTH_URL = 'https://auth-sandbox.yuju.io';
    public const PRODUCTION_AUTH_URL = 'https://auth.yuju.io';
    private const TOKEN_ENDPOINT = 'https://api.tp.yuju.io/auth-generate-token';
    private const TOKEN_EXPIRY_BUFFER = 300; // 5 minutos

    private $auth_url;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $logger;

    public function __construct()
    {
        $environment = YujuConfig::get('YUJU_ENVIRONMENT', 'sandbox');
        $this->auth_url = ($environment === 'production') ? self::PRODUCTION_AUTH_URL : self::SANDBOX_AUTH_URL;
        $this->logger = new YujuLogger();
        
        $this->client_id = YujuConfig::get('YUJU_CLIENT_ID');
        $this->client_secret = YujuConfig::get('YUJU_CLIENT_SECRET');
        $this->redirect_uri = $this->getRedirectUri();

        // Compatibilidad con configuraciones legacy: algunas pantallas guardan
        // en llaves YUJU_API_* o en Configuration de PrestaShop.
        if (empty($this->client_id) || empty($this->client_secret)) {
            $legacy_client_id = YujuConfig::get('YUJU_API_CLIENT_ID');
            $legacy_client_secret = YujuConfig::get('YUJU_API_CLIENT_SECRET');

            if (!empty($legacy_client_id) && !empty($legacy_client_secret)) {
                $this->client_id = $legacy_client_id;
                $this->client_secret = $legacy_client_secret;
            }
        }

        if ((empty($this->client_id) || empty($this->client_secret)) && class_exists('Configuration')) {
            $config_client_id = Configuration::get('YUJU_CLIENT_ID');
            $config_client_secret = Configuration::get('YUJU_CLIENT_SECRET');

            if (!empty($config_client_id) && !empty($config_client_secret)) {
                $this->client_id = $config_client_id;
                $this->client_secret = $config_client_secret;
            }
        }

        // Fallback: si YujuConfig no tiene credenciales, intentar recuperarlas desde la tabla OAuth.
        if (empty($this->client_id) || empty($this->client_secret)) {
            try {
                $row = Db::getInstance()->getRow(
                    'SELECT client_id, client_secret
                     FROM `' . _DB_PREFIX_ . 'yuju_oauth_tokens`
                     ORDER BY id DESC'
                );

                if (!empty($row['client_id']) && !empty($row['client_secret'])) {
                    $this->client_id = $row['client_id'];
                    $this->client_secret = $row['client_secret'];

                    // Persistir en múltiples llaves para evitar fallos futuros en OAuth.
                    $this->persistCredentials($this->client_id, $this->client_secret);
                }
            } catch (Exception $e) {
                $this->logger->log('error', 'No se pudieron recuperar credenciales OAuth desde BD', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($this->client_id) && !empty($this->client_secret)) {
            $this->persistCredentials($this->client_id, $this->client_secret);
        }
    }

    /**
     * Genera la URL de autorización para OAuth2.
     */
    public function getAuthorizationUrl($state = null)
    {
        if (!$this->client_id) {
            throw new Exception('Client ID no configurado');
        }

        $oauth_state = $state ?: $this->generateState();
        YujuConfig::set('YUJU_OAUTH_STATE', $oauth_state);
        YujuConfig::set('YUJU_OAUTH_PENDING_CLIENT_ID', $this->client_id);
        YujuConfig::set('YUJU_OAUTH_PENDING_CLIENT_SECRET', $this->client_secret);

        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'state' => $oauth_state,
        ];

        return self::TOKEN_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * Intercambia el código de autorización por un access token.
     */
    public function exchangeCodeForToken($code, $state = null)
    {
        $this->hydrateCredentialsFromStorage();

        if (!$this->isConfigured()) {
            $diagnostics = $this->buildCredentialDiagnostics();
            $this->logger->log('error', 'Credenciales OAuth no configuradas durante callback', $diagnostics);
            return $this->createErrorResponse('Credenciales OAuth no configuradas', null, [
                'credential_diagnostics' => $diagnostics,
            ]);
        }

        $data = [
            'client_id' => $this->client_id,
            'secret_key' => $this->client_secret,
            'code' => $code,
        ];

        try {
            $response = $this->makeTokenRequest($data);

            if ($response['success']) {
                $this->saveTokenData($response['data']);
                $this->logger->log('info', 'OAuth token obtenido exitosamente');
                
                return [
                    'success' => true,
                    'message' => 'Token obtenido exitosamente',
                    'data' => $response['data']
                ];
            }

            $this->logger->log('error', 'Error al obtener OAuth token', $response);
            return $this->createErrorResponse($response['message'] ?? 'Error desconocido al obtener token');

        } catch (Exception $e) {
            $this->logger->log('error', 'Excepción al obtener OAuth token', ['error' => $e->getMessage()]);
            return $this->createErrorResponse('Error al obtener token: ' . $e->getMessage());
        }
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

        $response = $this->makeLegacyTokenRequest($data);

        if ($response['success']) {
            $this->saveTokenData($response['data']);
            $this->logger->log('info', 'OAuth token refrescado exitosamente');
            return true;
        }

        $this->logger->log('error', 'Error al refrescar OAuth token', $response);
        throw new Exception('Error al refrescar token: ' . $response['message']);
    }

    /**
     * Intenta refrescar el token automáticamente cuando expire o falle.
     * Nota: La API de Yuju no usa refresh tokens tradicionales, por lo que
     * esta función notifica al administrador que debe reconectar.
     */
    public function attemptTokenRefresh()
    {
        $this->logger->log('warning', 'Token expirado o inválido. La API de Yuju requiere reconexión manual.');
        
        // Marcar el token como expirado
        $oauth_data = $this->getStoredTokenData();
        if ($oauth_data) {
            $table_name = 'yuju_oauth_tokens';
            Db::getInstance()->update(
                $table_name,
                [
                    'token_expires' => date('Y-m-d H:i:s', time() - 3600),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ' . (int) $oauth_data['id']
            );
        }
        
        // Registrar evento para notificar al administrador
        $this->logger->log('error', 'Token expirado. Se requiere reconexión en Configuración > Aplicaciones de Yuju');
        
        return false;
    }

    /**
     * Verifica si el token necesita ser renovado pronto.
     * @param int $threshold_seconds Segundos antes de expiración para considerar renovación
     * @return bool
     */
    public function needsRenewal($threshold_seconds = 86400)
    {
        $oauth_data = $this->getStoredTokenData();
        
        if (empty($oauth_data) || !is_array($oauth_data)) {
            return true;
        }

        $expires_field = $oauth_data['token_expires'] ?? $oauth_data['expires_at'] ?? null;
        
        if (!$expires_field) {
            return false;
        }

        $expires_at = strtotime($expires_field);
        if ($expires_at === false) {
            return true;
        }

        return (time() + $threshold_seconds) >= $expires_at;
    }

    /**
     * Obtiene un access token válido (refresca si es necesario).
     */
    public function getValidAccessToken()
    {
        try {
            $oauth_data = $this->getStoredTokenData();
            
            if (empty($oauth_data) || empty($oauth_data['access_token'])) {
                $this->logger->log('error', 'No se encontraron datos de OAuth válidos');
                return null;
            }

            $token = $oauth_data['access_token'];
            
            // Log detallado del token para debugging
            $this->logger->log('info', 'Token recuperado de BD', [
                'token_length' => strlen($token),
                'token_preview' => strlen($token) > 20 
                    ? substr($token, 0, 10) . '...' . substr($token, -10)
                    : $token,
                'is_base64_pattern' => (bool)preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $token),
                'has_special_chars' => (bool)preg_match('/[^A-Za-z0-9+\/=]/', $token),
                'token_expires' => $oauth_data['token_expires'] ?? 'N/A'
            ]);

            // NO intentar refrescar automáticamente - usar el token actual siempre
            // La API de Yuju no soporta refresh automático, requiere reconexión manual
            if ($this->isTokenExpired($oauth_data)) {
                $this->logger->log('warning', 'Token expirado - se requiere reconexión manual en la configuración');
                // Aún así, devolver el token para que se intente usar
                // Si falla, el usuario tendrá que reconectar manualmente
            }

            // Siempre devolver el access_token almacenado sin modificarlo
            return $token;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Error obteniendo token válido', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verifica si el token ha expirado.
     */
    public function isTokenExpired($oauth_data)
    {
        if (empty($oauth_data) || !is_array($oauth_data)) {
            return true;
        }

        $expires_field = $oauth_data['token_expires'] ?? $oauth_data['expires_at'] ?? null;
        
        if (!$expires_field) {
            return false; // Sin fecha de expiración, asumimos válido
        }

        $expires_at = strtotime($expires_field);
        if ($expires_at === false) {
            return true;
        }

        return (time() + self::TOKEN_EXPIRY_BUFFER) >= $expires_at;
    }

    /**
     * Realiza una petición para obtener token usando el endpoint específico de Yuju.
     */
    protected function makeTokenRequest($data)
    {
        $this->logger->log('info', 'Iniciando petición de token a Yuju');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::TOKEN_ENDPOINT,
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
        ]);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            return $this->createErrorResponse('cURL Error: ' . $curl_error);
        }

        $response_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->createErrorResponse('Error al decodificar respuesta JSON');
        }

        if ($http_code >= 200 && $http_code < 300 && isset($response_data['token'])) {
            return [
                'success' => true,
                'data' => [
                    'access_token' => $response_data['token'],
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'scope' => 'read write',
                ],
            ];
        }

        $error_message = $response_data['message'] ?? 'Error desconocido';
        return $this->createErrorResponse($error_message, $http_code);
    }

    /**
     * Realiza una petición para refrescar tokens (método legacy).
     */
    protected function makeLegacyTokenRequest($data)
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
        ]);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            return $this->createErrorResponse($curl_error);
        }

        $decoded_response = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->createErrorResponse('Invalid JSON response');
        }

        $success = ($http_code >= 200 && $http_code < 300);
        
        return [
            'success' => $success,
            'data' => $decoded_response,
            'message' => $success ? null : ($decoded_response['error_description'] ?? 'HTTP Error ' . $http_code),
        ];
    }

    /**
     * Guarda los datos del token en la base de datos.
     */
    protected function saveTokenData($token_data)
    {
        // Configurar fecha de expiración muy lejana (10 años) para que nunca expire
        // Yuju envía expires_in pero queremos mantener el token activo permanentemente
        $expires_at = date('Y-m-d H:i:s', time() + (10 * 365 * 24 * 3600)); // 10 años

        $oauth_record = $this->getStoredTokenData();
        $table_name = $this->getOAuthTableName();

        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'] ?? ($oauth_record['refresh_token'] ?? null),
            'token_expires' => $expires_at,
            'scope' => $token_data['scope'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($oauth_record) {
            $result = Db::getInstance()->update(
                str_replace(_DB_PREFIX_, '', $table_name),
                $data,
                'id = ' . (int) $oauth_record['id']
            );
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert(str_replace(_DB_PREFIX_, '', $table_name), $data);
        }

        if (!$result) {
            throw new Exception('Error al guardar datos de OAuth en la base de datos');
        }
        
        $this->logger->log('info', 'Token guardado con expiración extendida', [
            'expires_at' => $expires_at,
            'note' => 'Token configurado para no expirar (10 años)'
        ]);
    }

    /**
     * Obtiene los datos del token almacenados.
     */
    public function getStoredTokenData()
    {
        try {
            $table_name = $this->getOAuthTableName();
            if (!$table_name) {
                return null;
            }
            $table_name = 'yuju_oauth_tokens';
            //$sql = "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 1";
            $subquery = "SELECT MAX(id) as max_id FROM `" . _DB_PREFIX_ . pSQL($table_name) . "`";
            $maxIdResult = Db::getInstance()->getRow($subquery);
            
            if (!$maxIdResult || !$maxIdResult['max_id']) {
                $this->logger->log('debug', 'No records found in table', [
                    'table' => $table_name
                ]);
                return false;
            }
            
            $sql = "SELECT * FROM `" . _DB_PREFIX_ . pSQL($table_name) . "` WHERE id = " . (int)$maxIdResult['max_id'];
            $this->logger->log('debug', 'Executing SQL query ', [
                'query' => $sql,
                'table' => $table_name
            ]);
            $result = Db::getInstance()->getRow($sql);
            $this->logger->log('debug', 'Query result', [
                'result' => $result ? 'Found record' : 'No records found'
            ]);
            return $result;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Error en getStoredTokenData', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Obtiene el nombre de la tabla OAuth disponible.
     */
    private function getOAuthTableName()
    {
        $possible_tables = [
            _DB_PREFIX_ . 'yuju_oauth_tokens',
        ];

        foreach ($possible_tables as $table) {
            $check_sql = "SHOW TABLES LIKE '{$table}'";
            if (!empty(Db::getInstance()->executeS($check_sql))) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Revoca el token actual.
     */
    public function revokeToken()
    {
        $oauth_data = $this->getStoredTokenData();

        if ($oauth_data && $oauth_data['access_token']) {
            $data = [
                'token' => $oauth_data['access_token'],
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->auth_url . '/oauth/revoke',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        $this->clearStoredTokenData();
        return true;
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
        $has_token = !empty($oauth_data) && !empty($oauth_data['access_token']);

        return [
            'configured' => $this->isConfigured(),
            'has_token' => $has_token,
            'token_valid' => $this->hasValidToken(),
            'is_connected' => $this->hasValidToken(),
            'token_expires' => $oauth_data['token_expires'] ?? null,
            'scope' => $oauth_data['scope'] ?? null,
            'last_updated' => $oauth_data['updated_at'] ?? null,
        ];
    }

    /**
     * Actualiza las credenciales OAuth.
     */
    public function updateCredentials($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        YujuConfig::set('YUJU_CLIENT_ID', $client_id);
        YujuConfig::set('YUJU_CLIENT_SECRET', $client_secret);

        $this->clearStoredTokenData();
        $this->logger->log('info', 'Credenciales OAuth actualizadas');
    }

    /**
     * Valida el estado OAuth recibido.
     */
    public function validateState($received_state, $expected_state)
    {
        return is_string($received_state) && 
               is_string($expected_state) && 
               !empty($received_state) && 
               !empty($expected_state) &&
               hash_equals($expected_state, $received_state);
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

    // Métodos auxiliares privados

    private function generateState()
    {
        return bin2hex(random_bytes(16));
    }

    private function getRedirectUri()
    {
        $link = new Link();
        return $link->getModuleLink('prestashopyuju', 'oauth', [], true);
    }

    private function createErrorResponse($message, $http_code = null, $debug = null)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($http_code) {
            $response['http_code'] = $http_code;
        }

        if (is_array($debug) && !empty($debug)) {
            $response['debug'] = $debug;
        }
        
        return $response;
    }

    private function buildCredentialDiagnostics()
    {
        $source_config = [
            'yuju_client_id' => YujuConfig::get('YUJU_CLIENT_ID'),
            'yuju_client_secret' => YujuConfig::get('YUJU_CLIENT_SECRET'),
            'yuju_api_client_id' => YujuConfig::get('YUJU_API_CLIENT_ID'),
            'yuju_api_client_secret' => YujuConfig::get('YUJU_API_CLIENT_SECRET'),
            'yuju_oauth_pending_client_id' => YujuConfig::get('YUJU_OAUTH_PENDING_CLIENT_ID'),
            'yuju_oauth_pending_client_secret' => YujuConfig::get('YUJU_OAUTH_PENDING_CLIENT_SECRET'),
        ];

        $source_ps = [
            'yuju_client_id' => class_exists('Configuration') ? Configuration::get('YUJU_CLIENT_ID') : null,
            'yuju_client_secret' => class_exists('Configuration') ? Configuration::get('YUJU_CLIENT_SECRET') : null,
        ];

        $source_custom_table = [
            'yuju_client_id' => null,
            'yuju_client_secret' => null,
            'yuju_api_client_id' => null,
            'yuju_api_client_secret' => null,
        ];

        $source_oauth_table = [
            'client_id' => null,
            'client_secret' => null,
        ];

        try {
            $source_custom_table['yuju_client_id'] = Db::getInstance()->getValue(
                'SELECT `config_value` FROM `' . _DB_PREFIX_ . 'yuju_configuration` WHERE `config_key` = \'YUJU_CLIENT_ID\''
            );
            $source_custom_table['yuju_client_secret'] = Db::getInstance()->getValue(
                'SELECT `config_value` FROM `' . _DB_PREFIX_ . 'yuju_configuration` WHERE `config_key` = \'YUJU_CLIENT_SECRET\''
            );
            $source_custom_table['yuju_api_client_id'] = Db::getInstance()->getValue(
                'SELECT `config_value` FROM `' . _DB_PREFIX_ . 'yuju_configuration` WHERE `config_key` = \'YUJU_API_CLIENT_ID\''
            );
            $source_custom_table['yuju_api_client_secret'] = Db::getInstance()->getValue(
                'SELECT `config_value` FROM `' . _DB_PREFIX_ . 'yuju_configuration` WHERE `config_key` = \'YUJU_API_CLIENT_SECRET\''
            );
        } catch (Exception $e) {
            $source_custom_table['error'] = $e->getMessage();
        }

        try {
            $oauth_row = Db::getInstance()->getRow(
                'SELECT client_id, client_secret FROM `' . _DB_PREFIX_ . 'yuju_oauth_tokens` ORDER BY id DESC'
            );
            if (is_array($oauth_row)) {
                $source_oauth_table['client_id'] = $oauth_row['client_id'] ?? null;
                $source_oauth_table['client_secret'] = $oauth_row['client_secret'] ?? null;
            }
        } catch (Exception $e) {
            $source_oauth_table['error'] = $e->getMessage();
        }

        return [
            'runtime' => [
                'is_configured' => $this->isConfigured(),
                'client_id_current' => $this->maskValue($this->client_id),
                'client_secret_current' => $this->maskValue($this->client_secret),
                'client_id_length' => is_string($this->client_id) ? strlen($this->client_id) : 0,
                'client_secret_length' => is_string($this->client_secret) ? strlen($this->client_secret) : 0,
            ],
            'sources' => [
                'yuju_config' => $this->summarizeSource($source_config),
                'prestashop_configuration' => $this->summarizeSource($source_ps),
                'yuju_configuration_table' => $this->summarizeSource($source_custom_table),
                'yuju_oauth_tokens_table' => $this->summarizeSource($source_oauth_table),
            ],
        ];
    }

    private function summarizeSource($values)
    {
        $summary = [];

        foreach ($values as $key => $value) {
            if ($key === 'error') {
                $summary[$key] = $value;
                continue;
            }

            $summary[$key] = [
                'exists' => !empty($value),
                'length' => is_string($value) ? strlen($value) : 0,
                'masked' => $this->maskValue($value),
            ];
        }

        return $summary;
    }

    private function maskValue($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $len = strlen($value);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 3) . str_repeat('*', $len - 6) . substr($value, -3);
    }

    private function persistCredentials($client_id, $client_secret)
    {
        YujuConfig::set('YUJU_CLIENT_ID', $client_id);
        YujuConfig::set('YUJU_CLIENT_SECRET', $client_secret);
        YujuConfig::set('YUJU_API_CLIENT_ID', $client_id);
        YujuConfig::set('YUJU_API_CLIENT_SECRET', $client_secret);

        if (class_exists('Configuration')) {
            Configuration::updateValue('YUJU_CLIENT_ID', $client_id);
            Configuration::updateValue('YUJU_CLIENT_SECRET', $client_secret);
        }
    }

    private function hydrateCredentialsFromStorage()
    {
        if (!empty($this->client_id) && !empty($this->client_secret)) {
            return;
        }

        try {
            // Fallback robusto sin depender del contexto de tienda.
            $id_from_ps = Db::getInstance()->getValue(
                'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'YUJU_CLIENT_ID\' ORDER BY `id_configuration` DESC'
            );
            $secret_from_ps = Db::getInstance()->getValue(
                'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'YUJU_CLIENT_SECRET\' ORDER BY `id_configuration` DESC'
            );

            if (empty($id_from_ps) || empty($secret_from_ps)) {
                $id_from_custom = Db::getInstance()->getValue(
                    'SELECT `config_value` FROM `' . _DB_PREFIX_ . 'yuju_configuration` WHERE `config_key` IN (\'YUJU_CLIENT_ID\', \'YUJU_API_CLIENT_ID\') ORDER BY `updated_at` DESC'
                );
                $secret_from_custom = Db::getInstance()->getValue(
                    'SELECT `config_value` FROM `' . _DB_PREFIX_ . 'yuju_configuration` WHERE `config_key` IN (\'YUJU_CLIENT_SECRET\', \'YUJU_API_CLIENT_SECRET\') ORDER BY `updated_at` DESC'
                );

                if (!empty($id_from_custom) && !empty($secret_from_custom)) {
                    $id_from_ps = $id_from_custom;
                    $secret_from_ps = $secret_from_custom;
                }
            }

            if (empty($id_from_ps) || empty($secret_from_ps)) {
                $pending_id = YujuConfig::get('YUJU_OAUTH_PENDING_CLIENT_ID');
                $pending_secret = YujuConfig::get('YUJU_OAUTH_PENDING_CLIENT_SECRET');

                if (!empty($pending_id) && !empty($pending_secret)) {
                    $id_from_ps = $pending_id;
                    $secret_from_ps = $pending_secret;
                }
            }

            if (!empty($id_from_ps) && !empty($secret_from_ps)) {
                $this->client_id = $id_from_ps;
                $this->client_secret = $secret_from_ps;
                $this->persistCredentials($this->client_id, $this->client_secret);
            }
        } catch (Exception $e) {
            $this->logger->log('warning', 'No se pudo hidratar credenciales OAuth desde storage', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}