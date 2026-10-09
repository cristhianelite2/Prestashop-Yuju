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

class PrestashopyujuOAuthModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // Diagnóstico de la lectura del token: no consume `code`, no escribe
        // nada y enmascara credenciales. Uso: /shop/yuju/oauth.php?diag=1
        if (Tools::getValue('diag')) {
            header('Content-Type: application/json; charset=utf-8');

            $diagnostics = (new YujuOAuth())->diagnoseTokenStorage();
            echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        parent::initContent();

        try {
            $code = Tools::getValue('code');
            $state = Tools::getValue('state');
            $error = Tools::getValue('error');

            if ($error) {
                $this->handleAuthError($error);
                return;
            }

            if (!$code) {
                throw new Exception('Código de autorización no recibido');
            }

            // Validar state si está presente (CSRF protection)
            $saved_state = Context::getContext()->cookie->yuju_oauth_state;
            if ($saved_state && $state && $state !== $saved_state) {
                throw new Exception('Estado OAuth inválido (posible ataque CSRF)');
            }
            if ($saved_state) {
                Context::getContext()->cookie->yuju_oauth_state = '';
            }

            $oauth = new YujuOAuth();

            // Yuju redirige con `code` (y opcionalmente `state`); lo cambia por el token con client_id + secret_key.
            // exchangeCodeForToken() lanza una excepción si falla.
            $oauth->exchangeCodeForToken($code, $state);

            // Verificación del guardado: el token tiene que quedar legible en la
            // base de datos, no solo devuelto por Yuju. Si no se pudo persistir,
            // se reporta como error en lugar de un falso "éxito".
            $logger = new YujuLogger();

            if (empty($oauth->getValidAccessToken())) {
                $diagnostics = $oauth->diagnoseTokenStorage();

                $logger->error('OAuth: Yuju devolvió el token pero no quedó legible en la base de datos', $diagnostics);

                throw new Exception('Yuju devolvió el token, pero no se pudo leer de la base de datos '
                    . '(tabla `yuju_oauth_tokens`). Detalle: ' . json_encode($diagnostics, JSON_UNESCAPED_SLASHES));
            }

            $logger->info('OAuth: token intercambiado y guardado correctamente');

            $this->handleAuthSuccess($oauth->getOAuthStatus());
        } catch (Exception $e) {
            $this->handleAuthError($e->getMessage());
        }
    }

    private function handleAuthSuccess(array $status = [])
    {
        $this->context->smarty->assign([
            'success' => true,
            'message' => 'Autorización exitosa. El token quedó guardado y ya puede usarlo la tienda.',
            'token_saved' => true,
            'token_expires' => isset($status['token_expires']) ? $status['token_expires'] : null,
            'config_url' => $this->getModuleConfigUrl(),
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/oauth_callback.tpl');
    }

    private function handleAuthError($error_message)
    {
        $this->context->smarty->assign([
            'success' => false,
            'error' => $error_message,
            'token_saved' => false,
            'token_expires' => null,
            'config_url' => $this->getModuleConfigUrl(),
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/oauth_callback.tpl');
    }

    /**
     * URL de la configuración del módulo en el back office (para volver desde
     * la ventana de autorización y probar la conectividad).
     */
    private function getModuleConfigUrl()
    {
        try {
            return (string) $this->context->link->getAdminLink('AdminYujuConfiguration');
        } catch (Exception $e) {
            return '';
        }
    }
}