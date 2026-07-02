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

        if ($this->module && method_exists($this->module, 'ensureYujuCategoryBulkTab')) {
            $this->module->ensureYujuCategoryBulkTab();
        }

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
        
        // Forzar protocolo HTTPS en todas las URLs mostradas en el panel de configuración
        $redirect_uri = preg_replace('#^http://#i', 'https://', $redirect_uri);
        $webhook_url = preg_replace('#^http://#i', 'https://', $webhook_url);
        $terms_url = preg_replace('#^http://#i', 'https://', $terms_url);
        $shop_base_url = preg_replace('#^http://#i', 'https://', $shop_base_url);
        if (!empty($auth_url)) {
            $auth_url = preg_replace('#^http://#i', 'https://', $auth_url);
        }
        
        // URLs permitidas para autenticación (dominios donde se puede usar la app)
        $allowed_domains = [
            preg_replace('#^http://#i', 'https://', Tools::getHttpHost(true)),
            'https://' . ltrim(str_replace(['http://', 'https://'], '', Tools::getShopDomainSsl(true)), '/'),
        ];
        $allowed_domains = array_unique(array_filter($allowed_domains));

        $yuju_hooks_status = $this->getYujuHooksStatus();
        $yuju_hooks_all_active = true;
        foreach ($yuju_hooks_status as $h) {
            if (empty($h['registered'])) {
                $yuju_hooks_all_active = false;
                break;
            }
        }

        $yuju_tables_status = $this->getYujuTablesStatus();
        $yuju_tables_all_present = true;
        foreach ($yuju_tables_status as $t) {
            if (empty($t['exists'])) {
                $yuju_tables_all_present = false;
                break;
            }
        }

        $this->context->smarty->assign([
            'oauth_status' => $oauth_status,
            'api_stats' => $api_stats,
            'yuju_hooks_status' => $yuju_hooks_status,
            'yuju_hooks_all_active' => $yuju_hooks_all_active,
            'yuju_tables_status' => $yuju_tables_status,
            'yuju_tables_all_present' => $yuju_tables_all_present,
            'oauth_url' => $auth_url,
            'oauth_auth_url' => $auth_url,
            'module_path' => $this->module->getPathUri(),
            'current_tab' => 'configuration',
            // Use controller_name (AdminYujuConfiguration) instead of class name
            // (AdminYujuConfigurationController) for sidebar active states.
            'current_controller' => !empty($this->controller_name) ? $this->controller_name : get_class($this),
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
                'site_url' => $shop_base_url,
                'contact_email' => !empty($shop_email) ? $shop_email : '',
                'description' => 'Integracion oficial para sincronizar productos, stock, precios y ordenes entre PrestaShop y Yuju.',
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

            // Validar credenciales antes de intentar token/API para devolver
            // un mensaje claro al usuario en el debug de conectividad.
            $raw_client_id = trim((string) Tools::getValue('YUJU_CLIENT_ID', ''));
            $raw_client_secret = trim((string) Tools::getValue('YUJU_CLIENT_SECRET', ''));
            $has_form_client_id = !empty($raw_client_id);
            $has_form_client_secret = !empty($raw_client_secret);
            $credentials_ready = $has_form_client_id && $has_form_client_secret;

            if (!$credentials_ready) {
                $oauth_data_debug = $oauth->getStoredTokenData();

                $debug = [
                    'credentials' => [
                        'has_client_id' => $has_form_client_id,
                        'has_client_secret' => $has_form_client_secret,
                        'client_id_length' => strlen($raw_client_id),
                        'client_secret_length' => strlen($raw_client_secret),
                    ],
                    'db_oauth_snapshot' => [
                        'has_client_id' => !empty($oauth_data_debug['client_id']),
                        'has_client_secret' => !empty($oauth_data_debug['client_secret']),
                        'client_id_length' => !empty($oauth_data_debug['client_id']) ? strlen($oauth_data_debug['client_id']) : 0,
                        'client_secret_length' => !empty($oauth_data_debug['client_secret']) ? strlen($oauth_data_debug['client_secret']) : 0,
                    ],
                    'hint' => 'Guarda ID de Cliente y Secreto de Cliente antes de probar conectividad.',
                ];

                $response = [
                    'success' => false,
                    'message' => 'Faltan credenciales OAuth: completa ID de Cliente y Secreto de Cliente.',
                    'debug' => $debug,
                ];

                $logger->warning('testConnectivity sin credenciales completas', $debug);
                exit(json_encode($response));
            }
            
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
     * AJAX: listado de scripts cron y última ejecución registrada.
     */
    public function ajaxProcessGetCronManagerData()
    {
        $scripts = $this->getCronScriptsCatalog();
        $runs = $this->readCronRunsRegistry();

        $rows = [];
        foreach ($scripts as $script) {
            $key = $script['file'];
            $last = isset($runs[$key]) && is_array($runs[$key]) ? $runs[$key] : null;
            $rows[] = [
                'file' => $script['file'],
                'label' => $script['label'],
                'description' => $script['description'],
                'required_mode' => isset($script['required_mode']) ? $script['required_mode'] : 'manual',
                'last_run_at' => $last && !empty($last['ran_at']) ? $last['ran_at'] : null,
                'last_status' => $last && !empty($last['status']) ? $last['status'] : null,
                'last_duration_ms' => $last && isset($last['duration_ms']) ? (int) $last['duration_ms'] : null,
            ];
        }

        $this->ajaxDie(json_encode([
            'success' => true,
            'crons' => $rows,
        ]));
    }

    /**
     * AJAX: ejecuta un cron seleccionado y devuelve salida.
     */
    public function ajaxProcessRunCronScript()
    {
        $script = trim((string) Tools::getValue('script', ''));
        $catalog = $this->getCronScriptsCatalog();
        $allowed = [];
        foreach ($catalog as $c) {
            $allowed[$c['file']] = $c;
        }

        if ($script === '' || !isset($allowed[$script])) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Cron no válido.',
            ]));
        }

        $path = dirname(__FILE__) . '/../../cron/' . $script;
        if (!is_file($path)) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Archivo de cron no encontrado.',
            ]));
        }

        $phpBin = $this->resolvePhpCliBinary();
        if (!$phpBin) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No se encontró un binario PHP CLI válido para ejecutar crons.',
            ]));
        }

        $command = escapeshellarg($phpBin)
            . ' -d date.timezone=America/Bogota '
            . escapeshellarg($path)
            . ' 2>&1';
        $outputLines = [];
        $exitCode = 0;
        $start = microtime(true);
        @exec($command, $outputLines, $exitCode);
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $output = trim(implode("\n", $outputLines));
        if ($output === '') {
            $output = '(sin salida)';
        }

        $status = $exitCode === 0 ? 'success' : 'error';
        $this->registerCronRun($script, $status, $durationMs, $output);

        $this->ajaxDie(json_encode([
            'success' => ($exitCode === 0),
            'status' => $status,
            'script' => $script,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'output' => $output,
            'ran_at' => date('Y-m-d H:i:s'),
            'message' => $exitCode === 0 ? 'Cron ejecutado correctamente.' : 'El cron terminó con error (exit code ' . (int) $exitCode . ').',
        ]));
    }

    /**
     * Obtiene un ejecutable PHP CLI válido (evita php-fpm).
     *
     * @return string|null
     */
    private function resolvePhpCliBinary()
    {
        $candidates = [];

        if (defined('PHP_BINARY') && PHP_BINARY) {
            $candidates[] = PHP_BINARY;
        }

        $candidates = array_merge($candidates, [
            'php',
            'php8.3',
            'php8.2',
            '/usr/bin/php',
            '/usr/local/bin/php',
        ]);

        $checked = [];
        foreach ($candidates as $bin) {
            $bin = trim((string) $bin);
            if ($bin === '' || isset($checked[$bin])) {
                continue;
            }
            $checked[$bin] = true;

            $sapi = $this->detectPhpSapi($bin);
            if ($sapi === 'cli') {
                return $bin;
            }
        }

        return null;
    }

    /**
     * Detecta el SAPI de un binario PHP.
     *
     * @param string $bin
     *
     * @return string
     */
    private function detectPhpSapi($bin)
    {
        $out = [];
        $code = 1;
        $cmd = escapeshellarg($bin) . " -n -r 'echo PHP_SAPI;' 2>&1";
        @exec($cmd, $out, $code);
        if ($code !== 0) {
            return '';
        }

        return strtolower(trim(implode("\n", $out)));
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
            // Guardar credenciales OAuth tambien en YujuConfig.
            // YujuOAuth::__construct() lee YUJU_CLIENT_ID/YUJU_CLIENT_SECRET desde YujuConfig.
            YujuConfig::set('YUJU_CLIENT_ID', $client_id, 'string');
            YujuConfig::set('YUJU_CLIENT_SECRET', $client_secret, 'string');

            // Guardar configuraciones en YujuConfig
            foreach ($configs as $key => $value) {
                YujuConfig::set($key, $value, 'string');
            }
            
            // Guardar/actualizar client_id y client_secret en la tabla yuju_oauth_tokens
            $table_name = _DB_PREFIX_ . 'yuju_oauth_tokens';
            
            // Verificar si existe un registro
            $oauth_record = Db::getInstance()->getRow(
                "SELECT id FROM `{$table_name}` ORDER BY id DESC"
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
     * @return array<int, array{file:string,label:string,description:string}>
     */
    private function getCronScriptsCatalog()
    {
        $cronDir = dirname(__FILE__) . '/../../cron';
        $files = glob($cronDir . '/*.php');
        if (!is_array($files)) {
            return [];
        }

        $meta = [
            'cron.php' => [
                'label' => 'cron.php',
                'description' => 'Cron principal del módulo: procesa cola, tareas automáticas y sincronización programada.',
                'required_mode' => 'programar',
            ],
            'check_token.php' => [
                'label' => 'check_token.php',
                'description' => 'Verifica y renueva token OAuth. Normalmente lo invoca cron.php automáticamente.',
                'required_mode' => 'interno',
            ],
            'sync.php' => [
                'label' => 'sync.php',
                'description' => 'Sincronización legacy/incremental. Útil para pruebas manuales, no recomendado en crontab si ya usas cron.php.',
                'required_mode' => 'manual',
            ],
            'sync_products.php' => [
                'label' => 'sync_products.php',
                'description' => 'Sincronización avanzada de productos con cache JSON. Operación manual/diagnóstico.',
                'required_mode' => 'manual',
            ],
            'sync_to_yuju.php' => [
                'label' => 'sync_to_yuju.php',
                'description' => 'Sincroniza una descarga específica hacia Yuju por download_id. Herramienta puntual.',
                'required_mode' => 'manual',
            ],
            'check_sync_status.php' => [
                'label' => 'check_sync_status.php',
                'description' => 'Consulta estado de una sincronización puntual (diagnóstico).',
                'required_mode' => 'manual',
            ],
            'retry_download.php' => [
                'label' => 'retry_download.php',
                'description' => 'Reintenta una descarga fallida desde logs. Uso manual en incidentes.',
                'required_mode' => 'manual',
            ],
            'reset_sync.php' => [
                'label' => 'reset_sync.php',
                'description' => 'Resetea contadores/estado de sincronización. Solo mantenimiento.',
                'required_mode' => 'manual',
            ],
            'migrate_config.php' => [
                'label' => 'migrate_config.php',
                'description' => 'Migración de configuración histórica. Ejecutar una sola vez en actualizaciones.',
                'required_mode' => 'one_time',
            ],
            'webhook_manager.php' => [
                'label' => 'webhook_manager.php',
                'description' => 'API auxiliar para gestión de webhooks desde panel. No es cron recurrente.',
                'required_mode' => 'interno',
            ],
            'check_webhook_orders.php' => [
                'label' => 'check_webhook_orders.php',
                'description' => 'Auditoría de órdenes recibidas por webhook. Diagnóstico/manual.',
                'required_mode' => 'manual',
            ],
            'create_order_from_webhook.php' => [
                'label' => 'create_order_from_webhook.php',
                'description' => 'Crea orden PS desde un webhook específico. Herramienta operativa manual.',
                'required_mode' => 'manual',
            ],
            'delete_order.php' => [
                'label' => 'delete_order.php',
                'description' => 'Elimina orden de PrestaShop vía endpoint técnico. Uso manual.',
                'required_mode' => 'manual',
            ],
            'fetch_order_details.php' => [
                'label' => 'fetch_order_details.php',
                'description' => 'Trae detalles completos de orden desde API Yuju para depuración.',
                'required_mode' => 'manual',
            ],
            'fix_orders_shop.php' => [
                'label' => 'fix_orders_shop.php',
                'description' => 'Corrige órdenes con id_shop inválido. Script de mantenimiento puntual.',
                'required_mode' => 'manual',
            ],
            'get_sync_details.php' => [
                'label' => 'get_sync_details.php',
                'description' => 'Obtiene detalle de una sincronización específica (consulta).',
                'required_mode' => 'interno',
            ],
            'inspect_yuju_json.php' => [
                'label' => 'inspect_yuju_json.php',
                'description' => 'Inspector CLI de JSON de Yuju/CloudFront. Solo diagnóstico.',
                'required_mode' => 'manual',
            ],
            'inspect_yuju_json_web.php' => [
                'label' => 'inspect_yuju_json_web.php',
                'description' => 'Inspector web de JSON Yuju. Solo diagnóstico.',
                'required_mode' => 'manual',
            ],
            'manage_webhook_orders.php' => [
                'label' => 'manage_webhook_orders.php',
                'description' => 'UI técnica para administrar órdenes de webhook. Herramienta manual.',
                'required_mode' => 'manual',
            ],
            'check_order_23.php' => [
                'label' => 'check_order_23.php',
                'description' => 'Script de diagnóstico específico para una orden puntual.',
                'required_mode' => 'manual',
            ],
        ];

        $rows = [];
        foreach ($files as $full) {
            $file = basename($full);
            if ($file === 'index.php') {
                continue;
            }

            $m = isset($meta[$file]) ? $meta[$file] : [
                'label' => $file,
                'description' => 'Script técnico del módulo.',
                'required_mode' => 'manual',
            ];

            $rows[] = [
                'file' => $file,
                'label' => $m['label'],
                'description' => $m['description'],
                'required_mode' => $m['required_mode'],
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) $a['file'], (string) $b['file']);
        });

        return $rows;
    }

    /**
     * @return string
     */
    private function getCronRunsRegistryPath()
    {
        return dirname(__FILE__) . '/../../cache/yuju_cron_runs.json';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readCronRunsRegistry()
    {
        $path = $this->getCronRunsRegistryPath();
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $data
     *
     * @return void
     */
    private function writeCronRunsRegistry(array $data)
    {
        $path = $this->getCronRunsRegistryPath();
        @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param string $script
     * @param string $status
     * @param int $durationMs
     * @param string $output
     *
     * @return void
     */
    private function registerCronRun($script, $status, $durationMs, $output)
    {
        $all = $this->readCronRunsRegistry();
        $all[(string) $script] = [
            'ran_at' => date('Y-m-d H:i:s'),
            'status' => (string) $status,
            'duration_ms' => (int) $durationMs,
            'output_excerpt' => mb_substr((string) $output, 0, 8000),
        ];
        $this->writeCronRunsRegistry($all);
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

    /**
     * Devuelve la lista de hooks que el módulo Yuju necesita tener registrados,
     * con su estado actual (registrado o no) e información para la UI.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getYujuHooksStatus()
    {
        $required = [
            'actionProductAdd' => [
                'label' => 'Producto creado',
                'description' => 'Detecta cuando se crea un producto en PrestaShop.',
                'critical' => true,
            ],
            'actionProductUpdate' => [
                'label' => 'Producto actualizado',
                'description' => 'Detecta cambios generales del producto (precio, datos básicos, etc.).',
                'critical' => true,
            ],
            'actionProductDelete' => [
                'label' => 'Producto eliminado',
                'description' => 'Detecta eliminación de productos para retirarlos de Yuju.',
                'critical' => true,
            ],
            'actionUpdateQuantity' => [
                'label' => 'Stock actualizado',
                'description' => 'Detecta cambios de stock para enviarlos a Yuju.',
                'critical' => true,
            ],
            'actionProductAttributeUpdate' => [
                'label' => 'Combinación actualizada',
                'description' => 'Detecta cambios en combinaciones / atributos de producto.',
                'critical' => true,
            ],
            'actionProductAttributeDelete' => [
                'label' => 'Combinación eliminada',
                'description' => 'Detecta eliminación de combinaciones.',
                'critical' => false,
            ],
            'actionCategoryAdd' => [
                'label' => 'Categoría creada',
                'description' => 'Detecta creación de categorías.',
                'critical' => false,
            ],
            'actionCategoryUpdate' => [
                'label' => 'Categoría actualizada',
                'description' => 'Detecta actualización de categorías.',
                'critical' => false,
            ],
            'actionCategoryDelete' => [
                'label' => 'Categoría eliminada',
                'description' => 'Detecta eliminación de categorías.',
                'critical' => false,
            ],
            'actionValidateOrder' => [
                'label' => 'Pedido validado',
                'description' => 'Detecta nuevos pedidos validados.',
                'critical' => true,
            ],
            'actionOrderStatusUpdate' => [
                'label' => 'Estado del pedido',
                'description' => 'Detecta cambios en el estado de los pedidos.',
                'critical' => true,
            ],
            'actionOrderReturn' => [
                'label' => 'Devolución de pedido',
                'description' => 'Detecta devoluciones de pedidos.',
                'critical' => false,
            ],
            'actionAdminControllerSetMedia' => [
                'label' => 'Recursos del back office',
                'description' => 'Carga JS/CSS necesarios del módulo en el back office.',
                'critical' => false,
            ],
            'displayBackOfficeHeader' => [
                'label' => 'Cabecera back office',
                'description' => 'Inyecta el header del back office para asegurar la inicialización del módulo.',
                'critical' => false,
            ],
            'displayAdminProductsExtra' => [
                'label' => 'Pestaña en ficha de producto',
                'description' => 'Muestra la pestaña Yuju en la ficha de producto del back office.',
                'critical' => false,
            ],
        ];

        $module_id = 0;
        if (isset($this->module) && $this->module && !empty($this->module->id)) {
            $module_id = (int) $this->module->id;
        } else {
            $row = Db::getInstance()->getRow(
                'SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = "prestashopyuju"'
            );
            $module_id = !empty($row['id_module']) ? (int) $row['id_module'] : 0;
        }

        $rows = [];
        foreach ($required as $hook_name => $info) {
            $hook_id = 0;
            try {
                $hook_id = (int) Hook::getIdByName($hook_name);
            } catch (Exception $e) {
                $hook_id = 0;
            }

            $registered = false;
            if ($hook_id > 0 && $module_id > 0) {
                $reg_row = Db::getInstance()->getRow(
                    'SELECT `id_module` FROM `' . _DB_PREFIX_ . 'hook_module`'
                    . ' WHERE `id_hook` = ' . $hook_id
                    . ' AND `id_module` = ' . $module_id
                );
                $registered = !empty($reg_row);
            }

            $rows[] = [
                'name' => $hook_name,
                'label' => $info['label'],
                'description' => $info['description'],
                'critical' => !empty($info['critical']),
                'registered' => $registered,
            ];
        }

        return $rows;
    }

    /**
     * AJAX: devuelve el estado de los hooks requeridos por Yuju.
     */
    public function ajaxProcessGetYujuHooksStatus()
    {
        try {
            $hooks = $this->getYujuHooksStatus();
            $all_ok = true;
            foreach ($hooks as $h) {
                if (empty($h['registered'])) {
                    $all_ok = false;
                    break;
                }
            }
            $this->ajaxDie(json_encode([
                'success' => true,
                'hooks' => $hooks,
                'all_active' => $all_ok,
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * AJAX: registra (activa) un hook específico para el módulo Yuju.
     */
    public function ajaxProcessRegisterYujuHook()
    {
        $hook_name = trim((string) Tools::getValue('hook', ''));
        $allowed_hooks = array_column($this->getYujuHooksStatus(), 'name');

        if ($hook_name === '' || !in_array($hook_name, $allowed_hooks, true)) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Hook no válido o no permitido.',
            ]));
        }

        if (!isset($this->module) || !$this->module) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No se pudo cargar la instancia del módulo Yuju.',
            ]));
        }

        try {
            $registered_now = (bool) $this->module->registerHook($hook_name);

            // Verificar de nuevo con consulta directa por si el método devolvió true
            // pero la inserción no se reflejó (o ya estaba registrado previamente).
            $status_after = null;
            foreach ($this->getYujuHooksStatus() as $h) {
                if ($h['name'] === $hook_name) {
                    $status_after = $h;
                    break;
                }
            }

            $this->ajaxDie(json_encode([
                'success' => !empty($status_after['registered']),
                'message' => !empty($status_after['registered'])
                    ? 'Hook activado correctamente.'
                    : 'No se pudo activar el hook. Revise permisos o vuelva a intentar.',
                'hook' => $hook_name,
                'registered_now' => $registered_now,
                'status' => $status_after,
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Excepción al registrar el hook: ' . $e->getMessage(),
                'hook' => $hook_name,
            ]));
        }
    }

    /**
     * Devuelve la lista de tablas requeridas por el módulo Yuju con
     * indicación de existencia en la base de datos.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getYujuTablesStatus()
    {
        $tables = [
            'yuju_oauth_tokens' => [
                'label' => 'Tokens OAuth',
                'description' => 'Almacena los tokens OAuth para conectar con Yuju.',
                'critical' => true,
            ],
            'yuju_product_status' => [
                'label' => 'Estado de productos',
                'description' => 'Estado de sincronización por producto (Yuju ID, errores, fechas).',
                'critical' => true,
            ],
            'yuju_product_sync_history' => [
                'label' => 'Historial de sincronización',
                'description' => 'Historial detallado de envíos a Yuju por producto.',
                'critical' => true,
            ],
            'yuju_sync_queue' => [
                'label' => 'Cola de sincronización',
                'description' => 'Cola de productos pendientes de enviar a Yuju.',
                'critical' => true,
            ],
            'yuju_sync_logs' => [
                'label' => 'Logs de sincronización',
                'description' => 'Registro de eventos de la cola/sincronización.',
                'critical' => false,
            ],
            'yuju_logs' => [
                'label' => 'Logs del módulo',
                'description' => 'Logs generales del módulo Yuju.',
                'critical' => false,
            ],
            'yuju_category_mapping' => [
                'label' => 'Mapeo de categorías',
                'description' => 'Asociación entre categorías de PrestaShop y Yuju.',
                'critical' => true,
            ],
            'yuju_product_mapping' => [
                'label' => 'Mapeo de productos',
                'description' => 'Asociación entre productos PrestaShop y Yuju (datos transformados).',
                'critical' => false,
            ],
            'yuju_attribute_mapping' => [
                'label' => 'Mapeo de atributos',
                'description' => 'Mapeo de atributos/combinaciones.',
                'critical' => false,
            ],
            'yuju_attribute_value_mapping' => [
                'label' => 'Mapeo de valores de atributos',
                'description' => 'Mapeo de valores de atributos PrestaShop ↔ Yuju.',
                'critical' => false,
            ],
            'yuju_categories_cache' => [
                'label' => 'Caché de categorías Yuju',
                'description' => 'Caché de categorías traídas desde Yuju.',
                'critical' => false,
            ],
            'yuju_attributes_cache' => [
                'label' => 'Caché de atributos Yuju',
                'description' => 'Caché de atributos traídos desde Yuju.',
                'critical' => false,
            ],
            'yuju_attribute_values_cache' => [
                'label' => 'Caché de valores de atributos',
                'description' => 'Caché de valores de atributos de Yuju.',
                'critical' => false,
            ],
            'yuju_configuration' => [
                'label' => 'Configuración propia',
                'description' => 'Configuración interna del módulo.',
                'critical' => false,
            ],
            'yuju_webhook_logs' => [
                'label' => 'Logs de webhooks',
                'description' => 'Webhooks recibidos desde Yuju.',
                'critical' => true,
            ],
            'yuju_webhook_registrations' => [
                'label' => 'Webhooks registrados',
                'description' => 'Lista de webhooks registrados en Yuju.',
                'critical' => false,
            ],
            'yuju_order_mapping' => [
                'label' => 'Mapeo de pedidos',
                'description' => 'Mapeo de pedidos PrestaShop ↔ Yuju.',
                'critical' => false,
            ],
            'yuju_order_status_mapping' => [
                'label' => 'Mapeo de estados de pedido',
                'description' => 'Mapeo de estados de pedido entre PrestaShop y Yuju.',
                'critical' => false,
            ],
        ];

        $rows = [];
        foreach ($tables as $name => $info) {
            $full = _DB_PREFIX_ . $name;
            $exists = false;
            try {
                $check = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($full) . '"');
                $exists = !empty($check);
            } catch (Exception $e) {
                $exists = false;
            }
            $rows[] = [
                'name' => $name,
                'full_name' => $full,
                'label' => $info['label'],
                'description' => $info['description'],
                'critical' => !empty($info['critical']),
                'exists' => $exists,
            ];
        }

        return $rows;
    }

    /**
     * AJAX: devuelve el estado de las tablas requeridas por Yuju.
     */
    public function ajaxProcessGetYujuTablesStatus()
    {
        try {
            $tables = $this->getYujuTablesStatus();
            $all_present = true;
            foreach ($tables as $t) {
                if (empty($t['exists'])) {
                    $all_present = false;
                    break;
                }
            }
            $this->ajaxDie(json_encode([
                'success' => true,
                'tables' => $tables,
                'all_present' => $all_present,
            ]));
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * AJAX: crea una tabla específica de Yuju desde sql/install.sql.
     */
    public function ajaxProcessCreateYujuTable()
    {
        $table = trim((string) Tools::getValue('table', ''));
        $allowed = array_column($this->getYujuTablesStatus(), 'name');

        if ($table === '' || !in_array($table, $allowed, true)) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'Tabla no válida o no permitida.',
            ]));
        }

        $sql_file = _PS_MODULE_DIR_ . 'prestashopyuju/sql/install.sql';
        if (!is_readable($sql_file)) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No se pudo leer sql/install.sql en el módulo.',
            ]));
        }

        $sql = (string) file_get_contents($sql_file);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);

        $statement = $this->extractCreateTableStatement($sql, _DB_PREFIX_ . $table);
        if ($statement === null) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No se encontró la definición de la tabla en install.sql.',
            ]));
        }

        $ok = false;
        $err = '';
        try {
            $ok = (bool) Db::getInstance()->execute($statement);
            if (!$ok) {
                $err = Db::getInstance()->getMsgError();
            }
        } catch (Exception $e) {
            $ok = false;
            $err = $e->getMessage();
        }

        // Verificación posterior real (existe / no existe).
        $exists_now = false;
        try {
            $check = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL(_DB_PREFIX_ . $table) . '"');
            $exists_now = !empty($check);
        } catch (Exception $e) {
            // ignore
        }

        $this->ajaxDie(json_encode([
            'success' => $exists_now,
            'message' => $exists_now
                ? 'Tabla creada correctamente.'
                : 'No se pudo crear la tabla. ' . ($err !== '' ? $err : 'Revise permisos MySQL.'),
            'table' => $table,
            'exists' => $exists_now,
        ]));
    }

    /**
     * AJAX: crea todas las tablas faltantes ejecutando sql/install.sql íntegro
     * (usa CREATE TABLE IF NOT EXISTS, es idempotente).
     */
    public function ajaxProcessCreateAllYujuTables()
    {
        if (!isset($this->module) || !$this->module || !method_exists($this->module, 'ensureAllYujuTables')) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No se pudo cargar el módulo Yuju.',
            ]));
        }

        $result = $this->module->ensureAllYujuTables();

        $after = $this->getYujuTablesStatus();
        $all_present = true;
        foreach ($after as $t) {
            if (empty($t['exists'])) {
                $all_present = false;
                break;
            }
        }

        $this->ajaxDie(json_encode([
            'success' => $all_present,
            'message' => $all_present
                ? 'Todas las tablas requeridas están presentes.'
                : 'Algunas tablas siguen sin crearse. Revise los detalles.',
            'tables' => $after,
            'all_present' => $all_present,
            'executed' => isset($result['executed']) ? (int) $result['executed'] : 0,
            'errors' => isset($result['errors']) ? $result['errors'] : [],
        ]));
    }

    /**
     * Extrae la sentencia CREATE TABLE IF NOT EXISTS para una tabla concreta
     * dentro del SQL combinado de install.sql (ya sustituido el prefijo).
     *
     * @param string $sql
     * @param string $fullTableName
     *
     * @return string|null
     */
    private function extractCreateTableStatement($sql, $fullTableName)
    {
        $needle = 'CREATE TABLE IF NOT EXISTS `' . $fullTableName . '`';
        $pos = strpos($sql, $needle);
        if ($pos === false) {
            // Buscar variantes con o sin backticks/IF NOT EXISTS
            $needle_alt = 'CREATE TABLE `' . $fullTableName . '`';
            $pos = strpos($sql, $needle_alt);
            if ($pos === false) {
                return null;
            }
        }

        // Avanzar hasta el primer `;` que cierra la sentencia.
        $semicolon_pos = strpos($sql, ';', $pos);
        if ($semicolon_pos === false) {
            return null;
        }

        $statement = trim(substr($sql, $pos, $semicolon_pos - $pos));
        if ($statement === '') {
            return null;
        }

        return $statement;
    }

    /**
     * AJAX: registra todos los hooks faltantes del módulo Yuju.
     */
    public function ajaxProcessRegisterAllYujuHooks()
    {
        if (!isset($this->module) || !$this->module) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => 'No se pudo cargar la instancia del módulo Yuju.',
            ]));
        }

        $before = $this->getYujuHooksStatus();
        $attempted = [];
        foreach ($before as $h) {
            if (empty($h['registered'])) {
                try {
                    $this->module->registerHook($h['name']);
                } catch (Exception $e) {
                    // continuar con el siguiente
                }
                $attempted[] = $h['name'];
            }
        }

        $after = $this->getYujuHooksStatus();
        $all_ok = true;
        foreach ($after as $h) {
            if (empty($h['registered'])) {
                $all_ok = false;
                break;
            }
        }

        $this->ajaxDie(json_encode([
            'success' => true,
            'attempted' => $attempted,
            'hooks' => $after,
            'all_active' => $all_ok,
            'message' => $all_ok
                ? 'Todos los hooks requeridos están activos.'
                : 'Algunos hooks siguen sin activarse. Revise los logs.',
        ]));
    }
}
