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

class YujuOAuth
{
    public const SANDBOX_AUTH_URL = 'https://auth-sandbox.yuju.io';
    public const PRODUCTION_AUTH_URL = 'https://auth.yuju.io';

    private $auth_url;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $logger;

    public function __construct()
    {
        $environment = Configuration::get('YUJU_API_ENVIRONMENT', null) ?: 'sandbox';
        $this->auth_url = ($environment === 'production') ? self::PRODUCTION_AUTH_URL : self::SANDBOX_AUTH_URL;

        $this->client_id = Configuration::get('YUJU_API_CLIENT_ID');
        $this->client_secret = Configuration::get('YUJU_API_CLIENT_SECRET');
        $this->redirect_uri = $this->getRedirectUri();
        $this->logger = new YujuLogger();
    }

    /**
     * Genera la URL de autorización para OAuth2.
     */
    public function getAuthorizationUrl($state = null)
    {
        if (!$this->client_id) {
            throw new Exception('Client ID no configurado');
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'read write',
            'state' => $state ?: $this->generateState(),
        ];

        return $this->auth_url . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Intercambia el código de autorización por un access token.
     */
    public function exchangeCodeForToken($code, $state = null)
    {
        if (!$this->client_id || !$this->client_secret) {
            throw new Exception('Credenciales OAuth no configuradas');
        }

        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'redirect_uri' => $this->redirect_uri,
        ];

        $response = $this->makeTokenRequest($data);

        if ($response['success']) {
            $this->saveTokenData($response['data']);
            $this->logger->log('info', 'OAuth token obtenido exitosamente', ['state' => $state]);

            return true;
        } else {
            $this->logger->log('error', 'Error al obtener OAuth token', $response);

            throw new Exception('Error al obtener token: ' . $response['message']);
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
     */
    public function getValidAccessToken()
    {
        $oauth_data = $this->getStoredTokenData();

        if (!$oauth_data || !$oauth_data['access_token']) {
            return null;
        }

        // Verificar si el token ha expirado
        if ($this->isTokenExpired($oauth_data)) {
            try {
                $this->refreshToken();
                $oauth_data = $this->getStoredTokenData();
            } catch (Exception $e) {
                $this->logger->log('error', 'No se pudo refrescar el token', ['error' => $e->getMessage()]);

                return null;
            }
        }

        return $oauth_data['access_token'];
    }

    /**
     * Verifica si el token ha expirado.
     */
    private function isTokenExpired($oauth_data)
    {
        if (!isset($oauth_data['token_expires'])) {
            return true;
        }

        $expires_at = strtotime($oauth_data['token_expires']);
        $now = time();

        // Considerar expirado si faltan menos de 5 minutos
        return ($expires_at - $now) < 300;
    }

    /**
     * Realiza una petición para obtener o refrescar tokens.
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
                'yuju_oauth',
                $data,
                'id_oauth = ' . (int) $oauth_record['id_oauth']
            );
        } else {
            // Crear nuevo registro
            $data['created_at'] = date('Y-m-d H:i:s');
            $result = Db::getInstance()->insert('yuju_oauth', $data);
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
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_oauth` ORDER BY id_oauth DESC LIMIT 1';

        return Db::getInstance()->getRow($sql);
    }

    /**
     * Genera un estado aleatorio para OAuth.
     */
    private function generateState()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Obtiene la URI de redirección.
     */
    private function getRedirectUri()
    {
        $link = new Link();

        return $link->getModuleLink('prestashopyuju', 'oauth', [], true);
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
        return Db::getInstance()->delete('yuju_oauth', '1=1');
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
            'has_token' => !empty($oauth_data['access_token']),
            'token_valid' => $this->hasValidToken(),
            'token_expires' => $oauth_data ? $oauth_data['token_expires'] : null,
            'scope' => $oauth_data ? $oauth_data['scope'] : null,
            'last_updated' => $oauth_data ? $oauth_data['updated_at'] : null,
        ];
    }

    /**
     * Actualiza las credenciales OAuth.
     */
    public function updateCredentials($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        Configuration::updateValue('YUJU_API_CLIENT_ID', $client_id);
        Configuration::updateValue('YUJU_API_CLIENT_SECRET', $client_secret);

        // Si las credenciales cambian, limpiar tokens existentes
        $this->clearStoredTokenData();

        $this->logger->log('info', 'Credenciales OAuth actualizadas');
    }

    /**
     * Valida el estado OAuth recibido.
     */
    public function validateState($received_state, $expected_state)
    {
        return hash_equals($expected_state, $received_state);
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
}
