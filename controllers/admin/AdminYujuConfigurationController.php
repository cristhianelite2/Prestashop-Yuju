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
        ];

        // Generar URLs importantes para la configuración
        $link = new Link();
        $redirect_uri = $link->getModuleLink('prestashopyuju', 'oauth', [], true);
        $webhook_url = $link->getModuleLink('prestashopyuju', 'webhook', [], true);
        $terms_url = $link->getModuleLink('prestashopyuju', 'terms', [], true);
        $auth_url = $oauth_status['configured'] ? $oauth->getAuthorizationUrl() : null;
        
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
            'ajax_url' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminYujuConfiguration'),
            // URLs importantes para mostrar en la configuración
            'yuju_urls' => [
                'terms_conditions' => $terms_url,
                'auth_url' => $auth_url,
                'redirect_uri' => $redirect_uri,
                'webhook_url' => $webhook_url,
                'allowed_domains' => $allowed_domains,
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
            
            // Test basic connection first
            $connection_test = $api_client->testConnection();
            
            if (!$connection_test) {
                throw new Exception('No se pudo establecer conexión con la API de Yuju');
            }
            
            // Get stores from API
            $stores = $api_client->getStores();
            
            $response = [
                'success' => true,
                'message' => $this->trans('Connectivity test successful', array(), 'Modules.Prestashopyuju.Admin'),
                'data' => [
                    'connection' => $connection_test,
                    'stores' => $stores
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
            Configuration::updateValue('YUJU_API_CLIENT_ID', $client_id);
            Configuration::updateValue('YUJU_API_CLIENT_SECRET', $client_secret);
            Configuration::updateValue('YUJU_API_ENVIRONMENT', $environment);

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
            'client_id' => Configuration::get('YUJU_API_CLIENT_ID'),
            'client_secret' => Configuration::get('YUJU_API_CLIENT_SECRET'),
            'environment' => Configuration::get('YUJU_API_ENVIRONMENT'),
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

    private function getLastSyncTime()
    {
        $sql = 'SELECT MAX(start_time) FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE entity_type = \'products\'';

        return Db::getInstance()->getValue($sql);
    }
}
