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

require_once dirname(__FILE__) . '/YujuOAuth.php';
require_once dirname(__FILE__) . '/YujuLogger.php';

class YujuApiClient
{
    public const SANDBOX_BASE_URL = 'https://api.tp.yuju.io';
    public const PRODUCTION_BASE_URL = 'https://api.tp.yuju.io';

    private $base_url;
    private $oauth;
    private $logger;
    private $timeout = 30;
    private $max_retries = 3;

    public function __construct()
    {
        $environment = Configuration::get('YUJU_ENVIRONMENT') ?: 'sandbox';
        $this->base_url = ($environment === 'production') ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
        $this->oauth = new YujuOAuth();
        $this->logger = new YujuLogger();
    }

    /**
     * Realiza una petición GET a la API de Yuju.
     */
    public function get($endpoint, $params = [])
    {
        return $this->makeRequest('GET', $endpoint, null, $params);
    }

    /**
     * Realiza una petición POST a la API de Yuju.
     */
    public function post($endpoint, $data = [])
    {
        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Realiza una petición PUT a la API de Yuju.
     */
    public function put($endpoint, $data = [])
    {
        return $this->makeRequest('PUT', $endpoint, $data);
    }

    /**
     * Realiza una petición DELETE a la API de Yuju.
     */
    public function delete($endpoint)
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Método principal para realizar peticiones HTTP.
     */
    private function makeRequest($method, $endpoint, $data = null, $params = [])
    {
        $url = $this->buildUrl($endpoint, $params);
        $headers = $this->getHeaders();

        $this->logger->info('Making API request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => $url,
            'headers' => $headers,
            'data' => $data,
            'params' => $params
        ]);

        $start_time = microtime(true);
        $retry_count = 0;

        do {
            $response = $this->executeRequest($method, $url, $headers, $data);

            $this->logger->info('API response received', [
                'method' => $method,
                'endpoint' => $endpoint,
                'retry_count' => $retry_count,
                'http_code' => $response['http_code'] ?? 'unknown',
                'success' => $response['success'] ?? false,
                'response' => $response
            ]);

            // Un error de autenticación no se resuelve reintentando: se corta
            // el backoff para responder rápido (y poder revalidar credenciales).
            $http_code = (int) ($response['http_code'] ?? 0);

            if ($response['success']
                || $retry_count >= $this->max_retries
                || $http_code === 401
                || $http_code === 403
            ) {
                break;
            }

            ++$retry_count;
            $this->logger->warning('Request failed, retrying', [
                'retry_count' => $retry_count,
                'max_retries' => $this->max_retries
            ]);
            sleep(pow(2, $retry_count)); // Exponential backoff
        } while ($retry_count <= $this->max_retries);

        $execution_time = microtime(true) - $start_time;

        // Log de la petición
        $this->logRequest($method, $endpoint, $data, $response, $execution_time);

        YujuMonitor::emit('api.request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'httpStatus' => (int) ($response['http_code'] ?? 0),
            'status' => !empty($response['success']) ? 'success' : 'error',
            'durationMs' => (int) round($execution_time * 1000),
        ]);

        return $response;
    }

    /**
     * Ejecuta la petición HTTP usando cURL.
     */
    private function executeRequest($method, $url, $headers, $data = null)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);

                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'CURL_ERROR',
                'message' => $curl_error,
                'http_code' => 0,
                'data' => null,
            ];
        }

        $decoded_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON_DECODE_ERROR',
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'http_code' => $http_code,
                'data' => $response_body,
            ];
        }

        $success = ($http_code >= 200 && $http_code < 300);

        return [
            'success' => $success,
            'http_code' => $http_code,
            'data' => $decoded_response,
            'error' => $success ? null : $this->getErrorFromResponse($decoded_response, $http_code),
            'message' => $success ? null : $this->getMessageFromResponse($decoded_response, $http_code),
        ];
    }

    /**
     * Construye la URL completa para la petición.
     */
    private function buildUrl($endpoint, $params = [])
    {
        $url = rtrim($this->base_url, '/') . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Obtiene los headers necesarios para la petición según la documentación de Yuju.
     */
    private function getHeaders()
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: PrestaShop-Yuju-Module/1.0.0',
        ];

        $access_token = $this->oauth->getValidAccessToken();

        if ($access_token) {
            // Según la documentación de Yuju, el token se envía tal cual en la cabecera Authorization
            $headers[] = 'Authorization: ' . $access_token;
        }

        return $headers;
    }

    /**
     * Extrae el código de error de la respuesta.
     */
    private function getErrorFromResponse($response, $http_code)
    {
        if (isset($response['error'])) {
            return $response['error'];
        }

        if (isset($response['code'])) {
            return $response['code'];
        }

        return 'HTTP_' . $http_code;
    }

    /**
     * Extrae el mensaje de error de la respuesta.
     */
    private function getMessageFromResponse($response, $http_code)
    {
        if (isset($response['message'])) {
            return $response['message'];
        }

        if (isset($response['error_description'])) {
            return $response['error_description'];
        }

        return $this->getHttpStatusMessage($http_code);
    }

    /**
     * Obtiene el mensaje de estado HTTP.
     */
    private function getHttpStatusMessage($code)
    {
        $status_codes = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return isset($status_codes[$code]) ? $status_codes[$code] : 'Unknown Error';
    }

    /**
     * Registra la petición en los logs.
     */
    private function logRequest($method, $endpoint, $data, $response, $execution_time)
    {
        $log_data = [
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $data,
            'response' => $response,
            'execution_time' => $execution_time,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $log_level = $response['success'] ? 'info' : 'error';
        $this->logger->log($log_level, 'API Request: ' . $method . ' ' . $endpoint, $log_data);
    }

    /**
     * Verifica si la API está disponible.
     */
    public function isApiAvailable()
    {
        $response = $this->get('account');

        return $response['success'];
    }

    /**
     * Obtiene información del usuario autenticado.
     */
    public function getUserInfo()
    {
        return $this->get('account');
    }

    /**
     * Test API connection.
     *
     * Dos niveles distintos:
     *  1. Credenciales (Client ID + Secret Key): pide token a Yuju y, si lo
     *     devuelve, se guarda para usarlo en la API.
     *  2. Token (obtenido con code + secret + client_id): se usa contra la API.
     */
    public function testConnection()
    {
        try {
            $this->logger->info('Starting connection test', [
                'base_url' => $this->base_url,
                'environment' => Configuration::get('YUJU_ENVIRONMENT')
            ]);

            $access_token = $this->oauth->getValidAccessToken();
            $auth_level = 'token';

            if (!$access_token) {
                // Sin token guardado: comprobación a nivel de credenciales
                // (Client ID + Secret Key), sin necesidad de un code.
                $this->logger->info('No stored access token: running credentials-level check');

                $credentials_check = $this->oauth->requestTokenWithCredentials();

                if (empty($credentials_check['success'])) {
                    $message = isset($credentials_check['message']) ? $credentials_check['message'] : 'Error desconocido';
                    $this->logger->error('Credentials check failed', ['message' => $message]);

                    return [
                        'success' => false,
                        'needs_auth' => true,
                        'message' => 'Error de conexión: ' . $message,
                        'data' => [
                            'auth_level' => 'credentials',
                            'credential_audit' => $this->oauth->getCredentialAudit(),
                            'timestamp' => date('Y-m-d H:i:s'),
                        ],
                    ];
                }

                $access_token = $credentials_check['token'];
                $auth_level = 'credentials';
                $this->logger->info('Access token obtained from Client ID + Secret Key');
            }

            // Probar la conexión con el endpoint de webhooks
            $url = $this->buildUrl('webhook-sub');
            $this->logger->info('Testing connection with webhook-sub endpoint', [
                'url' => $url
            ]);

            $response = $this->get('webhook-sub');

            // Token rechazado por la API: reintenta una vez con credenciales
            // frescas antes de devolver el error.
            if (!$response['success'] && (int) ($response['http_code'] ?? 0) === 401) {
                $this->logger->warning('Stored token rejected (401): retrying with credentials-level check');

                $credentials_check = $this->oauth->requestTokenWithCredentials();

                if (!empty($credentials_check['success'])) {
                    $auth_level = 'credentials';
                    $response = $this->get('webhook-sub');
                }
            }

            $this->logger->info('webhook-sub response received', [
                'response' => $response
            ]);

            if ($response['success']) {
                $this->logger->info('Connection test successful');
                return [
                    'success' => true,
                    'needs_auth' => false,
                    'message' => 'Conexión exitosa con la API de Yuju',
                    'data' => [
                        'token_valid' => true,
                        'api_accessible' => true,
                        'auth_level' => $auth_level,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ],
                ];
            } else {
                $this->logger->error('API connection failed', [
                    'response' => $response
                ]);
                return [
                    'success' => false,
                    'needs_auth' => false,
                    'message' => 'Error al conectar con la API: ' . ($response['message'] ?? 'Error desconocido')
                        . (isset($response['http_code']) ? ' (HTTP ' . (int) $response['http_code'] . ')' : ''),
                    'data' => [
                        'auth_level' => $auth_level,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ],
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Connection test failed: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'needs_auth' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Obtiene las tiendas disponibles en Yuju.
     */
    public function getStores($params = [])
    {
        try {
            $url = $this->buildUrl('account', $params);
            $this->logger->info('Calling getStores endpoint', [
                'url' => $url,
                'params' => $params,
                'base_url' => $this->base_url
            ]);
            
            $response = $this->get('account', $params);
            
            $this->logger->info('getStores response received', [
                'response' => $response,
                'success' => isset($response['success']) ? $response['success'] : 'unknown',
                'data_count' => isset($response['data']) ? count($response['data']) : 0
            ]);
            
            if (isset($response['data'])) {
                return $response['data'];
            }
            
            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error getting stores: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Métodos específicos para productos.
     */
    public function createProduct($product_data)
    {
        return $this->post('products', $product_data);
    }

    public function updateProduct($product_id, $product_data)
    {
        return $this->put('products/' . $product_id, $product_data);
    }

    public function getProduct($product_id)
    {
        return $this->get('products/' . $product_id);
    }

    public function deleteProduct($product_id)
    {
        return $this->delete('products/' . $product_id);
    }

    public function getProducts($params = [])
    {
        return $this->get('products', $params);
    }

    /**
     * Métodos específicos para categorías.
     */
    public function getCategories($params = [])
    {
        return $this->get('categories', $params);
    }

    public function getCategory($category_id)
    {
        return $this->get('categories/' . $category_id);
    }

    /**
     * Métodos específicos para órdenes.
     */
    public function getOrders($params = [])
    {
        return $this->get('orders', $params);
    }

    public function getOrder($order_id)
    {
        return $this->get('orders/' . $order_id);
    }

    public function updateOrderStatus($order_id, $status_data)
    {
        return $this->put('orders/' . $order_id . '/status', $status_data);
    }

    /**
     * Métodos específicos para atributos.
     */
    public function getAttributes($params = [])
    {
        return $this->get('attributes', $params);
    }

    public function getAttribute($attribute_id)
    {
        return $this->get('attributes/' . $attribute_id);
    }

    /**
     * Métodos para webhooks.
     */
    public function createWebhook($webhook_data)
    {
        return $this->post('webhooks', $webhook_data);
    }

    public function getWebhooks()
    {
        return $this->get('webhooks');
    }

    public function deleteWebhook($webhook_id)
    {
        return $this->delete('webhooks/' . $webhook_id);
    }

    /**
     * Configuración del cliente.
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (int) $timeout;
    }

    /**
     * Obtiene la URL base de la API.
     */
    public function getBaseUrl()
    {
        return $this->base_url;
    }

    public function setMaxRetries($max_retries)
    {
        $this->max_retries = (int) $max_retries;
    }

    /**
     * Obtiene estadísticas de uso de la API.
     */
    public function getApiStats()
    {
        return [
            'base_url' => $this->base_url,
            'timeout' => $this->timeout,
            'max_retries' => $this->max_retries,
            'oauth_configured' => $this->oauth->isConfigured(),
            'token_valid' => $this->oauth->hasValidToken(),
        ];
    }
}
