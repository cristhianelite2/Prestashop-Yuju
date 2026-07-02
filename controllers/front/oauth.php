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
require_once dirname(__FILE__) . '/../../config/config.php';

class PrestashopyujuOauthModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        try {
            // Inicializar logger para debugging
            require_once dirname(__FILE__) . '/../../classes/YujuLogger.php';
            $logger = new YujuLogger();
            
            // Capturar TODOS los parámetros para debugging
            $all_params = $_GET;
            $request_uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
            $query_string = $_SERVER['QUERY_STRING'] ?? 'N/A';
            
            $logger->forceDebug('OAuth callback - Complete debug info', [
                'request_uri' => $request_uri,
                'query_string' => $query_string,
                'all_get_params' => $all_params,
                'get_keys' => array_keys($all_params),
                'get_count' => count($all_params)
            ]);

            $code = Tools::getValue('code');
            $state = Tools::getValue('state');
            $error = Tools::getValue('error');

            // Log individual parameter extraction
            $logger->forceDebug('OAuth parameter extraction', [
                'code_raw' => $code,
                'state_raw' => $state,
                'error_raw' => $error,
                'code_empty' => empty($code),
                'state_empty' => empty($state),
                'code_type' => gettype($code),
                'state_type' => gettype($state)
            ]);

            if ($error) {
                $logger->forceDebug('OAuth error received from provider', ['error' => $error]);
                $this->handleAuthError($error);
                return;
            }

            if (!$code) {
                $logger->forceDebug('No authorization code received');
                throw new Exception('Código de autorización no recibido');
            }

            // Yuju no envía parámetro state según su documentación
            // Solo validamos si se configuró un state esperado y si se recibió uno
            if (!$state) {
                $logger->forceDebug('No state parameter received - this is normal for Yuju OAuth');
                // No lanzamos excepción, Yuju no envía state
            }

            $oauth = new YujuOAuth();
            $logger = new YujuLogger();

            // Log para debugging
            $logger->info('OAuth callback received', [
                'code_length' => strlen($code),
                'state_received' => $state,
                'has_code' => !empty($code),
                'has_state' => !empty($state)
            ]);

            // Validar el state para prevenir ataques CSRF (opcional para Yuju)
            $expected_state = YujuConfig::get('YUJU_OAUTH_STATE');
            
            // Log state validation
            $logger->forceDebug('OAuth state validation', [
                'expected_state' => $expected_state,
                'received_state' => $state,
                'expected_type' => gettype($expected_state),
                'received_type' => gettype($state),
                'yuju_note' => 'Yuju does not send state parameter according to their documentation'
            ]);
            
            // Solo validar state si tanto el esperado como el recibido existen
            if ($expected_state && $state && !$oauth->validateState($state, $expected_state)) {
                $logger->forceDebug('OAuth state validation failed', [
                    'expected' => $expected_state,
                    'received' => $state
                ]);
                throw new Exception('Estado de autorización inválido');
            } else {
                $logger->forceDebug('OAuth state validation skipped or passed', [
                    'reason' => !$expected_state ? 'No expected state configured' : (!$state ? 'No state received from Yuju (normal)' : 'State validation passed')
                ]);
            }

            // Intercambiar el código por un token
            $logger->forceDebug('Starting token exchange', [
                'code_length' => strlen($code),
                'has_oauth_instance' => isset($oauth)
            ]);
            
            $token_data = $oauth->exchangeCodeForToken($code, $state);

            if (!empty($token_data['success'])) {
                $this->handleAuthSuccess();
            } else {
                $error_message = $token_data['message'] ?? 'Error al obtener el token';
                $oauth_debug = isset($token_data['debug']) && is_array($token_data['debug']) ? $token_data['debug'] : [];
                $this->handleAuthError($error_message, [
                    'oauth_result' => $token_data,
                    'oauth_debug' => $oauth_debug,
                ]);
                return;
            }
        } catch (Exception $e) {
            // Logging del error
            if (isset($logger)) {
                $logger->forceDebug('OAuth process failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            $this->handleAuthError($e->getMessage());
        }
    }

    private function handleAuthSuccess()
    {
        $admin_url = '';
        if (isset($this->context->link)) {
            $admin_url = $this->context->link->getAdminLink('AdminYujuConfiguration');
        }

        $this->context->smarty->assign([
            'success' => true,
            'message' => 'Autorización exitosa. Puedes cerrar esta ventana.',
            'admin_redirect_url' => $admin_url,
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/oauth_callback.tpl');
    }

    private function handleAuthError($error_message, $extra_debug = [])
    {
        // Mostrar información de debugging en desarrollo
        $debug_info = [
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'query_string' => $_SERVER['QUERY_STRING'] ?? 'N/A',
            'get_params' => $_GET,
            'code_param' => Tools::getValue('code'),
            'state_param' => Tools::getValue('state'),
            'error_param' => Tools::getValue('error'),
        ];

        if (is_array($extra_debug) && !empty($extra_debug)) {
            $debug_info['oauth_trace'] = $extra_debug;
        }

        $this->context->smarty->assign([
            'success' => false,
            'error' => $error_message,
            'debug_info' => $debug_info,
            'show_debug' => true, // Activar debugging temporal
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/oauth_callback.tpl');
    }
}