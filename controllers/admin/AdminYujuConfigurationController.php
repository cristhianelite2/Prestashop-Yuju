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
        $this->table = 'yuju_oauth';
        $this->className = 'YujuOAuth';
        $this->lang = false;
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();

        $this->meta_title = $this->l('Configuración Yuju');
        $this->toolbar_title = $this->l('Configuración de Yuju');
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

        $this->context->smarty->assign([
            'oauth_status' => $oauth_status,
            'api_stats' => $api_stats,
            'oauth_url' => $oauth_status['configured'] ? $oauth->getAuthorizationUrl() : null,
            'module_path' => $this->module->getPathUri(),
            'current_tab' => 'configuration',
        ]);

        // Verificar si es una petición AJAX
        if (Tools::getValue('ajax')) {
            $this->setTemplate('layout-ajax.tpl');
        } else {
            $this->setTemplate('configuration/oauth_setup.tpl');
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
                'message' => $this->l('Connection successful'),
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
            $this->errors[] = $this->l('Client ID y Client Secret son requeridos');

            return;
        }

        try {
            Configuration::updateValue('YUJU_API_CLIENT_ID', $client_id);
            Configuration::updateValue('YUJU_API_CLIENT_SECRET', $client_secret);
            Configuration::updateValue('YUJU_API_ENVIRONMENT', $environment);

            $oauth = new YujuOAuth();
            $oauth->updateCredentials($client_id, $client_secret);

            $this->confirmations[] = $this->l('Configuración OAuth guardada correctamente');

            $logger = new YujuLogger();
            $logger->info('Configuración OAuth actualizada', [
                'environment' => $environment,
                'client_id' => substr($client_id, 0, 8) . '...',
            ]);
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error al guardar configuración: ') . $e->getMessage();
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
            $this->errors[] = $this->l('El tamaño del lote debe estar entre 1 y 1000');

            return;
        }

        if ($configs['YUJU_BATCH_FREQUENCY'] < 60) {
            $this->errors[] = $this->l('La frecuencia mínima es de 60 segundos');

            return;
        }

        if ($configs['YUJU_EMAIL_NOTIFICATIONS'] && empty($configs['YUJU_NOTIFICATION_EMAIL'])) {
            $this->errors[] = $this->l('Email de notificación es requerido si las notificaciones están habilitadas');

            return;
        }

        try {
            foreach ($configs as $key => $value) {
                Configuration::updateValue($key, $value);
            }

            $this->confirmations[] = $this->l('Configuración general guardada correctamente');

            $logger = new YujuLogger();
            $logger->info('Configuración general actualizada', $configs);
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error al guardar configuración: ') . $e->getMessage();
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
            $this->errors[] = $this->l('Error en OAuth: ') . $error;

            return;
        }

        try {
            $oauth->exchangeCodeForToken($code, $state);
            $this->confirmations[] = $this->l('Autenticación OAuth completada exitosamente');

            // Redireccionar para limpiar la URL
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminYujuConfiguration'));
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error en autenticación OAuth: ') . $e->getMessage();
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
                $this->errors[] = $this->l('No se pudo conectar con la API de Yuju');

                return;
            }

            $user_info = $api_client->getUserInfo();

            if ($user_info['success']) {
                $this->confirmations[] = $this->l('Conexión exitosa con la API de Yuju');

                $logger = new YujuLogger();
                $logger->info('Prueba de conexión API exitosa', [
                    'user_data' => $user_info['data'],
                ]);
            } else {
                $this->errors[] = $this->l('Error en la conexión: ') . $user_info['message'];
            }
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error al probar conexión: ') . $e->getMessage();
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

            $this->confirmations[] = $this->l('Token OAuth revocado correctamente');

            $logger = new YujuLogger();
            $logger->info('Token OAuth revocado');
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error al revocar token: ') . $e->getMessage();
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
                    'title' => $this->l('Configuración OAuth'),
                    'icon' => 'icon-key',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Client ID'),
                        'name' => 'client_id',
                        'required' => true,
                        'desc' => $this->l('Client ID proporcionado por Yuju'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client Secret'),
                        'name' => 'client_secret',
                        'required' => true,
                        'desc' => $this->l('Client Secret proporcionado por Yuju'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Ambiente'),
                        'name' => 'environment',
                        'options' => [
                            'query' => [
                                ['id' => 'sandbox', 'name' => 'Sandbox (Pruebas)'],
                                ['id' => 'production', 'name' => 'Producción'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Selecciona el ambiente de Yuju'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar Configuración'),
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
        $helper->title = $this->l('Configuración OAuth');
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
                    'title' => $this->l('Configuración General'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Tamaño del Lote'),
                        'name' => 'batch_size',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'productos',
                        'desc' => $this->l('Número de productos a procesar por lote (1-1000)'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Frecuencia de Lotes'),
                        'name' => 'batch_frequency',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'segundos',
                        'desc' => $this->l('Tiempo entre lotes en segundos (mínimo 60)'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Frecuencia de Auditoría'),
                        'name' => 'audit_frequency',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'segundos',
                        'desc' => $this->l('Frecuencia de auditoría automática (86400 = diario)'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Notificaciones por Email'),
                        'name' => 'email_notifications',
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Email de Notificaciones'),
                        'name' => 'notification_email',
                        'desc' => $this->l('Email donde recibir notificaciones de errores'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Umbral de Errores'),
                        'name' => 'error_threshold',
                        'class' => 'fixed-width-sm',
                        'suffix' => 'errores',
                        'desc' => $this->l('Número de errores para enviar notificación'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar Configuración'),
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
        $helper->title = $this->l('Configuración General');
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
                WHERE log_type = \'order\' AND DATE(created_at) = CURDATE()';

        return (int) Db::getInstance()->getValue($sql);
    }

    private function getLastSyncTime()
    {
        $sql = 'SELECT MAX(created_at) FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE log_type = \'product\'';

        return Db::getInstance()->getValue($sql);
    }
}
