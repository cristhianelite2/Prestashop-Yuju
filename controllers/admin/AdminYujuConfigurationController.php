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

        $oauth = new YujuOAuth();
        $api_client = new YujuApiClient();
        $logger = new YujuLogger();

        // Verificar si se está procesando el callback de OAuth
        if (Tools::getValue('code') && Tools::getValue('state')) {
            $this->processOAuthCallback($oauth);
        }

        // Obtener estado actual
        $oauth_status = $oauth->getOAuthStatus();
        $api_stats = $api_client->getApiStats();

        // Obtener configuración actual
        $config = [
            'YUJU_ENVIRONMENT' => Configuration::get('YUJU_ENVIRONMENT', 'sandbox'),
            'YUJU_CLIENT_ID' => Configuration::get('YUJU_CLIENT_ID'),
            'YUJU_CLIENT_SECRET' => Configuration::get('YUJU_CLIENT_SECRET'),
            'YUJU_REDIRECT_URI' => Configuration::get('YUJU_REDIRECT_URI'),
            'YUJU_AUTO_SYNC' => Configuration::get('YUJU_AUTO_SYNC', 1),
            'YUJU_SYNC_FREQUENCY' => Configuration::get('YUJU_SYNC_FREQUENCY', 3600),
            'YUJU_BATCH_SIZE' => Configuration::get('YUJU_BATCH_SIZE', 50),
            'YUJU_EMAIL_NOTIFICATIONS' => Configuration::get('YUJU_EMAIL_NOTIFICATIONS', 1),
            'YUJU_NOTIFICATION_EMAIL' => Configuration::get('YUJU_NOTIFICATION_EMAIL'),
            'YUJU_WEBHOOK_SECRET' => Configuration::get('YUJU_WEBHOOK_SECRET'),
            'YUJU_LOG_LEVEL' => Configuration::get('YUJU_LOG_LEVEL', 'info'),
            'YUJU_LOG_RETENTION' => Configuration::get('YUJU_LOG_RETENTION', 30),
            'YUJU_MONITOR_URL' => Configuration::get('YUJU_MONITOR_URL'),
            'YUJU_ALLOWED_URLS' => Configuration::get('YUJU_ALLOWED_URLS') ?: Tools::getShopDomainSsl(true),
            'YUJU_MONITOR_TOKEN_SET' => (bool) Configuration::get('YUJU_MONITOR_TOKEN'),
            'YUJU_USE_ALTERNATIVE_OAUTH_ROUTE' => (bool) Configuration::get('YUJU_USE_ALTERNATIVE_OAUTH_ROUTE', false),
        ];

        // Generar URLs importantes para la configuración
        $auth_url = $oauth->getRedirectUri();
        $webhook_url = YujuConfig::getModuleFileUrl('webhook.php');
        $terms_url = YujuConfig::getModuleFileUrl('terms.php');
        
        $this->context->smarty->assign([
            'oauth_status' => $oauth_status,
            'api_stats' => $api_stats,
            'module_path' => $this->module->getPathUri(),
            'current_tab' => 'configuration',
            'current_controller' => get_class($this),
            'config' => $config,
            'current_index' => self::$currentIndex,
            'token' => Tools::getAdminTokenLite('AdminYujuConfiguration'),
            'ajax_url' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminYujuConfiguration'),
            // URLs importantes para mostrar en la configuración
            'yuju_urls' => [
                'terms_conditions' => $terms_url,
                'auth_url' => $auth_url,
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
            $api_client = new YujuApiClient();

            // Test basic connection first (usa el token guardado, no las
            // credenciales en vivo: solo valida que el token siga vigente).
            $connection_test = $api_client->testConnection();

            if (empty($connection_test['success'])) {
                $oauth = new YujuOAuth();
                $response = [
                    'success' => false,
                    'message' => isset($connection_test['message']) && $connection_test['message'] !== ''
                        ? $connection_test['message']
                        : 'No se pudo establecer conexión con la API de Yuju',
                    'data' => [
                        'connection' => $connection_test,
                        'stores' => [],
                        // Auditoría de credenciales guardadas (solo longitudes):
                        // permite diagnosticar el formato sin gastar un code.
                        'credential_audit' => $oauth->getCredentialAudit(),
                        // true solo cuando ni el token guardado ni la comprobación
                        // a nivel Client ID + Secret Key dieron resultado.
                        'needs_auth' => !empty($connection_test['needs_auth']),
                        'state' => isset($connection_test['state']) ? $connection_test['state'] : null,
                        'auth_level' => isset($connection_test['data']['auth_level'])
                            ? $connection_test['data']['auth_level']
                            : null,
                    ],
                ];
                exit(json_encode($response));
            }

            // Conectividad comprobada pero sin token usable (Yuju exige `code`
            // para emitirlo): no tiene sentido llamar a la API todavía.
            if (!empty($connection_test['needs_auth'])) {
                $response = [
                    'success' => true,
                    'message' => $connection_test['message'],
                    'data' => [
                        'connection' => $connection_test,
                        'stores' => [],
                        'stores_count' => 0,
                        'credential_audit' => (new YujuOAuth())->getCredentialAudit(),
                        'needs_auth' => true,
                        'state' => isset($connection_test['state']) ? $connection_test['state'] : null,
                        'auth_level' => isset($connection_test['data']['auth_level'])
                            ? $connection_test['data']['auth_level']
                            : null,
                    ],
                ];
                exit(json_encode($response));
            }

            // Get stores from API
            $stores = $api_client->getStores();
            $stores_list = $this->normalizeStoresList($stores);

            $response = [
                'success' => true,
                'message' => $this->trans('Connectivity test successful', array(), 'Modules.Prestashopyuju.Admin'),
                'data' => [
                    'connection' => $connection_test,
                    'stores' => $stores_list,
                    'stores_count' => count($stores_list),
                    // Auditoría de credenciales guardadas (solo longitudes, sin valores):
                    // permite diagnosticar sin gastar un code de un solo uso.
                    'credential_audit' => (new YujuOAuth())->getCredentialAudit(),
                    'needs_auth' => false,
                    'state' => isset($connection_test['state']) ? $connection_test['state'] : 'connected',
                    'auth_level' => isset($connection_test['data']['auth_level'])
                        ? $connection_test['data']['auth_level']
                        : null,
                ],
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
     * Normaliza la respuesta del endpoint `account` a una lista de tiendas.
     *
     * El endpoint puede devolver una lista directa, un objeto con la lista
     * dentro (`stores`/`data`) o una sola tienda como objeto.
     *
     * @param mixed $stores
     *
     * @return array
     */
    private function normalizeStoresList($stores)
    {
        if (empty($stores) || !is_array($stores)) {
            return [];
        }

        // Payload de error (p. ej. si `account` falló): no hay tiendas.
        if (array_key_exists('success', $stores) && empty($stores['success'])) {
            return [];
        }

        if (isset($stores['stores']) && is_array($stores['stores'])) {
            return array_values($stores['stores']);
        }

        if (isset($stores['data']) && is_array($stores['data'])) {
            return array_values($stores['data']);
        }

        // Lista secuencial: ya es una lista de tiendas.
        $keys = array_keys($stores);
        if ($keys === range(0, count($stores) - 1)) {
            return array_values($stores);
        }

        // Objeto único (una sola tienda): envolverlo para mostrarlo.
        return [$stores];
    }

    /**
     * Vincula la tienda con el monitor de telemetría (handshake) y guarda el token
     * de instalación devuelto por el monitor.
     */
    public function ajaxProcessTestMonitor()
    {
        $url = trim((string) Tools::getValue('monitor_url'));

        if ($url === '') {
            $url = YujuMonitor::getMonitorUrl();
        }

        $name = trim((string) Tools::getValue('monitor_name'));

        if ($name === '') {
            $name = (string) Configuration::get('PS_SHOP_NAME');
        }

        if ($name === '') {
            $name = Tools::getServerName();
        }

        $result = YujuMonitor::connect($url, $name);

        header('Content-Type: application/json');

        exit(json_encode([
            'success' => !empty($result['success']),
            'message' => isset($result['message']) ? $result['message'] : '',
        ]));
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
     * Handle AJAX requests for OAuth attempts history.
     */
    public function ajaxProcessGetOAuthAttempts()
    {
        try {
            $oauth = new YujuOAuth();
            $limit = (int) Tools::getValue('limit', 20);
            $attempts = $oauth->getOAuthAttempts($limit);

            $response = [
                'success' => true,
                'data' => $attempts,
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
     * Handle AJAX requests for OAuth attempt detail.
     */
    public function ajaxProcessGetOAuthAttemptDetail()
    {
        try {
            $oauth = new YujuOAuth();
            $id = (string) Tools::getValue('id');
            $detail = $oauth->getOAuthAttemptDetail($id);

            $response = [
                'success' => true,
                'data' => $detail,
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
     * Handle AJAX request to clean old OAuth attempts.
     */
    public function ajaxProcessCleanOAuthAttempts()
    {
        try {
            $oauth = new YujuOAuth();
            $days = (int) Tools::getValue('days', 30);
            $oauth->cleanOldOAuthAttempts($days);

            $response = [
                'success' => true,
                'message' => 'Intentos antiguos limpiados',
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
        $client_id = trim((string) Tools::getValue('client_id'));
        $client_id = trim($client_id, "\"'");
        $client_secret = trim((string) Tools::getValue('client_secret'));
        $environment = Tools::getValue('environment');

        if (empty($client_id) || empty($client_secret)) {
            $this->errors[] = $this->trans('Client ID y Client Secret son requeridos', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        if (!preg_match('/^[0-9a-fA-F]{32}$/', $client_id)) {
            $this->errors[] = $this->trans('El Client ID debe tener 32 caracteres hexadecimales (revisa que no tenga espacios, comillas ni caracteres de más).', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        try {
            $oauth = new YujuOAuth();
            // Guarda client/secret y solo elimina el token si cambiaron
            // (re-guardar los mismos valores conserva la autorización).
            $credentials_changed = $oauth->updateCredentials($client_id, $client_secret);
            Configuration::updateValue('YUJU_ENVIRONMENT', $environment);

            $this->confirmations[] = $this->trans('Configuración OAuth guardada correctamente', array(), 'Modules.Prestashopyuju.Admin');

            if ($credentials_changed) {
                $this->confirmations[] = $this->trans('Las credenciales cambiaron: autoriza la aplicación de nuevo con un code fresco.', array(), 'Modules.Prestashopyuju.Admin');
            }

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
            'YUJU_AUDIT_FREQUENCY' => (int) Tools::getValue('audit_frequency'),
            'YUJU_EMAIL_NOTIFICATIONS' => (int) Tools::getValue('email_notifications'),
            'YUJU_NOTIFICATION_EMAIL' => Tools::getValue('notification_email'),
            'YUJU_ERROR_THRESHOLD' => (int) Tools::getValue('error_threshold'),
        ];

        // Validaciones
        if ($configs['YUJU_BATCH_SIZE'] < 1 || $configs['YUJU_BATCH_SIZE'] > 1000) {
            $this->errors[] = $this->trans('El tamaño del lote debe estar entre 1 y 1000', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        if ($configs['YUJU_BATCH_FREQUENCY'] < 60) {
            $this->errors[] = $this->trans('La frecuencia mínima es de 60 segundos', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        if ($configs['YUJU_EMAIL_NOTIFICATIONS'] && empty($configs['YUJU_NOTIFICATION_EMAIL'])) {
            $this->errors[] = $this->trans('Email de notificación es requerido si las notificaciones están habilitadas', array(), 'Modules.Prestashopyuju.Admin');

            return;
        }

        try {
            foreach ($configs as $key => $value) {
                Configuration::updateValue($key, $value);
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

        // Validar state para CSRF protection
        $saved_state = Context::getContext()->cookie->yuju_oauth_state;
        if ($saved_state && $state !== $saved_state) {
            $this->errors[] = $this->trans('Estado OAuth inválido (posible ataque CSRF)', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }
        // Limpiar state usado
        Context::getContext()->cookie->yuju_oauth_state = '';

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
     * Prueba la conexión con la API (usa el token guardado contra `account`
     * y reporta la causa exacta si falla, en vez de un mensaje genérico).
     */
    private function testApiConnection()
    {
        try {
            $api_client = new YujuApiClient();
            $oauth = new YujuOAuth();
            $oauth_status = $oauth->getOAuthStatus();

            $user_info = $api_client->getUserInfo();

            if (!empty($user_info['success'])) {
                $this->confirmations[] = $this->trans('Conexión exitosa con la API de Yuju', array(), 'Modules.Prestashopyuju.Admin');

                $logger = new YujuLogger();
                $logger->info('Prueba de conexión API exitosa', [
                    'user_data' => $user_info['data'],
                ]);

                return;
            }

            $detail = isset($user_info['message']) && $user_info['message'] !== ''
                ? (string) $user_info['message']
                : 'sin respuesta de la API';
            $http = isset($user_info['http_code']) ? ' (HTTP ' . (int) $user_info['http_code'] . ')' : '';

            if (empty($oauth_status['has_token'])) {
                $token_note = 'No hay token guardado: usa "Probar Conectividad", que valida las credenciales (Client ID + Secret Key) '
                    . 'y obtiene un token automáticamente si Yuju las acepta.';
            } elseif (!empty($oauth_status['token_expires'])) {
                $token_note = 'Hay token guardado (expira: ' . $oauth_status['token_expires'] . '): puede estar vencido o revocado en Yuju.';
            } else {
                $token_note = 'Hay token guardado: puede estar revocado en Yuju o sin acceso a la cuenta.';
            }

            $this->errors[] = $this->trans('Error en la conexión: ', array(), 'Modules.Prestashopyuju.Admin') . $detail . $http . '. ' . $token_note;

            $logger = new YujuLogger();
            $logger->error('Prueba de conexión API fallida', [
                'detail' => $detail,
                'http_code' => isset($user_info['http_code']) ? $user_info['http_code'] : null,
                'has_token' => !empty($oauth_status['has_token']),
                'token_expires' => $oauth_status['token_expires'],
            ]);
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
            'client_id' => Configuration::get('YUJU_CLIENT_ID'),
            'client_secret' => Configuration::get('YUJU_CLIENT_SECRET'),
            'environment' => Configuration::get('YUJU_ENVIRONMENT'),
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
            'batch_size' => Configuration::get('YUJU_BATCH_SIZE'),
            'batch_frequency' => Configuration::get('YUJU_BATCH_FREQUENCY'),
            'audit_frequency' => Configuration::get('YUJU_AUDIT_FREQUENCY'),
            'email_notifications' => Configuration::get('YUJU_EMAIL_NOTIFICATIONS'),
            'notification_email' => Configuration::get('YUJU_NOTIFICATION_EMAIL'),
            'error_threshold' => Configuration::get('YUJU_ERROR_THRESHOLD'),
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Obtiene los logs recientes para mostrar en el dashboard.
     */
    public function getRecentLogs($limit = 10)
    {
        $logger = new YujuLogger();

        return $logger->getRecentLogs($limit);
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
        $configs = [
            'YUJU_ENVIRONMENT' => Tools::getValue('YUJU_ENVIRONMENT'),
            'YUJU_CLIENT_ID' => strtolower(trim(trim((string) Tools::getValue('YUJU_CLIENT_ID')), "\"'")),
            'YUJU_CLIENT_SECRET' => trim((string) Tools::getValue('YUJU_CLIENT_SECRET')),
            'YUJU_AUTO_SYNC' => (int) Tools::getValue('YUJU_AUTO_SYNC'),
            'YUJU_SYNC_FREQUENCY' => (int) Tools::getValue('YUJU_SYNC_FREQUENCY'),
            'YUJU_BATCH_SIZE' => (int) Tools::getValue('YUJU_BATCH_SIZE'),
            'YUJU_EMAIL_NOTIFICATIONS' => (int) Tools::getValue('YUJU_EMAIL_NOTIFICATIONS'),
            'YUJU_NOTIFICATION_EMAIL' => Tools::getValue('YUJU_NOTIFICATION_EMAIL'),
            'YUJU_WEBHOOK_SECRET' => Tools::getValue('YUJU_WEBHOOK_SECRET'),
            'YUJU_LOG_LEVEL' => Tools::getValue('YUJU_LOG_LEVEL'),
            'YUJU_LOG_RETENTION' => (int) Tools::getValue('YUJU_LOG_RETENTION'),
            'YUJU_MONITOR_URL' => Tools::getValue('YUJU_MONITOR_URL'),
            'YUJU_ALLOWED_URLS' => $this->normalizeAllowedUrls(Tools::getValue('YUJU_ALLOWED_URLS')),
            'YUJU_USE_ALTERNATIVE_OAUTH_ROUTE' => (int) Tools::getValue('YUJU_USE_ALTERNATIVE_OAUTH_ROUTE', 0),
        ];

        // Validaciones básicas
        if (empty($configs['YUJU_CLIENT_ID']) || empty($configs['YUJU_CLIENT_SECRET'])) {
            $this->errors[] = $this->trans('Client ID y Client Secret son requeridos', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        if (!preg_match('/^[0-9a-f]{32}$/', $configs['YUJU_CLIENT_ID'])) {
            $this->errors[] = $this->trans('El Client ID debe tener 32 caracteres hexadecimales (revisa que no tenga espacios, comillas ni caracteres de más).', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        foreach (explode(',', $configs['YUJU_ALLOWED_URLS']) as $allowed_url) {
            if ($allowed_url !== '' && !Validate::isUrl($allowed_url)) {
                $this->errors[] = $this->trans('URLs permitidas contiene un valor no válido: ', array(), 'Modules.Prestashopyuju.Admin') . $allowed_url;
                return;
            }
        }

        if ($configs['YUJU_BATCH_SIZE'] < 1 || $configs['YUJU_BATCH_SIZE'] > 1000) {
            $this->errors[] = $this->trans('El tamaño del lote debe estar entre 1 y 1000', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        if ($configs['YUJU_SYNC_FREQUENCY'] < 60) {
            $this->errors[] = $this->trans('La frecuencia mínima es de 60 segundos', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        if ($configs['YUJU_EMAIL_NOTIFICATIONS'] && empty($configs['YUJU_NOTIFICATION_EMAIL'])) {
            $this->errors[] = $this->trans('Email de notificación es requerido si las notificaciones están habilitadas', array(), 'Modules.Prestashopyuju.Admin');
            return;
        }

        try {
            $oauth = new YujuOAuth();
            // Guarda client/secret y solo elimina el token guardado si las
            // credenciales realmente cambiaron (re-guardar lo mismo conserva
            // la autorización existente).
            $credentials_changed = $oauth->updateCredentials($configs['YUJU_CLIENT_ID'], $configs['YUJU_CLIENT_SECRET']);

            foreach ($configs as $key => $value) {
                Configuration::updateValue($key, $value);
            }

            // El token solo se actualiza si se envía uno nuevo, para no perder el guardado.
            $monitor_token = trim((string) Tools::getValue('YUJU_MONITOR_TOKEN'));

            if ($monitor_token !== '') {
                YujuMonitor::saveConfiguration(Tools::getValue('YUJU_MONITOR_URL'), $monitor_token);
            }

            $this->confirmations[] = $this->trans('Configuración guardada correctamente', array(), 'Modules.Prestashopyuju.Admin');

            if ($credentials_changed) {
                $this->confirmations[] = $this->trans('Las credenciales cambiaron: el token anterior se eliminó, autoriza la aplicación de nuevo con un code fresco.', array(), 'Modules.Prestashopyuju.Admin');
            }

            $logger = new YujuLogger();
            $logger->info('Configuración principal actualizada', [
                'environment' => $configs['YUJU_ENVIRONMENT'],
                'client_id' => substr($configs['YUJU_CLIENT_ID'], 0, 8) . '...',
                'auto_sync' => $configs['YUJU_AUTO_SYNC'],
                'batch_size' => $configs['YUJU_BATCH_SIZE'],
            ]);
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error al guardar configuración: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    /**
     * Normaliza una lista de URLs/dominios separados por comas (sin vacíos ni duplicados).
     */
    private function normalizeAllowedUrls($value)
    {
        $urls = array_filter(array_map('trim', explode(',', (string) $value)));

        return implode(',', array_unique($urls));
    }

    private function getLastSyncTime()
    {
        $sql = 'SELECT MAX(start_time) FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE entity_type = \'products\'';

        return Db::getInstance()->getValue($sql);
    }
}
