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

require_once dirname(__FILE__) . '/../../classes/YujuOAuth.php';
require_once dirname(__FILE__) . '/../../classes/YujuApiClient.php';
require_once dirname(__FILE__) . '/../../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../../config/config.php';

class AdminYujuConfigurationController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'yuju_oauth_tokens';
        $this->className = 'YujuOAuth';
        $this->identifier = 'id';
        $this->lang = false;
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();

        $this->meta_title = $this->trans('Yuju Configuration', array(), 'Modules.Prestashopyuju.Admin');
        $this->toolbar_title = $this->trans('Yuju Configuration', array(), 'Modules.Prestashopyuju.Admin');
    }

    public function initContent()
    {
        parent::initContent();
        
        // Log para verificar que el controlador se carga
        file_put_contents(dirname(__FILE__) . '/../../logs/debug.log', 
            date('Y-m-d H:i:s') . " - AdminYujuConfigurationController initContent called\n", 
            FILE_APPEND | LOCK_EX);

        try {
            $oauth = new YujuOAuth();
            $api_client = new YujuApiClient();
            $logger = new YujuLogger();
        } catch (Exception $e) {
            file_put_contents(dirname(__FILE__) . '/../../logs/debug.log', 
                date('Y-m-d H:i:s') . " - Error creating objects: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }

        // Verificar si se está procesando el callback de OAuth
        if (Tools::getValue('code') && Tools::getValue('state')) {
            $this->processOAuthCallback($oauth);
        }

        // Obtener estado actual
        $oauth_status = $oauth->getOAuthStatus();
        $api_stats = $api_client->getApiStats();

        // Verificar estado del token y agregar alertas si es necesario
        $token_alerts = $this->checkTokenStatus($oauth);

        // Obtener datos de OAuth desde la base de datos
        $oauth_data = $oauth->getStoredTokenData();

        // Obtener configuración actual
        $config = [
            'YUJU_ENVIRONMENT' => YujuConfig::get('YUJU_ENVIRONMENT', 'production'),
            'YUJU_CLIENT_ID' => $oauth_data['client_id'] ?? YujuConfig::get('YUJU_CLIENT_ID'),
            'YUJU_CLIENT_SECRET' => $oauth_data['client_secret'] ?? YujuConfig::get('YUJU_CLIENT_SECRET'),
            'YUJU_REDIRECT_URI' => YujuConfig::get('YUJU_REDIRECT_URI'),
            'YUJU_AUTO_SYNC' => YujuConfig::get('YUJU_AUTO_SYNC', 1),
            'YUJU_SYNC_FREQUENCY' => YujuConfig::get('YUJU_SYNC_FREQUENCY', 3600),
            'YUJU_BATCH_SIZE' => YujuConfig::get('YUJU_BATCH_SIZE', 100),
            'YUJU_BATCH_FREQUENCY' => YujuConfig::get('YUJU_BATCH_FREQUENCY', 60),
            'YUJU_MAX_DAILY_SYNCS' => YujuConfig::get('YUJU_MAX_DAILY_SYNCS', 5),
            'YUJU_EMAIL_NOTIFICATIONS' => YujuConfig::get('YUJU_EMAIL_NOTIFICATIONS', 1),
            'YUJU_NOTIFICATION_EMAIL' => YujuConfig::get('YUJU_NOTIFICATION_EMAIL'),
            'YUJU_WEBHOOK_SECRET' => YujuConfig::get('YUJU_WEBHOOK_SECRET'),
            'YUJU_LOG_LEVEL' => YujuConfig::get('YUJU_LOG_LEVEL', 'info'),
            'YUJU_LOG_RETENTION' => YujuConfig::get('YUJU_LOG_RETENTION', 30),
            // Nuevas configuraciones que faltan
            'YUJU_PRESTASHOP_STORE_ID' => YujuConfig::get('YUJU_PRESTASHOP_STORE_ID', 1),
            'YUJU_STORE_LANGUAGE' => YujuConfig::get('YUJU_STORE_LANGUAGE', 'es'),
            'YUJU_SYNC_ENABLED' => YujuConfig::get('YUJU_SYNC_ENABLED', 1),
            'YUJU_SYNC_PRICES' => YujuConfig::get('YUJU_SYNC_PRICES', 1),
            'YUJU_SYNC_STOCK' => YujuConfig::get('YUJU_SYNC_STOCK', 1),
            'YUJU_SYNC_IMAGES' => YujuConfig::get('YUJU_SYNC_IMAGES', 1),
            'YUJU_SYNC_ORDERS' => YujuConfig::get('YUJU_SYNC_ORDERS', 1),
            'YUJU_CLEAN_HTML' => YujuConfig::get('YUJU_CLEAN_HTML', 1),
            'YUJU_LOGGING_ENABLED' => YujuConfig::get('YUJU_LOGGING_ENABLED', 1),
            'YUJU_FORCE_UPDATE' => YujuConfig::get('YUJU_FORCE_UPDATE', 0),
        ];

        // Generar URLs importantes para la configuración
        $link = new Link();
        $redirect_uri = $link->getModuleLink('prestashopyuju', 'oauth', [], true);
        $webhook_url = $link->getModuleLink('prestashopyuju', 'webhook', [], true);
        $terms_url = $link->getModuleLink('prestashopyuju', 'terms', [], true);
        $auth_url = $oauth_status['configured'] ? $oauth->getAuthorizationUrl() : null;
        $shop_base_url = rtrim($link->getPageLink('index', true), '/');
        $shop_name = Configuration::get('PS_SHOP_NAME');
        $shop_email = Configuration::get('PS_SHOP_EMAIL');
        
        // URLs permitidas para autenticación (dominios donde se puede usar la app)
        $allowed_domains = [
            Tools::getHttpHost(true),
            str_replace(['http://', 'https://'], '', Tools::getShopDomainSsl(true)),
        ];
        $allowed_domains = array_unique(array_filter($allowed_domains));

        $this->context->smarty->assign([
            'oauth_status' => $oauth_status,
            'api_stats' => $api_stats,
            'oauth_url' => $auth_url,
            'oauth_auth_url' => $auth_url,
            'module_path' => $this->module->getPathUri(),
            'current_tab' => 'configuration',
            'current_controller' => get_class($this),
            'config' => $config,
            'current_index' => self::$currentIndex,
            'token' => Tools::getAdminTokenLite('AdminYujuConfiguration'),
            'ajax_url' => $this->context->link->getAdminLink('AdminYujuConfiguration'),
            // Datos adicionales para el template
            'prestashop_shops' => Shop::getShops(true),
            'available_languages' => Language::getLanguages(false),
            // URLs importantes para mostrar en la configuración
            'yuju_urls' => [
                'terms_conditions' => $terms_url,
                'auth_url' => $auth_url,
                'redirect_uri' => $redirect_uri,
                'webhook_url' => $webhook_url,
                'allowed_domains' => $allowed_domains,
                'combined_domains' => (is_array($allowed_domains) ? implode(', ', $allowed_domains) : $allowed_domains) . (!empty($allowed_domains) ? ', ' : '') . $redirect_uri,
            ],
            'yuju_app_setup' => [
                'app_name' => 'Integracion Yuju - ' . (!empty($shop_name) ? $shop_name : 'PrestaShop'),
                'app_type' => 'API vendedor',
                'site_url' => $shop_base_url,
                'contact_email' => !empty($shop_email) ? $shop_email : '',
                'description' => 'Integracion oficial para sincronizar productos, stock, precios y ordenes entre PrestaShop y Yuju.',
                'icon_hint' => 'Use una imagen PNG cuadrada (recomendado 512x512). Puede reutilizar modules/prestashopyuju/logo.png',
                'terms_url' => $terms_url,
                // En esta integracion, la URL de autenticacion/callback coincide con el endpoint OAuth del modulo.
                'app_auth_url' => $redirect_uri,
                'allowed_redirection_urls' => (is_array($allowed_domains) ? implode(', ', $allowed_domains) : $allowed_domains) . (!empty($allowed_domains) ? ', ' : '') . $redirect_uri,
                'webhook_url' => $webhook_url,
            ],
        ]);

        // Verificar si es una petición AJAX
        if (Tools::getValue('ajax')) {
            $this->setTemplate('layout-ajax.tpl');
        } else {
            $this->setTemplate('configuration.tpl');
        }
    }

    public function displayAjax()
    {
        file_put_contents(dirname(__FILE__) . '/../../logs/debug.log', 
            date('Y-m-d H:i:s') . " - displayAjax called\n", 
            FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Procesa las peticiones AJAX para testProducts
     * 
     * NOTA: Usa cURL directo en lugar de YujuApiClient porque en el contexto
     * del controlador admin, YujuApiClient tiene problemas con el token.
     * Los tests externos (test_raw_curl.php, test_prestashop_context.php) 
     * funcionan correctamente, pero desde este controlador el token se hashea.
     * Solución: cURL directo con configuración exacta que funciona en Postman.
     */
    public function ajaxProcessTestProducts()
    {
        try {
            // Verificar el token antes de intentar crear producto
            $oauth = new YujuOAuth();
            $token = $oauth->getValidAccessToken();
            
            if (!$token) {
                throw new Exception("No hay token válido. Por favor reconecta OAuth.");
            }
            
            // Generar datos ficticios para producto de prueba
            $timestamp = time();
            $test_sku = 'TEST-YUJU-' . $timestamp;
            $test_product_name = 'Producto de Prueba Yuju ' . date('Y-m-d H:i:s');
            
            // Crear producto de prueba según la documentación de Yuju
            $product_data = [
                'sku_simple' => $test_sku,
                'sku' => $test_sku,
                'name' => $test_product_name,
                'description' => 'Este es un producto de prueba creado automáticamente para verificar la conexión con Yuju API.',
                'id_category' => 527,
                'stock' => 50,
                'price' => round(799.99, 2),
                'brand' => 'Samsung',
                'shipping' => 1,
                'dimensions_unit' => 'cm',
                'shipping_width' => round(7.31, 2),
                'shipping_depth' => round(0.79, 2),
                'shipping_height' => round(15.69, 2),
                'weight_unit' => 'kg',
                'weight' => round(0.169, 3),
                'images' => [],
                'listing_type' => 'gold_special',
                'ean' => '1234567890124',
                'product_weight' => '0.4',
                'net_content' => '300g',
                'channel_categories' => [],
                'channel_fields' => [
                    '15' => [
                        'custom' => [
                            'discount' => 20
                        ],
                        'general' => [
                            'name' => $test_product_name,
                            'price' => 800,
                            'stock' => 25
                        ]
                    ]
                ]
            ];
            
            // Preparar JSON con precisión correcta
            $old_precision = ini_get('serialize_precision');
            ini_set('serialize_precision', -1);
            $json_body = json_encode($product_data, JSON_UNESCAPED_SLASHES);
            ini_set('serialize_precision', $old_precision);
            
            // Enviar producto a Yuju usando cURL directo
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.tp.yuju.io/products',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $json_body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ],
            ]);
            
            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            $decoded_response = json_decode($response_body, true);
            $success = ($http_code >= 200 && $http_code < 300);
            
            $result = [
                'success' => $success,
                'http_code' => $http_code,
                'data' => $decoded_response,
                'error' => $success ? null : 'HTTP_' . $http_code,
                'message' => $success ? null : ($decoded_response['message'] ?? 'Error HTTP ' . $http_code),
            ];
            
            // Validar respuesta
            if (!$result['success']) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Error al crear producto de prueba',
                    'error' => $result['message'],
                    'http_code' => $result['http_code']
                ]));
            }
            
            // Extraer productos creados
            $created_products = $result['data']['success'] ?? [];
            $errors = $result['data']['errors'] ?? [];
            
            if (empty($created_products) && !empty($errors)) {
                $this->ajaxDie(json_encode([
                    'success' => false,
                    'message' => 'Yuju reportó errores al crear el producto',
                    'errors' => $errors
                ]));
            }
            
            $first_product = reset($created_products);
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => '✅ Producto creado exitosamente en Yuju',
                'product' => [
                    'sku' => $first_product['sku'] ?? $test_sku,
                    'name' => $first_product['name'] ?? $test_product_name,
                    'id_product' => $first_product['id_product'] ?? null,
                    'id_shop' => $first_product['id_shop'] ?? null,
                ]
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Error al procesar la prueba',
                'error' => $e->getMessage()
            ]));
        }
    }
    
    public function postProcess()
    {
        
        if (Tools::isSubmit('submitOAuthConfig')) {
            $this->processOAuthConfiguration();
        } elseif (Tools::isSubmit('submitGeneralConfig')) {
            $this->processGeneralConfiguration();
        } elseif (Tools::isSubmit('submitConfiguration')) {
            $this->processMainConfiguration();
        } elseif (Tools::isSubmit('testConnection')) {
            $this->testApiConnection();
        } elseif (Tools::isSubmit('revokeToken')) {
            $this->revokeOAuthToken();
        }

        parent::postProcess();
    }

    /**
     * Handle AJAX requests for testing API connection.
     */
    public function ajaxProcessTestConnection()
    {
        try {
            $api_client = new YujuApiClient();
            $result = $api_client->testConnection();

            $response = [
                'success' => true,
                'message' => $this->trans('Connection successful', array(), 'Modules.Prestashopyuju.Admin'),
                'data' => $result,
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        exit(json_encode($response));
    }

    /**
     * Handle AJAX requests for testing connectivity and getting stores.
     */
    public function ajaxProcessTestConnectivity()
    {
        try {
            $logger = new YujuLogger();
            $logger->info('Iniciando testConnectivity desde controlador', [
                'method' => 'ajaxProcessTestConnectivity',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $oauth = new YujuOAuth();
            
            // Verificar el estado de OAuth antes de obtener el token
            $oauth_status = $oauth->getOAuthStatus();
            $logger->info('Estado de OAuth verificado', [
                'oauth_status' => $oauth_status
            ]);
            
            // Obtener el access token
            $access_token = $oauth->getValidAccessToken();
            
            $logger->info('Resultado de getValidAccessToken', [
                'has_access_token' => !empty($access_token),
                'token_length' => $access_token ? strlen($access_token) : 0,
                'token_prefix' => $access_token ? substr($access_token, 0, 10) . '...' : 'null'
            ]);
            
            if (!$access_token) {
                $logger->error('No se pudo obtener token de acceso válido', [
                    'oauth_configured' => $oauth_status['configured'] ?? false,
                    'has_token' => $oauth_status['has_token'] ?? false,
                    'token_valid' => $oauth_status['token_valid'] ?? false
                ]);
                throw new Exception('No hay token de acceso válido. Por favor, autoriza la conexión OAuth primero.');
            }
            
            // Probar conectividad obteniendo lista de suscripciones activas
            // Según documentación: GET https://api.tp.yuju.io/webhook-sub
            $endpoint = 'https://api.tp.yuju.io/webhook-sub';
            $logger->info('Realizando petición GET al endpoint', ['endpoint' => $endpoint]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $access_token,
                    'Accept: application/json',
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            
            $api_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception('Error cURL: ' . $curl_error);
            }
            
            $logger->info('Respuesta de la API recibida', [
                'http_code' => $http_code,
                'response_length' => strlen($api_response),
                'response_preview' => substr($api_response, 0, 100)
            ]);
            
            // Obtener datos del token para debug
            $oauth_data = $oauth->getStoredTokenData();
            
            // Preparar respuesta exitosa
            $response = [
                'success' => true,
                'message' => 'Conectividad probada exitosamente',
                'data' => [
                    'endpoint' => $endpoint,
                    'http_code' => $http_code,
                    'response' => $api_response,
                    'status_text' => $this->getHttpStatusText($http_code),
                    'token_debug' => [
                        'token_usado' => $access_token,
                        'token_length' => strlen($access_token),
                        'token_db' => $oauth_data['access_token'] ?? 'N/A',
                        'son_iguales' => ($access_token === ($oauth_data['access_token'] ?? '')),
                        'expires_at' => $oauth_data['token_expires'] ?? 'N/A',
                        'client_id' => $oauth_data['client_id'] ?? 'N/A'
                    ]
                ],
            ];
            
            $logger->info('testConnectivity completado exitosamente', [
                'endpoint' => $endpoint,
                'http_code' => $http_code
            ]);
            
        } catch (Exception $e) {
            $logger = new YujuLogger();
            $logger->error('Error en testConnectivity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        exit(json_encode($response));
    }
    
    /**
     * Get HTTP status text for a given status code
     */
    private function getHttpStatusText($code)
    {
        $status_codes = [
            200 => 'OK',
            201 => 'Created',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];
        
        return $status_codes[$code] ?? 'Unknown Status';
    }

    /**
     * Handle AJAX requests for OAuth status.
     */
    public function ajaxProcessGetOAuthStatus()
    {
        try {
            $oauth = new YujuOAuth();
            $status = $oauth->getOAuthStatus();

            $response = [
                'success' => true,
                'data' => $status,
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        exit(json_encode($response));
    }

    /**
     * Procesa la configuración OAuth.
     */
    private function processOAuthConfiguration()
    {
        $client_id = Tools::getValue('client_id');
        $client_secret = Tools::getValue('client_secret');
        $environment = Tools::getValue('environment');

        if (empty($client_id) || empty($client_secret)) {
            $this->errors[] = $this->trans('Client ID y Client Secret son requeridos', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        try {
            YujuConfig::set('YUJU_API_CLIENT_ID', $client_id, 'string');
            YujuConfig::set('YUJU_API_CLIENT_SECRET', $client_secret, 'string');
            YujuConfig::set('YUJU_API_ENVIRONMENT', $environment, 'string');

            $oauth = new YujuOAuth();
            $oauth->updateCredentials($client_id, $client_secret);

            $this->confirmations[] = $this->trans('Configuración OAuth guardada correctamente', array(), 'Modules.Prestashopyuju.Admin');

            $logger = new YujuLogger();
            $logger->info('Configuración OAuth actualizada', [
                'environment' => $environment,
                'client_id' => substr($client_id, 0, 8) . '...',
            ]);
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error al guardar configuración: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    /**
     * Procesa la configuración general.
     */
    private function processGeneralConfiguration()
    {
        $configs = [
            'YUJU_BATCH_SIZE' => (int) Tools::getValue('batch_size'),
            'YUJU_BATCH_FREQUENCY' => (int) Tools::getValue('batch_frequency'),
            'YUJU_MAX_DAILY_SYNCS' => (int) Tools::getValue('max_daily_syncs'),
            'YUJU_AUDIT_FREQUENCY' => (int) Tools::getValue('audit_frequency'),
            'YUJU_EMAIL_NOTIFICATIONS' => (int) Tools::getValue('email_notifications'),
            'YUJU_NOTIFICATION_EMAIL' => Tools::getValue('notification_email'),
            'YUJU_ERROR_THRESHOLD' => (int) Tools::getValue('error_threshold'),
        ];

        // Validaciones
        if ($configs['YUJU_BATCH_SIZE'] < 1 || $configs['YUJU_BATCH_SIZE'] > 500) {
            $this->errors[] = $this->trans('El tamaño del lote debe estar entre 1 y 500', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        if ($configs['YUJU_BATCH_FREQUENCY'] < 30 || $configs['YUJU_BATCH_FREQUENCY'] > 3600) {
            $this->errors[] = $this->trans('La frecuencia debe estar entre 30 y 3600 segundos', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }
        
        if ($configs['YUJU_MAX_DAILY_SYNCS'] < 1 || $configs['YUJU_MAX_DAILY_SYNCS'] > 5) {
            $this->errors[] = $this->trans('El máximo de actualizaciones diarias debe estar entre 1 y 5', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        if ($configs['YUJU_EMAIL_NOTIFICATIONS'] && empty($configs['YUJU_NOTIFICATION_EMAIL'])) {
            $this->errors[] = $this->trans('Email de notificación es requerido si las notificaciones están habilitadas', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        try {
            foreach ($configs as $key => $value) {
                YujuConfig::set($key, $value, 'string');
            }

            $this->confirmations[] = $this->trans('Configuración general guardada correctamente', array(), 'Modules.Prestashopyuju.Admin');

            $logger = new YujuLogger();
            $logger->info('Configuración general actualizada', $configs);
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error al guardar configuración: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    /**
     * Procesa el callback de OAuth.
     */
    private function processOAuthCallback($oauth)
    {
        $code = Tools::getValue('code');
        $state = Tools::getValue('state');
        $error = Tools::getValue('error');

        if ($error) {
            $this->errors[] = $this->trans('Error en OAuth: ', array(), 'Modules.Prestashopyuju.Admin') . $error;

            return;
        }

        try {
            $oauth->exchangeCodeForToken($code, $state);
            $this->confirmations[] = $this->trans('Autenticación OAuth completada exitosamente', array(), 'Modules.Prestashopyuju.Admin');

            // Redireccionar para limpiar la URL
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminYujuConfiguration'));
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error en autenticación OAuth: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    /**
     * Prueba la conexión con la API.
     */
    private function testApiConnection()
    {
        try {
            $api_client = new YujuApiClient();

            if (!$api_client->isApiAvailable()) {
                $this->errors[] = $this->trans('No se pudo conectar con la API de Yuju', array(), 'Modules.Prestashopyuju.Admin');

                return;
            }

            $user_info = $api_client->getUserInfo();

            if ($user_info['success']) {
                $this->confirmations[] = $this->trans('Conexión exitosa con la API de Yuju', array(), 'Modules.Prestashopyuju.Admin');

                $logger = new YujuLogger();
                $logger->info('Prueba de conexión API exitosa', [
                    'user_data' => $user_info['data'],
                ]);
            } else {
                $this->errors[] = $this->trans('Error en la conexión: ', array(), 'Modules.Prestashopyuju.Admin') . $user_info['message'];
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error al probar conexión: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    /**
     * Revoca el token OAuth.
     */
    private function revokeOAuthToken()
    {
        try {
            $oauth = new YujuOAuth();
            $oauth->revokeToken();

            $this->confirmations[] = $this->trans('Token OAuth revocado correctamente', array(), 'Modules.Prestashopyuju.Admin');

            $logger = new YujuLogger();
            $logger->info('Token OAuth revocado');
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error al revocar token: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    /**
     * Renderiza el formulario de configuración OAuth.
     */
    public function renderOAuthForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Configuración OAuth', array(), 'Modules.Prestashopyuju.Admin'),
                    'icon' => 'icon-key',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Client ID', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'client_id',
                        'required' => true,
                        'desc' => $this->trans('Client ID proporcionado por Yuju', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Client Secret', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'client_secret',
                        'required' => true,
                        'desc' => $this->trans('Client Secret proporcionado por Yuju', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Ambiente', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'environment',
                        'options' => [
                            'query' => [
                                ['id' => 'sandbox', 'name' => 'Sandbox (Pruebas)'],
                                ['id' => 'production', 'name' => 'Producción'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->trans('Selecciona el ambiente de Yuju', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Guardar Configuración', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'submitOAuthConfig',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = 'AdminYujuConfiguration';
        $helper->token = Tools::getAdminTokenLite('AdminYujuConfiguration');
        $helper->currentIndex = AdminController::$currentIndex;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->title = $this->trans('Configuración OAuth', array(), 'Modules.Prestashopyuju.Admin');
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submitOAuthConfig';

        $helper->fields_value = [
            'client_id' => YujuConfig::get('YUJU_API_CLIENT_ID'),
            'client_secret' => YujuConfig::get('YUJU_API_CLIENT_SECRET'),
            'environment' => YujuConfig::get('YUJU_API_ENVIRONMENT'),
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Renderiza el formulario de configuración general.
     */
    public function renderGeneralForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Configuración General', array(), 'Modules.Prestashopyuju.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Tamaño del Lote', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'batch_size',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'productos',
                        'desc' => $this->trans('Número de productos a procesar por lote (1-1000)', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Frecuencia de Lotes', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'batch_frequency',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'segundos',
                        'desc' => $this->trans('Tiempo entre lotes en segundos (mínimo 60)', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Frecuencia de Auditoría', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'audit_frequency',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'segundos',
                        'desc' => $this->trans('Frecuencia de auditoría automática (86400 = diario)', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Notificaciones por Email', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'email_notifications',
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Sí', array(), 'Modules.Prestashopyuju.Admin')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', array(), 'Modules.Prestashopyuju.Admin')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Email de Notificaciones', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'notification_email',
                        'desc' => $this->trans('Email donde recibir notificaciones de errores', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Umbral de Errores', array(), 'Modules.Prestashopyuju.Admin'),
                        'name' => 'error_threshold',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'errores',
                        'desc' => $this->trans('Número de errores para enviar notificación', array(), 'Modules.Prestashopyuju.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Guardar Configuración', array(), 'Modules.Prestashopyuju.Admin'),
                    'name' => 'submitGeneralConfig',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = 'AdminYujuConfiguration';
        $helper->token = Tools::getAdminTokenLite('AdminYujuConfiguration');
        $helper->currentIndex = AdminController::$currentIndex;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->title = $this->trans('Configuración General', array(), 'Modules.Prestashopyuju.Admin');
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submitGeneralConfig';

        $helper->fields_value = [
            'batch_size' => YujuConfig::get('YUJU_BATCH_SIZE'),
            'batch_frequency' => YujuConfig::get('YUJU_BATCH_FREQUENCY'),
            'audit_frequency' => YujuConfig::get('YUJU_AUDIT_FREQUENCY'),
            'email_notifications' => YujuConfig::get('YUJU_EMAIL_NOTIFICATIONS'),
            'notification_email' => YujuConfig::get('YUJU_NOTIFICATION_EMAIL'),
            'error_threshold' => YujuConfig::get('YUJU_ERROR_THRESHOLD'),
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Obtiene los logs recientes para mostrar en el dashboard.
     */
    public function getRecentLogs($limit = 10)
    {
        $logger = new YujuLogger();

        return $logger->getLogsFromDatabase([], $limit);
    }

    /**
     * Obtiene estadísticas del módulo.
     */
    public function getModuleStats()
    {
        $logger = new YujuLogger();
        $stats = $logger->getLogStats();

        // Agregar estadísticas adicionales
        $stats['products_synced'] = $this->getProductsSyncedCount();
        $stats['orders_received'] = $this->getOrdersReceivedCount();
        $stats['last_sync'] = $this->getLastSyncTime();

        return $stats;
    }

    private function getProductsSyncedCount()
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_status` WHERE sync_status = \'synced\'';

        return (int) Db::getInstance()->getValue($sql);
    }

    private function getOrdersReceivedCount()
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_sync_logs`
                WHERE entity_type = \'orders\' AND DATE(start_time) = CURDATE()';

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Procesa la configuración principal del módulo.
     */
    private function processMainConfiguration()
    {
        $client_id = Tools::getValue('YUJU_CLIENT_ID');
        $client_secret = Tools::getValue('YUJU_CLIENT_SECRET');
        $environment = Tools::getValue('YUJU_ENVIRONMENT', 'production');
        
        $configs = [
            'YUJU_ENVIRONMENT' => $environment,
            'YUJU_AUTO_SYNC' => (int) Tools::getValue('YUJU_AUTO_SYNC'),
            'YUJU_SYNC_FREQUENCY' => (int) Tools::getValue('YUJU_SYNC_FREQUENCY'),
            'YUJU_BATCH_SIZE' => (int) Tools::getValue('YUJU_BATCH_SIZE'),
            'YUJU_BATCH_FREQUENCY' => (int) Tools::getValue('YUJU_BATCH_FREQUENCY'),
            'YUJU_MAX_DAILY_SYNCS' => (int) Tools::getValue('YUJU_MAX_DAILY_SYNCS'),
            'YUJU_EMAIL_NOTIFICATIONS' => (int) Tools::getValue('YUJU_EMAIL_NOTIFICATIONS'),
            'YUJU_NOTIFICATION_EMAIL' => Tools::getValue('YUJU_NOTIFICATION_EMAIL'),
            'YUJU_LOG_LEVEL' => Tools::getValue('YUJU_LOG_LEVEL'),
            'YUJU_LOG_RETENTION' => (int) Tools::getValue('YUJU_LOG_RETENTION'),
            'YUJU_PRESTASHOP_STORE_ID' => (int) Tools::getValue('YUJU_PRESTASHOP_STORE_ID'),
            'YUJU_STORE_LANGUAGE' => Tools::getValue('YUJU_STORE_LANGUAGE'),
            'YUJU_SYNC_ENABLED' => (int) Tools::getValue('YUJU_SYNC_ENABLED'),
            'YUJU_SYNC_PRICES' => (int) Tools::getValue('YUJU_SYNC_PRICES'),
            'YUJU_SYNC_STOCK' => (int) Tools::getValue('YUJU_SYNC_STOCK'),
            'YUJU_SYNC_IMAGES' => (int) Tools::getValue('YUJU_SYNC_IMAGES'),
            'YUJU_SYNC_ORDERS' => (int) Tools::getValue('YUJU_SYNC_ORDERS'),
            'YUJU_CLEAN_HTML' => (int) Tools::getValue('YUJU_CLEAN_HTML'),
            'YUJU_LOGGING_ENABLED' => (int) Tools::getValue('YUJU_LOGGING_ENABLED'),
        ];

        // Validaciones básicas
        if (empty($client_id) || empty($client_secret)) {
            $this->errors[] = $this->trans('Client ID y Client Secret son requeridos', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        if ($configs['YUJU_BATCH_SIZE'] < 1 || $configs['YUJU_BATCH_SIZE'] > 500) {
            $this->errors[] = $this->trans('El tamaño del lote debe estar entre 1 y 500', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        if ($configs['YUJU_SYNC_FREQUENCY'] < 60) {
            $this->errors[] = $this->trans('La frecuencia mínima es de 60 segundos', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }
        
        if ($configs['YUJU_BATCH_FREQUENCY'] < 30 || $configs['YUJU_BATCH_FREQUENCY'] > 3600) {
            $this->errors[] = $this->trans('La frecuencia entre lotes debe estar entre 30 y 3600 segundos', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }
        
        if ($configs['YUJU_MAX_DAILY_SYNCS'] < 1 || $configs['YUJU_MAX_DAILY_SYNCS'] > 5) {
            $this->errors[] = $this->trans('El máximo de sincronizaciones diarias debe estar entre 1 y 5', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        if ($configs['YUJU_EMAIL_NOTIFICATIONS'] && empty($configs['YUJU_NOTIFICATION_EMAIL'])) {
            $this->errors[] = $this->trans('Email de notificación es requerido si las notificaciones están habilitadas', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        try {
            // Guardar configuraciones en YujuConfig
            foreach ($configs as $key => $value) {
                YujuConfig::set($key, $value, 'string');
            }
            
            // Guardar/actualizar client_id y client_secret en la tabla yuju_oauth_tokens
            $table_name = _DB_PREFIX_ . 'yuju_oauth_tokens';
            
            // Verificar si existe un registro
            $oauth_record = Db::getInstance()->getRow(
                "SELECT id FROM `{$table_name}` ORDER BY id DESC LIMIT 1"
            );
            
            $oauth_data = [
                'client_id' => pSQL($client_id),
                'client_secret' => pSQL($client_secret),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($oauth_record) {
                // Actualizar registro existente
                Db::getInstance()->update(
                    'yuju_oauth_tokens',
                    $oauth_data,
                    'id = ' . (int) $oauth_record['id']
                );
            } else {
                // Crear nuevo registro
                $oauth_data['created_at'] = date('Y-m-d H:i:s');
                Db::getInstance()->insert('yuju_oauth_tokens', $oauth_data);
            }

            $this->confirmations[] = $this->trans('Configuración guardada correctamente', array(), 'Modules.Prestashopyuju.Admin');

            $logger = new YujuLogger();
            $logger->info('Configuración principal actualizada', [
                'environment' => $configs['YUJU_ENVIRONMENT'],
                'client_id' => substr($client_id, 0, 8) . '...',
                'auto_sync' => $configs['YUJU_AUTO_SYNC'],
                'batch_size' => $configs['YUJU_BATCH_SIZE'],
            ]);
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error al guardar configuración: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }



    private function getLastSyncTime()
    {
        $sql = 'SELECT MAX(start_time) FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE entity_type = \'products\'';

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Verifica el estado del token y genera alertas si es necesario
     */
    private function checkTokenStatus($oauth)
    {
        $alerts = [];
        
        try {
            $oauth_data = $oauth->getStoredTokenData();
            
            if (!$oauth_data || empty($oauth_data['access_token'])) {
                $this->warnings[] = $this->trans(
                    'No se ha configurado la conexión con Yuju. Por favor, configure las credenciales OAuth.',
                    [],
                    'Modules.Prestashopyuju.Admin'
                );
                return $alerts;
            }
            
            // Verificar si el token está expirado
            if ($oauth->isTokenExpired($oauth_data)) {
                $this->errors[] = $this->trans(
                    'El token de acceso a Yuju ha EXPIRADO. Es necesario reconectar inmediatamente desde la sección OAuth.',
                    [],
                    'Modules.Prestashopyuju.Admin'
                );
                $alerts['expired'] = true;
            }
            // Verificar si el token expirará pronto (menos de 24 horas)
            elseif ($oauth->needsRenewal(86400)) {
                $expires_at = isset($oauth_data['token_expires']) ? $oauth_data['token_expires'] : null;
                
                if ($expires_at) {
                    $remaining_time = strtotime($expires_at) - time();
                    $remaining_hours = floor($remaining_time / 3600);
                    
                    // Solo mostrar si quedan más de 2 horas
                    if ($remaining_hours > 2) {
                        $message = $this->trans(
                            'El token de acceso a Yuju expirará pronto',
                            [],
                            'Modules.Prestashopyuju.Admin'
                        );
                        
                        $message .= ' (' . $this->trans('aproximadamente %d horas restantes', [$remaining_hours], 'Modules.Prestashopyuju.Admin') . ')';
                        $message .= '. ' . $this->trans('Se recomienda reconectar desde la sección OAuth.', [], 'Modules.Prestashopyuju.Admin');
                        
                        $this->warnings[] = $message;
                        $alerts['expiring_soon'] = true;
                    }
                }
            }
            // Verificar si el token expirará en los próximos 7 días
            elseif ($oauth->needsRenewal(604800)) {
                $expires_at = isset($oauth_data['token_expires']) ? $oauth_data['token_expires'] : null;
                
                if ($expires_at) {
                    $remaining_time = strtotime($expires_at) - time();
                    $remaining_days = floor($remaining_time / 86400);
                    
                    $this->informations[] = $this->trans(
                        'El token de acceso expirará en %d días. Planifique reconectar pronto.',
                        [$remaining_days],
                        'Modules.Prestashopyuju.Admin'
                    );
                    $alerts['renewal_reminder'] = true;
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->trans(
                'Error al verificar el estado del token: %s',
                [$e->getMessage()],
                'Modules.Prestashopyuju.Admin'
            );
        }
        
        return $alerts;
    }
}
